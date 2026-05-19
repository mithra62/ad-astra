# New Field Types: Money, Country, State/Province, Time

> **Status:** Design proposal — under review. No code changes have been made.
> **Last updated:** 2026-05-19

## Context

The CMS's field system already covers the basics (text, number, date, select, relationship, users, media, structured rows), but everyday content models keep reaching for primitives the system doesn't have: prices, locations, and times-of-day. This batch adds four production-ready field types so editors stop having to fake them with plain text fields.

### Original scope and what changed

The original request named six field types: **Money, Country, State/Province, Address, Time, Users**. After review:

- **Users** — already implemented at [`app/Field/Types/Users.php`](../app/Field/Types/Users.php) with role filter, selection limit, three display modes (dropdown / checkboxes / tokens), and safe-column hydration. Dropped from scope.
- **Address** — deferred. The existing `StructuredRows` base is a multi-row table; a clean Address implementation should extend a single-row `StructuredData` abstract that doesn't yet exist. Better to revisit after this batch lands.

Final scope: **4 field types** (Money, Country, State/Province, Time) plus the supporting infrastructure described below.

### Confirmed design decisions

| Field | Storage column | Key decision |
|---|---|---|
| **Money** | `value_integer` | Minor units only. Currency configured per-field instance. Decimal precision auto-derived from ISO 4217 (USD=2, JPY=0, BHD=3). |
| **Country** | `value_text` | Single-select. ISO 3166-1 alpha-2 codes. Optional `allowed_countries` whitelist. |
| **State/Province** | `value_text` | `country` setting hardcoded per field instance. ISO 3166-2 subdivision codes (e.g. `US-CA`). |
| **Time** | `value_text` | 24-hour canonical `HH:MM` or `HH:MM:SS`. Settings for seconds, min/max, step. |

**ISO data sourcing:** hardcoded PHP datasets. No new tables, no Composer dependency.

---

## Foundational addition: `prepareForStorage()` hook

Currently [`EntryRepository::applyFieldValues()`](../app/Repositories/EntryRepository.php) hands the raw form value straight to `upsertFieldValue()`. Money needs to convert the form's `"42.50"` string into the integer `4250` before storage. The cleanest place to do that is a new hook on the field type itself.

**Add to [`app/Field/AbstractField.php`](../app/Field/AbstractField.php):**

```php
/**
 * Transform a value before it is written to its storage column.
 * Default: identity. Field types that accept a different shape on
 * the wire than they store on disk (e.g. Money) override this.
 */
public function prepareForStorage(mixed $value): mixed
{
    return $value;
}
```

**Wire into [`EntryRepository::applyFieldValues()`](../app/Repositories/EntryRepository.php) (around line 183)** — one line, just before the `upsertFieldValue` call:

```php
$value = $instance->prepareForStorage($value);
```

This is additive: every existing field type inherits the no-op default, so behavior is unchanged. The hook is also a natural home for future normalization needs (Time canonicalization, phone number formatting, etc.).

---

## ISO data: shared support classes

New namespace **`App\Support\Iso`** — static-data classes. Hardcoded PHP arrays, no DB.

### `app/Support/Iso/Currencies.php`

ISO 4217. Recommended starting set: ~50 most common currencies (covers >99% of practical CMS use); full ~180 is reasonable if we want completeness. Each entry: `code`, `name`, `symbol`, `decimals`.

```php
Currencies::all(): array
    // [['code'=>'USD','name'=>'US Dollar','symbol'=>'$','decimals'=>2], ...]

Currencies::exists(string $code): bool
Currencies::decimals(string $code): int     // 2 for USD, 0 for JPY, 3 for BHD
Currencies::symbol(string $code): string
Currencies::name(string $code): string
```

### `app/Support/Iso/Countries.php`

Full ISO 3166-1 alpha-2 list (249 entries). Each: `code`, `name`.

```php
Countries::all(): array               // [['code'=>'US','name'=>'United States'], ...]
Countries::exists(string $code): bool
Countries::name(string $code): string
```

### `app/Support/Iso/Subdivisions.php`

ISO 3166-2 subdivisions, **scoped to a practical subset** for first cut: US, CA, MX, GB, IE, AU, NZ, DE, FR, ES, IT, NL, BE, BR, IN, JP, CN. Countries outside the subset return an empty list, and the State field renders a free-text input as fallback (see State design below).

