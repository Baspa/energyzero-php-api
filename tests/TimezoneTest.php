<?php

use Baspa\EnergyZero\EnergyZero;
use Baspa\EnergyZero\SystemClock;
use Baspa\EnergyZero\Tests\FixedClock;
use Psr\Clock\ClockInterface;

beforeEach(function () {
    $this->defaultTimezone = date_default_timezone_get();
    date_default_timezone_set('America/New_York');

    $this->history = new ArrayObject;
});

afterEach(function () {
    date_default_timezone_set($this->defaultTimezone);
});

it('requests the Dutch day by default, regardless of the PHP timezone', function () {
    $energyZero = new EnergyZero(fakeApi(['13-11-2026' => hourlyResponse('2026-11-11T23:00:00Z', 48)], $this->history));

    $readingDates = array_column($energyZero->energyPrices('2026-11-13', '2026-11-13')['Prices'], 'readingDate');

    expect(requestedDates($this->history))->toBe(['13-11-2026'])
        ->and($readingDates)->toHaveCount(24)
        ->and($readingDates[0])->toBe('2026-11-12T23:00:00Z')
        ->and($readingDates[23])->toBe('2026-11-13T22:00:00Z');
});

it('keeps a summer day with the CEST offset', function () {
    $energyZero = new EnergyZero(fakeApi(['15-07-2026' => hourlyResponse('2026-07-13T22:00:00Z', 48)], $this->history));

    $readingDates = array_column($energyZero->energyPrices('2026-07-15', '2026-07-15')['Prices'], 'readingDate');

    expect($readingDates)->toHaveCount(24)
        ->and($readingDates[0])->toBe('2026-07-14T22:00:00Z')
        ->and($readingDates[23])->toBe('2026-07-15T21:00:00Z');
});

it('keeps all 25 hours of the day the clocks go back', function () {
    $energyZero = new EnergyZero(fakeApi(['25-10-2026' => hourlyResponse('2026-10-23T22:00:00Z', 49)], $this->history));

    $readingDates = array_column($energyZero->energyPrices('2026-10-25', '2026-10-25')['Prices'], 'readingDate');

    expect($readingDates)->toHaveCount(25)
        ->and($readingDates[0])->toBe('2026-10-24T22:00:00Z')
        ->and($readingDates[24])->toBe('2026-10-25T22:00:00Z');
});

it('interprets dates in a timezone set by name', function () {
    $energyZero = (new EnergyZero(fakeApi(['14-11-2026' => hourlyResponse('2026-11-12T23:00:00Z', 48)], $this->history)))
        ->setTimezone('UTC');

    $readingDates = array_column($energyZero->energyPrices('2026-11-13', '2026-11-13')['Prices'], 'readingDate');

    expect(requestedDates($this->history))->toBe(['14-11-2026'])
        ->and($readingDates)->toHaveCount(24)
        ->and($readingDates[0])->toBe('2026-11-13T00:00:00Z')
        ->and($readingDates[23])->toBe('2026-11-13T23:00:00Z');
});

it('returns the part of a day in another timezone that is published', function () {
    $energyZero = (new EnergyZero(fakeApi([
        '14-11-2026' => noPrices(),
        '13-11-2026' => hourlyResponse('2026-11-11T23:00:00Z', 48),
    ], $this->history)))->setTimezone('UTC');

    $readingDates = array_column($energyZero->energyPrices('2026-11-13', '2026-11-13')['Prices'], 'readingDate');

    expect(requestedDates($this->history))->toBe(['14-11-2026', '13-11-2026'])
        ->and($readingDates)->toHaveCount(23)
        ->and($readingDates[0])->toBe('2026-11-13T00:00:00Z')
        ->and($readingDates[22])->toBe('2026-11-13T22:00:00Z');
});

it('interprets dates in a timezone set as DateTimeZone', function () {
    $energyZero = new EnergyZero(fakeApi(['16-07-2026' => hourlyResponse('2026-07-14T22:00:00Z', 48)], $this->history));

    expect($energyZero->setTimezone(new DateTimeZone('Europe/London')))->toBe($energyZero);

    $readingDates = array_column($energyZero->energyPrices('2026-07-15', '2026-07-15')['Prices'], 'readingDate');

    expect(requestedDates($this->history))->toBe(['16-07-2026'])
        ->and($readingDates)->toHaveCount(24)
        ->and($readingDates[0])->toBe('2026-07-14T23:00:00Z')
        ->and($readingDates[23])->toBe('2026-07-15T22:00:00Z');
});

it('returns prices for Dutch hours', function () {
    $energyZero = new EnergyZero(fakeApi(['13-11-2026' => hourlyResponse('2026-11-11T23:00:00Z', 48)], $this->history));

    $prices = $energyZero->getPricesForHours('2026-11-13', [0, 8, 23]);

    expect(array_column($prices, 'readingDate'))->toBe([
        '2026-11-12T23:00:00Z',
        '2026-11-13T07:00:00Z',
        '2026-11-13T22:00:00Z',
    ])->and(sentQuery($this->history))->interval->toBe('INTERVAL_HOUR');
});

