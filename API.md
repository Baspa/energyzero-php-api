# EnergyZero API Documentation

This document describes the EnergyZero API for developers who want to implement their own client in any programming language.

## Overview

The EnergyZero API provides dynamic energy prices for electricity and gas in the Netherlands. The API is **public and requires no authentication**.

## Base URL

```
https://public.api.energyzero.nl/public/v1/
```

## Endpoints

### GET /prices

Retrieves the prices around one Dutch day.

#### Query Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `energyType` | string | Yes | `ENERGY_TYPE_ELECTRICITY` or `ENERGY_TYPE_GAS` |
| `date` | string | Yes | One day in Dutch time, formatted `dd-mm-YYYY` (for example `13-11-2026`) |
| `interval` | string | Yes | `INTERVAL_QUARTER`, `INTERVAL_HOUR` or `INTERVAL_DAY` (see below) |

#### Intervals

| Interval | Electricity | Gas |
|----------|-------------|-----|
| `INTERVAL_QUARTER` | 15-minute prices | Not available |
| `INTERVAL_HOUR` | Hourly prices (the mean of the four quarters) | Not available |
| `INTERVAL_DAY` | Not available | One price per gas day (06:00 to 06:00 Dutch time) |

An interval that isn't available returns `400 Bad Request`.

#### What a date returns

A response covers more than the requested day:

- **Electricity:** the requested Dutch day and the day before it, plus the day after it once that day is published (so always for dates in the past). `date=13-11-2026` returned the range `2026-11-11T23:00:00Z` to `2026-11-13T23:00:00Z` (12 and 13 November in Dutch time).
- **Gas:** the gas day that starts on the requested date and the one before it, plus the one after it once that is published. `date=13-11-2026` returned the gas days starting at `2026-11-12T05:00:00Z` and `2026-11-13T05:00:00Z`.

The `range` field tells you which period the response covers. Filter the entries yourself if you only want the requested day. To fetch a longer period, request every other day: each response also covers the day before it.

## Response Format

### Success Response (200 OK)

```json
{
  "interval": "RESPONSE_INTERVAL_QUARTER",
  "range": {
    "start": "2026-11-11T23:00:00Z",
    "end": "2026-11-13T23:00:00Z"
  },
  "base": [
    {
      "start": "2026-11-11T23:00:00Z",
      "end": "2026-11-11T23:15:00Z",
      "price": { "value": "0.2249" }
    }
  ],
  "base_with_vat": [
    {
      "start": "2026-11-11T23:00:00Z",
      "end": "2026-11-11T23:15:00Z",
      "price": { "value": "0.272129" }
    }
  ],
  "all_in": [
    {
      "start": "2026-11-11T23:00:00Z",
      "end": "2026-11-11T23:15:00Z",
      "price": { "value": "0.31651" }
    }
  ],
  "all_in_with_vat": [
    {
      "start": "2026-11-11T23:00:00Z",
      "end": "2026-11-11T23:15:00Z",
      "price": { "value": "0.3829771" }
    }
  ]
}
```

The lists are shortened here; a quarter-hour response for two days holds 192 entries per list.

### Response Fields

| Field | Type | Description |
|-------|------|-------------|
| `interval` | string | `RESPONSE_INTERVAL_QUARTER`, `RESPONSE_INTERVAL_HOUR` or `RESPONSE_INTERVAL_DAY` |
| `range.start`, `range.end` | string | The period the response covers (UTC) |
| `base` | array | Market prices without VAT |
| `base_with_vat` | array | Market prices with 21% VAT |
| `all_in` | array | All-in prices (market price plus energy tax and surcharges, as EnergyZero calculates them) without VAT |
| `all_in_with_vat` | array | All-in prices with 21% VAT |
| `[].start`, `[].end` | string | Start and end of the slot (UTC) |
| `[].price.value` | string | Price in EUR per kWh (electricity) or EUR per m³ (gas). It's a **string**; convert it to a number yourself. |

The four lists have the same slots in chronological order. There is no `average` field; calculate it yourself if you need it.

### Error Responses

Errors return a JSON body with a numeric `code` and a `message`:

```json
{
  "code": 3,
  "message": "invalid interval type for electricity: daily",
  "details": []
}
```

| Status | `code` | When | Example `message` |
|--------|--------|------|-------------------|
| `400 Bad Request` | `3` | A parameter is missing or invalid | `invalid interval type for electricity: daily`, `interval is required`, `invalid DailyEnergyPricesRequest.Date: value does not match regex pattern "^\\d{2}-\\d{2}-\\d{4}$"` |
| `404 Not Found` | `5` | There are no prices for the date (yet) | `prices do not span the whole requested date`, `no prices found for the given date` |

Asking for tomorrow before its prices are published returns `404`.

## Price Availability

### Electricity Prices
- Prices per quarter-hour or hour, per Dutch day (00:00 to 24:00)
- **Tomorrow's prices:** Published around **14:00 UTC** (15:00 CET / 16:00 CEST)

### Gas Prices
- One price per gas day, from 06:00 to 06:00 Dutch time
- **A gas day's price:** Published around **05:00 UTC** (06:00 CET / 07:00 CEST), when the gas day starts. Before 06:00 Dutch time, the current gas price is the one for the gas day that started the previous morning.

## Date/Time Handling

The `date` parameter is a day in Dutch time (`Europe/Amsterdam`); the timestamps in the response are in UTC:

- Winter (CET, UTC+1): Dutch day `13-11-2026` runs from `2026-11-12T23:00:00Z` to `2026-11-13T23:00:00Z`
- Summer (CEST, UTC+2): Dutch day `15-07-2026` runs from `2026-07-14T22:00:00Z` to `2026-07-15T22:00:00Z`

