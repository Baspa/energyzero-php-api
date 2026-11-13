<?php

namespace Baspa\EnergyZero;

use Baspa\EnergyZero\Enums\EnergyType;
use Baspa\EnergyZero\Enums\Interval;
use Baspa\EnergyZero\Exceptions\ApiException;
use Baspa\EnergyZero\Exceptions\NoPricesException;
use DateTime;
use DateTimeZone;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use InvalidArgumentException;
use JsonException;

class EnergyZero
{
    private bool $vat = true;

    private int $requestTimeout = 10;

    private string $baseUri = 'https://api.energyzero.nl/v1/';

    private ClientInterface $client;

    private Interval $defaultInterval = Interval::HOUR;

    private EnergyType $defaultEnergyType = EnergyType::ELECTRICITY;

    public function __construct(?ClientInterface $client = null)
    {
        $this->client = $client ?? new Client([
            'base_uri' => $this->baseUri,
            'timeout' => $this->requestTimeout,
            'headers' => [
                'Accept' => 'application/json, text/plain',
                'User-Agent' => 'PHPEnergyZero/1.0',
            ],
        ]);
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

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     *
     * @throws ApiException
     */
    public function request(string $uri, array $params = []): array
    {
        $url = UriResolver::resolve(new Uri($this->baseUri), new Uri($uri));

        try {
            $response = $this->client->request('GET', $url, ['query' => $params]);
        } catch (GuzzleException $e) {
            throw new ApiException('EnergyZero API request failed: '.$e->getMessage(), $e->getCode(), $e);
        }

        $status = $response->getStatusCode();

        if ($status < 200 || $status >= 300) {
            throw new ApiException('EnergyZero API returned HTTP '.$status.'.', $status);
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
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    public function energyPrices(
        string $startDate,
        string $endDate,
        Interval|int|null $interval = null,
        ?bool $vat = null,
        EnergyType|int|null $energyType = null
    ): array {
        $localTz = new DateTimeZone(date_default_timezone_get());
        $utcTz = new DateTimeZone('UTC');

        $utcStartDate = new DateTime($startDate, $localTz);
        $utcStartDate->setTime(0, 0, 0);
        $utcStartDate->setTimezone($utcTz);

        $utcEndDate = new DateTime($endDate, $localTz);
        $utcEndDate->setTime(23, 59, 59);
        $utcEndDate->setTimezone($utcTz);

        if ($vat === null) {
            $vat = $this->vat;
        }

        $intervalValue = $this->resolveIntervalValue($interval);
        $energyTypeValue = $this->resolveEnergyTypeValue($energyType);

        if ($intervalValue === Interval::QUARTER->value && $energyTypeValue === EnergyType::GAS->value) {
            throw new InvalidArgumentException('Interval::QUARTER is not available for gas prices.');
        }

        $params = [
            'fromDate' => $utcStartDate->format('Y-m-d\TH:i:s.000\Z'),
            'tillDate' => $utcEndDate->format('Y-m-d\TH:i:s.999\Z'),
            'interval' => $intervalValue,
            'usageType' => $energyTypeValue,
            'inclBtw' => $vat ? 'true' : 'false',
        ];

        $data = $this->request('energyprices', $params);

        if (empty($data['Prices'])) {
            throw new NoPricesException('No energy prices found for this period.');
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    public function gasPrices(
        string $startDate,
        string $endDate,
        Interval|int|null $interval = null,
        ?bool $vat = null
    ): array {
        return $this->energyPrices($startDate, $endDate, $interval, $vat, EnergyType::GAS);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    public function getCurrentPrice(?bool $vat = null, EnergyType|int|null $energyType = null): array
    {
        $now = new DateTime;
        $today = $now->format('Y-m-d');

        $data = $this->energyPrices($today, $today, Interval::HOUR, $vat, $energyType);
        $currentHour = (int) $now->format('G');

        foreach ($data['Prices'] as $price) {
            $priceDateTime = new DateTime($price['readingDate']);
            if ((int) $priceDateTime->format('G') === $currentHour) {
                return [
                    'price' => $price['price'],
                    'datetime' => $price['readingDate'],
                ];
            }
        }

        throw new NoPricesException('Could not find current price.');
    }

    /**
     * @param  array<int>  $hours
     * @return array<int, array<string, mixed>>
     *
     * @throws Exception
     */
    public function getPricesForHours(string $date, array $hours, ?bool $vat = null, EnergyType|int|null $energyType = null): array
    {
        $data = $this->energyPrices($date, $date, Interval::HOUR, $vat, $energyType);
        $result = [];

        foreach ($data['Prices'] as $price) {
            $priceDateTime = new DateTime($price['readingDate']);
            $hour = (int) $priceDateTime->format('G');

            if (in_array($hour, $hours, true)) {
                $result[] = $price;
            }
        }

        return $result;
    }

    /**
     * @throws Exception
     */
    public function getAveragePriceForPeriod(
        string $startDate,
        string $endDate,
        Interval|int|null $interval = null,
        ?bool $vat = null,
        EnergyType|int|null $energyType = null
    ): float {
        $intervalValue = $this->resolveIntervalValue($interval);
        $data = $this->energyPrices($startDate, $endDate, $intervalValue, $vat, $energyType);

        return $data['average'];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    public function getLowestPriceForPeriod(
        string $startDate,
        string $endDate,
        Interval|int|null $interval = null,
        ?bool $vat = null,
        EnergyType|int|null $energyType = null
    ): array {
        $intervalValue = $this->resolveIntervalValue($interval);
        $data = $this->energyPrices($startDate, $endDate, $intervalValue, $vat, $energyType);
        $prices = array_column($data['Prices'], 'price');

        if ($prices === []) {
            throw new NoPricesException('No prices available for this period.');
        }

        $lowestPrice = min($prices);
        $lowestPriceIndex = array_search($lowestPrice, $prices);

        return [
            'price' => $lowestPrice,
            'datetime' => $data['Prices'][$lowestPriceIndex]['readingDate'],
        ];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    public function getHighestPriceForPeriod(
        string $startDate,
        string $endDate,
        Interval|int|null $interval = null,
        ?bool $vat = null,
        EnergyType|int|null $energyType = null
    ): array {
        $intervalValue = $this->resolveIntervalValue($interval);
        $data = $this->energyPrices($startDate, $endDate, $intervalValue, $vat, $energyType);
        $prices = array_column($data['Prices'], 'price');

        if ($prices === []) {
            throw new NoPricesException('No prices available for this period.');
        }

        $highestPrice = max($prices);
        $highestPriceIndex = array_search($highestPrice, $prices);

        return [
            'price' => $highestPrice,
            'datetime' => $data['Prices'][$highestPriceIndex]['readingDate'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     *
     * @throws Exception
     */
    public function getPricesAboveThreshold(
        string $startDate,
        string $endDate,
        float $threshold,
        Interval|int|null $interval = null,
        ?bool $vat = null,
        EnergyType|int|null $energyType = null
    ): array {
        $intervalValue = $this->resolveIntervalValue($interval);
        $data = $this->energyPrices($startDate, $endDate, $intervalValue, $vat, $energyType);

        return array_values(array_filter($data['Prices'], function ($price) use ($threshold) {
            return $price['price'] > $threshold;
        }));
    }

    /**
     * @return array<int, array<string, mixed>>
     *
     * @throws Exception
     */
    public function getPricesBelowThreshold(
        string $startDate,
        string $endDate,
        float $threshold,
        Interval|int|null $interval = null,
        ?bool $vat = null,
        EnergyType|int|null $energyType = null
    ): array {
        $intervalValue = $this->resolveIntervalValue($interval);
        $data = $this->energyPrices($startDate, $endDate, $intervalValue, $vat, $energyType);

        return array_values(array_filter($data['Prices'], function ($price) use ($threshold) {
            return $price['price'] < $threshold;
        }));
    }

    /**
     * @return array<int, array<string, mixed>>
     *
     * @throws Exception
     */
    public function getPeakHours(
        string $startDate,
        string $endDate,
        int $topN = 5,
        Interval|int|null $interval = null,
        ?bool $vat = null,
        EnergyType|int|null $energyType = null
    ): array {
        $intervalValue = $this->resolveIntervalValue($interval);
        $data = $this->energyPrices($startDate, $endDate, $intervalValue, $vat, $energyType);
        $prices = $data['Prices'];
        usort($prices, function ($a, $b) {
            return $b['price'] <=> $a['price'];
        });

        return array_slice($prices, 0, $topN);
    }

    /**
     * @return array<int, array<string, mixed>>
     *
     * @throws Exception
     */
    public function getValleyHours(
        string $startDate,
        string $endDate,
        int $topN = 5,
        Interval|int|null $interval = null,
        ?bool $vat = null,
        EnergyType|int|null $energyType = null
    ): array {
        $intervalValue = $this->resolveIntervalValue($interval);
        $data = $this->energyPrices($startDate, $endDate, $intervalValue, $vat, $energyType);
        $prices = $data['Prices'];
        usort($prices, function ($a, $b) {
            return $a['price'] <=> $b['price'];
        });

        return array_slice($prices, 0, $topN);
    }

    private function resolveIntervalValue(Interval|int|null $interval): int
    {
        if ($interval === null) {
            return $this->defaultInterval->value;
        }

        if ($interval instanceof Interval) {
            return $interval->value;
        }

        $resolved = Interval::tryFrom($interval);

        if ($resolved === null) {
            throw new InvalidArgumentException("Unknown interval {$interval}; use an Interval enum case.");
        }

        return $resolved->value;
    }

    private function resolveEnergyTypeValue(EnergyType|int|null $energyType): int
    {
        if ($energyType === null) {
            return $this->defaultEnergyType->value;
        }

        if ($energyType instanceof EnergyType) {
            return $energyType->value;
        }

        $resolved = EnergyType::tryFrom($energyType);

        if ($resolved === null) {
            throw new InvalidArgumentException("Unknown energy type {$energyType}; use an EnergyType enum case.");
        }

        return $resolved->value;
    }
}
