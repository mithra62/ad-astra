# New Field Types: Money, Country, State/Province, Time

> **Status:** Design proposal — Rev 3, for review. No code changes have been made.
> **Last updated:** 2026-05-19

## Context

The CMS's field system already covers the basics (text, number, date, select, relationship, users, media, structured rows), but everyday content models keep reaching for primitives the system doesn't have: prices, locations, and times-of-day. This batch adds four production-ready field types so editors stop having to fake them with plain text fields.

### Scope

Original request named six field types: **Money, Country, State/Province, Address, Time, Users**. After review:

- **Users** — already implemented at [`app/Field/Types/Users.php`](../app/Field/Types/Users.php) with role filter, selection limit, three display modes (dropdown / checkboxes / tokens), and safe-column hydration. Dropped from scope.
- **Address** — deferred. The existing `StructuredRows` base is a multi-row table; a clean Address implementation should extend a single-row `StructuredData` abstract that doesn't yet exist. Better to revisit after this batch lands.

**Final scope: 4 field types** (Money, Country, State/Province, Time) plus the supporting infrastructure described below.

### Resolved design decisions

| Concern | Decision |
|---|---|
| **Money storage** | Integer minor units in `value_integer`. Currency configured per-field instance. Decimals auto-derived from ISO 4217. |
| **Money decimal parsing** | String-based, no float math. Validation rejects too-many-fractional-digits with a friendly error message. No implicit rounding. |
| **Country storage** | Single-select. ISO 3166-1 alpha-2 codes in `value_text`. Optional `allowed_countries` whitelist. |
| **State/Province storage** | `country` setting hardcoded per field instance. ISO 3166-2 subdivision codes (e.g. `US-CA`) in `value_text`. |
| **Time storage** | 24-hour canonical `HH:MM` or `HH:MM:SS` in `value_text`. Invalid input rejected by request validation, returning a friendly form error. |
| **Time return type** | New `TimeValue` immutable value object — explicit hours/minutes/seconds. Not `DateTimeImmutable` anchored to an arbitrary date. |
| **Currency dataset** | Full ISO 4217 (~180 currencies). |
| **Country dataset** | Full ISO 3166-1 (249 countries). |
| **Subdivision dataset** | Practical first-cut subset (17 countries); free-text fallback for countries without data. |
| **Money formatting** | Simple `symbol + amount` concatenation. No `intl` dependency. Locale-aware formatting deferred. |
| **Crypto / custom currency** | No v1 escape hatch. Currency must be a valid ISO 4217 code. |
| **Validation philosophy** | **Validation is the gate. Normalization is downstream.** Bad input is rejected at the HTTP boundary via Laravel rules — never via exceptions from model code. `prepareForStorage()` is a trusted normalizer that assumes input has passed validation. |

---

## Foundational addition: validation & normalization pipeline

Money and Time need to convert a wire-format value (e.g. `"42.50"`, `"9:30"`) into a different storage shape (integer minor units, canonical `HH:MM:SS`). That conversion has to apply **everywhere** field values flow into the database **or** into a query — not just the entry write path. And bad input has to produce friendly form errors, not 500s.

### Validation is the gate; normalization is downstream

**This is the design's load-bearing principle.** The codebase is already wired for it.

[`FormRequest::schemaFieldRules()`](../app/Http/Requests/FormRequest.php) (L26) builds a per-field rules array from the layout, calling each field type's `getRules()`:

```php
$rules[$key] = array_merge($fieldRules, $field->typeInstance()->getRules());
```

It's consumed by every HTTP write path for every Fieldable model:

- Entries — [`StoreEntryRequest`](../app/Http/Requests/Entry/StoreEntryRequest.php) L60, [`EditEntryRequest`](../app/Http/Requests/Entry/EditEntryRequest.php) L61
- Users — [`StoreUserRequest`](../app/Http/Requests/User/StoreUserRequest.php) L35, [`EditUserRequest`](../app/Http/Requests/User/EditUserRequest.php) L38
- Categories — [`StoreCategoryRequest`](../app/Http/Requests/Category/StoreCategoryRequest.php) L42, [`EditCategoryRequest`](../app/Http/Requests/Category/EditCategoryRequest.php) L48
- Media — [`EditMediaRequest`](../app/Http/Requests/Media/EditMediaRequest.php) L31

So the friendly-error pipeline already exists. Money and Time just need to declare proper rules — including custom Rule objects for what Laravel built-ins can't express.

### Call sites for normalization