it('returns both slots for the repeated hour on the day the clocks go back', function () {
    $energyZero = new EnergyZero(fakeApi(['25-10-2026' => hourlyResponse('2026-10-23T22:00:00Z', 49)], $this->history));

    $prices = $energyZero->getPricesForHours('2026-10-25', [2, 3]);

    expect(array_column($prices, 'readingDate'))->toBe([
        '2026-10-25T00:00:00Z',
        '2026-10-25T01:00:00Z',
        '2026-10-25T02:00:00Z',
    ]);
});

it('returns prices for hours in a configured timezone', function () {
    $energyZero = (new EnergyZero(fakeApi(['14-11-2026' => hourlyResponse('2026-11-12T23:00:00Z', 48)], $this->history)))
        ->setTimezone('UTC');

    $prices = $energyZero->getPricesForHours('2026-11-13', [8]);

    expect(array_column($prices, 'readingDate'))->toBe(['2026-11-13T08:00:00Z']);
});

it('returns the slot that contains now', function (string $now, string $date, string $firstSlot, int $hours, string $expectedSlot) {
    $energyZero = new EnergyZero(fakeApi([$date => hourlyResponse($firstSlot, $hours)], $this->history), new FixedClock($now));

    expect($energyZero->getCurrentPrice()['datetime'])->toBe($expectedSlot)
        ->and(requestedDates($this->history))->toBe([$date]);
})->with([
    'morning in winter' => ['2026-11-13T09:35:00Z', '13-11-2026', '2026-11-11T23:00:00Z', 48, '2026-11-13T09:00:00Z'],
    'just after Dutch midnight' => ['2026-11-12T23:10:00Z', '13-11-2026', '2026-11-11T23:00:00Z', 48, '2026-11-12T23:00:00Z'],
    'last hour of the Dutch day' => ['2026-11-13T22:59:59Z', '13-11-2026', '2026-11-11T23:00:00Z', 48, '2026-11-13T22:00:00Z'],
    'exactly at the start of a slot' => ['2026-11-13T10:00:00Z', '13-11-2026', '2026-11-11T23:00:00Z', 48, '2026-11-13T10:00:00Z'],
    'first 02:00 on the DST day' => ['2026-10-25T00:30:00Z', '25-10-2026', '2026-10-23T22:00:00Z', 49, '2026-10-25T00:00:00Z'],
    'second 02:00 on the DST day' => ['2026-10-25T01:30:00Z', '25-10-2026', '2026-10-23T22:00:00Z', 49, '2026-10-25T01:00:00Z'],
]);

it('returns the slot that contains now in a configured timezone', function () {
    $energyZero = (new EnergyZero(
        fakeApi([
            '14-11-2026' => hourlyResponse('2026-11-12T23:00:00Z', 48),
            '12-11-2026' => hourlyResponse('2026-11-10T23:00:00Z', 48),
        ], $this->history),
        new FixedClock('2026-11-13T23:30:00Z'),
    ))->setTimezone('UTC');

    expect($energyZero->getCurrentPrice()['datetime'])->toBe('2026-11-13T23:00:00Z')
        ->and(requestedDates($this->history))->toBe(['14-11-2026', '12-11-2026']);
});

it('returns the current slot just after midnight in a timezone with a half-hour offset', function () {
    $energyZero = (new EnergyZero(
        fakeApi([
            '13-11-2026' => hourlyResponse('2026-11-11T23:00:00Z', 48),
            '11-11-2026' => hourlyResponse('2026-11-09T23:00:00Z', 48),
        ], $this->history),
        new FixedClock('2026-11-12T18:45:00Z'),
    ))->setTimezone('Asia/Kolkata');

    expect($energyZero->getCurrentPrice()['datetime'])->toBe('2026-11-12T18:00:00Z');
});

it('uses the system time by default', function () {
    $clock = new SystemClock;

    expect($clock)->toBeInstanceOf(ClockInterface::class);
    expect($clock->now()->getTimestamp())->toBeGreaterThanOrEqual(time() - 1)->toBeLessThanOrEqual(time() + 1);
});

it('applies the configured timezone to dates that carry their own offset', function () {
    $energyZero = new EnergyZero(fakeApi(['13-11-2026' => hourlyResponse('2026-11-11T23:00:00Z', 48)], $this->history));

    $readingDates = array_column($energyZero->energyPrices('2026-11-13T10:00:00Z', '2026-11-13T10:00:00+00:00')['Prices'], 'readingDate');

    expect(requestedDates($this->history))->toBe(['13-11-2026'])
        ->and($readingDates[0])->toBe('2026-11-12T23:00:00Z')
        ->and($readingDates[23])->toBe('2026-11-13T22:00:00Z');
});
