# Entry Type Todos — Architecture Review

> **Reviewed against live codebase on 2026-04-28.**
> Each layer from `ENTRY_TYPE_TODOS.md` is evaluated against the actual source in
> `app/`, `database/`, and `resources/`. Verdict: **YAY** = correct approach, do it.
> **NAY** = wrong approach or superseded. **DONE** = already implemented.

---

## ⚠️ Critical Finding: AbstractEntryType.php is Truncated

Before reviewing any layer, the most urgent issue in the codebase is that
`app/EntryTypes/AbstractEntryType.php` is physically truncated at 981 bytes. The file
ends mid-declaration — `public function ` — and is missing the `afterUpdate` method
signature, its body, and the class closing brace. PHP will fatal-error if this class
is ever loaded in a context that doesn't cache the class definition. The file needs
to be completed immediately:

```php
    public function afterUpdate(Entry $entry, array $data): void
    {
    }
}
```

This is not covered by any layer in the original TODO and must be resolved before
anything in Layer 6–8 can safely run.

---

## Layer 1 — Boolean FieldType

**Verdict: DONE ✅**

All three checklist items are complete and correct. `app/Field/Types/Boolean.php`
exists, extends `AbstractField`, returns `value_boolean` from `storageColumn()`, and
casts via `(bool)`. The Twig view at `resources/views/_fields/boolean.twig` uses the
double-input pattern (a hidden `0` input followed by the checkbox) which is actually
better than the spec's description — it ensures the value is always submitted even
when the checkbox is unchecked, which the original spec did not call out explicitly.
`FieldTypeSeeder` includes Boolean in the `$types` array.

Nothing further is needed here.

---

## Layer 2 — New StatusGroups

**Verdict: DONE ✅**

Both `job-status` and `product-status` are seeded in `StatusGroupSeeder.php` with
their full status sets. One minor observation: `job-status` has `expired` spelled
as `Expired` (handle `expired`) and `closed` as handle `closed`, which matches what
the TODO specifies. `product-status` has `out-of-stock`, `pre-order`, and
`discontinued` all present.

Nothing further is needed here.

---

## Layer 3 — New CategoryGroups

**Verdict: DONE ✅**

All five new groups — `cuisines`, `diet-types`, `event-types`, `employment-types`,
and `experience-levels` — are seeded in `CategoryGroupSeeder.php` using
`seedSimpleCategories()`, which is the correct lightweight pattern for controlled
vocabulary groups (no field layout, no field values). The order of the items differs
slightly from the TODO (e.g. cuisines are alphabetical rather than the TODO's
Italian-first ordering) but this is inconsequential.

Nothing further is needed here.

---

## Layer 4 — Domain FieldGroups

**Verdict: YAY — but not yet done ❌**

The approach is correct and well-suited to the architecture. Adding private seed
methods to `FieldGroupSeeder.php` following the existing `seedContentFields` /
`seedSeoFields` pattern keeps all field definitions in one place and makes them
discoverable. The `hidden: true` flag on computed fields (`reading_time`,
`total_time`) is the right call — they should be persisted as field values but not
appear as editable inputs.

**What to do:** Add one private method per entry in the table from the TODO to
`FieldGroupSeeder.php`. Each method needs to resolve the appropriate `FieldType`
models, call `Field::firstOrCreate` for each field (handles are globally unique, so
be deliberate about naming — no collisions exist yet), and attach them to a new
`FieldGroup` via `syncWithoutDetaching`. The `Boolean` type must be resolved for
`is_online` and the `Number` type for all numeric fields.

**Caution on `transcript`:** Both `podcast-fields` and `video-fields` define a
`transcript` field. The TODO notes these are separate field records, not shared. This
means they cannot share the same `handle` — use `podcast_transcript` and
`video_transcript` (or similar) rather than both using `transcript`. The TODO's
handle table does not disambiguate this, but the globally-unique constraint on
`fields.handle` enforces it.

**Caution on `location`:** Both `event-fields` and `job-fields` include a `location`
(Text) field. Same issue — they must have distinct handles (`event_location` and
`job_location`, for example). Verify all handles in the table are unique before
seeding.

