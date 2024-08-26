# PHP client for the dynamic prices from EnergyZero

[![Latest Version on Packagist](https://img.shields.io/packagist/v/baspa/energyzero-php-api.svg?style=flat-square)](https://packagist.org/packages/baspa/energyzero-php-api)
[![Tests](https://img.shields.io/github/actions/workflow/status/baspa/energyzero-php-api/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/baspa/energyzero-php-api/actions/workflows/run-tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/baspa/energyzero-php-api.svg?style=flat-square)](https://packagist.org/packages/baspa/energyzero-php-api)

This PHP package provides a client for fetching dynamic energy prices from the EnergyZero API. It allows you to retrieve energy prices for a specified date range with customizable intervals and VAT options.

## Installation

You can install the package via composer:

```bash
composer require baspa/energyzero-php-api
```

## Usage

You can fetch the energy prices for a specific date range with a specific interval and VAT option. When the VAT option is not provided, it will default to `true`. Make sure you provide a date in the format `Y-m-d`.

```php
use Baspa\EnergyZero;

$prices = (new EnergyZero())->energyPrices(
    startDate: '2024-01-01',
    endDate: '2024-01-02',
    interval: 4,
    vat: true
);
```

The response will be an array of prices for the specified date range and also include the average price for the period.

### Get the lowest price for a period

```php
$lowestPrice = (new EnergyZero())->getLowestPriceForPeriod(
    startDate: '2024-01-01',
    endDate: '2024-01-02',
    vat: true
);
```

### Get the highest price for a period

```php
$highestPrice = (new EnergyZero())->getHighestPriceForPeriod(
    startDate: '2024-01-01',
    endDate: '2024-01-02',
    vat: true
);
```

### Get the prices above a threshold

```php
$prices = (new EnergyZero())->getPricesAboveThreshold(
    startDate: '2024-01-01',
    endDate: '2024-01-02',
    threshold: 0.05,
    vat: true
);
```

### Get the prices below a threshold

```php
$prices = (new EnergyZero())->getPricesBelowThreshold(
    startDate: '2024-01-01',
    endDate: '2024-01-02',
    threshold: 0.05,
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
