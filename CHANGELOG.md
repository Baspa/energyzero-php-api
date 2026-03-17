# Changelog

All notable changes to `energyzero-php-api` will be documented in this file.

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
