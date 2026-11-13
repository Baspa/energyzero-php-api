<?php

use Baspa\EnergyZero\EnergyZero;
use Baspa\EnergyZero\SystemClock;
use Baspa\EnergyZero\Tests\FixedClock;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Psr\Clock\ClockInterface;

function hourlyApiPrices(string $firstSlot, int $hours): array
{
    $slot = new DateTimeImmutable($firstSlot);
    $prices = [];

    for ($i = 0; $i < $hours; $i++) {
        $prices[] = [
            'readingDate' => $slot->modify("+{$i} hours")->format('Y-m-d\TH:i:s\Z'),
            'price' => round(0.2 + $i / 100, 2),
        ];
    }

    return ['Prices' => $prices, 'average' => 0.25];
}

function sentQuery(ArrayObject $history): array
{
    parse_str($history[0]['request']->getUri()->getQuery(), $query);

    return $query;
}

beforeEach(function () {
    $this->defaultTimezone = date_default_timezone_get();
    date_default_timezone_set('America/New_York');

    $this->history = new ArrayObject;
    $this->mockHandler = new MockHandler;
    $handlerStack = HandlerStack::create($this->mockHandler);
    $handlerStack->push(Middleware::history($this->history));
    $this->client = new Client(['handler' => $handlerStack]);
});

afterEach(function () {
    date_default_timezone_set($this->defaultTimezone);
});

it('requests the Dutch day by default, regardless of the PHP timezone', function () {
    $this->mockHandler->append(new Response(200, [], json_encode(hourlyApiPrices('2026-11-12T23:00:00Z', 24))));

    (new EnergyZero($this->client))->energyPrices('2026-11-13', '2026-11-13');

    expect(sentQuery($this->history))
        ->fromDate->toBe('2026-11-12T23:00:00.000Z')
        ->tillDate->toBe('2026-11-13T22:59:59.999Z');
});

it('requests a summer day with the CEST offset', function () {
    $this->mockHandler->append(new Response(200, [], json_encode(hourlyApiPrices('2026-07-14T22:00:00Z', 24))));

    (new EnergyZero($this->client))->energyPrices('2026-07-15', '2026-07-15');

    expect(sentQuery($this->history))
        ->fromDate->toBe('2026-07-14T22:00:00.000Z')
        ->tillDate->toBe('2026-07-15T21:59:59.999Z');
});

it('requests all 25 hours of the day the clocks go back', function () {
    $this->mockHandler->append(new Response(200, [], json_encode(hourlyApiPrices('2026-10-24T22:00:00Z', 25))));

    (new EnergyZero($this->client))->energyPrices('2026-10-25', '2026-10-25');

    expect(sentQuery($this->history))
        ->fromDate->toBe('2026-10-24T22:00:00.000Z')
        ->tillDate->toBe('2026-10-25T22:59:59.999Z');
});

it('interprets dates in a timezone set by name', function () {
    $this->mockHandler->append(new Response(200, [], json_encode(hourlyApiPrices('2026-11-13T00:00:00Z', 24))));

    (new EnergyZero($this->client))
        ->setTimezone('UTC')
        ->energyPrices('2026-11-13', '2026-11-13');

    expect(sentQuery($this->history))
        ->fromDate->toBe('2026-11-13T00:00:00.000Z')
        ->tillDate->toBe('2026-11-13T23:59:59.999Z');
});

it('interprets dates in a timezone set as DateTimeZone', function () {
    $this->mockHandler->append(new Response(200, [], json_encode(hourlyApiPrices('2026-07-14T23:00:00Z', 24))));

    $energyZero = new EnergyZero($this->client);

    expect($energyZero->setTimezone(new DateTimeZone('Europe/London')))->toBe($energyZero);

    $energyZero->energyPrices('2026-07-15', '2026-07-15');

    expect(sentQuery($this->history))
        ->fromDate->toBe('2026-07-14T23:00:00.000Z')
        ->tillDate->toBe('2026-07-15T22:59:59.999Z');
});

it('returns prices for Dutch hours', function () {
    $this->mockHandler->append(new Response(200, [], json_encode(hourlyApiPrices('2026-11-12T23:00:00Z', 24))));

    $prices = (new EnergyZero($this->client))->getPricesForHours('2026-11-13', [0, 8, 23]);

    expect(array_column($prices, 'readingDate'))->toBe([
        '2026-11-12T23:00:00Z',
        '2026-11-13T07:00:00Z',
        '2026-11-13T22:00:00Z',
    ]);
    expect(sentQuery($this->history))
        ->fromDate->toBe('2026-11-12T23:00:00.000Z')
        ->tillDate->toBe('2026-11-13T22:59:59.999Z');
});

