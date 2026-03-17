<?php

use Baspa\EnergyZero\EnergyZero;
use Baspa\EnergyZero\Enums\EnergyType;
use Baspa\EnergyZero\Enums\Interval;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

beforeEach(function () {
    $this->mockHandler = new MockHandler;
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

it('can get energy prices with interval enum', function () {
    $mockData = [
        'Prices' => [
            ['readingDate' => '2023-05-01T00:00:00', 'price' => 0.1],
            ['readingDate' => '2023-05-01T00:15:00', 'price' => 0.12],
            ['readingDate' => '2023-05-01T00:30:00', 'price' => 0.11],
            ['readingDate' => '2023-05-01T00:45:00', 'price' => 0.13],
        ],
        'average' => 0.115,
    ];

    $this->mockHandler->append(new Response(200, [], json_encode($mockData)));

    $result = $this->energyZero->energyPrices('2023-05-01', '2023-05-01', Interval::QUARTER);

    expect($result)->toBe($mockData);
    expect(count($result['Prices']))->toBe(4);
});

it('can get energy prices with integer interval for backward compatibility', function () {
    $mockData = [
        'Prices' => [
            ['readingDate' => '2023-05-01T00:00:00', 'price' => 0.1],
            ['readingDate' => '2023-05-01T00:15:00', 'price' => 0.12],
        ],
        'average' => 0.11,
    ];

    $this->mockHandler->append(new Response(200, [], json_encode($mockData)));

    $result = $this->energyZero->energyPrices('2023-05-01', '2023-05-01', 3);

    expect($result)->toBe($mockData);
});

it('can set default interval', function () {
    $mockData = [
        'Prices' => [
            ['readingDate' => '2023-05-01T00:00:00', 'price' => 0.1],
        ],
        'average' => 0.1,
    ];

    $this->mockHandler->append(new Response(200, [], json_encode($mockData)));

    $result = $this->energyZero
        ->setDefaultInterval(Interval::QUARTER)
        ->energyPrices('2023-05-01', '2023-05-01');

    expect($result)->toBe($mockData);
});

it('can set default energy type', function () {
    $mockData = [
        'Prices' => [
            ['readingDate' => '2023-05-01T00:00:00', 'price' => 0.8],
        ],
        'average' => 0.8,
    ];

    $this->mockHandler->append(new Response(200, [], json_encode($mockData)));

    $result = $this->energyZero
        ->setDefaultEnergyType(EnergyType::GAS)
        ->energyPrices('2023-05-01', '2023-05-01');

    expect($result)->toBe($mockData);
});

it('can get gas prices', function () {
    $mockData = [
        'Prices' => [
            ['readingDate' => '2023-05-01T00:00:00', 'price' => 0.8],
            ['readingDate' => '2023-05-01T01:00:00', 'price' => 0.85],
        ],
        'average' => 0.825,
    ];

    $this->mockHandler->append(new Response(200, [], json_encode($mockData)));

    $result = $this->energyZero->gasPrices('2023-05-01', '2023-05-02');

    expect($result)->toBe($mockData);
});

it('can get current price', function () {
    $now = new DateTime;
    $currentHour = (int) $now->format('G');
    $today = $now->format('Y-m-d');

    $mockData = [
        'Prices' => [
            ['readingDate' => $today.'T'.str_pad((string) $currentHour, 2, '0', STR_PAD_LEFT).':00:00', 'price' => 0.15],
        ],
        'average' => 0.15,
    ];

    $this->mockHandler->append(new Response(200, [], json_encode($mockData)));

    $result = $this->energyZero->getCurrentPrice();

    expect($result['price'])->toBe(0.15);
});

it('can get prices for specific hours', function () {
    $mockData = [
        'Prices' => [
            ['readingDate' => '2023-05-01T08:00:00', 'price' => 0.1],
            ['readingDate' => '2023-05-01T09:00:00', 'price' => 0.12],
            ['readingDate' => '2023-05-01T10:00:00', 'price' => 0.15],
            ['readingDate' => '2023-05-01T11:00:00', 'price' => 0.18],
        ],
        'average' => 0.1375,
    ];

    $this->mockHandler->append(new Response(200, [], json_encode($mockData)));

    $result = $this->energyZero->getPricesForHours('2023-05-01', [8, 10]);

    expect($result)->toHaveCount(2);
    expect($result[0]['price'])->toBe(0.1);
    expect($result[1]['price'])->toBe(0.15);
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

it('can get average price for period with interval', function () {
    $mockData = [
        'Prices' => [
            ['readingDate' => '2023-05-01T00:00:00', 'price' => 0.1],
            ['readingDate' => '2023-05-01T00:15:00', 'price' => 0.12],
        ],
        'average' => 0.11,
    ];

    $this->mockHandler->append(new Response(200, [], json_encode($mockData)));

    $result = $this->energyZero->getAveragePriceForPeriod('2023-05-01', '2023-05-01', Interval::QUARTER);

    expect($result)->toBe(0.11);
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

it('can get lowest price for period with interval', function () {
    $mockData = [
        'Prices' => [
            ['readingDate' => '2023-05-01T00:00:00', 'price' => 0.12],
            ['readingDate' => '2023-05-01T00:15:00', 'price' => 0.08],
        ],
    ];

    $this->mockHandler->append(new Response(200, [], json_encode($mockData)));

    $result = $this->energyZero->getLowestPriceForPeriod('2023-05-01', '2023-05-01', Interval::QUARTER);

    expect($result)->toBe([
        'price' => 0.08,
        'datetime' => '2023-05-01T00:15:00',
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

it('can get highest price for period with interval', function () {
    $mockData = [
        'Prices' => [
            ['readingDate' => '2023-05-01T00:00:00', 'price' => 0.12],
            ['readingDate' => '2023-05-01T00:15:00', 'price' => 0.18],
        ],
    ];

    $this->mockHandler->append(new Response(200, [], json_encode($mockData)));

    $result = $this->energyZero->getHighestPriceForPeriod('2023-05-01', '2023-05-01', Interval::QUARTER);

    expect($result)->toBe([
        'price' => 0.18,
        'datetime' => '2023-05-01T00:15:00',
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

it('can get prices above threshold with interval', function () {
    $mockData = [
        'Prices' => [
            ['readingDate' => '2023-05-01T00:00:00', 'price' => 0.1],
            ['readingDate' => '2023-05-01T00:15:00', 'price' => 0.2],
        ],
    ];

    $this->mockHandler->append(new Response(200, [], json_encode($mockData)));

    $result = $this->energyZero->getPricesAboveThreshold('2023-05-01', '2023-05-01', 0.15, Interval::QUARTER);

    expect($result)->toBe([
        ['readingDate' => '2023-05-01T00:15:00', 'price' => 0.2],
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

it('can get prices below threshold with interval', function () {
    $mockData = [
        'Prices' => [
            ['readingDate' => '2023-05-01T00:00:00', 'price' => 0.1],
            ['readingDate' => '2023-05-01T00:15:00', 'price' => 0.2],
        ],
    ];

    $this->mockHandler->append(new Response(200, [], json_encode($mockData)));

    $result = $this->energyZero->getPricesBelowThreshold('2023-05-01', '2023-05-01', 0.15, Interval::QUARTER);

    expect($result)->toBe([
        ['readingDate' => '2023-05-01T00:00:00', 'price' => 0.1],
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

it('can get peak hours with interval', function () {
    $mockData = [
        'Prices' => [
            ['readingDate' => '2023-05-01T00:00:00', 'price' => 0.1],
            ['readingDate' => '2023-05-01T00:15:00', 'price' => 0.3],
            ['readingDate' => '2023-05-01T00:30:00', 'price' => 0.2],
        ],
    ];

    $this->mockHandler->append(new Response(200, [], json_encode($mockData)));

    $result = $this->energyZero->getPeakHours('2023-05-01', '2023-05-01', 2, Interval::QUARTER);

    expect($result)->toBe([
        ['readingDate' => '2023-05-01T00:15:00', 'price' => 0.3],
        ['readingDate' => '2023-05-01T00:30:00', 'price' => 0.2],
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

it('can get valley hours with interval', function () {
    $mockData = [
        'Prices' => [
            ['readingDate' => '2023-05-01T00:00:00', 'price' => 0.1],
            ['readingDate' => '2023-05-01T00:15:00', 'price' => 0.3],
            ['readingDate' => '2023-05-01T00:30:00', 'price' => 0.2],
        ],
    ];

    $this->mockHandler->append(new Response(200, [], json_encode($mockData)));

    $result = $this->energyZero->getValleyHours('2023-05-01', '2023-05-01', 2, Interval::QUARTER);

    expect($result)->toBe([
        ['readingDate' => '2023-05-01T00:00:00', 'price' => 0.1],
        ['readingDate' => '2023-05-01T00:30:00', 'price' => 0.2],
    ]);
});

it('returns fluent interface for setDefaultInterval', function () {
    $result = $this->energyZero->setDefaultInterval(Interval::QUARTER);

    expect($result)->toBeInstanceOf(EnergyZero::class);
});

it('returns fluent interface for setDefaultEnergyType', function () {
    $result = $this->energyZero->setDefaultEnergyType(EnergyType::GAS);

    expect($result)->toBeInstanceOf(EnergyZero::class);
});

it('interval enum has correct values', function () {
    expect(Interval::QUARTER->value)->toBe(3);
    expect(Interval::HOUR->value)->toBe(4);
    expect(Interval::DAY->value)->toBe(5);
    expect(Interval::WEEK->value)->toBe(6);
    expect(Interval::MONTH->value)->toBe(7);
    expect(Interval::YEAR->value)->toBe(8);
});

it('energy type enum has correct values', function () {
    expect(EnergyType::ELECTRICITY->value)->toBe(1);
    expect(EnergyType::GAS->value)->toBe(3);
});
