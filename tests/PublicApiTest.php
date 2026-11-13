<?php

use Baspa\EnergyZero\EnergyZero;
use Baspa\EnergyZero\Enums\EnergyType;
use Baspa\EnergyZero\Enums\Interval;
use Baspa\EnergyZero\Enums\PriceType;
use Baspa\EnergyZero\Exceptions\ApiException;
use Baspa\EnergyZero\Exceptions\NoPricesException;
use Baspa\EnergyZero\Tests\FixedClock;
use GuzzleHttp\Psr7\Response;

beforeEach(function () {
    $this->history = new ArrayObject;
});

it('gets quarter-hour electricity prices for a Dutch day', function () {
    $energyZero = new EnergyZero(fakeApi(['13-11-2026' => recorded('electricity-quarter-13-11-2026')], $this->history));

    $result = $energyZero->energyPrices('2026-11-13', '2026-11-13', Interval::QUARTER);

    expect($result['Prices'])->toHaveCount(96)
        ->and($result['Prices'][0])->toBe(['readingDate' => '2026-11-12T23:00:00Z', 'price' => 0.222277])
        ->and($result['Prices'][95])->toBe(['readingDate' => '2026-11-13T22:45:00Z', 'price' => 0.2405601])
        ->and(sentQuery($this->history))->toBe([
            'energyType' => 'ENERGY_TYPE_ELECTRICITY',
            'date' => '13-11-2026',
            'interval' => 'INTERVAL_QUARTER',
        ]);
});

it('gets hourly electricity prices for a Dutch day', function () {
    $energyZero = new EnergyZero(fakeApi(['13-11-2026' => recorded('electricity-hour-13-11-2026')], $this->history));

    $result = $energyZero->energyPrices('2026-11-13', '2026-11-13');

    expect($result['Prices'])->toHaveCount(24)
        ->and($result['Prices'][0])->toBe(['readingDate' => '2026-11-12T23:00:00Z', 'price' => 0.21305075])
        ->and($result['Prices'][23]['readingDate'])->toBe('2026-11-13T22:00:00Z')
        ->and(sentQuery($this->history))->toBe([
            'energyType' => 'ENERGY_TYPE_ELECTRICITY',
            'date' => '13-11-2026',
            'interval' => 'INTERVAL_HOUR',
        ]);
});

it('gets daily gas prices without an interval', function () {
    $energyZero = new EnergyZero(fakeApi(['13-11-2026' => recorded('gas-day-13-11-2026')], $this->history));

    $result = $energyZero->gasPrices('2026-11-13', '2026-11-13');

    expect($result)->toBe([
        'Prices' => [['readingDate' => '2026-11-13T05:00:00Z', 'price' => 1.09319677812796]],
        'average' => 1.09319677812796,
    ])->and(sentQuery($this->history))->toBe([
        'energyType' => 'ENERGY_TYPE_GAS',
        'date' => '13-11-2026',
        'interval' => 'INTERVAL_DAY',
    ]);
});

it('maps integer intervals and energy types to the API values', function (int $interval, int $energyType, string $apiInterval, string $apiEnergyType) {
    $energyZero = new EnergyZero(fakeApi(['13-11-2026' => hourlyResponse('2026-11-12T23:00:00Z', 24)], $this->history));

    $energyZero->energyPrices('2026-11-13', '2026-11-13', $interval, null, $energyType);

    expect(sentQuery($this->history))
        ->interval->toBe($apiInterval)
        ->energyType->toBe($apiEnergyType);
})->with([
    'quarter' => [3, 1, 'INTERVAL_QUARTER', 'ENERGY_TYPE_ELECTRICITY'],
    'hour' => [4, 1, 'INTERVAL_HOUR', 'ENERGY_TYPE_ELECTRICITY'],
    'day' => [5, 3, 'INTERVAL_DAY', 'ENERGY_TYPE_GAS'],
]);

it('reads the requested price type', function (PriceType|bool|null $priceType, float $quarterPrice, float $gasPrice) {
    $energyZero = new EnergyZero(fakeApi(['13-11-2026' => recorded('electricity-quarter-13-11-2026')], $this->history));
    $gas = new EnergyZero(fakeApi(['13-11-2026' => recorded('gas-day-13-11-2026')], $this->history));

    expect($energyZero->energyPrices('2026-11-13', '2026-11-13', Interval::QUARTER, $priceType)['Prices'][0]['price'])->toBe($quarterPrice)
        ->and($gas->gasPrices('2026-11-13', '2026-11-13', null, $priceType)['Prices'][0]['price'])->toBe($gasPrice);
})->with([
    'market' => [PriceType::MARKET, 0.1837, 0.903468411676],
    'market with VAT' => [PriceType::MARKET_WITH_VAT, 0.222277, 1.09319677812796],
    'all-in' => [PriceType::ALL_IN, 0.27531, 1.504128411676],
    'all-in with VAT' => [PriceType::ALL_IN_WITH_VAT, 0.3331251, 1.81999537812796],
    'true' => [true, 0.222277, 1.09319677812796],
    'false' => [false, 0.1837, 0.903468411676],
    'null' => [null, 0.222277, 1.09319677812796],
]);

