# EnergyZero API Documentation

This document describes the EnergyZero API for developers who want to implement their own client in any programming language.

## Overview

The EnergyZero API provides dynamic energy prices for electricity and gas in the Netherlands. The API is **public and requires no authentication**.

## Base URL

```
https://api.energyzero.nl/v1/
```

## Endpoints

### GET /energyprices

Retrieves energy prices for a specified date range.

#### Query Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `fromDate` | string | Yes | Start date/time in ISO 8601 UTC format: `YYYY-MM-DDTHH:mm:ss.sssZ` |
| `tillDate` | string | Yes | End date/time in ISO 8601 UTC format: `YYYY-MM-DDTHH:mm:ss.sssZ` |
| `interval` | integer | Yes | Time interval for price data (see table below) |
| `usageType` | integer | Yes | Type of energy: `1` = electricity, `3` = gas |
| `inclBtw` | string | No | Include VAT: `true` or `false` (default: `true`) |

#### Interval Values

| Value | Interval | Description |
|-------|----------|-------------|
| `3` | Quarter | 15-minute intervals (electricity only) |
| `4` | Hour | Hourly intervals |
| `5` | Day | Daily intervals |
| `6` | Week | Weekly intervals |
| `7` | Month | Monthly intervals |
| `8` | Year | Yearly intervals |

> **Note:** Quarter-hour intervals (`3`) are only available for electricity, not for gas.

## Price Availability

### Electricity Prices
- **Today's prices:** Available from 00:00
- **Tomorrow's prices:** Published around **14:00 UTC** (15:00 CET / 16:00 CEST)
- Prices change every hour (or every 15 minutes for quarter-hour data)

### Gas Prices
- **Today's prices:** Available from 06:00 CET
- **Tomorrow's prices:** Published around **05:00 UTC** (06:00 CET / 07:00 CEST)
- Prices are fixed for the entire day

## Example Requests

### Electricity prices for today (hourly)

```bash
# Replace dates with actual values
curl "https://api.energyzero.nl/v1/energyprices?fromDate=2024-01-15T23:00:00.000Z&tillDate=2024-01-16T22:59:59.999Z&interval=4&usageType=1&inclBtw=true"
```

### Electricity prices for today (quarter-hour)

```bash
curl "https://api.energyzero.nl/v1/energyprices?fromDate=2024-01-15T23:00:00.000Z&tillDate=2024-01-16T22:59:59.999Z&interval=3&usageType=1&inclBtw=true"
```

### Gas prices for today

```bash
curl "https://api.energyzero.nl/v1/energyprices?fromDate=2024-01-15T23:00:00.000Z&tillDate=2024-01-16T22:59:59.999Z&interval=4&usageType=3&inclBtw=true"
```

### Electricity prices for tomorrow (after 14:00 UTC)

```bash
curl "https://api.energyzero.nl/v1/energyprices?fromDate=2024-01-16T23:00:00.000Z&tillDate=2024-01-17T22:59:59.999Z&interval=3&usageType=1&inclBtw=true"
```

## Date/Time Handling

The API expects dates in **UTC timezone**. When requesting prices for a specific day in local time (CET/CEST), you need to convert accordingly:

### For CET (Winter: UTC+1)
- Local day `2024-01-15` = UTC `2024-01-14T23:00:00.000Z` to `2024-01-15T22:59:59.999Z`

### For CEST (Summer: UTC+2)
- Local day `2024-07-15` = UTC `2024-07-14T22:00:00.000Z` to `2024-07-15T21:59:59.999Z`

### Example: Get today's prices in PHP

```php
$localTz = new DateTimeZone('Europe/Amsterdam');
$utcTz = new DateTimeZone('UTC');

$startDate = new DateTime('today', $localTz);
$startDate->setTime(0, 0, 0);
$startDate->setTimezone($utcTz);

$endDate = new DateTime('today', $localTz);
$endDate->setTime(23, 59, 59);
$endDate->setTimezone($utcTz);

$fromDate = $startDate->format('Y-m-d\TH:i:s.000\Z');
$tillDate = $endDate->format('Y-m-d\TH:i:s.999\Z');
```

## Response Format

### Success Response (200 OK)

