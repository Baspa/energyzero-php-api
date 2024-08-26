<?php

namespace Baspa\EnergyZero;

use DateTime;
use DateTimeZone;
use Exception;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Http;

class EnergyZero
{
    private bool $vat = true;

    private float $requestTimeout = 10.0;

    private string $baseUri = 'https://api.energyzero.nl/v1/';

    public function request(string $uri, array $params = [])
    {
        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json, text/plain',
                'User-Agent' => 'PHPEnergyZero/1.0',
            ])->timeout($this->requestTimeout)->get($this->baseUri.$uri, $params);

            if ($response->successful()) {
                return $response->json();
            } else {
                throw new Exception('Unexpected response status: '.$response->status());
            }
        } catch (RequestException $e) {
            echo 'Error: ', $e->getMessage(), "\n";
        }
    }

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
}