| Site | File | Covered models | What's needed |
|---|---|---|---|
| Generic fieldable repo writes | [`AbstractFieldableRepository::applyFieldValues()`](../app/Repositories/AbstractFieldableRepository.php) L42 | **Category, Media, and every future Fieldable repo that extends this base** | Call `prepareForStorage()` before `upsertFieldValue` |
| Entry writes (relational-aware) | [`EntryRepository::applyFieldValues()`](../app/Repositories/EntryRepository.php) ~L183 | Entry only (does not extend the abstract — handles relational fields) | Call `prepareForStorage()` first |
| Trait-based direct-set | [`PersistsFieldValues::setField()` & `setFields()`](../app/Traits/Field/PersistsFieldValues.php) | UserService, CategoryService, and any future service that uses the trait | Call `prepareForStorage()` first |
| Field queries | [`EntryQueryBuilder::whereField()`](../app/Builders/EntryQueryBuilder.php) ~L114 | Entry only (no other query builders exist yet) | Call `prepareForQuery()` first |

### Why this is model-agnostic by design

The `Fieldable` paradigm is already structurally model-agnostic:

- Three models use the trait today — [`Entry`](../app/Models/Entry.php), [`Category`](../app/Models/Category.php), [`Media`](../app/Models/Media.php). More are planned (Products, Events, etc.).
- [`AbstractFieldableRepository`](../app/Repositories/AbstractFieldableRepository.php) is the **single chokepoint** for repository-based field-value writes. CategoryRepository and MediaRepository both extend it. Any future fieldable repo (`ProductRepository`, `EventRepository`, etc.) should extend it too. **One hook call in the abstract base normalizes for all of them.**
- The Entry path is special because it handles relational fields in addition to scalar fields. We hook the Entry-specific `applyFieldValues` separately. (Folding relational handling into the abstract is captured as follow-up.)
- `PersistsFieldValues` is the trait equivalent for services that set field values without going through a repository. Hooking it once covers every consumer.

### What a future Fieldable model gets for free

When the next fieldable model lands (e.g. `Product`):

1. Add `use Fieldable;` on the model — existing pattern.
2. If its repository extends `AbstractFieldableRepository`, **field-type normalization is already wired**: Money/Time/etc. store correctly with no extra code.
3. If its service uses `PersistsFieldValues`, **the same normalization applies** to direct `setField` calls.
4. If its FormRequest extends `App\Http\Requests\FormRequest` and calls `schemaFieldRules()`, **validation is already wired**: bad input produces friendly 422 form errors automatically.
5. If it needs field-aware querying, a new query builder must call `$instance->prepareForQuery($value)` before the WHERE — one line. The `prepareForQuery()` hook lives on `AbstractField`, already available — only the call site is per-builder.

### Two hooks on `AbstractField`

```php
/**
 * Convert a validated wire-format value into the form written to the storage
 * column. Assumes the FormRequest pipeline (or another caller) has already
 * validated input via this field type's getRules().
 *
 * For programmer-error safety (a non-HTTP caller bypassing validation),
 * implementations MAY throw InvalidArgumentException for structurally-impossible
 * input — but this should be unreachable via the normal HTTP write path.
 *
 * Default: identity.
 */
public function prepareForStorage(mixed $value): mixed
{
    return $value;
}

/**
 * Convert a wire-format value for use in a WHERE clause against the storage
 * column. Default delegates to prepareForStorage(). Tolerant of malformed
 * input — bad query input should produce zero results, not exceptions
 * (queries from code don't pass through request validation).
 */
public function prepareForQuery(mixed $value): mixed
{
    try {
        return $this->prepareForStorage($value);
    } catch (\InvalidArgumentException) {
        return $value;
    }
}
```

Why two hooks rather than one: storage and query share the conversion 99% of the time, but the semantics differ on error handling. Storage assumes validated input; query is best-effort. The catch in the default `prepareForQuery` keeps "trusted normalizer" semantics for storage while letting queries fall back gracefully.

### Validation contract for the new field types

Each field type declares its rules in `getRules()` — including custom Rule objects for things Laravel built-ins can't express.

**Money:**

```php
public function getRules(): array
{
    $currency = $this->getSetting('currency', 'USD');
    return [
        'nullable',
        'string',
        new MoneyDecimalFormatRule($currency),       // currency-aware precision
        new MoneyRangeRule(
            min: $this->getSetting('min'),
            max: $this->getSetting('max'),
            currency: $currency,
        ),
    ];
}
```

**Time:**

```php
public function getRules(): array
{
    return [
        'nullable',
        'string',
        new TimeFormatRule(
            includeSeconds: (bool) $this->getSetting('include_seconds', false),
            minTime: $this->getSetting('min_time'),
            maxTime: $this->getSetting('max_time'),
        ),
    ];
}
```

Each Rule's `message()` returns a friendly error suitable for showing under the form field. The user sees:

- *"The price field may have at most 2 decimal places for USD."*
- *"The open time must be a valid time."*
- *"The country is not an allowed country."*

— not a 500.