**What not to do:** Do not create these fields outside a seeder. Do not attempt to
attach field groups to entry groups inside `FieldGroupSeeder` — that belongs in
Layer 5.

---

## Layer 5 — Updated EntryGroup Seeders

**Verdict: YAY — but not yet done ❌**

The approach of updating the existing seeder methods rather than writing new ones is
correct. Using `syncWithoutDetaching` for field group and category group attachment is
idempotent and safe to re-run.

**Critical seeder re-run caveat:** The existing `EntryGroupSeeder` and
`ExtendedEntryGroupSeeder` both use `EntryGroup::firstOrCreate` for group creation,
which means the group row itself won't be updated on re-seed. For changes like
swapping `status_group_id` on Products and Jobs, you must use
`$group->update(['status_group_id' => $jobStatus->id])` after the `firstOrCreate`
call — you cannot rely on the `firstOrCreate` defaults to backfill. The same applies
to updating field layouts: if the group already exists with an old layout, the
`firstOrCreate` default won't apply a new one. Use `$group->update(['field_layout_id'
=> $newLayout->id])` explicitly.

**What to do for Blog and Products (EntryGroupSeeder):**

For Blog: after the existing `firstOrCreate`, attach `blog-fields` via
`syncWithoutDetaching`, then extend the existing layout to add a "Publishing" tab
containing `reading_time`. Do not replace the existing layout — add a tab to it.
Since the layout is created inside `createLayout()` and the Blog group's
`firstOrCreate` only runs once, the correct approach is to load the group's existing
layout and add the tab if it does not already exist:

```php
$group = EntryGroup::where('handle', 'blog')->firstOrFail();
$blogFields = FieldGroup::where('handle', 'blog-fields')->firstOrFail();
$group->fieldGroups()->syncWithoutDetaching([$blogFields->id]);

