# EntryType Layer — Action Plan

Findings from a post-implementation review of the EntryType architecture. Items are ordered by severity: bugs first, then structural gaps, then enhancements.

---

## Item 1 — Fix nullable `$entry` crash in `validate()`

**Priority: High (bug)**

### What is broken

`AbstractEntryType::existingFieldValue(Entry $entry, ...)` has a non-nullable type hint, but
`validate(array $data, ?Entry $entry = null)` accepts null. Every concrete type that reads
existing field values inside `validate()` uses the pattern:

```php
$value = $data['fields']['field'] ?? $this->existingFieldValue($entry, 'field');
```

When called on create — where `$entry` is `null` — and the field is absent from the payload,
PHP evaluates the right-hand side and passes `null` to a non-nullable parameter. This throws a
`TypeError` before any validation error is returned.

**Affected files:**
- `app/EntryTypes/AbstractEntryType.php` — `existingFieldValue()` signature
- `app/EntryTypes/NewsArticleEntryType.php` — `source_url` / `source` lookup
- `app/EntryTypes/JobListingEntryType.php` — `application_url` / `application_email` lookup
- `app/EntryTypes/ProductEntryType.php` — `sku` lookup
- `app/EntryTypes/VideoEntryType.php` — `platform_id` / `video_url` lookup

### What changes

Change `existingFieldValue(Entry $entry, ...)` to `existingFieldValue(?Entry $entry, ...)` and
return `null` immediately when `$entry` is null. All call sites already treat a null return as
"no existing value," so no other code changes are needed.

### Repercussions

- `validate()` becomes safe to call in both create and update contexts without callers needing
  to guard the entry argument.
- No behaviour change on update paths (entry is always present there).
- Tests for `validate()` on create should be added for each affected type to confirm the null
  entry path returns errors correctly rather than crashing.

---

## Item 2 — Wire `validate()` into the service layer (single implementation point)

**Priority: High (dead feature)**

### What is broken

The `AbstractEntryType::validate()` docblock states explicitly that the repository does not call
it automatically. Currently nothing calls it. The publish-gate checks in `JobListingEntryType`,
`ProductEntryType`, `VideoEntryType`, and `NewsArticleEntryType` are completely inert in
production — a product can be published without a SKU, a job can be published with no contact
method, and so on.

### Design constraint

The `StoreEntryRequest` and `EditEntryRequest` form requests must continue to function
identically. They handle structural validation (field types, required-by-layout flags, status
enum membership, etc.) via Laravel's standard rule pipeline and must not be modified.

### What changes

The single implementation point is `EntryService`. Both `create()` and `update()` already hold
a reference to the registry and resolve the EntryType. Add a validation step in each method
immediately before delegating to the repository:

1. Resolve the `AbstractEntryType` instance.
2. Call `$type->validate($data, $entry ?? null)`.
3. If the returned errors array is non-empty, throw a `ValidationException` using
   `Validator::make([], [])->errors()` seeded with those errors — or use
   `throw ValidationException::withMessages($errors)`.

Because `ValidationException` is a standard Laravel exception, the framework's exception
handler converts it to a 422 response with an `errors` JSON body automatically, and Blade form
views pick it up through `$errors` without any controller changes.

**Affected files:**
- `app/Services/EntryService.php` — `create()` and `update()` methods
- No changes to `StoreEntryRequest`, `EditEntryRequest`, controllers, or actions

**Affected files (tests):**
- `tests/Unit/Services/` — unit tests for `EntryService::create()` and `EntryService::update()`
  should assert that validation errors from the EntryType surface as `ValidationException`
- Integration test (see Item 6) should confirm 422 responses end-to-end

### Repercussions

- All EntryType `validate()` implementations become active. Fields that were silently ignored
  (missing SKU on publish, missing application contact, etc.) will now return user-facing errors.
- Any existing test that calls `EntryService::create()` or `EntryService::update()` with
  a payload that would fail EntryType validation will need to be updated to either supply valid
  data or expect a `ValidationException`.
