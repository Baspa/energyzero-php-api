# PHP client for the dynamic prices from EnergyZero

[![Latest Version on Packagist](https://img.shields.io/packagist/v/baspa/energyzero-php-api.svg?style=flat-square)](https://packagist.org/packages/baspa/energyzero-php-api)
[![Tests](https://img.shields.io/github/actions/workflow/status/baspa/energyzero-php-api/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/baspa/energyzero-php-api/actions/workflows/run-tests.yml)
[![PHPStan](https://img.shields.io/github/actions/workflow/status/baspa/energyzero-php-api/phpstan.yml?branch=main&label=phpstan&style=flat-square)](https://github.com/baspa/energyzero-php-api/actions/workflows/phpstan.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/baspa/energyzero-php-api.svg?style=flat-square)](https://packagist.org/packages/baspa/energyzero-php-api)

This PHP package fetches dynamic electricity and gas prices from the public API of [EnergyZero](https://www.energyzero.nl/). You can get the prices for a date range per quarter-hour, hour or (for gas) day, as market prices or all-in prices, with or without VAT.

> **Building your own client?** See [API.md](API.md) for detailed API documentation with examples in multiple languages.

## Price availability

| Energy type | Prices | Next day's prices |
|-------------|--------|-------------------|
| Electricity | Per quarter-hour or hour, per Dutch day (00:00 to 24:00) | Published around **14:00 UTC** (15:00 CET / 16:00 CEST) |
| Gas | One price per gas day, from 06:00 to 06:00 Dutch time | Published around **05:00 UTC** (06:00 CET / 07:00 CEST), when the gas day starts |

Days that aren't published yet have no prices. A range that only covers such days throws a `NoPricesException`; a range that partly covers them returns the prices that are published.

## Installation

You can install the package via composer:

```bash
composer require baspa/energyzero-php-api
```

Upgrading from v2? See [Upgrading from v2](#upgrading-from-v2).

## Usage

Fetch the prices for a date range. Dates are days in the format `Y-m-d`; both the start and end date are included.

```php
use Baspa\EnergyZero\EnergyZero;
use Baspa\EnergyZero\Enums\Interval;
use Baspa\EnergyZero\Enums\PriceType;

$prices = (new EnergyZero())->energyPrices(
    startDate: '2026-11-12',
    endDate: '2026-11-13',
    interval: Interval::QUARTER,
    priceType: PriceType::MARKET_WITH_VAT,
);
```

The result holds the prices in chronological order and their average:

```php
[
    'Prices' => [
        ['readingDate' => '2026-11-11T23:00:00Z', 'price' => 0.272129],
        ['readingDate' => '2026-11-11T23:15:00Z', 'price' => 0.2631266],
        // ...
    ],
    'average' => 0.24822349635416666,
]
```

- `readingDate` is the start of the slot in UTC.
- `price` is in euros per kWh for electricity and per m³ for gas.
- `average` is the mean of the returned prices.

The API serves one Dutch day per request and includes the day before it, so a range costs one request per two days.

### Price types

| Price type | Description |
|------------|-------------|
| `PriceType::MARKET` | Market price, without VAT |
| `PriceType::MARKET_WITH_VAT` | Market price with 21% VAT (default) |
| `PriceType::ALL_IN` | All-in price as EnergyZero calculates it: the market price plus energy tax and surcharges, without VAT |
| `PriceType::ALL_IN_WITH_VAT` | All-in price with 21% VAT |

Every method takes a `priceType`. A boolean still works: `true` is `PriceType::MARKET_WITH_VAT` and `false` is `PriceType::MARKET`. Change the default for all calls with `setDefaultPriceType()`:

```php
$energyZero = (new EnergyZero())->setDefaultPriceType(PriceType::ALL_IN_WITH_VAT);
```

### Interval options

| Interval | Enum | Value | Available for |
|----------|------|-------|---------------|
| Quarter | `Interval::QUARTER` | 3 | Electricity |
| Hour | `Interval::HOUR` | 4 | Electricity (default) |
| Day | `Interval::DAY` | 5 | Gas (always) |

You can also pass the integer value:

```php
$prices = (new EnergyZero())->energyPrices('2026-11-12', '2026-11-13', 4);
```

`Interval::DAY` for electricity, or any other interval than `Interval::DAY` for gas, throws an `\InvalidArgumentException`. Gas without an interval uses `Interval::DAY`.

### Set default interval

You can set a default interval that will be used for all electricity requests:

```php
use Baspa\EnergyZero\EnergyZero;
use Baspa\EnergyZero\Enums\Interval;

$energyZero = (new EnergyZero())->setDefaultInterval(Interval::QUARTER);

$prices = $energyZero->energyPrices('2026-11-12', '2026-11-13');
```

### Timezone

Dates and hours are interpreted in `Europe/Amsterdam` by default, because EnergyZero prices are per Dutch day. `energyPrices('2026-11-13', '2026-11-13')` returns the prices from 00:00 to 24:00 Dutch time. The `readingDate` values stay in UTC (`2026-11-12T23:00:00Z` is 00:00 in Amsterdam).

Use `setTimezone()` to change it:

```php
$energyZero = (new EnergyZero())->setTimezone('UTC');

$energyZero = (new EnergyZero())->setTimezone(new DateTimeZone('Europe/Brussels'));
```

The package then requests every Dutch day the range touches and returns the slots that start inside it. The hours passed to `getPricesForHours()` use this timezone too, and `getCurrentPrice()` uses it to decide which day is today. On the day the clocks go back, hour 2 occurs twice, so `getPricesForHours()` returns two prices for it.

`getCurrentPrice()` reads the time from a [PSR-20](https://www.php-fig.org/psr/psr-20/) clock. Pass your own as the second constructor argument to control "now", for example in tests:

```php
$energyZero = new EnergyZero(clock: $clock);
```

### Get gas prices

```php
use Baspa\EnergyZero\EnergyZero;
use Baspa\EnergyZero\Enums\PriceType;

$gasPrices = (new EnergyZero())->gasPrices(
    startDate: '2026-11-12',
    endDate: '2026-11-13',
    priceType: PriceType::ALL_IN_WITH_VAT,
);
```

A gas price belongs to the day its gas day starts on: the price for 13 November runs from 06:00 on 13 November to 06:00 on 14 November.

Or set the default energy type:

```php
use Baspa\EnergyZero\EnergyZero;
use Baspa\EnergyZero\Enums\EnergyType;

$energyZero = (new EnergyZero())->setDefaultEnergyType(EnergyType::GAS);

$prices = $energyZero->energyPrices('2026-11-12', '2026-11-13');
```

### Get the current price

Get the price of the slot that started most recently: the current hour for electricity, the current gas day for gas.

```php
$currentPrice = (new EnergyZero())->getCurrentPrice();
// Returns: ['price' => 0.19797112, 'datetime' => '2026-11-13T11:00:00Z']

$currentGasPrice = (new EnergyZero())->getCurrentPrice(energyType: EnergyType::GAS);
// Returns: ['price' => 1.09319677812796, 'datetime' => '2026-11-13T05:00:00Z']
```

Before 06:00 Dutch time, the current gas price is the one for the gas day that started the previous morning.

### Get prices for specific hours

Get the hourly prices for specific hours of a day, in the configured timezone:

```php
$prices = (new EnergyZero())->getPricesForHours(
    date: '2026-11-13',
    hours: [8, 12, 18, 22]
);
```

### Get the average price for a period

```php
$averagePrice = (new EnergyZero())->getAveragePriceForPeriod(
    startDate: '2026-11-12',
    endDate: '2026-11-13',
    interval: Interval::HOUR,
    priceType: PriceType::MARKET_WITH_VAT,
);
```

### Get the lowest price for a period

```php
$lowestPrice = (new EnergyZero())->getLowestPriceForPeriod(
    startDate: '2026-11-12',
    endDate: '2026-11-13',
    interval: Interval::QUARTER,
    priceType: PriceType::ALL_IN_WITH_VAT,
);
// Returns: ['price' => 0.2754686, 'datetime' => '2026-11-13T03:00:00Z']
```

### Get the highest price for a period

```php
$highestPrice = (new EnergyZero())->getHighestPriceForPeriod(
    startDate: '2026-11-12',
    endDate: '2026-11-13',
    interval: Interval::QUARTER,
);
```

### Get the prices above a threshold

```php
$prices = (new EnergyZero())->getPricesAboveThreshold(
    startDate: '2026-11-12',
    endDate: '2026-11-13',
    threshold: 0.30,
    interval: Interval::QUARTER,
);
```

### Get the prices below a threshold

```php
$prices = (new EnergyZero())->getPricesBelowThreshold(
    startDate: '2026-11-12',
    endDate: '2026-11-13',
    threshold: 0.20,
    interval: Interval::QUARTER,
);
```

### Get the peak hours

Get the N most expensive slots of a period, most expensive first.

```php
$peakHours = (new EnergyZero())->getPeakHours(
    startDate: '2026-11-12',
    endDate: '2026-11-13',
    topN: 5,
    interval: Interval::HOUR,
);
```

### Get the valley hours

Get the N cheapest slots of a period, cheapest first.

```php
$valleyHours = (new EnergyZero())->getValleyHours(
    startDate: '2026-11-12',
    endDate: '2026-11-13',
    topN: 5,
    interval: Interval::HOUR,
);
```

## Error handling

The package throws these exceptions from `Baspa\EnergyZero\Exceptions`. Both extend `EnergyZeroException`, which extends `\Exception`.

| Exception | Thrown when |
|-----------|-------------|
| `ApiException` | The API can't be reached or times out, rejects the request (the message includes the API's reason, for example `EnergyZero API returned HTTP 400: invalid interval type for electricity: daily`), returns another non-2xx status, or returns a response the package can't read. `getPrevious()` holds the original Guzzle or JSON exception; `getCode()` holds the HTTP status for non-2xx responses. |
| `NoPricesException` | None of the requested days has prices (for example, tomorrow's prices aren't published yet). |

Invalid input throws `\InvalidArgumentException` before a request is sent: an unknown integer interval or energy type, `Interval::DAY` for electricity, any other interval than `Interval::DAY` for gas, or an end date before the start date.

```php
use Baspa\EnergyZero\Exceptions\ApiException;
use Baspa\EnergyZero\Exceptions\NoPricesException;

try {
    $prices = (new EnergyZero())->energyPrices('2026-11-13', '2026-11-14');
} catch (NoPricesException $e) {
    // No prices for this period (yet)
} catch (ApiException $e) {
    // EnergyZero is unreachable or returned an error
}
```

`request()` stays available for calls of your own. It returns the decoded JSON and throws an `ApiException` for every non-2xx response, a 404 included.

## Upgrading from v2

v3 moves to EnergyZero's public API (`https://public.api.energyzero.nl/public/v1/prices`), because the old `api.energyzero.nl/v1/energyprices` endpoint no longer returns quarter-hour prices and rounds prices with VAT to two decimals. These changes can break your code:

- **PHP 8.3 or higher** is required (v2 supported 8.2).
- **`vat` is now `priceType`.** Every `?bool $vat` parameter is now `PriceType|bool|null $priceType`, in the same position. Positional booleans keep working (`true` is `PriceType::MARKET_WITH_VAT`, `false` is `PriceType::MARKET`), but named arguments must change from `vat: true` to `priceType: true` or `priceType: PriceType::MARKET_WITH_VAT`; PHP throws an `Error` for an unknown named parameter.
- **Intervals.** `Interval::WEEK`, `Interval::MONTH` and `Interval::YEAR` are removed, and the integers 6, 7 and 8 now throw an `\InvalidArgumentException`. Electricity is available per quarter-hour or hour; `Interval::DAY` for electricity throws. Gas is only available per day: gas without an interval now uses `Interval::DAY` (v2 used the default interval), and any other interval for gas throws.
- **Response keys.** The result only has `Prices` and `average`; `fromDate`, `tillDate` and `intervalType` are gone. `average` is now the unrounded mean of the returned prices, and prices are no longer rounded. `Prices` only holds slots that start inside the requested days.
- **Exceptions.** Methods throw `ApiException` and `NoPricesException` instead of a plain `\Exception`. Both extend `EnergyZeroException`, which extends `\Exception`, so `catch (\Exception $e)` still catches them.
- **`request()` throws.** It used to log the error with `error_log()` and return `null`; it now throws an `ApiException` and always returns an array.
- **Timezone.** Dates and hours are interpreted in `Europe/Amsterdam` instead of PHP's default timezone. Call `setTimezone(date_default_timezone_get())` to get the v2 behaviour back.
- **Custom clients.** If you pass your own Guzzle client with a `base_uri` (for example a proxy), point it at `https://public.api.energyzero.nl/public/v1/`. The package requests the relative path `prices` instead of `energyprices`.

## Testing

```bash
composer test
```

## API Documentation

For developers who want to build their own client in another programming language, see [API.md](API.md) for:
- Complete API endpoint documentation
- Query parameters and response format
- Code examples in Python, JavaScript, and cURL
- Information about price availability times

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Contributions are welcome:

1. Fork the repository and create a branch.
2. Make your change and add or update tests.
3. Run `composer test` (tests must pass), `composer analyse` (PHPStan) and `composer format` (fixes code style).
4. Open a pull request against `main`.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

-   [Baspa](https://github.com/Baspa)
-   [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
