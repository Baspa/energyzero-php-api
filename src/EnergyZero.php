<?php

namespace Baspa\EnergyZero;

use Baspa\EnergyZero\Enums\EnergyType;
use Baspa\EnergyZero\Enums\Interval;
use Baspa\EnergyZero\Enums\PriceType;
use Baspa\EnergyZero\Exceptions\ApiException;
use Baspa\EnergyZero\Exceptions\EnergyZeroException;
use Baspa\EnergyZero\Exceptions\NoPricesException;
use DateTimeImmutable;
use DateTimeZone;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use InvalidArgumentException;
use JsonException;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * @phpstan-type Price array{readingDate: string, price: float}
 * @phpstan-type Prices array{Prices: non-empty-list<Price>, average: float}
 * @phpstan-type PriceAt array{price: float, datetime: string}
 */
class EnergyZero
{
    private const GRPC_NOT_FOUND = 5;

    private PriceType $defaultPriceType = PriceType::MARKET_WITH_VAT;

    private int $requestTimeout = 10;

    private string $baseUri = 'https://public.api.energyzero.nl/public/v1/';

    private ClientInterface $client;

    private Interval $defaultInterval = Interval::HOUR;

    private EnergyType $defaultEnergyType = EnergyType::ELECTRICITY;

    private ClockInterface $clock;

    private DateTimeZone $timezone;

    private DateTimeZone $apiTimezone;

    public function __construct(?ClientInterface $client = null, ?ClockInterface $clock = null)
    {
        $this->client = $client ?? new Client([
            'base_uri' => $this->baseUri,
            'timeout' => $this->requestTimeout,
            'headers' => [
                'Accept' => 'application/json, text/plain',
                'User-Agent' => 'PHPEnergyZero/1.0',
            ],
        ]);
        $this->clock = $clock ?? new SystemClock;
        $this->apiTimezone = new DateTimeZone('Europe/Amsterdam');
        $this->timezone = $this->apiTimezone;
    }

    public function setDefaultInterval(Interval $interval): self
    {
        $this->defaultInterval = $interval;

        return $this;
    }

    public function setDefaultEnergyType(EnergyType $type): self
    {
        $this->defaultEnergyType = $type;

        return $this;
    }

    public function setDefaultPriceType(PriceType $priceType): self
    {
        $this->defaultPriceType = $priceType;

        return $this;
    }

