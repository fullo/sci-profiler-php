# Grid Carbon Intensity Reference

The `grid_carbon_intensity` parameter (I) in the SCI formula has a large impact on the final score. SCI Profiler PHP includes a built-in reference table to help you choose the right value for your location.

## Auto-detection

If you don't configure `grid_carbon_intensity`, the profiler uses the default value of 332 gCO2eq/kWh (global median estimate). You can use the built-in `GridCarbonData` helper to find a more accurate value based on your timezone or country:

```php
use SciProfiler\GridCarbonData;

// Detect from system timezone
$detected = GridCarbonData::detectFromSystem();
// e.g. ['intensity' => 324, 'country' => 'IT', 'timezone' => 'Europe/Rome']

// Look up by country code
$intensity = GridCarbonData::forCountry('DE');  // 298

// Look up by timezone
$intensity = GridCarbonData::forTimezone('America/New_York');  // 348
```

## Country Reference Table

Values are in **gCO2eq/kWh** (lifecycle emissions, including upstream). Source: [Ember Climate — Yearly Electricity Data](https://ember-energy.org/data/yearly-electricity-data/) (2024), licensed CC BY 4.0.

### Europe — EU

| Country | Code | gCO2eq/kWh |
|---------|------|-----------|
| Austria | AT | 126 |
| Belgium | BE | 185 |
| Bulgaria | BG | 255 |
| Croatia | HR | 243 |
| Czechia | CZ | 323 |
| Denmark | DK | 120 |
| Estonia | EE | 353 |
| Finland | FI | 87 |
| France | FR | 33 |
| Germany | DE | 298 |
| Greece | GR | 278 |
| Hungary | HU | 230 |
| Ireland | IE | 301 |
| Italy | IT | 324 |
| Latvia | LV | 215 |
| Lithuania | LT | 217 |
| Luxembourg | LU | 326 |
| Netherlands | NL | 281 |
| Poland | PL | 530 |
| Portugal | PT | 160 |
| Romania | RO | 242 |
| Slovakia | SK | 91 |
| Slovenia | SI | 165 |
| Spain | ES | 126 |
| Sweden | SE | 32 |

### Europe — Non-EU

| Country | Code | gCO2eq/kWh |
|---------|------|-----------|
| Albania | AL | 25 |
| Bosnia and Herzegovina | BA | 507 |
| Iceland | IS | 28 |
| Montenegro | ME | 352 |
| North Macedonia | MK | 502 |
| Norway | NO | 27 |
| Serbia | RS | 556 |
| Switzerland | CH | 38 |
| Turkey | TR | 392 |
| Ukraine | UA | 230 |
| United Kingdom | GB | 229 |

### Americas

| Country | Code | gCO2eq/kWh |
|---------|------|-----------|
| Argentina | AR | 275 |
| Brazil | BR | 109 |
| Canada | CA | 136 |
| Chile | CL | 228 |
| Colombia | CO | 157 |
| Mexico | MX | 412 |
| United States | US | 348 |

### Asia

| Country | Code | gCO2eq/kWh |
|---------|------|-----------|
| China | CN | 478 |
| Hong Kong | HK | 559 |
| India | IN | 595 |
| Indonesia | ID | 625 |
| Japan | JP | 436 |
| Malaysia | MY | 538 |
| Philippines | PH | 597 |
| Singapore | SG | 480 |
| South Korea | KR | 396 |
| Taiwan | TW | 540 |
| Thailand | TH | 479 |
| Vietnam | VN | 447 |

### Oceania

| Country | Code | gCO2eq/kWh |
|---------|------|-----------|
| Australia | AU | 466 |
| New Zealand | NZ | 109 |

### Africa

| Country | Code | gCO2eq/kWh |
|---------|------|-----------|
| Egypt | EG | 446 |
| Kenya | KE | 112 |
| Nigeria | NG | 383 |
| South Africa | ZA | 683 |

### Middle East

| Country | Code | gCO2eq/kWh |
|---------|------|-----------|
| Israel | IL | 502 |
| Saudi Arabia | SA | 543 |
| United Arab Emirates | AE | 359 |

## Updating the Data

The carbon intensity of electricity grids changes over time as countries transition to renewable sources. The built-in table should be updated periodically.

**How to update:**

1. Visit [Ember Electricity Data Explorer](https://ember-energy.org/data/electricity-data-explorer/)
2. Select "Carbon intensity" metric and "Yearly" resolution
3. Download the latest values for the countries you need
4. Update the `COUNTRY_INTENSITY` array in `src/GridCarbonData.php`

Alternatively, use these sources:

| Source | URL | Notes |
|--------|-----|-------|
| **Ember Yearly Data** | https://ember-energy.org/data/yearly-electricity-data/ | Annual averages, CC BY 4.0, downloadable CSV |
| **Ember Data Explorer** | https://ember-energy.org/data/electricity-data-explorer/ | Interactive, per-country |
| **Electricity Maps** | https://app.electricitymaps.com/ | Real-time data (requires API key for programmatic access) |
| **Our World in Data** | https://ourworldindata.org/grapher/carbon-intensity-electricity | Uses Ember data, good for historical trends |
| **Low Carbon Power** | https://lowcarbonpower.org/map-gCO2eq-kWh | Lifecycle values using IPCC factors |

## Regional Notes

### United States

The US value (348 gCO2eq/kWh) is a national average. Regional variation is significant:

- California (CAISO): ~200 gCO2eq/kWh
- New York (NYISO): ~250 gCO2eq/kWh
- Texas (ERCOT): ~350 gCO2eq/kWh
- Midwest (MISO): ~450 gCO2eq/kWh

If you know your grid region, configure `grid_carbon_intensity` manually.

### Italy

Italy's value (324 gCO2eq/kWh) is a national average. Northern Italy has lower carbon intensity than the south due to hydroelectric resources.

### India

India's value (595 gCO2eq/kWh) is a national average. Southern and western regions tend to be lower due to solar and wind capacity.

## Methodology: Lifecycle vs Direct Emissions

The values in this table are **lifecycle** emissions (gCO2eq/kWh), which include:

- Direct combustion emissions at the power plant
- Upstream emissions from fuel extraction, processing, and transport
- Construction and decommissioning of power generation facilities

This is the methodology recommended by the IPCC and used in LCA (Life Cycle Assessment) studies. Lifecycle values are more comprehensive than direct-only emissions and align with the embodied carbon approach used in the SCI formula.