```json
{
  "Prices": [
    {
      "readingDate": "2024-01-15T23:00:00Z",
      "price": 0.08234
    },
    {
      "readingDate": "2024-01-16T00:00:00Z",
      "price": 0.07856
    }
  ],
  "average": 0.08045,
  "fromDate": "2024-01-15T23:00:00Z",
  "tillDate": "2024-01-16T22:59:59Z",
  "intervalType": 4
}
```

### Response Fields

| Field | Type | Description |
|-------|------|-------------|
| `Prices` | array | Array of price objects |
| `Prices[].readingDate` | string | Timestamp for this price period (UTC) |
| `Prices[].price` | float | Price in EUR per kWh (electricity) or EUR per m³ (gas) |
| `average` | float | Average price for the requested period |
| `fromDate` | string | Start of the requested period |
| `tillDate` | string | End of the requested period |
| `intervalType` | integer | The interval type used |

### Error Responses

The API returns empty `Prices` array when:
- Requesting future prices before they're published
- Invalid date range
- Invalid parameters

## Rate Limiting

The API does not appear to have strict rate limiting, but it's recommended to:
- Cache responses appropriately
- Poll at reasonable intervals (e.g., every 10 minutes)
- Avoid unnecessary requests

## Code Examples

### Python

```python
import requests
from datetime import datetime, timezone

def get_electricity_prices(date, interval=4, vat=True):
    """
    Get electricity prices for a specific date.

    Args:
        date: Date string in YYYY-MM-DD format (local time)
        interval: 3 for quarter-hour, 4 for hourly
        vat: Include VAT in prices
    """
    # Convert local date to UTC range
    # Note: Adjust for your timezone
    from_date = f"{date}T23:00:00.000Z"  # Previous day 23:00 UTC for CET
    till_date = f"{date}T22:59:59.999Z"   # Same day 22:59 UTC for CET

    url = "https://api.energyzero.nl/v1/energyprices"
    params = {
        "fromDate": from_date,
        "tillDate": till_date,
        "interval": interval,
        "usageType": 1,
        "inclBtw": "true" if vat else "false"
    }

    response = requests.get(url, params=params)
    return response.json()

# Get today's quarter-hour prices
prices = get_electricity_prices("2024-01-15", interval=3)
for price in prices["Prices"]:
    print(f"{price['readingDate']}: €{price['price']:.4f}/kWh")
```

### JavaScript/Node.js

```javascript
async function getElectricityPrices(date, interval = 4, vat = true) {
  // Convert local date to UTC range (for CET timezone)
  const fromDate = `${date}T23:00:00.000Z`;
  const tillDate = `${date}T22:59:59.999Z`;

  const params = new URLSearchParams({
    fromDate,
    tillDate,
    interval: interval.toString(),
    usageType: '1',
    inclBtw: vat.toString()
  });

  const response = await fetch(
    `https://api.energyzero.nl/v1/energyprices?${params}`
  );

  return response.json();
}

// Get today's quarter-hour prices
const prices = await getElectricityPrices('2024-01-15', 3);
prices.Prices.forEach(p => {
  console.log(`${p.readingDate}: €${p.price.toFixed(4)}/kWh`);
});
```

### cURL

```bash
# Quarter-hour electricity prices for January 15, 2024 (CET)
curl -s "https://api.energyzero.nl/v1/energyprices?\
fromDate=2024-01-14T23:00:00.000Z&\
tillDate=2024-01-15T22:59:59.999Z&\
interval=3&\
usageType=1&\
inclBtw=true" | jq '.Prices[] | "\(.readingDate): €\(.price)"'
```

## Related Resources

- [Python client (python-energyzero)](https://github.com/klaasnicolaas/python-energyzero)
- [Home Assistant integration](https://www.home-assistant.io/integrations/energyzero/)
- [This PHP client](https://github.com/Baspa/energyzero-php-api)

## Notes

- Prices are wholesale market prices. Energy providers add additional costs (energy tax, transport costs, etc.)
- The API is used by various Dutch energy providers including EnergyZero, ANWB Energie, and others
- Quarter-hour prices (`interval=3`) provide more granular data, useful for optimizing energy usage