### Why we don't need to wire `AbstractField::validate()` into the pipeline

Originally I planned to flag "field-level `validate()` is not auto-run" as a follow-up. With this design, that's no longer load-bearing for the new field types:

- **HTTP path:** `getRules()` is already wired through `FormRequest::schemaFieldRules()`. Friendly errors, no follow-up needed.
- **Non-HTTP path** (services, queue jobs, importers): documented contract — caller is responsible for validation. They can compose `Validator::make($data, $field->typeInstance()->getRules())` if they want strict checks, OR they can rely on `prepareForStorage` throwing `InvalidArgumentException` for clearly-bad input (programmer-error surfacing).

`AbstractField::validate()` exists today and several legacy types still use it (Users, Select, StructuredRows). We leave those untouched — this batch's new types don't override `validate()` at all. Their validation lives in `getRules()` via Rule objects. Migrating legacy types to the same pattern is a separate refactor (still on the follow-up list).

### Note on settings-form option shape

The admin's `select` macro at [`resources/views/admin/_inc/_form-fields.twig:126`](../resources/views/admin/_inc/_form-fields.twig) reads `option.value` and `option.label`. The ISO support classes expose semantically-named `code` and `name` keys — those names stay (the classes are general-purpose). Each field type's `settingsFormOptions()` is responsible for mapping `code/name` → `value/label`:

```php
public function settingsFormOptions(): array
{
    return [
        'currency' => array_map(
            fn($c) => ['value' => $c['code'], 'label' => "{$c['code']} — {$c['name']}"],
            Currencies::all()
        ),
    ];
}
```

### Query-builder generalization

`EntryQueryBuilder::whereField()` is currently the only model-scoped field query API. A natural follow-up is extracting a `FieldableQueryBuilder` base (or trait) that any model-specific builder can compose — but that's a separate refactor. For this batch:

- We add `prepareForQuery()` on `AbstractField` so it's available to any builder.
- We wire it into `EntryQueryBuilder::whereField()` (the existing site).
- Documented contract: any future model-scoped query builder that filters by field MUST call `prepareForQuery()` before the WHERE.

---

## ISO data: shared support classes

New namespace **`App\Support\Iso`** — static-data classes. Hardcoded PHP arrays, no DB.

### `app/Support/Iso/Currencies.php`

**Full ISO 4217 list** (~180 currencies). Each entry: `code`, `name`, `symbol`, `decimals`.

```php
Currencies::all(): array
    // [['code'=>'USD','name'=>'US Dollar','symbol'=>'$','decimals'=>2], ...]

Currencies::exists(string $code): bool
Currencies::decimals(string $code): int     // 2 for USD, 0 for JPY, 3 for BHD
Currencies::symbol(string $code): string
Currencies::name(string $code): string
```

### `app/Support/Iso/Countries.php`

**Full ISO 3166-1 alpha-2 list** (249 entries). Each: `code`, `name`.

```php
Countries::all(): array               // [['code'=>'US','name'=>'United States'], ...]
Countries::exists(string $code): bool
Countries::name(string $code): string
```

### `app/Support/Iso/Subdivisions.php`

ISO 3166-2 subdivisions for a **practical first-cut subset**: US, CA, MX, GB, IE, AU, NZ, DE, FR, ES, IT, NL, BE, BR, IN, JP, CN. Countries outside the subset return an empty list; the State field renders a free-text input as fallback. Adding more countries later is data-only — no code changes.

```php
Subdivisions::forCountry(string $countryCode): array
    // [['code'=>'US-CA','name'=>'California'], ...]

Subdivisions::exists(string $countryCode, string $subdivisionCode): bool
Subdivisions::hasData(string $countryCode): bool      // drives fallback decision
```

> **Note:** Subdivision codes follow ISO 3166-2 (e.g. `US-CA`, `CA-ON`). Storing the full `XX-YY` form keeps values unambiguous across countries.

> **Formatting:** `MoneyValue::formatted()` uses simple `symbol + amount` concatenation. No `intl` dependency.

---

## Field type 1: Money

- **File:** `app/Field/Types/Money.php` (new)
- **Storage:** `value_integer` — minor units, always. No `HasDecimalStorage` trait, no float storage.
- **Handle:** `money` · **Name:** `Money`

### Settings

| Key | Type | Default | Purpose |
|---|---|---|---|
| `currency` | select | `USD` | ISO 4217 code. Options mapped to `value`/`label` from `Currencies::all()`. Required. |
| `min` | number | `null` | Minimum allowed in major units (e.g. `0.00`). Optional. |
| `max` | number | `null` | Maximum allowed in major units. Optional. |
| `default` | number | `null` | Pre-filled value in major units. Optional. |

### Behavior