$layout = $group->fieldLayout;
if (!$layout->tabs()->where('name', 'Publishing')->exists()) {
    $tab = Tab::create(['field_layout_id' => $layout->id, 'name' => 'Publishing', 'sort_order' => 99]);
    TabElement::create([
        'field_layout_tab_id' => $tab->id,
        'field_id'            => Field::where('handle', 'reading_time')->value('id'),
        'sort_order'          => 1,
    ]);
}
```

For Products: change `status_group_id` to `product-status`, attach `product-fields`,
and add "Pricing" and "Inventory" tabs using the same guard pattern above.

**What to do for ExtendedEntryGroupSeeder:** Apply the same pattern for each group.
For Jobs, swap `status_group_id` to `job-status` and attach `employment-types` and
`experience-levels`. Resolve these via
`CategoryGroup::where('handle', '...')->firstOrFail()` before using them.

**What not to do:** Do not recreate the layouts from scratch — that would orphan the
existing layout rows. Do not change the `createLayout()` call arguments in the
`firstOrCreate` block — that code only runs once on a fresh seed, and altering it
produces no effect on existing data.

---

## Layer 6 — AbstractEntryType: Safe Field Reading

**Verdict: YAY — not yet done ❌, and blocked by the truncation issue**

The `existingFieldValue()` helper is a sound defensive pattern. `loadMissing` is
idempotent and N+1-safe. This is the correct way to read field values inside
`beforeUpdate` when the caller's `$data` array may not include the field being
inspected (e.g. reading `stock_quantity` to decide on auto-status when the update
payload only touches `price`).

**What to do:** Fix the truncated `AbstractEntryType.php` first (restore `afterUpdate`
and the closing brace). Then add:

```php
protected function existingFieldValue(Entry $entry, string $handle): mixed
{
    $entry->loadMissing([
        'fieldValues.field.fieldType',
        'entryRelationships.field',
        'entryRelationships.relatedEntry',
    ]);

    return $entry->field($handle);
}
```

The import for `Entry` is already present in the file.

**What not to do:** Do not call `$entry->field($handle)` directly in `beforeUpdate`
without this guard — if the relationship is not loaded, you will get `null` regardless
of whether a value exists. Do not eager-load unconditionally in the constructor or
on every hook call — only load when needed.

---

## Layer 7 — Validation Contract on AbstractEntryType

**Verdict: YAY with one design note — not yet done ❌**

Adding an opt-in `validate()` method that returns a field-keyed error map is the right
pattern. It keeps business-rule validation separate from the persistence hooks and
gives controllers/form requests a clean pre-flight check without catching exceptions.

**Design note:** The return type `array<string, string>` (one error message per
field) is intentionally simple. This works well for these use cases. If a future
requirement needs multiple messages per field, the signature can be changed to
`array<string, string|array>` without breaking callers that only check
`empty($errors)`. Document the contract clearly in the docblock so implementors
know to follow the same convention.

**What to do:** Add `validate()` to `AbstractEntryType.php` exactly as specified. The
concrete types that need it (JobListing, News, Product, Video) should then override
it independently. Note that `validate()` does NOT receive an `Entry` for creates —
the `?Entry $entry = null` signature handles both cases, which is the right choice.

**What not to do:** Do not have `EntryRepository` call `validate()` automatically
before persisting. The TODO is explicit that callers invoke it manually. Automatic
invocation would make `validate()` indistinguishable from `beforeCreate` and would
silently change the behavior of types that haven't implemented it (the base no-op
returning `[]` is fine, but auto-invocation could break callers expecting exceptions
only from hooks). Invoke it from Form Requests or controllers before calling
`Content::create()` / `Content::update()`.

---

## Layer 8 — EntryType Lifecycle Hook Implementations

**Verdict: YAY for the approach overall — partially done ⚠️**

Several entry types already have lifecycle logic. Here is the current state and
what remains for each:

### BlogPostEntryType — Empty, nothing done ❌

**What to do:** Add `beforeCreate` to stamp `published_at` when status is
`published` and no date is provided (match the `NewsArticleEntryType` pattern
already in the codebase). Add `beforeCreate` / `beforeUpdate` to compute
`reading_time` from `str_word_count($data['fields']['body'] ?? '') / 200` rounded
up with `(int) ceil(...)`. Inject the result into `$data['fields']['reading_time']`.

**Caution:** `reading_time` only persists if it's in the resolved field layout
(i.e. `blog-fields` is attached to the Blog group in Layer 5). These two layers
are coupled — implement Layer 4 and Layer 5 first.

### EventEntryType — `published_at` defaulting done ✅, date validation missing ❌

**What to do:** In `beforeUpdate`, add the date guard after the existing
`published_at` block:

```php
$endDate   = $data['fields']['end_date'] ?? null;
$startDate = $data['fields']['start_date'] ?? $this->existingFieldValue($entry, 'start_date');

if ($endDate && $startDate && $endDate < $startDate) {
    throw new \InvalidArgumentException('end_date cannot be earlier than start_date.');
}
```

Note this requires Layer 6 (`existingFieldValue`) to be in place for reading
the existing `start_date` when only `end_date` is in the update payload.

**What not to do:** Do not compare date strings directly — use Carbon or `strtotime`
to normalise before comparing, since Date fields return Carbon instances via
`$entry->field()` but the `$data` array may contain a raw string.

### JobListingEntryType — `published_at` + close clearing done ✅, auto-expire missing ❌, `validate()` missing ❌

**What to do:** In `beforeUpdate`, after the existing `expired`/`closed` block,
add the auto-expire logic:

```php
$closingDate = $data['fields']['closing_date']
    ?? $this->existingFieldValue($entry, 'closing_date');

