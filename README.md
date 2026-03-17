# PHP client for the dynamic prices from EnergyZero

[![Latest Version on Packagist](https://img.shields.io/packagist/v/baspa/energyzero-php-api.svg?style=flat-square)](https://packagist.org/packages/baspa/energyzero-php-api)
[![Tests](https://img.shields.io/github/actions/workflow/status/baspa/energyzero-php-api/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/baspa/energyzero-php-api/actions/workflows/run-tests.yml)
[![PHPStan](https://img.shields.io/github/actions/workflow/status/baspa/energyzero-php-api/phpstan.yml?branch=main&label=phpstan&style=flat-square)](https://github.com/baspa/energyzero-php-api/actions/workflows/phpstan.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/baspa/energyzero-php-api.svg?style=flat-square)](https://packagist.org/packages/baspa/energyzero-php-api)

This PHP package provides a client for fetching dynamic energy prices from the [EnergyZero](https://www.energyzero.nl/) API. It allows you to retrieve energy prices for a specified date range with customizable intervals and VAT options.

## Installation

You can install the package via composer:

```bash
composer require baspa/energyzero-php-api
```

## Usage

You can fetch the energy prices for a specific date range with a specific interval and VAT option. When the VAT option is not provided, it will default to `true`. Make sure you provide a date in the format `Y-m-d`.

```php
use Baspa\EnergyZero\EnergyZero;
use Baspa\EnergyZero\Enums\Interval;

$prices = (new EnergyZero())->energyPrices(
    startDate: '2024-01-01',
    endDate: '2024-01-02',
    interval: Interval::HOUR,
    vat: true
);
```

The response will be an array of prices for the specified date range and also include the average price for the period.

### Interval options

The following interval options are available:

| Interval | Enum | Value | Description |
|----------|------|-------|-------------|
| Quarter | `Interval::QUARTER` | 3 | 15-minute intervals |
| Hour | `Interval::HOUR` | 4 | Hourly intervals (default) |
| Day | `Interval::DAY` | 5 | Daily intervals |
| Week | `Interval::WEEK` | 6 | Weekly intervals |
| Month | `Interval::MONTH` | 7 | Monthly intervals |
| Year | `Interval::YEAR` | 8 | Yearly intervals |

You can also use integer values directly for backward compatibility:

```php
$prices = (new EnergyZero())->energyPrices('2024-01-01', '2024-01-02', 4);
```

### Set default interval

You can set a default interval that will be used for all requests:

```php
use Baspa\EnergyZero\EnergyZero;
use Baspa\EnergyZero\Enums\Interval;

$energyZero = (new EnergyZero())->setDefaultInterval(Interval::QUARTER);

// All subsequent calls will use 15-minute intervals by default
$prices = $energyZero->energyPrices('2024-01-01', '2024-01-02');
```

### Get quarter-hour prices

Fetch prices in 15-minute intervals for more granular data:

```php
use Baspa\EnergyZero\EnergyZero;
use Baspa\EnergyZero\Enums\Interval;

$prices = (new EnergyZero())->energyPrices(
    startDate: '2024-01-01',
    endDate: '2024-01-01',
    interval: Interval::QUARTER
);
```

### Get gas prices

Fetch gas prices instead of electricity prices:

```php
use Baspa\EnergyZero\EnergyZero;

$gasPrices = (new EnergyZero())->gasPrices(
    startDate: '2024-01-01',
    endDate: '2024-01-02',
    vat: true
);
```

Or set the default energy type:

```php
use Baspa\EnergyZero\EnergyZero;
use Baspa\EnergyZero\Enums\EnergyType;

$energyZero = (new EnergyZero())->setDefaultEnergyType(EnergyType::GAS);

$prices = $energyZero->energyPrices('2024-01-01', '2024-01-02');
```

### Get the current price

Get the current electricity price for the current hour:

```php
$currentPrice = (new EnergyZero())->getCurrentPrice();
// Returns: ['price' => 0.25, 'datetime' => '2024-01-01T14:00:00']
```

### Get prices for specific hours

Get prices for specific hours of a day:

```php
$prices = (new EnergyZero())->getPricesForHours(
    date: '2024-01-01',
    hours: [8, 12, 18, 22]
);
```

### Get the average price for a period

```php
$averagePrice = (new EnergyZero())->getAveragePriceForPeriod(
    startDate: '2024-01-01',
    endDate: '2024-01-02',
    interval: Interval::HOUR,
    vat: true
);
```

### Get the lowest price for a period

```php
$lowestPrice = (new EnergyZero())->getLowestPriceForPeriod(
    startDate: '2024-01-01',
    endDate: '2024-01-02',
    interval: Interval::HOUR,
    vat: true
);
```

### Get the highest price for a period

```php
$highestPrice = (new EnergyZero())->getHighestPriceForPeriod(
    startDate: '2024-01-01',
    endDate: '2024-01-02',
    interval: Interval::HOUR,
    vat: true
);
```

### Get the prices above a threshold

```php
$prices = (new EnergyZero())->getPricesAboveThreshold(
    startDate: '2024-01-01',
    endDate: '2024-01-02',
    threshold: 0.05,
    interval: Interval::HOUR,
    vat: true
);
```

### Get the prices below a threshold

```php
$prices = (new EnergyZero())->getPricesBelowThreshold(
    startDate: '2024-01-01',
    endDate: '2024-01-02',
    threshold: 0.05,
    interval: Interval::HOUR,
    vat: true
);
```

### Get the peak hours

Get the top N peak hours for a period.

```php
$peakHours = (new EnergyZero())->getPeakHours(
    startDate: '2024-01-01',
    endDate: '2024-01-02',
    topN: 5,
    interval: Interval::HOUR,
    vat: true
);
```

### Get the valley hours

Get the top N valley hours for a period.

```php
$valleyHours = (new EnergyZero())->getValleyHours(
    startDate: '2024-01-01',
    endDate: '2024-01-02',
    topN: 5,
    interval: Interval::HOUR,
    vat: true
);
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](https://github.com/spatie/.github/blob/main/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

-   [Baspa](https://github.com/Baspa)
-   [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