it('uses the default price type for a null price type', function () {
    $energyZero = (new EnergyZero(fakeApi(['13-11-2026' => recorded('electricity-hour-13-11-2026')], $this->history)))
        ->setDefaultPriceType(PriceType::ALL_IN);

    expect($energyZero->energyPrices('2026-11-13', '2026-11-13')['Prices'][0]['price'])->toBe(0.267685)
        ->and($energyZero->energyPrices('2026-11-13', '2026-11-13', priceType: true)['Prices'][0]['price'])->toBe(0.21305075);
});

it('skips dates that an earlier response already covered', function () {
    $energyZero = new EnergyZero(fakeApi([
        '13-11-2026' => hourlyResponse('2026-11-11T23:00:00Z', 48),
        '11-11-2026' => hourlyResponse('2026-11-09T23:00:00Z', 48),
    ], $this->history));

    $readingDates = array_column($energyZero->energyPrices('2026-11-10', '2026-11-13')['Prices'], 'readingDate');

    expect(requestedDates($this->history))->toBe(['13-11-2026', '11-11-2026'])
        ->and($readingDates)->toHaveCount(96)
        ->and($readingDates[0])->toBe('2026-11-09T23:00:00Z')
        ->and($readingDates[95])->toBe('2026-11-13T22:00:00Z');
});

it('drops duplicate slots and slots outside the range', function () {
    $energyZero = new EnergyZero(fakeApi([
        '12-11-2026' => hourlyResponse('2026-11-10T23:00:00Z', 72),
        '10-11-2026' => hourlyResponse('2026-11-08T23:00:00Z', 72),
    ], $this->history));

    $readingDates = array_column($energyZero->energyPrices('2026-11-10', '2026-11-12')['Prices'], 'readingDate');
    $sorted = $readingDates;
    sort($sorted);

    expect(requestedDates($this->history))->toBe(['12-11-2026', '10-11-2026'])
        ->and($readingDates)->toHaveCount(72)
        ->and($readingDates)->toBe(array_values(array_unique($readingDates)))
        ->and($readingDates)->toBe($sorted)
        ->and($readingDates[0])->toBe('2026-11-09T23:00:00Z')
        ->and($readingDates[71])->toBe('2026-11-12T22:00:00Z');
});

it('treats a 404 as a day without prices', function () {
    $energyZero = new EnergyZero(fakeApi([
        '14-11-2026' => noPrices(),
        '13-11-2026' => recorded('electricity-hour-13-11-2026'),
    ], $this->history));

    $prices = $energyZero->energyPrices('2026-11-13', '2026-11-14')['Prices'];

    expect(requestedDates($this->history))->toBe(['14-11-2026', '13-11-2026'])
        ->and($prices)->toHaveCount(24)
        ->and($prices[23]['readingDate'])->toBe('2026-11-13T22:00:00Z');
});

it('throws a NoPricesException when no day in the range has prices', function () {
    $energyZero = new EnergyZero(fakeApi([
        '15-11-2026' => noPrices(),
        '14-11-2026' => noPrices(),
    ], $this->history));

    expect(fn () => $energyZero->energyPrices('2026-11-14', '2026-11-15'))
        ->toThrow(NoPricesException::class, 'No energy prices found for this period.')
        ->and(requestedDates($this->history))->toBe(['15-11-2026', '14-11-2026']);
});

it('throws an ApiException with the API message on a 400', function () {
    $energyZero = new EnergyZero(fakeApi([
        '13-11-2026' => apiError(400, 3, 'invalid interval type for electricity: daily'),
    ], $this->history));

    expect(fn () => $energyZero->energyPrices('2026-11-13', '2026-11-13'))
        ->toThrow(function (ApiException $e) {
            expect($e->getCode())->toBe(400)
                ->and($e->getMessage())->toBe('EnergyZero API returned HTTP 400: invalid interval type for electricity: daily');
        });
});

it('returns the current electricity price', function () {
    $energyZero = new EnergyZero(
        fakeApi(['13-11-2026' => recorded('electricity-hour-13-11-2026')], $this->history),
        new FixedClock('2026-11-13T09:35:00Z'),
    );

    expect($energyZero->getCurrentPrice())->toBe(['price' => 0.2410562, 'datetime' => '2026-11-13T09:00:00Z'])
        ->and(requestedDates($this->history))->toBe(['13-11-2026'])
        ->and(sentQuery($this->history))->interval->toBe('INTERVAL_HOUR');
});