    public function setTimezone(DateTimeZone|string $timezone): self
    {
        $this->timezone = is_string($timezone) ? new DateTimeZone($timezone) : $timezone;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<mixed>
     *
     * @throws ApiException
     * @throws NoPricesException
     */
    public function request(string $uri, array $params = []): array
    {
        $target = $this->client->getConfig('base_uri') === null
            ? UriResolver::resolve(new Uri($this->baseUri), new Uri($uri))
            : $uri;

        try {
            $response = $this->client->request('GET', $target, ['query' => $params]);
        } catch (BadResponseException $e) {
            throw $this->httpError($e->getResponse(), $e);
        } catch (GuzzleException $e) {
            throw new ApiException('EnergyZero API request failed: '.$e->getMessage(), $e->getCode(), $e);
        }

        $status = $response->getStatusCode();

        if ($status < 200 || $status >= 300) {
            throw $this->httpError($response);
        }

        try {
            $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new ApiException('EnergyZero API returned invalid JSON: '.$e->getMessage(), 0, $e);
        }

        if (! is_array($data)) {
            throw new ApiException('EnergyZero API returned JSON that is not an object.');
        }

        return $data;
    }

    /**
     * @return Prices
     *
     * @throws EnergyZeroException
     * @throws InvalidArgumentException
     */
    public function energyPrices(
        string $startDate,
        string $endDate,
        Interval|int|null $interval = null,
        PriceType|bool|null $priceType = null,
        EnergyType|int|null $energyType = null
    ): array {
        $energyType = $this->resolveEnergyType($energyType);
        $interval = $this->resolveInterval($interval, $energyType);
        $priceType = $this->resolvePriceType($priceType);

        $from = $this->startOfDay($startDate);
        $till = $this->startOfDay($endDate)->modify('+1 day');

        if ($till <= $from) {
            throw new InvalidArgumentException('The end date is before the start date.');
        }

        $prices = [];
        $coveredDates = [];

        foreach ($this->apiDates($from, $till) as $date) {
            if (isset($coveredDates[$date])) {
                continue;
            }

            $response = $this->pricesForApiDate($date, $interval, $priceType, $energyType);
            $coveredDates += array_fill_keys($response['coveredDates'], true);

            foreach ($response['prices'] as $timestamp => $price) {
                if ($timestamp >= $from->getTimestamp() && $timestamp < $till->getTimestamp()) {
                    $prices[$timestamp] = $price;
                }
            }
        }

        ksort($prices);
        $prices = array_values($prices);

        if ($prices === []) {
            throw new NoPricesException('No energy prices found for this period.');
        }

        return [
            'Prices' => $prices,
            'average' => array_sum(array_column($prices, 'price')) / count($prices),
        ];
    }

    /**
     * @return Prices
     *
     * @throws EnergyZeroException
     * @throws InvalidArgumentException
     */
    public function gasPrices(
        string $startDate,
        string $endDate,
        Interval|int|null $interval = null,
        PriceType|bool|null $priceType = null
    ): array {
        return $this->energyPrices($startDate, $endDate, $interval, $priceType, EnergyType::GAS);
    }

    /**
     * @return PriceAt
     *
     * @throws EnergyZeroException
     * @throws InvalidArgumentException
     */
    public function getCurrentPrice(PriceType|bool|null $priceType = null, EnergyType|int|null $energyType = null): array
    {
        $energyType = $this->resolveEnergyType($energyType);
        $now = $this->clock->now();
        $today = $now->setTimezone($this->timezone);

        $prices = $this->energyPrices(
            $today->modify('-1 day')->format('Y-m-d'),
            $today->format('Y-m-d'),
            $this->hourOrDay($energyType),
            $priceType,
            $energyType,
        )['Prices'];
        $current = null;

        foreach ($prices as $price) {
            if (new DateTimeImmutable($price['readingDate']) <= $now) {
                $current = $price;
            }
        }

        if ($current === null) {
            throw new NoPricesException('Could not find current price.');
        }

        return $this->priceAt($current);
    }

    /**
     * @param  array<int>  $hours
     * @return list<Price>
     *
     * @throws EnergyZeroException
     * @throws InvalidArgumentException
     */
    public function getPricesForHours(string $date, array $hours, PriceType|bool|null $priceType = null, EnergyType|int|null $energyType = null): array
    {
        $energyType = $this->resolveEnergyType($energyType);

        if ($energyType === EnergyType::GAS) {
            throw new InvalidArgumentException('Gas only has daily prices; use gasPrices() instead of getPricesForHours().');
        }

        $prices = $this->energyPrices($date, $date, Interval::HOUR, $priceType, $energyType)['Prices'];
        $result = [];

        foreach ($prices as $price) {
            $priceDateTime = (new DateTimeImmutable($price['readingDate']))->setTimezone($this->timezone);
            $hour = (int) $priceDateTime->format('G');

            if (in_array($hour, $hours, true)) {
                $result[] = $price;
            }
        }

        return $result;
    }

    /**
     * @throws EnergyZeroException
     * @throws InvalidArgumentException
     */
    public function getAveragePriceForPeriod(
        string $startDate,
        string $endDate,
        Interval|int|null $interval = null,
        PriceType|bool|null $priceType = null,
        EnergyType|int|null $energyType = null
    ): float {
        return $this->energyPrices($startDate, $endDate, $interval, $priceType, $energyType)['average'];
    }

    /**
     * @return PriceAt
     *
     * @throws EnergyZeroException
     * @throws InvalidArgumentException
     */
    public function getLowestPriceForPeriod(
        string $startDate,
        string $endDate,
        Interval|int|null $interval = null,
        PriceType|bool|null $priceType = null,
        EnergyType|int|null $energyType = null
    ): array {
        $prices = $this->energyPrices($startDate, $endDate, $interval, $priceType, $energyType)['Prices'];
        $lowest = $prices[0];

        foreach ($prices as $price) {
            if ($price['price'] < $lowest['price']) {
                $lowest = $price;
            }
        }

        return $this->priceAt($lowest);
    }

    /**
     * @return PriceAt
     *
     * @throws EnergyZeroException
     * @throws InvalidArgumentException
     */
    public function getHighestPriceForPeriod(
        string $startDate,
        string $endDate,
        Interval|int|null $interval = null,
        PriceType|bool|null $priceType = null,
        EnergyType|int|null $energyType = null
    ): array {
        $prices = $this->energyPrices($startDate, $endDate, $interval, $priceType, $energyType)['Prices'];
        $highest = $prices[0];

        foreach ($prices as $price) {
            if ($price['price'] > $highest['price']) {
                $highest = $price;
            }
        }

        return $this->priceAt($highest);
    }

    /**
     * @return list<Price>
     *
     * @throws EnergyZeroException
     * @throws InvalidArgumentException
     */
    public function getPricesAboveThreshold(
        string $startDate,
        string $endDate,
        float $threshold,
        Interval|int|null $interval = null,
        PriceType|bool|null $priceType = null,
        EnergyType|int|null $energyType = null
    ): array {
        $prices = $this->energyPrices($startDate, $endDate, $interval, $priceType, $energyType)['Prices'];

        return array_values(array_filter($prices, fn (array $price) => $price['price'] > $threshold));
    }

    /**
     * @return list<Price>
     *
     * @throws EnergyZeroException
     * @throws InvalidArgumentException
     */
    public function getPricesBelowThreshold(
        string $startDate,
        string $endDate,
        float $threshold,
        Interval|int|null $interval = null,
        PriceType|bool|null $priceType = null,
        EnergyType|int|null $energyType = null
    ): array {
        $prices = $this->energyPrices($startDate, $endDate, $interval, $priceType, $energyType)['Prices'];

        return array_values(array_filter($prices, fn (array $price) => $price['price'] < $threshold));
    }

    /**
     * @return list<Price>
     *
     * @throws EnergyZeroException
     * @throws InvalidArgumentException
     */
    public function getPeakHours(
        string $startDate,
        string $endDate,
        int $topN = 5,
        Interval|int|null $interval = null,
        PriceType|bool|null $priceType = null,
        EnergyType|int|null $energyType = null
    ): array {
        $prices = $this->energyPrices($startDate, $endDate, $interval, $priceType, $energyType)['Prices'];
        usort($prices, fn (array $a, array $b) => $b['price'] <=> $a['price']);

        return array_slice($prices, 0, $topN);
    }

    /**
     * @return list<Price>
     *
     * @throws EnergyZeroException
     * @throws InvalidArgumentException
     */
    public function getValleyHours(
        string $startDate,
        string $endDate,
        int $topN = 5,
        Interval|int|null $interval = null,
        PriceType|bool|null $priceType = null,
        EnergyType|int|null $energyType = null
    ): array {
        $prices = $this->energyPrices($startDate, $endDate, $interval, $priceType, $energyType)['Prices'];
        usort($prices, fn (array $a, array $b) => $a['price'] <=> $b['price']);

        return array_slice($prices, 0, $topN);
    }

    /**
     * @return array{prices: array<int, Price>, coveredDates: list<string>}
     *
     * @throws ApiException
     */
    private function pricesForApiDate(string $date, Interval $interval, PriceType $priceType, EnergyType $energyType): array
    {
        try {
            $data = $this->request('prices', [
                'energyType' => $energyType->apiValue(),
                'date' => $date,
                'interval' => $interval->apiValue(),
            ]);
        } catch (NoPricesException) {
            return ['prices' => [], 'coveredDates' => [$date]];
        }

        $entries = $data[$priceType->value] ?? null;

        if (! is_array($entries)) {
            throw new ApiException("EnergyZero API returned no {$priceType->value} prices.");
        }

        $prices = [];

        foreach ($entries as $entry) {
            $start = is_array($entry) ? $entry['start'] ?? null : null;
            $price = is_array($entry) ? $entry['price'] ?? null : null;
            $value = is_array($price) ? $price['value'] ?? null : null;
            $timestamp = is_string($start) ? strtotime($start) : false;

            if (! is_string($start) || $timestamp === false || ! is_numeric($value)) {
                throw new ApiException('EnergyZero API returned a price in an unexpected format.');
            }

            $prices[$timestamp] = ['readingDate' => $start, 'price' => (float) $value];
        }

        $coveredDates = array_map(fn (int $timestamp) => $this->apiDate(new DateTimeImmutable('@'.$timestamp)), array_keys($prices));

        return ['prices' => $prices, 'coveredDates' => array_values(array_unique([$date, ...$coveredDates]))];
    }

    /**
     * @return list<string>
     */
    private function apiDates(DateTimeImmutable $from, DateTimeImmutable $till): array
    {
        $first = $from->setTimezone($this->apiTimezone)->setTime(0, 0);
        $day = $till->modify('-1 second')->setTimezone($this->apiTimezone)->setTime(0, 0);
        $dates = [];

        // Newest first: the response for a date also holds the day before it.
        while ($day >= $first) {
            $dates[] = $this->apiDate($day);
            $day = $day->modify('-1 day');
        }

        return $dates;
    }

    private function apiDate(DateTimeImmutable $moment): string
    {
        return $moment->setTimezone($this->apiTimezone)->format('d-m-Y');
    }

    private function startOfDay(string $date): DateTimeImmutable
    {
        return (new DateTimeImmutable($date, $this->timezone))
            ->setTimezone($this->timezone)
            ->setTime(0, 0);
    }

    /**
     * @param  Price  $price
     * @return PriceAt
     */
    private function priceAt(array $price): array
    {
        return ['price' => $price['price'], 'datetime' => $price['readingDate']];
    }

    private function hourOrDay(EnergyType $energyType): Interval
    {
        return $energyType === EnergyType::GAS ? Interval::DAY : Interval::HOUR;
    }

    private function httpError(ResponseInterface $response, ?Throwable $previous = null): ApiException|NoPricesException
    {
        $status = $response->getStatusCode();
        $error = json_decode((string) $response->getBody(), true);
        $message = is_array($error) && is_string($error['message'] ?? null) ? $error['message'] : null;

        if ($status === 404 && is_array($error) && ($error['code'] ?? null) === self::GRPC_NOT_FOUND) {
            return new NoPricesException("EnergyZero API has no prices: {$message}", $status, $previous);
        }

        return new ApiException(
            "EnergyZero API returned HTTP {$status}".($message === null ? '.' : ": {$message}"),
            $status,
            $previous,
        );
    }

    private function resolvePriceType(PriceType|bool|null $priceType): PriceType
    {
        return match ($priceType) {
            null => $this->defaultPriceType,
            true => PriceType::MARKET_WITH_VAT,
            false => PriceType::MARKET,
            default => $priceType,
        };
    }

    private function resolveInterval(Interval|int|null $interval, EnergyType $energyType): Interval
    {
        if (is_int($interval)) {
            $interval = Interval::tryFrom($interval)
                ?? throw new InvalidArgumentException("Unknown interval {$interval}; use an Interval enum case.");
        }

        if ($energyType === EnergyType::GAS) {
            if ($interval !== null && $interval !== Interval::DAY) {
                throw new InvalidArgumentException('Gas prices are only available per day; use Interval::DAY or leave the interval out.');
            }

            return Interval::DAY;
        }

        $interval ??= $this->defaultInterval;

        if ($interval === Interval::DAY) {
            throw new InvalidArgumentException('Interval::DAY is not available for electricity prices; use Interval::QUARTER or Interval::HOUR.');
        }

        return $interval;
    }

    private function resolveEnergyType(EnergyType|int|null $energyType): EnergyType
    {
        if ($energyType === null) {
            return $this->defaultEnergyType;
        }

        if ($energyType instanceof EnergyType) {
            return $energyType;
        }

        return EnergyType::tryFrom($energyType)
            ?? throw new InvalidArgumentException("Unknown energy type {$energyType}; use an EnergyType enum case.");
    }
}