it('returns both slots for the repeated hour on the day the clocks go back', function () {
    $this->mockHandler->append(new Response(200, [], json_encode(hourlyApiPrices('2026-10-24T22:00:00Z', 25))));

    $prices = (new EnergyZero($this->client))->getPricesForHours('2026-10-25', [2, 3]);

    expect(array_column($prices, 'readingDate'))->toBe([
        '2026-10-25T00:00:00Z',
        '2026-10-25T01:00:00Z',
        '2026-10-25T02:00:00Z',
    ]);
});

it('returns prices for hours in a configured timezone', function () {
    $this->mockHandler->append(new Response(200, [], json_encode(hourlyApiPrices('2026-11-13T00:00:00Z', 24))));

    $prices = (new EnergyZero($this->client))
        ->setTimezone('UTC')
        ->getPricesForHours('2026-11-13', [8]);

    expect(array_column($prices, 'readingDate'))->toBe(['2026-11-13T08:00:00Z']);
});

it('returns the slot that contains now', function (string $now, string $fromDate, string $firstSlot, int $hours, string $expectedSlot) {
    $this->mockHandler->append(new Response(200, [], json_encode(hourlyApiPrices($firstSlot, $hours))));

    $current = (new EnergyZero($this->client, new FixedClock($now)))->getCurrentPrice();

    expect($current['datetime'])->toBe($expectedSlot);
    expect(sentQuery($this->history))->fromDate->toBe($fromDate);
})->with([
    'morning in winter' => ['2026-11-13T09:35:00Z', '2026-11-12T23:00:00.000Z', '2026-11-12T23:00:00Z', 24, '2026-11-13T09:00:00Z'],
    'just after Dutch midnight' => ['2026-11-12T23:10:00Z', '2026-11-12T23:00:00.000Z', '2026-11-12T23:00:00Z', 24, '2026-11-12T23:00:00Z'],
    'last hour of the Dutch day' => ['2026-11-13T22:59:59Z', '2026-11-12T23:00:00.000Z', '2026-11-12T23:00:00Z', 24, '2026-11-13T22:00:00Z'],
    'first 02:00 on the DST day' => ['2026-10-25T00:30:00Z', '2026-10-24T22:00:00.000Z', '2026-10-24T22:00:00Z', 25, '2026-10-25T00:00:00Z'],
    'second 02:00 on the DST day' => ['2026-10-25T01:30:00Z', '2026-10-24T22:00:00.000Z', '2026-10-24T22:00:00Z', 25, '2026-10-25T01:00:00Z'],
]);

it('returns the slot that contains now in a configured timezone', function () {
    $this->mockHandler->append(new Response(200, [], json_encode(hourlyApiPrices('2026-11-13T00:00:00Z', 24))));

    $current = (new EnergyZero($this->client, new FixedClock('2026-11-13T23:30:00Z')))
        ->setTimezone('UTC')
        ->getCurrentPrice();

    expect($current['datetime'])->toBe('2026-11-13T23:00:00Z');
    expect(sentQuery($this->history))->fromDate->toBe('2026-11-13T00:00:00.000Z');
});

it('uses the system time by default', function () {
    $clock = new SystemClock;

    expect($clock)->toBeInstanceOf(ClockInterface::class);
    expect($clock->now()->getTimestamp())->toBeGreaterThanOrEqual(time() - 1)->toBeLessThanOrEqual(time() + 1);
});

it('applies the configured timezone to dates that carry their own offset', function () {
    $this->mockHandler->append(new Response(200, [], json_encode(hourlyApiPrices('2026-11-12T23:00:00Z', 24))));

    (new EnergyZero($this->client))->energyPrices('2026-11-13T10:00:00Z', '2026-11-13T10:00:00+00:00');

    expect(sentQuery($this->history))
        ->fromDate->toBe('2026-11-12T23:00:00.000Z')
        ->tillDate->toBe('2026-11-13T22:59:59.999Z');
});

it('picks the quarter-hour slot that contains now', function () {
    $this->mockHandler->append(new Response(200, [], json_encode(['Prices' => [
        ['readingDate' => '2026-11-13T09:00:00Z', 'price' => 0.1],
        ['readingDate' => '2026-11-13T09:15:00Z', 'price' => 0.2],
        ['readingDate' => '2026-11-13T09:30:00Z', 'price' => 0.3],
        ['readingDate' => '2026-11-13T09:45:00Z', 'price' => 0.4],
        ['readingDate' => '2026-11-13T10:00:00Z', 'price' => 0.5],
    ]])));

    $current = (new EnergyZero($this->client, new FixedClock('2026-11-13T09:50:00Z')))->getCurrentPrice();

    expect($current)->toBe(['price' => 0.4, 'datetime' => '2026-11-13T09:45:00Z']);
});