- `getRules()` — returns Laravel rules including custom Rule objects:
  ```php
  return [
      'nullable',
      'string',
      new MoneyDecimalFormatRule($this->getSetting('currency', 'USD')),
      new MoneyRangeRule(
          min: $this->getSetting('min'),
          max: $this->getSetting('max'),
          currency: $this->getSetting('currency', 'USD'),
      ),
  ];
  ```
  Compiled into request rules via `FormRequest::schemaFieldRules()`. User sees friendly form errors for bad input — never a 500.

- `MoneyDecimalFormatRule` — new class at `app/Rules/Field/MoneyDecimalFormatRule.php`. Validates:
  - Input matches `^-?\d+(\.\d+)?$`.
  - Fractional-part length ≤ `Currencies::decimals($currency)`. No implicit rounding.
  - Friendly messages: *"The :attribute must be a valid decimal."* / *"The :attribute may have at most {N} decimal places for {CURRENCY}."*

- `MoneyRangeRule` — new class at `app/Rules/Field/MoneyRangeRule.php`. Enforces min/max in **major units** so the setting UI stays human-readable. Uses string comparison (not float) for safety. Allows negative amounts only when `min < 0`.

- `prepareForStorage($value)` — **trusted normalizer**, assumes validation has passed. Converts the validated decimal-string into integer minor units **without float math**:
  1. `null`, `''` → `null`.
  2. Match against `^-?\d+(\.\d+)?$`. (Should always match because validation ran; if not, throws `InvalidArgumentException` to surface programmer error.)
  3. Split on `.`. Right-pad fractional part with zeros to exactly `Currencies::decimals($currency)` digits.
  4. Concatenate (sign +) integer part with padded fractional part. Strip decimal point. Cast to `int`.
  5. Examples: `"42.50"` (USD) → `4250`. `"100"` (JPY) → `100`. `"1.234"` (BHD) → `1234`.

- `prepareForQuery($value)` — inherits default: tries `prepareForStorage`, falls back to passing the raw value through on conversion failure (so a typo in code-level `whereField` produces zero results, not an exception).

- `cast($value)` — return the stored value as `int`. Storage column is integer; this guarantees the type for downstream consumers.

- `value($raw)` — return a `MoneyValue` immutable value object (new class `app/Support/Iso/MoneyValue.php`):

  ```php
  $money->minor_units;          // 4250 (int)
  $money->currency;             // 'USD'
  $money->decimals;             // 2
  $money->amount();             // '42.50' (string — decimal-safe, not float)
  $money->amountAsFloat();      // 42.50 (float — explicit opt-in)
  $money->formatted();          // '$42.50' (simple symbol + amount; no intl)
  ```

  Templates that need numeric comparison can call `amountAsFloat()`; everything else (display, JSON serialization) should prefer `amount()` to avoid float drift.

- `render($params)` — twig template at `resources/views/_fields/money.twig` with a currency symbol prefix and `<input type="number" step="...">` (step derived from `10 ** -decimals`, e.g. `0.01` for USD, `1` for JPY).

---

## Field type 2: Country

- **File:** `app/Field/Types/Country.php` (new)
- **Storage:** `value_text`
- **Handle:** `country` · **Name:** `Country`

### Settings

| Key | Type | Default | Purpose |
|---|---|---|---|
| `allowed_countries` | select_multiple | `[]` | Optional ISO-2 whitelist. Empty = all. Options mapped to `value`/`label`. |
| `default` | select | `null` | Pre-selected country code. |
| `placeholder` | text | `— Select —` | Empty-option label. |

### Behavior

- `getRules()` — returns:
  ```php
  return [
      'nullable',
      'string',
      new CountryCodeRule((array) $this->getSetting('allowed_countries', [])),
  ];
  ```

- `CountryCodeRule` — new class at `app/Rules/Field/CountryCodeRule.php`. Validates:
  - Value exists in `Countries::all()`.
  - If `$allowed` is non-empty, value is in that list too.
  - Messages: *"The :attribute must be a valid country code."* / *"The :attribute is not an allowed country."*

- `prepareForStorage($value)` — trusted normalizer; returns the string code uppercased. `null`/`''` → `null`.
- `cast($value)` — returns the stored string code as-is.
- `value($raw)` — returns `['code' => 'US', 'name' => 'United States']` for template convenience.
- `render($params)` — twig template at `resources/views/_fields/country.twig`. Native `<select>` populated from either the whitelist (if set) or `Countries::all()`.

---

## Field type 3: State/Province

- **File:** `app/Field/Types/StateProvince.php` (new)
- **Storage:** `value_text`
- **Handle:** `state_province` · **Name:** `State/Province`

### Settings