- The `afterCreate` / `afterUpdate` hooks are not affected — they run after the transaction and
  are never reached if `validate()` throws.
- Third-party or console code that calls `EntryService` directly (not via HTTP) will also hit
  this validation. That is generally desirable but should be communicated to any consumers.

---

## Item 3 — Move `ProductEntryType` pricing guards into `validate()`

**Priority: High (bug in disguise)**

### What is broken

`ProductEntryType::validateAndNormalisePricing()` is called from both `beforeCreate()` and
`beforeUpdate()`. It throws `InvalidArgumentException` for user-caused errors (negative price,
sale price >= price, sale price on a zero-priced item). Since the repository does not catch
`InvalidArgumentException`, these become unhandled exceptions and return 500 responses.

### What changes

Split the method's responsibilities:

- **`validate()`** — check for negative price, sale price >= price, sale price on a zero-priced
  item. Return field-keyed error messages. This runs before any writes, giving the user a 422.
- **`validateAndNormalisePricing()`** (renamed `normalisePricing()`) — only normalise data that
  is already known valid (e.g. type coercion). Remove the `throw` statements.

**Affected files:**
- `app/EntryTypes/ProductEntryType.php` — `validate()`, `beforeCreate()`, `beforeUpdate()`,
  `validateAndNormalisePricing()` (split into validate + normalise)
- `tests/Unit/EntryTypes/ProductEntryTypeTest.php` — move exception-expectation tests to
  validate()-based assertions; remove assertions that `beforeCreate`/`beforeUpdate` throw

### Repercussions

- Pricing errors surface as 422 validation responses instead of 500s. This is the correct
  behaviour and is a breaking fix — any caller currently catching `InvalidArgumentException`
  from the service for pricing errors must be updated to catch `ValidationException` instead.
- The `applyStockStatus()` method (sets `out-of-stock` status when stock hits zero) stays in
  `beforeUpdate()` — that is a silent data mutation, not a user-facing error, and belongs in
  the hook.

---

## Item 4 — Add `EntryService::recordMetric()` and a write path for entry metrics

**Priority: Medium (incomplete feature)**

### What is missing

`EntryMetric`, `Entry::metrics()`, `Entry::metricTotal()`, and the migration all exist. There
is no way to write a metric row through the application layer. The table is read-only from the
application's perspective.

### What changes

**`app/Services/EntryService.php`** — add `recordMetric(Entry $entry, string $metric, int $value, ?Carbon $date = null): EntryMetric`

The method upserts into `entry_metrics` on the `(entry_id, metric, recorded_date)` unique key:
if a row exists for today's date it increments `value`; if not it inserts. The `$date` parameter
defaults to today, allowing backdated imports.

**`app/Actions/Entry/RecordEntryMetric.php`** — thin action wrapping `EntryService::recordMetric()`,
following the same pattern as `CreateNewEntry` and `UpdateEntry`.

**`tests/Unit/Services/EntryServiceMetricTest.php`** — tests for insert, increment-on-upsert,
backdated write, and isolation between entries.

**No controller or route changes are required for 1.0** unless metrics are to be exposed via an
API endpoint. The action and service method are sufficient for internal use (scheduled jobs,
event listeners, etc.).

### Repercussions

- The upsert behaviour (increment on conflict) is an intentional design choice. If the
  requirement is "replace, not accumulate" the method signature needs a `$mode` parameter or
  two separate methods (`setMetric` / `incrementMetric`). This should be confirmed before
  implementing.
- A console command or event listener that calls `recordMetric()` should be considered at the
  same time. Writing a method no code calls is only marginally better than the current state.

---

## Item 5 — Register `GeneralEntryType` in the seeder

**Priority: Medium (class without a record)**

### What is missing

`GeneralEntryType` exists as a PHP class but has no corresponding `entry_types` row, no
`entry_group` using it, and no field group. It is unreachable through the registry.

### Decision required first

There are two valid approaches and they have different implications:

