<?php

namespace Baspa\EnergyZero;

use Baspa\EnergyZero\Enums\EnergyType;
use Baspa\EnergyZero\Enums\Interval;
use DateTime;
use DateTimeZone;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;

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
     * @return array<string, mixed>|null
     *
     * @throws Exception
     */
    public function request(string $uri, array $params = []): ?array
    {
        try {
            $response = $this->client->request('GET', $uri, ['query' => $params]);

            if ($response->getStatusCode() === 200) {
                return json_decode($response->getBody()->getContents(), true);
            } else {
                throw new Exception('Unexpected response status: '.$response->getStatusCode());
            }
        } catch (RequestException $e) {
            error_log('Error: '.$e->getMessage());

            return null;
        }
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

        $params = [
            'fromDate' => $utcStartDate->format('Y-m-d\TH:i:s.000\Z'),
            'tillDate' => $utcEndDate->format('Y-m-d\TH:i:s.999\Z'),
            'interval' => $intervalValue,
            'usageType' => $energyTypeValue,
            'inclBtw' => $vat ? 'true' : 'false',
        ];

        $data = $this->request('energyprices', $params);

        if (empty($data['Prices'])) {
            throw new Exception('No energy prices found for this period.');
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

        throw new Exception('Could not find current price.');
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
        $lowestPrice = min(array_column($data['Prices'], 'price'));
        $lowestPriceIndex = array_search($lowestPrice, array_column($data['Prices'], 'price'));

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
        $highestPrice = max(array_column($data['Prices'], 'price'));
        $highestPriceIndex = array_search($highestPrice, array_column($data['Prices'], 'price'));

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

        return $interval;
    }

    private function resolveEnergyTypeValue(EnergyType|int|null $energyType): int
    {
        if ($energyType === null) {
            return $this->defaultEnergyType->value;
        }

        if ($energyType instanceof EnergyType) {
            return $energyType->value;
        }

        return $energyType;
    }
}
