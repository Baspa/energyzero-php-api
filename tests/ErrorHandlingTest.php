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
                ->and($e->getPrevious())->toBeInstanceOf(ServerException::class);
        });
});

it('throws an ApiException on a 4xx response', function () {
    $this->mockHandler->append(new Response(404));

    expect(fn () => $this->energyZero->energyPrices('2024-01-01', '2024-01-01'))
        ->toThrow(function (ApiException $e) {
            expect($e->getCode())->toBe(404)
                ->and($e->getPrevious())->toBeInstanceOf(ClientException::class);
        });
});

it('throws an ApiException on a non-2xx response when http_errors is disabled', function () {
    $mockHandler = new MockHandler([new Response(500)]);
    $energyZero = new EnergyZero(new Client([
        'handler' => HandlerStack::create($mockHandler),
        'http_errors' => false,
    ]));

    expect(fn () => $energyZero->energyPrices('2024-01-01', '2024-01-01'))
        ->toThrow(function (ApiException $e) {
            expect($e->getCode())->toBe(500);
        });
});

it('throws an ApiException that keeps the ConnectException as previous', function () {
    $connectException = new ConnectException(
        'cURL error 6: Could not resolve host: api.energyzero.nl',
        new Request('GET', 'https://api.energyzero.nl/v1/energyprices'),
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

    expect(fn () => $this->energyZero->request('energyprices'))->toThrow(ApiException::class);
});

it('throws a NoPricesException when the API returns no prices', function () {
    $this->mockHandler->append(new Response(200, [], json_encode(['Prices' => []])));

    expect(fn () => $this->energyZero->energyPrices('2024-01-01', '2024-01-01'))
        ->toThrow(NoPricesException::class, 'No energy prices found for this period.');
});

it('keeps the exceptions catchable as Exception', function () {
    expect(new ApiException)->toBeInstanceOf(EnergyZeroException::class)->toBeInstanceOf(Exception::class)
        ->and(new NoPricesException)->toBeInstanceOf(EnergyZeroException::class)->toBeInstanceOf(Exception::class);
});

it('sends an absolute URL when the injected client has no base_uri', function () {
    $this->mockHandler->append(new Response(200, [], json_encode([
        'Prices' => [['readingDate' => '2024-01-01T00:00:00Z', 'price' => 0.1]],
    ])));

    $this->energyZero->energyPrices('2024-01-01', '2024-01-01');

    $uri = $this->history[0]['request']->getUri();

    expect($uri->getScheme())->toBe('https')
        ->and($uri->getHost())->toBe('api.energyzero.nl')
        ->and($uri->getPath())->toBe('/v1/energyprices');
});

it('keeps the base_uri of an injected client', function () {
    $history = [];
    $mockHandler = new MockHandler([new Response(200, [], json_encode([
        'Prices' => [['readingDate' => '2024-01-01T00:00:00Z', 'price' => 0.1]],
    ]))]);
    $handlerStack = HandlerStack::create($mockHandler);
    $handlerStack->push(Middleware::history($history));
    $energyZero = new EnergyZero(new Client(['handler' => $handlerStack, 'base_uri' => 'https://proxy.test/energyzero/']));

    $energyZero->energyPrices('2024-01-01', '2024-01-01');

    expect((string) $history[0]['request']->getUri()->withQuery(''))->toBe('https://proxy.test/energyzero/energyprices');
});

it('rejects an unknown integer interval before sending a request', function () {
    $this->mockHandler->append(new Response(200, [], json_encode(['Prices' => []])));

    expect(fn () => $this->energyZero->energyPrices('2024-01-01', '2024-01-01', 99))
        ->toThrow(InvalidArgumentException::class, 'Unknown interval 99');
    expect($this->mockHandler)->toHaveCount(1);
});

it('rejects an unknown integer energy type before sending a request', function () {
    $this->mockHandler->append(new Response(200, [], json_encode(['Prices' => []])));

    expect(fn () => $this->energyZero->energyPrices('2024-01-01', '2024-01-01', null, null, 2))
        ->toThrow(InvalidArgumentException::class, 'Unknown energy type 2');
    expect($this->mockHandler)->toHaveCount(1);
});

it('rejects a quarter interval for gas before sending a request', function () {
    $this->mockHandler->append(new Response(200, [], json_encode(['Prices' => []])));

    expect(fn () => $this->energyZero->gasPrices('2024-01-01', '2024-01-01', Interval::QUARTER))
        ->toThrow(InvalidArgumentException::class, 'Interval::QUARTER is not available for gas prices.');
    expect(fn () => $this->energyZero->energyPrices('2024-01-01', '2024-01-01', 3, null, 3))
        ->toThrow(InvalidArgumentException::class);
    expect(fn () => $this->energyZero
        ->setDefaultInterval(Interval::QUARTER)
        ->setDefaultEnergyType(EnergyType::GAS)
        ->energyPrices('2024-01-01', '2024-01-01'))
        ->toThrow(InvalidArgumentException::class);
    expect($this->mockHandler)->toHaveCount(1);
});
