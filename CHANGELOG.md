# Changelog

All notable changes to `energyzero-php-api` will be documented in this file.

## v3.0.0 - 2026-11-13

### Breaking changes

- Prices now come from EnergyZero's public API (`public.api.energyzero.nl`). The old endpoint never returned quarter-hour prices, so `Interval::QUARTER` didn't work in v2.
- `$vat` is now `$priceType` (`PriceType|bool|null`); `true`/`false` still work positionally, named `vat:` arguments must be renamed.
- `Interval::WEEK`, `MONTH` and `YEAR` are removed. Electricity: `QUARTER` or `HOUR`. Gas: daily only.
- Results contain `Prices` and `average` only.
- Dates and hours are interpreted in `Europe/Amsterdam` by default instead of PHP's default timezone (`setTimezone()` to change).
- Typed exceptions: `ApiException` and `NoPricesException` (both extend `EnergyZeroException`, which extends `\Exception`). `request()` throws instead of returning `null`; nothing is written to `error_log()` any more.
- Requires PHP 8.3+.

See "Upgrading from v2" in the README.

### Added

- Quarter-hour electricity prices that actually return data.
- `PriceType` enum with market and all-in prices, with or without VAT, and `setDefaultPriceType()`.
- `setTimezone()` and an optional PSR-20 clock as second constructor argument.

### Fixed

- `getCurrentPrice()` and `getPricesForHours()` compared UTC hours with local hours and returned the wrong slot.
- API errors were logged and reported as "No energy prices found"; network errors leaked raw Guzzle exceptions.
- An injected client without `base_uri` failed silently.

### What's Changed

* fix!: throw typed exceptions instead of swallowing API errors by @Baspa in https://github.com/Baspa/energyzero-php-api/pull/31
* chore: housekeeping (changelog, contributing, dist archive, keywords, Pest 4) by @Baspa in https://github.com/Baspa/energyzero-php-api/pull/30
* fix!: compare price hours in the configured timezone by @Baspa in https://github.com/Baspa/energyzero-php-api/pull/32
* feat!: move to the public EnergyZero API by @Baspa in https://github.com/Baspa/energyzero-php-api/pull/33

**Full Changelog**: https://github.com/Baspa/energyzero-php-api/compare/v2.0.0...v3.0.0

## v2.0.0 - 2026-03-17

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
- Helper methods take the new `interval` parameter before `vat`. A positional `vat` boolean now lands in `interval` without an error, so pass `vat` by name
- Requires PHP 8.2 or higher (was 8.1)

### Unchanged

- Integer interval values (3, 4, etc.) work as before
- The default is still hourly electricity prices

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

## v1.0.0 - 2024-08-26

**Full Changelog**: https://github.com/Baspa/energyzero-php-api/commits/v1.0.0