| Key | Type | Default | Purpose |
|---|---|---|---|
| `country` | select | `US` | Required. ISO-2 code whose subdivisions are shown. Options mapped to `value`/`label`. |
| `default` | text | `null` | Pre-selected subdivision code (e.g. `US-CA`). |
| `placeholder` | text | `— Select —` | Empty-option label. |
| `allow_freetext_fallback` | toggle | `true` | If the configured country has no subdivision data, render a text input instead of failing. |

### Behavior

- `getRules()` — returns:
  ```php
  return [
      'nullable',
      'string',
      new SubdivisionCodeRule(
          country: (string) $this->getSetting('country', 'US'),
          allowFreetextFallback: (bool) $this->getSetting('allow_freetext_fallback', true),
      ),
  ];
  ```

- `SubdivisionCodeRule` — new class at `app/Rules/Field/SubdivisionCodeRule.php`. Validates:
  - If `Subdivisions::hasData($country)` is true: value must satisfy `Subdivisions::exists($country, $value)`.
  - If no data and `$allowFreetextFallback` is true: accept any non-empty string up to 100 chars.
  - If no data and `$allowFreetextFallback` is false: reject.
  - Messages: *"The :attribute must be a valid {country} subdivision."* / *"The :attribute may not exceed 100 characters."*

- `prepareForStorage($value)` — trusted normalizer; returns the string code as-is. `null`/`''` → `null`.
- `cast($value)` — returns the stored string code as-is.
- `value($raw)` — returns `['code' => 'US-CA', 'name' => 'California', 'country' => 'US']`. If freetext fallback was used, `name === code`.
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

- `getRules()` — returns Laravel rules including a custom Rule:
  ```php
  return [
      'nullable',
      'string',
      new TimeFormatRule(
          includeSeconds: (bool) $this->getSetting('include_seconds', false),
          minTime: $this->getSetting('min_time'),
          maxTime: $this->getSetting('max_time'),
      ),
  ];
  ```
  Compiled into request rules via `FormRequest::schemaFieldRules()`. User sees friendly errors for bad input.

- `TimeFormatRule` — new class at `app/Rules/Field/TimeFormatRule.php`. Validates:
  - Input matches `^(?:[01]?\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$` (accepts `H:MM`, `HH:MM`, `H:MM:SS`, `HH:MM:SS`).
  - If `min_time`/`max_time` are set, value falls within the range (string compare on canonical form).
  - Friendly messages: *"The :attribute must be a valid time."*, *"The :attribute must be at or after {min}."*, *"The :attribute must be at or before {max}."*

- `prepareForStorage($value)` — **trusted normalizer**, assumes validation has passed. `null`/`''` → `null`. Otherwise canonicalize:
  1. Zero-pad the hour to 2 digits.
  2. If `include_seconds` is true and seconds are missing, append `:00`. If `include_seconds` is false and seconds are present, drop them (truncation only, not rounding).
  3. If somehow given malformed input (programmer error bypassing validation), throws `InvalidArgumentException` to surface the bug.

- `prepareForQuery($value)` — inherits default: tries `prepareForStorage`, falls back to raw value on failure (best-effort for code-level queries).

- `cast($value)` — return the canonical string.

- `value($raw)` — return a new `TimeValue` immutable value object (`app/Support/Iso/TimeValue.php`):

  ```php
  $time->hours;        // 9
  $time->minutes;      // 30
  $time->seconds;      // 0
  $time->canonical();  // '09:30' or '09:30:00'
  $time->format(string $phpFormat);  // e.g. 'g:i A' → '9:30 AM'
  $time->toMinutes();  // total minutes since midnight (570)
  ```

  A small dedicated value object reads more clearly than handing templates a `DateTimeImmutable` anchored to an arbitrary date — and avoids the bug-class where someone compares two such DateTimes and the date component matters.

- `render($params)` — twig template at `resources/views/_fields/time.twig`, native `<input type="time" step="...">` with `step = step_minutes * 60`. When `default === 'now'`, the template renders the current time at form-render time.

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

### Field type tests — `tests/Unit/Field/Types/`

Mirror [`UsersTest.php`](../tests/Unit/Field/Types/UsersTest.php) and [`SelectTest.php`](../tests/Unit/Field/Types/SelectTest.php).

