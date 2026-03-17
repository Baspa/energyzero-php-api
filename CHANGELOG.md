# Changelog

All notable changes to `energyzero-php-api` will be documented in this file.

## v2.0.0 - 2026-03-17

### What's Changed

* Bump actions/checkout from 3 to 4 by @dependabot[bot] in https://github.com/Baspa/energyzero-php-api/pull/4
* Bump dependabot/fetch-metadata from 1.5.1 to 2.2.0 by @dependabot[bot] in https://github.com/Baspa/energyzero-php-api/pull/3
* Bump stefanzweifel/git-auto-commit-action from 4 to 5 by @dependabot[bot] in https://github.com/Baspa/energyzero-php-api/pull/1
* Bump aglipanci/laravel-pint-action from 1.0.0 to 2.3.1 by @dependabot[bot] in https://github.com/Baspa/energyzero-php-api/pull/2
* Bump aglipanci/laravel-pint-action from 2.3.1 to 2.4 by @dependabot[bot] in https://github.com/Baspa/energyzero-php-api/pull/5
* Bump dependabot/fetch-metadata from 2.2.0 to 2.3.0 by @dependabot[bot] in https://github.com/Baspa/energyzero-php-api/pull/6
* Bump aglipanci/laravel-pint-action from 2.4 to 2.5 by @dependabot[bot] in https://github.com/Baspa/energyzero-php-api/pull/7
* Bump dependabot/fetch-metadata from 2.3.0 to 2.4.0 by @dependabot[bot] in https://github.com/Baspa/energyzero-php-api/pull/8
* Bump aglipanci/laravel-pint-action from 2.5 to 2.6 by @dependabot[bot] in https://github.com/Baspa/energyzero-php-api/pull/10
* Bump actions/checkout from 4 to 5 by @dependabot[bot] in https://github.com/Baspa/energyzero-php-api/pull/11
* Bump stefanzweifel/git-auto-commit-action from 5 to 7 by @dependabot[bot] in https://github.com/Baspa/energyzero-php-api/pull/12
* Bump actions/checkout from 5 to 6 by @dependabot[bot] in https://github.com/Baspa/energyzero-php-api/pull/13
* Bump dependabot/fetch-metadata from 2.4.0 to 2.5.0 by @dependabot[bot] in https://github.com/Baspa/energyzero-php-api/pull/14
* feat: add quarter-hour pricing and gas prices support by @Baspa in https://github.com/Baspa/energyzero-php-api/pull/16

### New Contributors

* @Baspa made their first contribution in https://github.com/Baspa/energyzero-php-api/pull/16

**Full Changelog**: https://github.com/Baspa/energyzero-php-api/compare/v1.0.0...v2.0.0

## v1.1.0 - 2025-03-17

### Added

- `Interval` enum for type-safe interval selection (QUARTER, HOUR, DAY, WEEK, MONTH, YEAR)
- `EnergyType` enum for selecting between electricity and gas prices
- `gasPrices()` method for fetching gas prices
- `getCurrentPrice()` method for fetching the current hour's price
- `getPricesForHours()` method for fetching prices for specific hours of a day
- `setDefaultInterval()` method for setting a default interval
- `setDefaultEnergyType()` method for setting a default energy type
- Added `interval` parameter to all helper methods (`getAveragePriceForPeriod`, `getLowestPriceForPeriod`, `getHighestPriceForPeriod`, `getPricesAboveThreshold`, `getPricesBelowThreshold`, `getPeakHours`, `getValleyHours`)
- Added `energyType` parameter to all methods for fetching electricity or gas prices

### Changed

- `energyPrices()` now accepts `Interval` enum or integer for backward compatibility
- All helper methods now accept optional `Interval` parameter

### Backward Compatibility

- Integer interval values (3, 4, etc.) continue to work as before
- Default behavior remains hourly electricity prices
- All existing method signatures remain compatible

## v1.0.0 - 2024-08-26

**Full Changelog**: https://github.com/Baspa/energyzero-php-api/commits/v1.0.0
