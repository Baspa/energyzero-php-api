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

```php
use Baspa\EnergyZero;

$prices = (new EnergyZero())->energyPrices(
    startDate: '2024-01-01',
    endDate: '2024-01-02',
    interval: 4,
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
