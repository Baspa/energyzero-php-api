<?php

use Baspa\EnergyZero\EnergyZero;
use Baspa\EnergyZero\Enums\EnergyType;
use Baspa\EnergyZero\Enums\Interval;
use Baspa\EnergyZero\Exceptions\ApiException;
use Baspa\EnergyZero\Exceptions\EnergyZeroException;
use Baspa\EnergyZero\Exceptions\NoPricesException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

beforeEach(function () {
    $this->history = [];
    $this->mockHandler = new MockHandler;
    $handlerStack = HandlerStack::create($this->mockHandler);
    $handlerStack->push(Middleware::history($this->history));
    $this->energyZero = new EnergyZero(new Client(['handler' => $handlerStack]));
});

it('throws an ApiException on a 5xx response', function () {
    $this->mockHandler->append(new Response(503));

    expect(fn () => $this->energyZero->energyPrices('2024-01-01', '2024-01-01'))
        ->toThrow(function (ApiException $e) {
            expect($e->getCode())->toBe(503)
                ->and($e->getMessage())->toBe('EnergyZero API returned HTTP 503.')
                ->and($e->getPrevious())->toBeInstanceOf(ServerException::class);
        });
});

it('throws an ApiException with the API message on a 4xx response', function () {
    $this->mockHandler->append(apiError(400, 3, 'energy type is required'));

    expect(fn () => $this->energyZero->energyPrices('2024-01-01', '2024-01-01'))
        ->toThrow(function (ApiException $e) {
            expect($e->getCode())->toBe(400)
                ->and($e->getMessage())->toBe('EnergyZero API returned HTTP 400: energy type is required')
                ->and($e->getPrevious())->toBeInstanceOf(ClientException::class);
        });
});

it('throws a NoPricesException when the API reports no prices from request()', function () {
    $this->mockHandler->append(noPrices());

    expect(fn () => $this->energyZero->request('prices'))
        ->toThrow(function (NoPricesException $e) {
            expect($e->getCode())->toBe(404)
                ->and($e->getMessage())->toBe('EnergyZero API has no prices: prices do not span the whole requested date');
        });
});

it('throws an ApiException on a 404 that is not the API reporting no prices', function () {
    $this->mockHandler->append(new Response(404), new Response(404, [], json_encode(['code' => 3, 'message' => 'bad request'])));

    expect(fn () => $this->energyZero->energyPrices('2026-11-13', '2026-11-13'))
        ->toThrow(ApiException::class, 'EnergyZero API returned HTTP 404.')
        ->and(fn () => $this->energyZero->energyPrices('2026-11-13', '2026-11-13'))
        ->toThrow(ApiException::class, 'EnergyZero API returned HTTP 404: bad request');
});

it('handles non-2xx responses the same when http_errors is disabled', function () {
    $mockHandler = new MockHandler([new Response(500), apiError(400, 3, 'interval is required'), noPrices()]);
    $energyZero = new EnergyZero(new Client([
        'handler' => HandlerStack::create($mockHandler),
        'http_errors' => false,
    ]));

    expect(fn () => $energyZero->energyPrices('2024-01-01', '2024-01-01'))
        ->toThrow(function (ApiException $e) {
            expect($e->getCode())->toBe(500)
                ->and($e->getPrevious())->toBeNull();
        })
        ->and(fn () => $energyZero->energyPrices('2024-01-01', '2024-01-01'))
        ->toThrow(ApiException::class, 'EnergyZero API returned HTTP 400: interval is required')
        ->and(fn () => $energyZero->energyPrices('2024-01-01', '2024-01-01'))
        ->toThrow(NoPricesException::class);
});

it('throws an ApiException that keeps the ConnectException as previous', function () {
    $connectException = new ConnectException(
        'cURL error 6: Could not resolve host: public.api.energyzero.nl',
        new Request('GET', 'https://public.api.energyzero.nl/public/v1/prices'),
    );
    $this->mockHandler->append($connectException);

    expect(fn () => $this->energyZero->energyPrices('2024-01-01', '2024-01-01'))
        ->toThrow(function (ApiException $e) use ($connectException) {
            expect($e->getPrevious())->toBe($connectException)
                ->and($e->getMessage())->toContain('Could not resolve host');
        });
});

it('throws an ApiException when the response is not JSON', function () {
    $this->mockHandler->append(new Response(200, [], '<html>Bad Gateway</html>'));

    expect(fn () => $this->energyZero->energyPrices('2024-01-01', '2024-01-01'))
        ->toThrow(function (ApiException $e) {
            expect($e->getPrevious())->toBeInstanceOf(JsonException::class);
        });
});

it('throws an ApiException when the JSON is not an object', function () {
    $this->mockHandler->append(new Response(200, [], 'null'));

    expect(fn () => $this->energyZero->request('prices'))->toThrow(ApiException::class);
});

