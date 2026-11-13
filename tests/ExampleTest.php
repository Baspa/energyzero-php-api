<?php

use Baspa\EnergyZero\EnergyZero;
use Baspa\EnergyZero\Enums\EnergyType;
use Baspa\EnergyZero\Enums\Interval;
use Baspa\EnergyZero\Enums\PriceType;
use Baspa\EnergyZero\Exceptions\ApiException;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

beforeEach(function () {
    $this->history = new ArrayObject;
    $this->energyZero = new EnergyZero(fakeApi([
        '13-11-2026' => pricesResponse('2026-11-12T23:00:00Z', [0.25, 0.75, 0.5, 0.25]),
    ], $this->history));
});

it('can make a request', function () {
    $mockHandler = new MockHandler([new Response(200, [], json_encode(['data' => 'test']))]);
    $energyZero = new EnergyZero(new Client(['handler' => HandlerStack::create($mockHandler)]));

    expect($energyZero->request('test'))->toBe(['data' => 'test']);
});

it('handles failed requests', function () {
    $mockHandler = new MockHandler([new Response(500)]);
    $energyZero = new EnergyZero(new Client(['handler' => HandlerStack::create($mockHandler)]));

    $energyZero->request('test');
})->throws(ApiException::class);

it('can get energy prices', function () {
    expect($this->energyZero->energyPrices('2026-11-13', '2026-11-13'))->toBe([
        'Prices' => [
            ['readingDate' => '2026-11-12T23:00:00Z', 'price' => 0.25],
            ['readingDate' => '2026-11-13T00:00:00Z', 'price' => 0.75],
            ['readingDate' => '2026-11-13T01:00:00Z', 'price' => 0.5],
            ['readingDate' => '2026-11-13T02:00:00Z', 'price' => 0.25],
        ],
        'average' => 0.4375,
    ]);
});

it('can set default interval', function () {
    $this->energyZero->setDefaultInterval(Interval::QUARTER)->energyPrices('2026-11-13', '2026-11-13');

    expect(sentQuery($this->history))->interval->toBe('INTERVAL_QUARTER');
});

it('can set default energy type', function () {
    $this->energyZero->setDefaultEnergyType(EnergyType::GAS)->energyPrices('2026-11-13', '2026-11-13');

    expect(sentQuery($this->history))
        ->energyType->toBe('ENERGY_TYPE_GAS')
        ->interval->toBe('INTERVAL_DAY');
});

it('can get prices for specific hours', function () {
    expect($this->energyZero->getPricesForHours('2026-11-13', [1, 2]))->toBe([
        ['readingDate' => '2026-11-13T00:00:00Z', 'price' => 0.75],
        ['readingDate' => '2026-11-13T01:00:00Z', 'price' => 0.5],
    ]);
});

it('can get average price for period', function () {
    expect($this->energyZero->getAveragePriceForPeriod('2026-11-13', '2026-11-13'))->toBe(0.4375);
});

it('can get the first lowest price for period', function () {
    expect($this->energyZero->getLowestPriceForPeriod('2026-11-13', '2026-11-13'))
        ->toBe(['price' => 0.25, 'datetime' => '2026-11-12T23:00:00Z']);
});

it('can get highest price for period', function () {
    expect($this->energyZero->getHighestPriceForPeriod('2026-11-13', '2026-11-13'))
        ->toBe(['price' => 0.75, 'datetime' => '2026-11-13T00:00:00Z']);
});

it('can get prices above threshold', function () {
    expect($this->energyZero->getPricesAboveThreshold('2026-11-13', '2026-11-13', 0.3))->toBe([
        ['readingDate' => '2026-11-13T00:00:00Z', 'price' => 0.75],
        ['readingDate' => '2026-11-13T01:00:00Z', 'price' => 0.5],
    ]);
});

it('can get prices below threshold', function () {
    expect($this->energyZero->getPricesBelowThreshold('2026-11-13', '2026-11-13', 0.3))->toBe([
        ['readingDate' => '2026-11-12T23:00:00Z', 'price' => 0.25],
        ['readingDate' => '2026-11-13T02:00:00Z', 'price' => 0.25],
    ]);
});

it('can get peak hours', function () {
    expect($this->energyZero->getPeakHours('2026-11-13', '2026-11-13', 2))->toBe([
        ['readingDate' => '2026-11-13T00:00:00Z', 'price' => 0.75],
        ['readingDate' => '2026-11-13T01:00:00Z', 'price' => 0.5],
    ]);
});