**Option A — General as an explicit group:** Create a "General" `EntryGroup` in
`ExtendedEntryGroupSeeder` backed by `GeneralEntryType`, with the standard content + SEO field
groups. Useful when the CMS needs a catch-all section for miscellaneous content.

**Option B — General as the fallback class:** In `EntryTypeRegistry::instantiate()`, check
whether `$record->class` is null or points to a missing class and fall back to
`GeneralEntryType` rather than throwing a `RuntimeException`. Useful when entry types are
created via the admin UI without a custom class being assigned.

These are not mutually exclusive. Option B is a safe default that prevents 500s when an entry
type record has no class.

### What changes (Option A)
- `database/seeders/ExtendedEntryGroupSeeder.php` — add `seedGeneralGroup()`
- No field group seeder changes needed (reuses content + SEO fields)

### What changes (Option B)
- `app/EntryTypes/EntryTypeRegistry.php` — `instantiate()` falls back to `GeneralEntryType`
  when `$class` is null or the class does not exist, rather than throwing

### Repercussions (Option B)
- `RuntimeException` for missing class is currently the safety net against misconfigured
  entry type records. Silencing it with a fallback hides configuration errors. Consider
  logging a warning rather than swallowing the problem entirely.
- Existing tests for the registry that assert `RuntimeException` on a bad class will need
  updating.

---

## Item 6 — Add integration tests for the full HTTP → hook chain

**Priority: Medium (test coverage gap)**

### What is missing

All EntryType tests are unit tests that instantiate types directly and call hook methods in
isolation. There are no tests confirming that:

- `validate()` errors return a 422 from `POST /entries` or `PUT /entries/{id}`
- `beforeCreate()` side effects (e.g. `reading_time`, `episode_number`) are actually persisted
  after a real HTTP request
- `beforeUpdate()` auto-expire logic on `JobListingEntryType` is triggered when the update
  goes through the full stack

### What changes

**`tests/Feature/Admin/EntryTypeHooksTest.php`** — feature tests using `actingAs()` with a
user that has create/edit entry permissions. Cover at minimum:

- Blog post create: assert `reading_time` field value is persisted
- Product create: assert publishing without SKU returns 422 with `fields.sku` error key
- Job listing update: assert entry with past `closing_date` is set to `expired` status after
  update
- Event update: assert `end_date` before `start_date` throws a meaningful error (currently a
  500 — see repercussion below)

### Repercussions

- `EventEntryType::validateDateRange()` currently throws `InvalidArgumentException` from
  `beforeUpdate()`. Like the `ProductEntryType` pricing issue, this becomes a 500. The feature
  test will expose this. The fix mirrors Item 3: move the date-range check into `validate()`.
  This is a fourth type that needs the same treatment and should be done alongside Item 3.
- Writing these tests will likely surface additional gaps. Budget time accordingly.

---

## Summary table

| # | Item | Priority | Files changed | Breaks existing tests |
|---|------|----------|---------------|-----------------------|
| 1 | Nullable `$entry` fix in `existingFieldValue` | High | `AbstractEntryType` + 4 EntryType classes | No |
| 2 | Wire `validate()` into `EntryService` | High | `EntryService` only | Possibly — any test using bad payloads |
| 3 | Move `ProductEntryType` pricing into `validate()` | High | `ProductEntryType` + its test | Yes — exception assertions flip to validation assertions |
| 4 | `EntryService::recordMetric()` write path | Medium | `EntryService`, new Action, new test | No |
| 5 | Register `GeneralEntryType` (Option A and/or B) | Medium | Seeder and/or Registry | Option B: registry test updates |
| 6 | Feature tests for full HTTP → hook chain | Medium | New test file | No (also surfaces `EventEntryType` 500 — fix alongside Item 3) |

**Recommended implementation order:** 1 → 3+6a (fix EventEntryType at the same time) → 2 → 4 → 5.
Item 2 depends on Item 1 being done first so that validate() is crash-safe before it is wired in.