The day the clocks go back has 25 hours (100 quarters), the day they go forward 23 (92 quarters).

### Example: Get today's prices in PHP

```php
$tz = new DateTimeZone('Europe/Amsterdam');
$dayStart = new DateTimeImmutable('today', $tz);
$dayEnd = $dayStart->modify('+1 day');

$query = http_build_query([
    'energyType' => 'ENERGY_TYPE_ELECTRICITY',
    'date' => $dayStart->format('d-m-Y'),
    'interval' => 'INTERVAL_QUARTER',
]);
$data = json_decode(file_get_contents("https://public.api.energyzero.nl/public/v1/prices?{$query}"), true);

$today = array_filter($data['base_with_vat'], function (array $entry) use ($dayStart, $dayEnd) {
    $start = new DateTimeImmutable($entry['start']);

    return $start >= $dayStart && $start < $dayEnd;
});
```

## Rate Limiting

The API does not appear to have strict rate limiting, but it's recommended to:
- Cache responses appropriately
- Poll at reasonable intervals (e.g., every 10 minutes)
- Avoid unnecessary requests

## Code Examples

### Python

```python
from datetime import datetime, timedelta
from zoneinfo import ZoneInfo

import requests

AMSTERDAM = ZoneInfo("Europe/Amsterdam")


def get_electricity_prices(day, interval="INTERVAL_QUARTER", price_type="base_with_vat"):
    """
    Get electricity prices for one Dutch day.

    Args:
        day: a date in Dutch time
        interval: "INTERVAL_QUARTER" or "INTERVAL_HOUR"
        price_type: "base", "base_with_vat", "all_in" or "all_in_with_vat"
    """
    response = requests.get(
        "https://public.api.energyzero.nl/public/v1/prices",
        params={
            "energyType": "ENERGY_TYPE_ELECTRICITY",
            "date": day.strftime("%d-%m-%Y"),
            "interval": interval,
        },
    )
    if response.status_code == 404:
        return []  # not published yet
    response.raise_for_status()

    # The response also holds the day before; keep only the requested day.
    day_start = datetime(day.year, day.month, day.day, tzinfo=AMSTERDAM)
    day_end = day_start + timedelta(days=1)

    return [
        {"start": entry["start"], "price": float(entry["price"]["value"])}
        for entry in response.json()[price_type]
        if day_start <= datetime.fromisoformat(entry["start"].replace("Z", "+00:00")) < day_end
    ]


prices = get_electricity_prices(datetime.now(AMSTERDAM).date())
for price in prices:
    print(f"{price['start']}: €{price['price']:.4f}/kWh")
if prices:
    print(f"Average: €{sum(p['price'] for p in prices) / len(prices):.4f}/kWh")
```

### JavaScript/Node.js

```javascript
function dutchDate(date) {
  // en-GB formats as dd/mm/yyyy
  return date
    .toLocaleDateString('en-GB', { timeZone: 'Europe/Amsterdam' })
    .replaceAll('/', '-');
}

async function getElectricityPrices(date, interval = 'INTERVAL_QUARTER', priceType = 'base_with_vat') {
  const day = dutchDate(date);
  const params = new URLSearchParams({
    energyType: 'ENERGY_TYPE_ELECTRICITY',
    date: day,
    interval,
  });

  const response = await fetch(`https://public.api.energyzero.nl/public/v1/prices?${params}`);

  if (response.status === 404) {
    return []; // not published yet
  }
  if (!response.ok) {
    const error = await response.json();
    throw new Error(`EnergyZero API returned ${response.status}: ${error.message}`);
  }

  const data = await response.json();

  // The response also holds the day before; keep only the requested day.
  return data[priceType]
    .filter((entry) => dutchDate(new Date(entry.start)) === day)
    .map((entry) => ({ start: entry.start, price: Number(entry.price.value) }));
}

const prices = await getElectricityPrices(new Date());
prices.forEach((p) => {
  console.log(`${p.start}: €${p.price.toFixed(4)}/kWh`);
});
```

### cURL

```bash
# Quarter-hour electricity prices (market price with VAT) for 13 November 2026
curl -s "https://public.api.energyzero.nl/public/v1/prices?\
energyType=ENERGY_TYPE_ELECTRICITY&\
date=13-11-2026&\
interval=INTERVAL_QUARTER" \
  | jq -r '.base_with_vat[] | select(.start >= "2026-11-12T23:00:00Z") | "\(.start): €\(.price.value)"'

# Gas prices (all-in with VAT) around 13 November 2026
curl -s "https://public.api.energyzero.nl/public/v1/prices?\
energyType=ENERGY_TYPE_GAS&\
date=13-11-2026&\
interval=INTERVAL_DAY" \
  | jq -r '.all_in_with_vat[] | "\(.start): €\(.price.value)/m³"'
```

## Related Resources

- [Python client (python-energyzero)](https://github.com/klaasnicolaas/python-energyzero)
- [Home Assistant integration](https://www.home-assistant.io/integrations/energyzero/)
- [This PHP client](https://github.com/Baspa/energyzero-php-api)

## Notes

- `base` and `base_with_vat` are wholesale market prices. The all-in prices add energy tax and surcharges; your own supplier may charge different costs.
- The API is used by various Dutch energy providers including EnergyZero, ANWB Energie, and others
- Quarter-hour prices (`INTERVAL_QUARTER`) provide more granular data, useful for optimizing energy usage