it('can get valley hours', function () {
    expect($this->energyZero->getValleyHours('2026-11-13', '2026-11-13', 2))->toBe([
        ['readingDate' => '2026-11-12T23:00:00Z', 'price' => 0.25],
        ['readingDate' => '2026-11-13T02:00:00Z', 'price' => 0.25],
    ]);
});

it('passes the interval and price type through every helper', function (Closure $call, mixed $expected) {
    $history = new ArrayObject;
    $energyZero = new EnergyZero(fakeApi(['13-11-2026' => recorded('electricity-quarter-13-11-2026')], $history));

    expect($call($energyZero))->toBe($expected)
        ->and(sentQuery($history))->interval->toBe('INTERVAL_QUARTER');
})->with([
    'lowest' => [
        fn (EnergyZero $e) => $e->getLowestPriceForPeriod('2026-11-13', '2026-11-13', Interval::QUARTER, PriceType::ALL_IN),
        ['price' => 0.22766, 'datetime' => '2026-11-13T03:00:00Z'],
    ],
    'highest' => [
        fn (EnergyZero $e) => $e->getHighestPriceForPeriod('2026-11-13', '2026-11-13', Interval::QUARTER, PriceType::ALL_IN),
        ['price' => 0.42071, 'datetime' => '2026-11-13T16:45:00Z'],
    ],
    'average' => [
        fn (EnergyZero $e) => round($e->getAveragePriceForPeriod('2026-11-13', '2026-11-13', Interval::QUARTER, false), 12),
        0.209379270833,
    ],
    'above threshold' => [
        fn (EnergyZero $e) => count($e->getPricesAboveThreshold('2026-11-13', '2026-11-13', 0.42, Interval::QUARTER, PriceType::ALL_IN)),
        1,
    ],
    'below threshold' => [
        fn (EnergyZero $e) => count($e->getPricesBelowThreshold('2026-11-13', '2026-11-13', 0.2, 3, true)),
        24,
    ],
    'peak' => [
        fn (EnergyZero $e) => $e->getPeakHours('2026-11-13', '2026-11-13', 1, Interval::QUARTER, PriceType::ALL_IN_WITH_VAT),
        [['readingDate' => '2026-11-13T16:45:00Z', 'price' => 0.5090591]],
    ],
    'valley' => [
        fn (EnergyZero $e) => $e->getValleyHours('2026-11-13', '2026-11-13', 1, Interval::QUARTER, PriceType::MARKET),
        [['readingDate' => '2026-11-13T03:00:00Z', 'price' => 0.13605]],
    ],
]);

it('returns fluent interfaces for the setters', function () {
    expect($this->energyZero->setDefaultInterval(Interval::QUARTER))->toBe($this->energyZero)
        ->and($this->energyZero->setDefaultEnergyType(EnergyType::GAS))->toBe($this->energyZero)
        ->and($this->energyZero->setDefaultPriceType(PriceType::ALL_IN))->toBe($this->energyZero);
});

it('interval enum has correct values', function () {
    expect(Interval::cases())->toBe([Interval::QUARTER, Interval::HOUR, Interval::DAY])
        ->and(Interval::QUARTER->value)->toBe(3)
        ->and(Interval::HOUR->value)->toBe(4)
        ->and(Interval::DAY->value)->toBe(5)
        ->and(Interval::QUARTER->apiValue())->toBe('INTERVAL_QUARTER')
        ->and(Interval::HOUR->apiValue())->toBe('INTERVAL_HOUR')
        ->and(Interval::DAY->apiValue())->toBe('INTERVAL_DAY');
});

it('energy type enum has correct values', function () {
    expect(EnergyType::ELECTRICITY->value)->toBe(1)
        ->and(EnergyType::GAS->value)->toBe(3)
        ->and(EnergyType::ELECTRICITY->apiValue())->toBe('ENERGY_TYPE_ELECTRICITY')
        ->and(EnergyType::GAS->apiValue())->toBe('ENERGY_TYPE_GAS');
});

it('price type enum values are the response keys', function () {
    expect(array_column(PriceType::cases(), 'value'))->toBe(['base', 'base_with_vat', 'all_in', 'all_in_with_vat']);
});