- **`MoneyTest.php`** — `storageColumn` returns `value_integer`; `getRules()` returns expected Rule instances using the configured currency; `prepareForStorage` converts `"42.50"`→`4250` (USD), `"100"`→`100` (JPY), `"1.234"`→`1234` (BHD); explicit float-precision test (`"0.1"`+`"0.2"` proving no naive `*100` float math); `null`/`""`→`null`; programmer-error path: `prepareForStorage("garbage")` throws `InvalidArgumentException` (validation bypass surfacing); `cast` returns int; `value()` returns `MoneyValue` with correct string `amount()` and simple `formatted()`; `prepareForQuery` does the same conversion on valid input AND falls back to passthrough on invalid input; `settingsFormOptions['currency']` returns `value`/`label` keys.
- **`CountryTest.php`** — `value_text`; `getRules()` includes `CountryCodeRule` with the configured allowed-list; `prepareForStorage` uppercases the code; `value()` returns `['code', 'name']`; `settingsFormOptions` keys are `value`/`label`. Validation-rejection cases live in the rule's own test.
- **`StateProvinceTest.php`** — `value_text`; `getRules()` includes `SubdivisionCodeRule` configured from settings; `prepareForStorage` returns the code as-is; `value()` shape correct for data-backed (`['code','name','country']`) and freetext (`name === code`) cases. Validation-rejection cases live in the rule's own test.
- **`TimeTest.php`** — `value_text`; `getRules()` includes `TimeFormatRule` with include_seconds + min/max from settings; `prepareForStorage` canonicalizes `"9:30"`→`"09:30"`; `include_seconds=true` adds `:00`; `include_seconds=false` drops seconds; programmer-error path: malformed input throws `InvalidArgumentException`; `value()` returns `TimeValue` with correct `hours`/`minutes`/`format()`/`toMinutes()`. Validation-rejection cases live in the rule's own test.

### Rule tests — `tests/Unit/Rules/Field/`

These hold the rejection cases. They're regular validation rules with friendly messages.

- **`MoneyDecimalFormatRuleTest.php`** — accepts `"42"`, `"42.50"`, `"-3.14"`, `null`; rejects `"abc"`, `"4.2.0"`, `"42.555"` (USD), arrays; per-currency precision (`"42.555"` accepted for BHD, rejected for USD); friendly message includes the currency code.
- **`MoneyRangeRuleTest.php`** — accepts values inside min/max; rejects below min, above max; comparison uses string-decimal arithmetic, not float; default `min=0` rejects negatives.
- **`TimeFormatRuleTest.php`** — accepts `"09:30"`, `"9:30"`, `"23:59:59"`, `null`; rejects `"25:00"`, `"09:60"`, `"9:30 AM"`, `"bad"`; min/max enforcement.
- **`CountryCodeRuleTest.php`** — accepts known ISO-2 codes; rejects unknowns and lowercase; whitelist filtering when configured.
- **`SubdivisionCodeRuleTest.php`** — for data-backed country: rejects unknown subdivisions; for no-data country: accepts freetext when toggle on, rejects when toggle off; freetext length limit enforced.

### Feature test — request validation end-to-end

- **`tests/Feature/Field/RequestValidationTest.php`** (new) — proves the full validation pipeline works for every new field type on at least one real fieldable model:
  - POST to `StoreEntryRequest` with a Money field set to `"42.555"` (USD) returns a 422 with the friendly error attached to `fields.price`.
  - POST to `StoreEntryRequest` with a Time field set to `"25:00"` returns a 422 with the friendly error.
  - POST with valid Money / Time succeeds and the persisted value is correctly normalized (e.g. `value_integer=4250`).
  - One smoke test against a different fieldable model (e.g. `EditCategoryRequest` with a Money field) confirms the FormRequest pipeline works model-agnostically.

### Pipeline tests

