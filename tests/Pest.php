<?php

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

function recorded(string $name): Response
{
    return new Response(200, ['Content-Type' => 'application/json'], file_get_contents(__DIR__."/Fixtures/{$name}.json"));
}

function pricesResponse(string $firstStart, array $values, int $minutes = 60): Response
{
    $entries = [];

    foreach (array_values($values) as $i => $value) {
        $start = (new DateTimeImmutable($firstStart))->modify('+'.($i * $minutes).' minutes');
        $entries[] = [
            'start' => $start->format('Y-m-d\TH:i:s\Z'),
            'end' => $start->modify("+{$minutes} minutes")->format('Y-m-d\TH:i:s\Z'),
            'price' => ['value' => (string) $value],
        ];
    }

    return new Response(200, ['Content-Type' => 'application/json'], json_encode([
        'interval' => 'RESPONSE_INTERVAL_HOUR',
        'range' => ['start' => $entries[0]['start'] ?? null, 'end' => end($entries)['end'] ?? null],
        'base' => $entries,
        'base_with_vat' => $entries,
        'all_in' => $entries,
        'all_in_with_vat' => $entries,
    ]));
}

function hourlyResponse(string $firstStart, int $hours): Response
{
    $values = [];

    for ($i = 0; $i < $hours; $i++) {
        $hour = (int) (new DateTimeImmutable($firstStart))->modify("+{$i} hours")->format('G');
        $values[] = round(0.2 + $hour / 100, 2);
    }

    return pricesResponse($firstStart, $values);
}

function apiError(int $status, int $code, string $message): Response
{
    return new Response($status, ['Content-Type' => 'application/json'], json_encode([
        'code' => $code,
        'message' => $message,
        'details' => [],
    ]));
}

function noPrices(): Response
{
    return apiError(404, 5, 'prices do not span the whole requested date');
}

function fakeApi(array $responsesByDate, ArrayObject $history): Client
{
    $stack = HandlerStack::create(function (RequestInterface $request) use ($responsesByDate): PromiseInterface {
        parse_str($request->getUri()->getQuery(), $query);

        return Create::promiseFor(
            $responsesByDate[$query['date']] ?? throw new RuntimeException("Unexpected request for {$query['date']}.")
        );
    });
    $stack->push(Middleware::history($history));

    return new Client(['handler' => $stack]);
}

function sentQuery(ArrayObject $history, int $index = 0): array
{
    parse_str($history[$index]['request']->getUri()->getQuery(), $query);

    return $query;
}

function requestedDates(ArrayObject $history): array
{
    return array_map(fn (int $index) => sentQuery($history, $index)['date'], array_keys($history->getArrayCopy()));
}
