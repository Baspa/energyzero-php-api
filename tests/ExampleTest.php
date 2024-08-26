<?php

use Baspa\EnergyZero\EnergyZero;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

beforeEach(function () {
    $this->mockHandler = new MockHandler();
    $handlerStack = HandlerStack::create($this->mockHandler);
    $client = new Client(['handler' => $handlerStack]);
    $this->energyZero = new EnergyZero($client);
});

it('can make a request', function () {
    $this->mockHandler->append(new Response(200, [], json_encode(['data' => 'test'])));

    $result = $this->energyZero->request('test');

    expect($result)->toBe(['data' => 'test']);
});

it('handles failed requests', function () {
    $this->mockHandler->append(new Response(500));

    $result = $this->energyZero->request('test');

    expect($result)->toBeNull();
});

it('can get energy prices', function () {
    $mockData = [
        'Prices' => [
            ['readingDate' => '2023-05-01T00:00:00', 'price' => 0.1],
            ['readingDate' => '2023-05-01T01:00:00', 'price' => 0.2],
        ],
        'average' => 0.15,
    ];

    $this->mockHandler->append(new Response(200, [], json_encode($mockData)));

    $result = $this->energyZero->energyPrices('2023-05-01', '2023-05-02');

    expect($result)->toBe($mockData);
});

it('can get average price for period', function () {
    $mockData = [
        'Prices' => [
            ['readingDate' => '2023-05-01T00:00:00', 'price' => 0.1],
            ['readingDate' => '2023-05-01T01:00:00', 'price' => 0.2],
        ],
        'average' => 0.15,
    ];

    $this->mockHandler->append(new Response(200, [], json_encode($mockData)));

    $result = $this->energyZero->getAveragePriceForPeriod('2023-05-01', '2023-05-02');

    expect($result)->toBe(0.15);
});

it('can get lowest price for period', function () {
    $mockData = [
        'Prices' => [
            ['readingDate' => '2023-05-01T00:00:00', 'price' => 0.1],
            ['readingDate' => '2023-05-01T01:00:00', 'price' => 0.2],
        ],
    ];

    $this->mockHandler->append(new Response(200, [], json_encode($mockData)));

    $result = $this->energyZero->getLowestPriceForPeriod('2023-05-01', '2023-05-02');

    expect($result)->toBe([
        'price' => 0.1,
        'datetime' => '2023-05-01T00:00:00',
    ]);
});

it('can get highest price for period', function () {
    $mockData = [
        'Prices' => [
            ['readingDate' => '2023-05-01T00:00:00', 'price' => 0.1],
            ['readingDate' => '2023-05-01T01:00:00', 'price' => 0.2],
        ],
    ];

    $this->mockHandler->append(new Response(200, [], json_encode($mockData)));

    $result = $this->energyZero->getHighestPriceForPeriod('2023-05-01', '2023-05-02');

    expect($result)->toBe([
        'price' => 0.2,
        'datetime' => '2023-05-01T01:00:00',
    ]);
});

it('can get prices above threshold', function () {
    $mockData = [
        'Prices' => [
            ['readingDate' => '2023-05-01T00:00:00', 'price' => 0.1],
            ['readingDate' => '2023-05-01T01:00:00', 'price' => 0.2],
            ['readingDate' => '2023-05-01T02:00:00', 'price' => 0.3],
        ],
    ];

    $this->mockHandler->append(new Response(200, [], json_encode($mockData)));

    $result = $this->energyZero->getPricesAboveThreshold('2023-05-01', '2023-05-02', 0.15);

    expect($result)->toBe([
        ['readingDate' => '2023-05-01T01:00:00', 'price' => 0.2],
        ['readingDate' => '2023-05-01T02:00:00', 'price' => 0.3],
    ]);
});

it('can get prices below threshold', function () {
    $mockData = [
        'Prices' => [
            ['readingDate' => '2023-05-01T00:00:00', 'price' => 0.1],
            ['readingDate' => '2023-05-01T01:00:00', 'price' => 0.2],
            ['readingDate' => '2023-05-01T02:00:00', 'price' => 0.3],
        ],
    ];

    $this->mockHandler->append(new Response(200, [], json_encode($mockData)));

    $result = $this->energyZero->getPricesBelowThreshold('2023-05-01', '2023-05-02', 0.25);

    expect($result)->toBe([
        ['readingDate' => '2023-05-01T00:00:00', 'price' => 0.1],
        ['readingDate' => '2023-05-01T01:00:00', 'price' => 0.2],
    ]);
});

it('can get peak hours', function () {
    $mockData = [
        'Prices' => [
            ['readingDate' => '2023-05-01T00:00:00', 'price' => 0.1],
            ['readingDate' => '2023-05-01T01:00:00', 'price' => 0.3],
            ['readingDate' => '2023-05-01T02:00:00', 'price' => 0.2],
        ],
    ];

    $this->mockHandler->append(new Response(200, [], json_encode($mockData)));

    $result = $this->energyZero->getPeakHours('2023-05-01', '2023-05-02', 2);

    expect($result)->toBe([
        ['readingDate' => '2023-05-01T01:00:00', 'price' => 0.3],
        ['readingDate' => '2023-05-01T02:00:00', 'price' => 0.2],
    ]);
});

it('can get valley hours', function () {
    $mockData = [
        'Prices' => [
            ['readingDate' => '2023-05-01T00:00:00', 'price' => 0.1],
            ['readingDate' => '2023-05-01T01:00:00', 'price' => 0.3],
            ['readingDate' => '2023-05-01T02:00:00', 'price' => 0.2],
        ],
    ];

    $this->mockHandler->append(new Response(200, [], json_encode($mockData)));

    $result = $this->energyZero->getValleyHours('2023-05-01', '2023-05-02', 2);

    expect($result)->toBe([
        ['readingDate' => '2023-05-01T00:00:00', 'price' => 0.1],
        ['readingDate' => '2023-05-01T02:00:00', 'price' => 0.2],
    ]);
});