if ($closingDate && now()->gt(\Carbon\Carbon::parse($closingDate))) {
    $data['status'] = 'expired';
}
```

This correctly sets the status to `expired` (which is now a valid status in
`job-status`). The `expired` status sets `is_public = false` so the StatusObserver
will update `status_is_public` on any linked entries automatically.

Add `validate()` to return an error when neither `application_url` nor
`application_email` is present and the requested status is `published`.

### NewsArticleEntryType — `published_at` stamping done ✅, `validate()` missing ❌

**What to do:** Add `validate()` to return an error on `source` when `source_url`
is non-empty but `source` is empty. This is a lightweight consistency check that is
much better as a validation error than a hook exception.

### PageEntryType — Empty ❌

**What to do:** Add `beforeCreate` to default `published_at` to `now()`. Pages
are static content that should be immediately accessible once created.

### PodcastEpisodeEntryType — `episode_number` locking and `published_at` done ✅, duration validation missing ❌

**What to do:** Add `beforeUpdate` to validate `episode_duration`:

```php
$duration = $data['fields']['episode_duration'] ?? null;
if ($duration !== null && (!is_int($duration) || $duration <= 0)) {
    throw new \InvalidArgumentException('episode_duration must be a positive integer.');
}
```

**Note on episode_number persistence:** The TODO observes that `episode_number`
was "previously silently dropped." This is because `episode_number` was not in the
field layout. Once `podcast-fields` is attached (Layer 5), the value will persist
correctly — the existing `PodcastEpisodeEntryType` logic requires no change.

### PortfolioItemEntryType — Empty ❌

**What to do:** Add `beforeCreate` to default `published_at` to `now()`. Same
rationale as PageEntryType.

### ProductEntryType — Empty ❌

This is the most work. All of the following must be added:

**Price validation in `beforeCreate`/`beforeUpdate`:** Throw `InvalidArgumentException`
when `price` is explicitly set (present in `$data['fields']`) and negative. Do not
throw on absence — price may not be in the payload.

**Sale price validation in `beforeCreate`/`beforeUpdate`:** When `sale_price` is
set and `price > 0`, throw if `sale_price >= price`. When `price === 0`, strip
`sale_price` from `$data['fields']` and throw — do not silently discard it.

**Auto out-of-stock in `beforeUpdate`:** When `stock_quantity` drops to zero, inject
`$data['status'] = 'out-of-stock'`. Read the current quantity via
`existingFieldValue($entry, 'stock_quantity')` when `stock_quantity` is not in
`$data['fields']`. This requires Layer 6.

**`validate()`:** Return an error when `sku` is empty and the requested status
is `published`.

**Important design consideration:** The price and sale_price rules throw exceptions
in hooks, not in `validate()`. This is inconsistent — throwing from a hook produces
a 500 unless the HTTP layer catches it, while `validate()` returns user-facing
errors cleanly. A better approach is to move price/sale_price checks into
`validate()` and only throw from hooks for truly unexpected data integrity violations
(e.g. an internal computed field producing a bad value). Consider relocating these
to `validate()` and calling `validate()` from your Form Request before persisting.

### RecipeEntryType — Empty ❌

**What to do:** Add `beforeCreate` to default `published_at` to `now()`. Add
`beforeCreate`/`beforeUpdate` to compute `total_time`:

```php
$prepTime  = $data['fields']['prep_time']  ?? $this->existingFieldValue($entry, 'prep_time');
$cookTime  = $data['fields']['cook_time']  ?? $this->existingFieldValue($entry, 'cook_time');