it('returns the gas day that started yesterday before 06:00 Dutch time', function () {
    $energyZero = new EnergyZero(
        fakeApi(['13-11-2026' => recorded('gas-day-13-11-2026')], $this->history),
        new FixedClock('2026-11-13T02:00:00Z'),
    );

    expect($energyZero->getCurrentPrice(energyType: EnergyType::GAS))
        ->toBe(['price' => 1.02179777359836, 'datetime' => '2026-11-12T05:00:00Z'])
        ->and(sentQuery($this->history))->toBe([
            'energyType' => 'ENERGY_TYPE_GAS',
            'date' => '13-11-2026',
            'interval' => 'INTERVAL_DAY',
        ]);
});

it('returns the current gas price before today\'s gas price is published', function () {
    $energyZero = new EnergyZero(
        fakeApi([
            '13-11-2026' => noPrices(),
            '12-11-2026' => pricesResponse('2026-11-11T05:00:00Z', [0.98, 1.02], 1440),
        ], $this->history),
        new FixedClock('2026-11-13T02:00:00Z'),
    );

    expect($energyZero->getCurrentPrice(energyType: EnergyType::GAS))->toBe(['price' => 1.02, 'datetime' => '2026-11-12T05:00:00Z'])
        ->and(requestedDates($this->history))->toBe(['13-11-2026', '12-11-2026']);
});

it('returns today\'s gas day after 06:00 Dutch time', function () {
    $energyZero = new EnergyZero(
        fakeApi(['13-11-2026' => recorded('gas-day-13-11-2026')], $this->history),
        new FixedClock('2026-11-13T09:00:00Z'),
    );

    expect($energyZero->getCurrentPrice(PriceType::MARKET, EnergyType::GAS))
        ->toBe(['price' => 0.903468411676, 'datetime' => '2026-11-13T05:00:00Z']);
});

it('rejects hourly lookups for gas before sending a request', function () {
    $energyZero = new EnergyZero(fakeApi([], $this->history));

    expect(fn () => $energyZero->getPricesForHours('2026-11-13', [6], energyType: EnergyType::GAS))
        ->toThrow(InvalidArgumentException::class, 'Gas only has daily prices')
        ->and($this->history)->toHaveCount(0);
});

it('computes the average of the returned prices without rounding', function () {
    $energyZero = new EnergyZero(fakeApi([
        '13-11-2026' => pricesResponse('2026-11-12T23:00:00Z', [0.1, 0.2, 0.25]),
    ], $this->history));

    expect($energyZero->energyPrices('2026-11-13', '2026-11-13')['average'])->toBe((0.1 + 0.2 + 0.25) / 3)
        ->and($energyZero->getAveragePriceForPeriod('2026-11-13', '2026-11-13'))->toBe((0.1 + 0.2 + 0.25) / 3);
});

it('averages only the prices inside the range', function () {
    $energyZero = new EnergyZero(fakeApi(['13-11-2026' => recorded('electricity-quarter-13-11-2026')], $this->history));

    expect($energyZero->getAveragePriceForPeriod('2026-11-13', '2026-11-13', Interval::QUARTER))
        ->toEqualWithDelta(0.25334891770833, 1e-12);
});

it('throws an ApiException when the response has no prices for the price type', function () {
    $energyZero = new EnergyZero(fakeApi([
        '13-11-2026' => new Response(200, [], json_encode(['interval' => 'RESPONSE_INTERVAL_HOUR'])),
    ], $this->history));

    expect(fn () => $energyZero->energyPrices('2026-11-13', '2026-11-13'))
        ->toThrow(ApiException::class, 'EnergyZero API returned no base_with_vat prices.');
});

it('throws an ApiException when a price has an unexpected format', function (mixed $entry) {
    $energyZero = new EnergyZero(fakeApi([
        '13-11-2026' => new Response(200, [], json_encode(['base_with_vat' => [$entry]])),
    ], $this->history));

    expect(fn () => $energyZero->energyPrices('2026-11-13', '2026-11-13'))
        ->toThrow(ApiException::class, 'EnergyZero API returned a price in an unexpected format.');
})->with([
    'not an object' => ['0.21'],
    'no start' => [['price' => ['value' => '0.21']]],
    'invalid start' => [['start' => 'soon', 'price' => ['value' => '0.21']]],
    'no value' => [['start' => '2026-11-12T23:00:00Z', 'price' => []]],
    'non-numeric value' => [['start' => '2026-11-12T23:00:00Z', 'price' => ['value' => 'n/a']]],
]);
