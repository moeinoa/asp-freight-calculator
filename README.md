# ASP Freight Calculator

WordPress plugin that estimates intercity freight cost by vehicle type. The visitor picks an origin city, a destination city and a vehicle; the server gets the road distance from the Neshan API (cached), calculates the price and returns it.

- **Version:** 1.1.1
- **Requires:** WordPress 5.8+, PHP 7.4+ (tested on 8.3)
- **Text domain:** `asp-freight` (Persian translation included)
- **External service:** [Neshan distance-matrix API](https://platform.neshan.org) (requires an API key)

## Usage

1. Install and activate the plugin.
2. Go to **Settings → محاسبه‌گر کرایه** and enter your Neshan API key.
3. Put the shortcode on any page:

```
[asp_freight_calculator]
```

Cities and vehicles come from `data/calculator-data.json`. Only city names and ids are sent to the browser, never coordinates or rates.

## Settings

| Option | Meaning |
|---|---|
| Neshan API key | Required to get distances. Stored as `asp_freight_neshan_api_key`. |
| Distance cache (days) | Distances are cached as transients per city pair (A→B equals B→A). Default 30 days, 1–365. |
| Clear distance cache | Button on the settings page. |

The settings page also tells you whether the data file is valid and how many cities have no coordinates.

## Price formula

```
price = rate_per_km × distance_km + base_fee
if min_price is set and price < min_price → price = min_price
price = ceil(price / round_to) × round_to
```

`round_to` (default 100000) and `currency_label` come from `settings` in the data file. If `round_to` is missing or not a positive number, 100000 is used. The result also reports the distance, the destination note and whether the minimum price was applied.

## Data file: `data/calculator-data.json`

```json
{
  "origin":   { ... },
  "settings": { "round_to": 600000, "currency_label": "تومان" },
  "vehicles": [
    { "id": "nissan", "label": "نیسان", "rate_per_km": 6076, "base_fee": 1633639, "min_price": 0 }
  ],
  "destinations": [
    { "id": "ری", "city": "ری", "note": "", "lat": 35.59871, "lng": 51.438391 }
  ]
}
```

Every destination needs `lat` and `lng`. **Do not overwrite this file when you update the plugin from a zip**; keep your own copy or restore it afterwards.

## Spam protection (server side, no third-party service)

- Hidden honeypot field.
- A form-render timestamp: requests sent less than 1.2 seconds after the form was rendered are rejected.
- Per-IP rate limit: 12 requests per minute and 150 per day.
- WordPress nonce on every request. On pages served from a full-page cache the nonce may be expired, so the script asks for a fresh nonce once (`asp_freight_nonce` action) and retries.

The IP is read from `REMOTE_ADDR`. If the site is put behind a CDN or reverse proxy, adjust `ASP_Freight_Antispam::get_client_ip()`.

## Files

| File | Purpose |
|---|---|
| `asp-freight-calculator.php` | Bootstrap and constants |
| `includes/class-admin.php` | Settings page |
| `includes/class-ajax.php` | Calculation, distance lookup + cache, fresh nonce, cache clearing |
| `includes/class-antispam.php` | Honeypot, minimum time, rate limits |
| `includes/class-shortcode.php` | Shortcode and asset loading |
| `templates/calculator.php` | Form markup |
| `assets/` | CSS and JS (searchable city combobox) |

## Changelog

### 1.1.1
- A zero or invalid `round_to` no longer causes a fatal error (division by zero).
- Fresh-nonce retry for cached pages.
- City names in the suggestion list are escaped; clearing the cache matches the exact transient prefix.

### 1.1.0
- Previous release.