```php
Subdivisions::forCountry(string $countryCode): array
    // [['code'=>'US-CA','name'=>'California'], ...]

Subdivisions::exists(string $countryCode, string $subdivisionCode): bool
Subdivisions::hasData(string $countryCode): bool      // drives fallback decision
```

> **Note:** Subdivision codes follow ISO 3166-2 (e.g. `US-CA`, `CA-ON`). Storing the full `XX-YY` form keeps values unambiguous across countries.

**Open question for review:** which countries should be in the v1 subdivision subset? The list above is a starting point.

---

## Field type 1: Money

- **File:** `app/Field/Types/Money.php` (new)
- **Storage:** `value_integer` — minor units, always. No `HasDecimalStorage` trait, no float storage.
- **Handle:** `money` · **Name:** `Money`

### Settings

| Key | Type | Default | Purpose |
|---|---|---|---|
| `currency` | select | `USD` | ISO 4217 code. Options from `Currencies::all()` via `settingsFormOptions()`. Required. |
| `min` | number | `null` | Minimum allowed in major units (e.g. `0.00`). Optional. |
| `max` | number | `null` | Maximum allowed in major units. Optional. |
| `default` | number | `null` | Pre-filled value in major units. Optional. |

### Behavior

- `prepareForStorage($value)` — accepts string `"42.50"` or numeric `42.5`, multiplies by `10 ** Currencies::decimals($this->getSetting('currency'))`, returns int `4250`. Empty/null → `null`.
- `validate($value)` — checks numeric, enforces `min`/`max` against the major-unit input, rejects negative amounts unless allowed by `min`.
- `cast($value)` — returns the raw int minor units (storage column is integer; this is mainly a type guarantee).
- `value($raw)` — returns a `MoneyValue` value object (`app/Support/Iso/MoneyValue.php`):

  ```php
  $money->minor_units;          // 4250
  $money->currency;             // 'USD'
  $money->decimals;             // 2
  $money->amount();             // 42.50 (float)
  $money->formatted();          // '$42.50'
  ```

- `render($params)` — twig template at `resources/views/_fields/money.twig` with a currency symbol prefix and `<input type="number" step="0.01">` (step derived from currency decimals).

---

## Field type 2: Country

- **File:** `app/Field/Types/Country.php` (new)
- **Storage:** `value_text`
- **Handle:** `country` · **Name:** `Country`

### Settings

| Key | Type | Default | Purpose |
|---|---|---|---|
| `allowed_countries` | select_multiple | `[]` | Optional ISO-2 whitelist. Empty = all. Options from `Countries::all()`. |
| `default` | select | `null` | Pre-selected country code. |
| `placeholder` | text | `— Select —` | Empty-option label. |

### Behavior

- `validate($value)` — non-empty values must exist in `Countries::exists($value)`. If `allowed_countries` is set, must also be in that list.
- `cast($value)` — return the string code uppercased.
- `value($raw)` — return `['code' => 'US', 'name' => 'United States']` for template convenience.
- `render($params)` — twig template at `resources/views/_fields/country.twig`. Native `<select>` populated from whitelist (if set) or `Countries::all()`.

---

## Field type 3: State/Province

- **File:** `app/Field/Types/StateProvince.php` (new)
- **Storage:** `value_text`
- **Handle:** `state_province` · **Name:** `State/Province`

### Settings

| Key | Type | Default | Purpose |
|---|---|---|---|
| `country` | select | `US` | Required. ISO-2 code whose subdivisions are shown. Options from `Countries::all()`. |
| `default` | text | `null` | Pre-selected subdivision code (e.g. `US-CA`). |
| `placeholder` | text | `— Select —` | Empty-option label. |
| `allow_freetext_fallback` | toggle | `true` | If the configured country has no subdivision data, render a text input instead of failing. |

### Behavior

- `validate($value)` — when non-empty:
  - If `Subdivisions::hasData($country)` is true: value must satisfy `Subdivisions::exists($country, $value)`.
  - If no subdivision data and `allow_freetext_fallback` is true: accept any non-empty string up to 100 chars.
  - Otherwise: reject with a clear error.
- `cast($value)` — return the string code as-is.
- `value($raw)` — return `['code' => 'US-CA', 'name' => 'California', 'country' => 'US']`. If freetext fallback was used, `name === code`.
- `render($params)` — twig template at `resources/views/_fields/state_province.twig`. Select when subdivision data exists; text input otherwise.

---

## Field type 4: Time