- **`tests/Unit/Field/PrepareForStoragePipelineTest.php`** (new) — covers all four call sites:
  - **Generic fieldable repo path:** Calling `CategoryRepository::create` (which extends `AbstractFieldableRepository`) with a Money field results in `value_integer = 4250` for input `"42.50"`. **Model-agnostic regression guard** — if it passes for Category, it passes for Media and every future repo that extends the abstract.
  - **Media write path:** Same assertion against `MediaRepository` to prove a second consumer of the abstract works (catches accidental subclass overrides).
  - **Entry write path:** Calling `EntryRepository::applyFieldValues` with a Money field stores `value_integer = 4250` for `"42.50"`. Verifies the parallel Entry implementation also normalizes (doesn't extend the abstract).
  - **Trait-based direct-set:** Calling `PersistsFieldValues::setField` with a Money field on a User stores `value_integer = 4250` for `"42.50"`.
  - **Query path:** `EntryQueryBuilder::whereField('price', 42.50)` produces the same generated SQL as `whereField('price', 4250)` against `value_integer`.
- **Existing trait tests** — extend [`tests/Unit/Traits/PersistsFieldValuesTest.php`](../tests/Unit/Traits/PersistsFieldValuesTest.php) with a case verifying that a no-op-default field type round-trips identically (no regression for non-Money types).

### Support-class tests — `tests/Unit/Support/Iso/`

- **`CurrenciesTest.php`** — `decimals('USD')===2`, `decimals('JPY')===0`, `decimals('BHD')===3`, `exists()`, every entry has all four keys, full list size sanity (~180).
- **`CountriesTest.php`** — known codes resolve, unknowns reject, every entry has `code`+`name`, full list size sanity (~249).
- **`SubdivisionsTest.php`** — `forCountry('US')` includes `US-CA`; `hasData('US')===true`; `hasData('XX')===false`; spot-check a non-US country (e.g. CA includes `CA-ON`).
- **`MoneyValueTest.php`** — `amount()` returns string-form decimal; `formatted()` concatenates symbol + amount without locale lookup; constructor rejects negative `decimals`.
- **`TimeValueTest.php`** — constructor rejects out-of-range hours/minutes/seconds; `canonical()` returns proper form; `format('g:i A')` returns correct AM/PM.

Existing tests should remain green — the framework changes are additive (no-op defaults on the hooks).

---

## Files

### To create

- `app/Field/Types/Money.php`
- `app/Field/Types/Country.php`
- `app/Field/Types/StateProvince.php`
- `app/Field/Types/Time.php`
- `app/Rules/Field/MoneyDecimalFormatRule.php`
- `app/Rules/Field/MoneyRangeRule.php`
- `app/Rules/Field/TimeFormatRule.php`
- `app/Rules/Field/CountryCodeRule.php`
- `app/Rules/Field/SubdivisionCodeRule.php`
- `app/Support/Iso/Currencies.php`
- `app/Support/Iso/Countries.php`
- `app/Support/Iso/Subdivisions.php`
- `app/Support/Iso/MoneyValue.php`
- `app/Support/Iso/TimeValue.php`
- `resources/views/_fields/money.twig`
- `resources/views/_fields/country.twig`
- `resources/views/_fields/state_province.twig`
- `resources/views/_fields/time.twig`
- `tests/Unit/Field/Types/MoneyTest.php`
- `tests/Unit/Field/Types/CountryTest.php`
- `tests/Unit/Field/Types/StateProvinceTest.php`
- `tests/Unit/Field/Types/TimeTest.php`
- `tests/Unit/Field/PrepareForStoragePipelineTest.php`
- `tests/Unit/Rules/Field/MoneyDecimalFormatRuleTest.php`
- `tests/Unit/Rules/Field/MoneyRangeRuleTest.php`
- `tests/Unit/Rules/Field/TimeFormatRuleTest.php`
- `tests/Unit/Rules/Field/CountryCodeRuleTest.php`
- `tests/Unit/Rules/Field/SubdivisionCodeRuleTest.php`
- `tests/Feature/Field/RequestValidationTest.php`
- `tests/Unit/Support/Iso/CurrenciesTest.php`
- `tests/Unit/Support/Iso/CountriesTest.php`
- `tests/Unit/Support/Iso/SubdivisionsTest.php`
- `tests/Unit/Support/Iso/MoneyValueTest.php`
- `tests/Unit/Support/Iso/TimeValueTest.php`

### To modify

- [`app/Field/AbstractField.php`](../app/Field/AbstractField.php) — add `prepareForStorage()` and `prepareForQuery()` hooks (no-op defaults; `prepareForQuery` delegates to `prepareForStorage` with a try/catch fallback)
- [`app/Repositories/AbstractFieldableRepository.php`](../app/Repositories/AbstractFieldableRepository.php) — call `$instance->prepareForStorage($value)` in `applyFieldValues()` (L42) before `upsertFieldValue`. **This single change covers Category, Media, and every future repo that extends this base.**
- [`app/Repositories/EntryRepository.php`](../app/Repositories/EntryRepository.php) — same hook call in its own `applyFieldValues` (~L183). Entry has a parallel implementation because of relational fields.
- [`app/Traits/Field/PersistsFieldValues.php`](../app/Traits/Field/PersistsFieldValues.php) — call `prepareForStorage` in both `setField()` (L11) and `setFields()` (L29). Covers services that set field values directly on any Fieldable model.
- [`app/Builders/EntryQueryBuilder.php`](../app/Builders/EntryQueryBuilder.php) — call `$instance->prepareForQuery($value)` in `whereField()` (~L114) before the WHERE. (Currently the only model-scoped field query API.)
- [`database/seeders/FieldTypeSeeder.php`](../database/seeders/FieldTypeSeeder.php) — register four new types
- [`tests/Unit/Traits/PersistsFieldValuesTest.php`](../tests/Unit/Traits/PersistsFieldValuesTest.php) — add a no-regression case proving non-Money field types are unaffected

---

## Verification (when we implement)

1. **Targeted tests:**
   ```bash
   php artisan test --filter=MoneyTest
   php artisan test --filter=CountryTest
   php artisan test --filter=StateProvinceTest
   php artisan test --filter=TimeTest
   php artisan test --filter=Field/RequestValidation
   php artisan test --filter=PrepareForStoragePipelineTest
   php artisan test --filter=Rules/Field
   php artisan test --filter=Support/Iso
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
   - Round-trip the edit form; confirm edge-case rejections produce friendly form errors (out-of-range Money, off-list country, malformed time, too-many-decimal-digits Money).
   - Smoke test the same Money field on a Category or Media item to confirm model-agnostic normalization.

5. **Style:**
   ```bash
   vendor/bin/pint --preset psr12 --dirty
   ```

---

## Follow-up work (out of scope for this batch)

Captured here so it isn't lost:

- **Migrate legacy field types' `validate()` logic to Rule objects.** Users, Select, MultiSelect, RadioGroup, StructuredRows currently use the `AbstractField::validate()` hook. None of those rejection messages reach the user today because `validate()` isn't wired into the persistence pipeline. Moving their logic into Rule objects exposed via `getRules()` would give them the same friendly-error pipeline we're building for Money/Time. Pure refactor; no behavior change for the happy path.
- **Wire `AbstractField::validate()` into the persistence pipeline (or deprecate it).** Once legacy types are migrated to Rule objects, `validate()` becomes redundant and can be deprecated. Alternatively, call it from `AbstractFieldableRepository::applyFieldValues`, `EntryRepository::applyFieldValues`, and `PersistsFieldValues::setField(s)` to give non-HTTP callers automatic guards. Decision deferred until the legacy migration is scoped.
- **Fold relational-field handling into `AbstractFieldableRepository`.** `EntryRepository::applyFieldValues` exists as a parallel implementation only because it has to dispatch relational fields to `syncRelationshipField`. If the abstract base learned to handle relational fields (or delegated to a model-supplied hook), `EntryRepository` could extend the abstract and the duplicate hook call would collapse to one.
- **Extract a `FieldableQueryBuilder` base / trait.** When a second Fieldable model needs field-aware querying, lift the `whereField()` logic out of `EntryQueryBuilder` into a base any model-scoped builder can compose. The `prepareForQuery()` hook is already model-agnostic — only the wiring needs lifting.
- **Address field type.** Needs a single-row `StructuredData` abstract base extracted from `StructuredRows`. Will use the same `prepareForStorage()` infrastructure landing in this batch.
- **Locale-aware Money formatting.** Add an optional `MoneyValue::formattedLocale($locale)` that uses `NumberFormatter` when the `intl` extension is available; falls back to `formatted()` otherwise.
- **Subdivision data expansion.** Adding more countries to `Subdivisions` is purely a data-file edit — no code changes. Drive by demand.
- **Cross-field Country → State coordination.** A `country_field_handle` setting on State/Province that reads a sibling Country field. Belongs to the admin JS layer more than the field-type layer; revisit when the admin form has a clean event bus for inter-field dependencies.

---

## Revision log

- **Rev 3** — Moved validation from "self-defending normalization throws" to "request-attached validation rejects." Each new field type declares custom Laravel Rule objects (`MoneyDecimalFormatRule`, `MoneyRangeRule`, `TimeFormatRule`, `CountryCodeRule`, `SubdivisionCodeRule`) via `getRules()`. The existing `FormRequest::schemaFieldRules()` chokepoint already compiles these into per-field request rules, returning friendly 422 form errors for bad input on every fieldable HTTP write path (Entry, User, Category, Media). `prepareForStorage()` is now a *trusted normalizer* that assumes input has passed validation; it still throws `InvalidArgumentException` for programmer-error bypasses. `prepareForQuery()` default catches that exception and falls back to passthrough so code-level queries with typos produce zero results, not 500s. Added Rule unit tests and an HTTP-pipeline feature test.
- **Rev 2** — Replaced single `prepareForStorage()` proposal with full pipeline section covering all four call sites (`AbstractFieldableRepository`, `EntryRepository`, `PersistsFieldValues`, `EntryQueryBuilder`). Added `prepareForQuery()` hook. Made Money's decimal parsing string-based with no implicit rounding. Made Time fail-loud (throws instead of silent `null`). Switched Time `value()` from `DateTimeImmutable` to a dedicated `TimeValue` value object. Expanded Currencies and Countries to full ISO datasets. Documented that `AbstractField::validate()` is not currently wired into persistence — self-defending normalization is the contract. Noted admin macro option-shape (`value`/`label`) and that ISO classes' semantic `code`/`name` keys must be mapped by each field type's `settingsFormOptions()`. Added pipeline test, `MoneyValueTest`, `TimeValueTest`. Added "Follow-up work" section. Made the pipeline model-agnostic by hooking `AbstractFieldableRepository` so Category, Media, and any future fieldable repo that extends the abstract are automatically covered.
- **Rev 1** — Initial proposal.