if (isset($data['fields']['prep_time']) || isset($data['fields']['cook_time'])) {
    $data['fields']['total_time'] = ((int) $prepTime) + ((int) $cookTime);
}
```

Only inject `total_time` when at least one of its inputs is in the current payload
to avoid overwriting a valid value during unrelated updates.

### VideoEntryType — Empty ❌

**What to do:** Add `beforeCreate` to default `published_at` to `now()`. Add
`validate()` to return an error when both `platform_id` and `video_url` are empty on
publish.

---

## Layer 9 — Entry Metrics Table

**Verdict: YAY with one design note — not yet done ❌**

Separating metrics from `field_values` is the right call. Metrics are append-only
time-series data with a known shape — not arbitrary EAV rows. Putting them in
`field_values` would mean aggregating across a typed-column EAV table, which is
awkward and conflicts with the purpose of `value_integer` (a field value for a
specific field, not a metric accumulator).

**What to do:** Create the migration and `EntryMetric` model as specified. The
`[entry_id, metric, recorded_date]` unique constraint is correct — it enforces
one row per metric per day per entry, which is the right granularity for daily
aggregation. Add a `metrics(): HasMany` relationship to `Entry`.

**Design note the TODO does not address:** The read pattern suggested —
`$entry->metrics->where('metric', 'downloads')->sum('value')` — loads all metric
rows for an entry into memory before filtering and summing in PHP. For entries with
long histories this is a collection scan. Prefer a scoped query method on
`Entry`:

```php
public function metricTotal(string $metric, ?\Carbon\Carbon $from = null): int
{
    return $this->metrics()
        ->where('metric', $metric)
        ->when($from, fn ($q) => $q->where('recorded_date', '>=', $from))
        ->sum('value');
}
```

This keeps aggregation in the database. The relationship-based `->where()->sum()`
pattern from the TODO is fine for templates rendering a single entry but should not
be used in loops.

**What not to do:** Do not add `download_count` or `view_count` as fields in any
FieldGroup. Do not store incremental event counts in `field_values`. The metric
table is the exclusive home for this data.

---

## Layer 10 — Settings System

**Verdict: LARGELY DONE but has diverged from the original plan in meaningful ways ⚠️**

The implementation that exists is more sophisticated than what the TODO proposed and
is a better design. Key differences:

**Config-driven field definitions vs. database FieldLayout:** The implemented system
stores field definitions in `config/settings.php` rather than using the DB-driven
`FieldLayout` / `Field` / `FieldGroup` stack. This is intentional and correct —
settings fields have a stable, developer-controlled schema that does not need to be
user-editable at runtime. Using the EAV field stack for settings would add database
reads to every settings access and couple settings admin to the full field/layout
pipeline.

**`user_overridable` in config, not on the `fields` table:** The TODO proposed adding
a `user_overridable` boolean column to the `fields` table. The implementation instead
defines `user_overridable` per field in `config/settings.php`. This is better — the
settings system does not use the `fields` table at all, so adding a column there
would be incoherent.

**`SettingsDomain` does not implement `Fieldable`:** The `SettingDomain` model exists
as a registry row (name, handle, description, icon, sort_order) but does not carry a
`FieldLayout`. This is correct given the config-driven approach.

**What is still needed:**

The old `app/Models/Settings.php` (the flat Eloquent model targeting the `settings`
table) still exists in the codebase. It is dead code and should be deleted. Its
presence alongside the new `SettingDomain` and `SettingValue` models will cause
confusion.

The two migration files (`create_settings_table`, `create_user_settings_table`) have
been repurposed in-place — they now create `setting_domains` and `setting_values`
respectively. If these migrations have already run on any environment with the
original schema, the repurposed content will not re-run (migrations are idempotent by
timestamp). Verify that no live database still carries the old flat `settings` /
`user_settings` tables; if any do, write a new migration to drop the old tables.

**What not to do:** Do not add a `user_overridable` column to the `fields` table —
that was superseded by the config approach. Do not attempt to port settings to use
FieldLayout — the current design is simpler and correct. Do not delete
`app/Settings.php` — it is the replacement service, not the old one (same path,
entirely new content).

---

## DatabaseSeeder Load Order

**Verdict: YAY, with one addition ✅**

The load order is correct for the layers that are done. One class is missing from
the listed sequence: `SettingsDomainSeeder` is present in the codebase but not
mentioned in the TODO's load order table. It should run after `UserSchemaSeeder`
(its only dependency is `users`, which is already seeded by then). Confirm it is
registered in `DatabaseSeeder.php`.

---

## Summary Table

| Layer | Item                          | Status       | Priority |
|-------|-------------------------------|--------------|----------|
| —     | AbstractEntryType truncation  | ⛔ Bug        | Immediate |
| 1     | Boolean FieldType             | ✅ Done       | — |
| 2     | New StatusGroups              | ✅ Done       | — |
| 3     | New CategoryGroups            | ✅ Done       | — |
| 4     | Domain FieldGroups seeder     | ❌ Not done   | High (blocks Layer 5 & 8) |
| 5     | EntryGroup seeder updates     | ❌ Not done   | High (blocks Layer 8) |
| 6     | `existingFieldValue()` helper | ❌ Not done   | High (blocks Layer 8 reads) |
| 7     | `validate()` contract         | ❌ Not done   | High (blocks Layer 8 validation) |
| 8     | Lifecycle implementations     | ⚠️ Partial   | Medium — unblock with 4–7 first |
| 9     | Entry Metrics table           | ❌ Not done   | Medium |
| 10    | Settings system               | ⚠️ Diverged  | Low — mostly done, delete old model |