- **File:** `app/Field/Types/Time.php` (new)
- **Storage:** `value_text` (canonical 24-hour `HH:MM` or `HH:MM:SS`)
- **Handle:** `time` · **Name:** `Time`

### Settings

| Key | Type | Default | Purpose |
|---|---|---|---|
| `include_seconds` | toggle | `false` | When true, canonical format is `HH:MM:SS`. |
| `min_time` | text | `null` | Earliest allowed time. |
| `max_time` | text | `null` | Latest allowed time. |
| `step_minutes` | number | `1` | UI step granularity in minutes (drives input's `step` attribute). |
| `default` | text | `null` | Pre-filled time, or the literal string `"now"`. |

### Behavior

- `prepareForStorage($value)` — normalize to canonical form. `<input type="time">` already submits `HH:MM` or `HH:MM:SS`, but we still validate and trim. `"9:30"` → `"09:30"`. Invalid → `null`.
- `validate($value)` — regex `^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$`, then enforce `min_time`/`max_time` via string comparison (canonical form sorts correctly).
- `cast($value)` — return the canonical string.
- `value($raw)` — return a `DateTimeImmutable` anchored to `1970-01-01`, so templates can call `format()` directly:

  ```twig
  {{ entry.field('open_time')|date('g:i A') }}   {# "9:30 AM" #}
  ```

- `render($params)` — twig template at `resources/views/_fields/time.twig`, native `<input type="time" step="...">` with `step = step_minutes * 60`.

---

## Registration

**Update [`database/seeders/FieldTypeSeeder.php`](../database/seeders/FieldTypeSeeder.php)** — append four entries:

```php
['name' => 'Money',           'object' => \App\Field\Types\Money::class],
['name' => 'Country',         'object' => \App\Field\Types\Country::class],
['name' => 'State/Province',  'object' => \App\Field\Types\StateProvince::class],
['name' => 'Time',            'object' => \App\Field\Types\Time::class],
```

`Type::firstOrCreate` keeps the seeder idempotent. Run with:

```bash
php artisan db:seed --class=FieldTypeSeeder
```

---

## Twig templates (new)

All under [`resources/views/_fields/`](../resources/views/_fields/):

- `money.twig` — currency-prefixed number input
- `country.twig` — select populated from whitelist or full list
- `state_province.twig` — select when subdivision data exists, else text input
- `time.twig` — native `<input type="time">` with appropriate `step`

Style follows the patterns in [`select.twig`](../resources/views/_fields/select.twig) and [`date.twig`](../resources/views/_fields/date.twig) — Tailwind classes, `old('fields.' ~ field.handle, value)` for value rehydration.

---

## Testing

New unit tests under [`tests/Unit/Field/Types/`](../tests/Unit/Field/Types/), mirroring [`UsersTest.php`](../tests/Unit/Field/Types/UsersTest.php) and [`SelectTest.php`](../tests/Unit/Field/Types/SelectTest.php):

- **`MoneyTest.php`** — `storageColumn` returns `value_integer`; `prepareForStorage` converts `"42.50"`→`4250` for USD, `"100"`→`100` for JPY, `"1.234"`→`1234` for BHD; `validate` enforces min/max in major units; `cast` returns int; `value()` returns `MoneyValue` with correct `amount()`/`formatted()`; settings form keys.
- **`CountryTest.php`** — `value_text`; `validate` rejects unknown codes; whitelist enforcement; `value()` returns `['code', 'name']`.
- **`StateProvinceTest.php`** — `value_text`; subdivisions resolve for known countries (US, CA); unknown subdivision rejected for countries with data; freetext fallback accepted for countries without data when toggle is on; rejected when toggle off; `value()` shape.
- **`TimeTest.php`** — `value_text`; `prepareForStorage` canonicalizes `"9:30"`→`"09:30"`; `validate` accepts canonical forms and rejects `"25:00"`/`"09:60"`; min/max enforcement; `value()` returns `DateTimeImmutable`.

Plus support-class tests under `tests/Unit/Support/Iso/`:

- **`CurrenciesTest.php`** — `decimals('USD')===2`, `decimals('JPY')===0`, `decimals('BHD')===3`, `exists()`, `all()` non-empty.
- **`CountriesTest.php`** — known codes resolve, unknowns reject, all entries have `code`+`name`.
- **`SubdivisionsTest.php`** — `forCountry('US')` includes `US-CA`, `hasData('US')===true`, `hasData('XX')===false`.

Existing tests should be untouched and pass — the only framework change is the new `prepareForStorage()` hook with a no-op default.

---

## Files

### To create

- `app/Field/Types/Money.php`
- `app/Field/Types/Country.php`
- `app/Field/Types/StateProvince.php`
- `app/Field/Types/Time.php`
- `app/Support/Iso/Currencies.php`
- `app/Support/Iso/Countries.php`
- `app/Support/Iso/Subdivisions.php`
- `app/Support/Iso/MoneyValue.php`
- `resources/views/_fields/money.twig`
- `resources/views/_fields/country.twig`
- `resources/views/_fields/state_province.twig`
- `resources/views/_fields/time.twig`
- `tests/Unit/Field/Types/MoneyTest.php`
- `tests/Unit/Field/Types/CountryTest.php`
- `tests/Unit/Field/Types/StateProvinceTest.php`
- `tests/Unit/Field/Types/TimeTest.php`
- `tests/Unit/Support/Iso/CurrenciesTest.php`
- `tests/Unit/Support/Iso/CountriesTest.php`
- `tests/Unit/Support/Iso/SubdivisionsTest.php`

### To modify

- [`app/Field/AbstractField.php`](../app/Field/AbstractField.php) — add `prepareForStorage()` no-op default
- [`app/Repositories/EntryRepository.php`](../app/Repositories/EntryRepository.php) — call `prepareForStorage()` before `upsertFieldValue` (line ~183)
- [`database/seeders/FieldTypeSeeder.php`](../database/seeders/FieldTypeSeeder.php) — register four new types

---

## Open questions / discussion points

1. **Currency dataset size.** Ship ~50 most-common currencies, or the full ISO 4217 list (~180)? Full list is more inclusive but adds ~3KB of static data.
2. **Subdivision country coverage.** Proposed v1 set is 17 countries. Acceptable, or do we need more (e.g. all of EU, all of LATAM)? The free-text fallback handles gaps gracefully.
3. **Currency display.** Should `MoneyValue::formatted()` use PHP's `NumberFormatter` (locale-aware, e.g. `1.234,56 €` for `de_DE`) or a simple `symbol + amount` concatenation? `NumberFormatter` requires the `intl` extension.
4. **Currency override.** Auto-derive decimals from ISO is the chosen path. Should we still expose an escape hatch for non-standard currencies (crypto, internal loyalty points)? Could be deferred.
5. **Time `value()` return type.** `DateTimeImmutable` on `1970-01-01` is template-friendly, but consumers iterating `entry->field('open_time')` may be surprised it's a full datetime. Alternative: a tiny `TimeValue` value object with `hours`/`minutes`/`seconds`/`format()` — more explicit, more code.
6. **State field cross-form coordination.** We deliberately chose hardcoded `country` per field instance over reading a sibling Country field. If editors need a dynamic Country→State dropdown pair, that becomes a UI concern (admin JS) rather than a field-type concern. Acceptable for v1?
7. **`prepareForStorage()` hook** — does the additive hook on `AbstractField` + `EntryRepository` feel right, or is there a preferred existing seam I missed?

---

## Verification (when we implement)

1. **Targeted tests:**
   ```bash
   php artisan test --filter=MoneyTest
   php artisan test --filter=CountryTest
   php artisan test --filter=StateProvinceTest
   php artisan test --filter=TimeTest
   php artisan test --filter=CurrenciesTest
   php artisan test --filter=CountriesTest
   php artisan test --filter=SubdivisionsTest
   ```

2. **Full suite for regressions:**
   ```bash
   composer test
   ```

3. **Seed and inspect:**
   ```bash
   php artisan db:seed --class=FieldTypeSeeder
   php artisan tinker
   >>> \App\Models\Field\Type::pluck('name')->all()
   ```

4. **End-to-end manual via admin UI:**
   - `composer run dev`
   - Add each new type to a test field layout.
   - Configure realistic settings (Money USD min=0 max=10000; Country whitelist US,CA,GB; State US; Time 09:00–17:00 step=15).
   - Create entry, save, verify storage:
     ```bash
     php artisan tinker
     >>> \App\Models\FieldValue::latest()->first()
     ```
     Money's `value_integer` should be minor units (e.g. `4250` for `$42.50`).
   - Round-trip the edit form; confirm edge-case rejections (out-of-range Money, off-list country, malformed time).

5. **Style:**
   ```bash
   vendor/bin/pint --preset psr12 --dirty
   ```
