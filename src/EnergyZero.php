<?php

namespace Baspa\EnergyZero;

use DateTime;
use DateTimeZone;
use Exception;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class EnergyZero
{
    private bool $vat = true;

    private int $requestTimeout = 10;

    private string $baseUri = 'https://api.energyzero.nl/v1/';

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>|null
     *
     * @throws Exception
     */
    public function request(string $uri, array $params = []): ?array
    {
        try {
            /** @var PendingRequest $request */
            $request = Http::withHeaders([
                'Accept' => 'application/json, text/plain',
                'User-Agent' => 'PHPEnergyZero/1.0',
            ]);

            $response = $request->timeout($this->requestTimeout)
                ->get($this->baseUri.$uri, $params);

            if ($response->successful()) {
                return $response->json();
            } else {
                throw new Exception('Unexpected response status: '.$response->status());
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
    public function energyPrices(string $startDate, string $endDate, int $interval = 4, ?bool $vat = null): array
    {
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

        $params = [
            'fromDate' => $utcStartDate->format('Y-m-d\TH:i:s.000\Z'),
            'tillDate' => $utcEndDate->format('Y-m-d\TH:i:s.999\Z'),
            'interval' => $interval,
            'usageType' => 1,
            'inclBtw' => $vat ? 'true' : 'false',
        ];

        $data = $this->request('energyprices', $params);

        if (empty($data['Prices'])) {
            throw new Exception('No energy prices found for this period.');
        }

        return $data;
    }

    /**
     * @throws Exception
     */
    public function getAveragePriceForPeriod(string $startDate, string $endDate, ?bool $vat = null): float
    {
        $data = $this->energyPrices($startDate, $endDate, 4, $vat);

        return $data['average'];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    public function getLowestPriceForPeriod(string $startDate, string $endDate, ?bool $vat = null): array
    {
        $data = $this->energyPrices($startDate, $endDate, 4, $vat);
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
    public function getHighestPriceForPeriod(string $startDate, string $endDate, ?bool $vat = null): array
    {
        $data = $this->energyPrices($startDate, $endDate, 4, $vat);
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
    public function getPricesAboveThreshold(string $startDate, string $endDate, float $threshold, ?bool $vat = null): array
    {
        $data = $this->energyPrices($startDate, $endDate, 4, $vat);

        return array_values(array_filter($data['Prices'], function ($price) use ($threshold) {
            return $price['price'] > $threshold;
        }));
    }

    /**
     * @return array<int, array<string, mixed>>
     *
     * @throws Exception
     */
    public function getPricesBelowThreshold(string $startDate, string $endDate, float $threshold, ?bool $vat = null): array
    {
        $data = $this->energyPrices($startDate, $endDate, 4, $vat);

        return array_values(array_filter($data['Prices'], function ($price) use ($threshold) {
            return $price['price'] < $threshold;
        }));
    }

    /**
     * @return array<int, array<string, mixed>>
     *
     * @throws Exception
     */
    public function getPeakHours(string $startDate, string $endDate, int $topN = 5, ?bool $vat = null): array
    {
        $data = $this->energyPrices($startDate, $endDate, 4, $vat);
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
    public function getValleyHours(string $startDate, string $endDate, int $topN = 5, ?bool $vat = null): array
    {
        $data = $this->energyPrices($startDate, $endDate, 4, $vat);
        $prices = $data['Prices'];
        usort($prices, function ($a, $b) {
            return $a['price'] <=> $b['price'];
        });

        return array_slice($prices, 0, $topN);
    }
}