it('throws a NoPricesException when the API returns empty price lists', function () {
    $this->mockHandler->append(pricesResponse('2024-01-01T00:00:00Z', []));

    expect(fn () => $this->energyZero->energyPrices('2024-01-01', '2024-01-01'))
        ->toThrow(NoPricesException::class, 'No energy prices found for this period.');
});

it('keeps the exceptions catchable as Exception', function () {
    expect(new ApiException)->toBeInstanceOf(EnergyZeroException::class)->toBeInstanceOf(Exception::class)
        ->and(new NoPricesException)->toBeInstanceOf(EnergyZeroException::class)->toBeInstanceOf(Exception::class);
});

it('sends an absolute URL when the injected client has no base_uri', function () {
    $this->mockHandler->append(hourlyResponse('2023-12-31T23:00:00Z', 24));

    $this->energyZero->energyPrices('2024-01-01', '2024-01-01');

    $uri = $this->history[0]['request']->getUri();

    expect($uri->getScheme())->toBe('https')
        ->and($uri->getHost())->toBe('public.api.energyzero.nl')
        ->and($uri->getPath())->toBe('/public/v1/prices');
});

it('keeps the base_uri of an injected client', function () {
    $history = [];
    $mockHandler = new MockHandler([hourlyResponse('2023-12-31T23:00:00Z', 24)]);
    $handlerStack = HandlerStack::create($mockHandler);
    $handlerStack->push(Middleware::history($history));
    $energyZero = new EnergyZero(new Client(['handler' => $handlerStack, 'base_uri' => 'https://proxy.test/energyzero/']));

    $energyZero->energyPrices('2024-01-01', '2024-01-01');

    expect((string) $history[0]['request']->getUri()->withQuery(''))->toBe('https://proxy.test/energyzero/prices');
});

it('rejects an unknown integer interval before sending a request', function (int $interval) {
    expect(fn () => $this->energyZero->energyPrices('2024-01-01', '2024-01-01', $interval))
        ->toThrow(InvalidArgumentException::class, "Unknown interval {$interval}")
        ->and($this->history)->toBeEmpty();
})->with([
    'week' => 6,
    'month' => 7,
    'year' => 8,
    'unknown' => 99,
]);

it('rejects an unknown integer energy type before sending a request', function () {
    expect(fn () => $this->energyZero->energyPrices('2024-01-01', '2024-01-01', null, null, 2))
        ->toThrow(InvalidArgumentException::class, 'Unknown energy type 2')
        ->and($this->history)->toBeEmpty();
});

it('rejects a daily interval for electricity before sending a request', function (Interval|int $interval) {
    expect(fn () => $this->energyZero->energyPrices('2024-01-01', '2024-01-01', $interval))
        ->toThrow(InvalidArgumentException::class, 'Interval::DAY is not available for electricity prices')
        ->and(fn () => $this->energyZero->setDefaultInterval(Interval::DAY)->energyPrices('2024-01-01', '2024-01-01'))
        ->toThrow(InvalidArgumentException::class, 'Interval::DAY is not available for electricity prices')
        ->and($this->history)->toBeEmpty();
})->with([
    'enum' => Interval::DAY,
    'integer' => 5,
]);

it('rejects any interval but daily for gas before sending a request', function (Interval|int $interval) {
    expect(fn () => $this->energyZero->gasPrices('2024-01-01', '2024-01-01', $interval))
        ->toThrow(InvalidArgumentException::class, 'Gas prices are only available per day')
        ->and(fn () => $this->energyZero->energyPrices('2024-01-01', '2024-01-01', $interval, null, EnergyType::GAS))
        ->toThrow(InvalidArgumentException::class, 'Gas prices are only available per day')
        ->and($this->history)->toBeEmpty();
})->with([
    'quarter' => Interval::QUARTER,
    'hour' => Interval::HOUR,
    'quarter as integer' => 3,
    'hour as integer' => 4,
]);

it('requests daily gas prices when the default interval is not daily', function () {
    $this->mockHandler->append(pricesResponse('2024-01-01T05:00:00Z', [1.1], 1440));

    $this->energyZero
        ->setDefaultInterval(Interval::QUARTER)
        ->setDefaultEnergyType(EnergyType::GAS)
        ->energyPrices('2024-01-01', '2024-01-01');

    parse_str($this->history[0]['request']->getUri()->getQuery(), $query);

    expect($query['interval'])->toBe('INTERVAL_DAY');
});

it('rejects an end date before the start date before sending a request', function () {
    expect(fn () => $this->energyZero->energyPrices('2024-01-02', '2024-01-01'))
        ->toThrow(InvalidArgumentException::class, 'The end date is before the start date.')
        ->and($this->history)->toBeEmpty();
});
