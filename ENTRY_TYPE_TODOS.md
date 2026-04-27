# Entry Type Implementation TODOs

Ordered by dependency layer. Each layer must be complete before the next begins.

---

## Layer 1 — Boolean FieldType

Everything that needs flag-style fields (online events, free products, breaking news) is blocked until this exists.

- [x] **Create** `app/Field/Types/Boolean.php`
  - Extends `AbstractField`
  - `storageColumn()` returns `'value_boolean'`
  - `cast()` returns `(bool) $value`
- [x] **Create** `resources/views/_fields/boolean.twig`
  - Checkbox input with `name="fields[{{ field.handle }}]"` submitting `1`/`0`
- [x] **Modify** `database/seeders/FieldTypeSeeder.php`
  - Add `Boolean` to the `$types` array

---

## Layer 2 — New StatusGroups

Jobs and Products have statuses that are meaningless on a blog post. They each need their own StatusGroup instead of sharing the global `publication` group.

- [x] **Modify** `database/seeders/StatusGroupSeeder.php` — add two new groups:
  - `job-status`: draft (default, not public), published (public), expired (not public), closed (not public)
  - `product-status`: draft (default, not public), published (public), out-of-stock (not public), pre-order (public), discontinued (not public)
  - Events stay on `publication` — `archived` covers past events

---

## Layer 3 — New CategoryGroups

Must be seeded before entry group seeders reference them. Add as private methods in the existing seeder following the established pattern.

- [x] **Modify** `database/seeders/CategoryGroupSeeder.php` — add:
  - `cuisines` — Italian, Mexican, Thai, Japanese, French, American, Indian, Mediterranean _(for Recipes)_
  - `diet-types` — Vegan, Vegetarian, Gluten-Free, Dairy-Free, Keto, Paleo _(for Recipes)_
  - `event-types` — Conference, Webinar, Workshop, Meetup, Course, Networking _(for Events)_
  - `employment-types` — Full-Time, Part-Time, Contract, Freelance, Remote _(for Jobs)_
  - `experience-levels` — Entry Level, Mid Level, Senior, Lead, Executive _(for Jobs)_

---

## Layer 4 — Domain FieldGroups

Add one private seed method per group in the existing `FieldGroupSeeder.php`, following the `seedContentFields` / `seedSeoFields` pattern. Note: `is_online` depends on Boolean being seeded first (Layer 1). `reading_time` and `total_time` should be seeded with `hidden: true` — they are system-computed and must not appear as editable inputs.

- [ ] **Modify** `database/seeders/FieldGroupSeeder.php` — add:

| Handle | Fields |
|---|---|
| `blog-fields` | `reading_time` (Number, hidden) |
| `event-fields` | `start_date` (Date), `end_date` (Date), `location` (Text), `venue` (Text), `ticket_url` (Url), `registration_deadline` (Date), `capacity` (Number), `is_online` (Boolean) |
| `job-fields` | `department` (Text), `location` (Text), `salary_min` (Number), `salary_max` (Number), `closing_date` (Date), `application_url` (Url), `application_email` (EmailAddress) |
| `news-fields` | `source` (Text), `source_url` (Url), `dateline` (Text) |
| `page-fields` | `layout` (Text), `cta_text` (Text), `cta_url` (Url) |
| `podcast-fields` | `episode_number` (Number), `season_number` (Number), `episode_duration` (Number), `audio_url` (Url), `transcript` (Textarea), `guest_names` (Text), `sponsor` (Text) |
| `portfolio-fields` | `client_name` (Text), `project_url` (Url), `project_date` (Date), `role` (Text), `technologies` (Text), `testimonial` (Textarea) |
| `product-fields` | `sku` (Text), `price` (Number), `sale_price` (Number), `stock_quantity` (Number), `weight` (Number), `dimensions` (Text) |
| `recipe-fields` | `prep_time` (Number), `cook_time` (Number), `total_time` (Number, hidden), `servings` (Number), `calories` (Number), `ingredients` (Textarea), `instructions` (Textarea) |
| `video-fields` | `video_platform` (Text), `platform_id` (Text), `video_url` (Url), `video_duration` (Number), `transcript` (Textarea), `captions_url` (Url) |

> `episode_duration` and `video_duration` are separate field records (not shared) so they can be placed in their respective layouts independently.

---

## Layer 5 — Updated EntryGroup Seeders

### `database/seeders/EntryGroupSeeder.php`

- [ ] **Blog group**
  - Attach `blog-fields` via `syncWithoutDetaching`
  - Add "Publishing" tab to layout containing `reading_time`
- [ ] **Products group**
  - Change `status_group_id` to use `product-status` instead of `publication`
  - Attach `product-fields`
  - Add "Pricing" tab to layout (`price`, `sale_price`, `sku`)
  - Add "Inventory" tab to layout (`stock_quantity`, `weight`, `dimensions`)

### `database/seeders/ExtendedEntryGroupSeeder.php`

- [ ] **Events group**
  - Attach `event-fields`
  - Attach `event-types` CategoryGroup
  - Add "Event Details" tab (`start_date`, `end_date`, `location`, `venue`, `is_online`, `ticket_url`, `registration_deadline`, `capacity`)
- [ ] **News group**
  - Attach `news-fields`
  - Add "Attribution" tab (`source`, `source_url`, `dateline`)
- [ ] **Pages group**
  - Attach `page-fields`
  - Add "Page Options" tab (`layout`, `cta_text`, `cta_url`)
- [ ] **Jobs group**
  - Change `status_group_id` to `job-status`
  - Attach `job-fields`
  - Attach `employment-types` and `experience-levels` CategoryGroups
  - Add "Role Details" tab (`department`, `location`, `salary_min`, `salary_max`, `closing_date`, `application_url`, `application_email`)
- [ ] **Podcast group**
  - Attach `podcast-fields`
  - Add "Episode" tab (`episode_number`, `season_number`, `audio_url`, `episode_duration`, `guest_names`, `sponsor`)
  - Add "Transcript" tab (`transcript`)
- [ ] **Portfolio group**
  - Attach `portfolio-fields`
  - Add "Project Details" tab (`client_name`, `project_date`, `role`, `technologies`, `project_url`, `testimonial`)
- [ ] **Videos group**
  - Attach `video-fields`
  - Add "Video" tab (`video_platform`, `platform_id`, `video_url`, `video_duration`, `captions_url`)
  - Add "Transcript" tab (`transcript`)
- [ ] **Recipes group**
  - Attach `recipe-fields`
  - Attach `cuisines` and `diet-types` CategoryGroups
  - Add "Recipe Details" tab (`prep_time`, `cook_time`, `total_time`, `servings`, `calories`)
  - Add "Content" tab (`ingredients`, `instructions`)

---

## Layer 6 — AbstractEntryType: Safe Field Reading

Lifecycle hooks cannot safely call `$entry->field()` because eager loading is not guaranteed on the `Entry` passed to `beforeUpdate`. Add one protected helper.

- [ ] **Modify** `app/EntryTypes/AbstractEntryType.php` — add `existingFieldValue()`:

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

> `loadMissing` is idempotent — if the relation is already loaded it is a no-op.

---

## Layer 7 — Validation Contract on AbstractEntryType

Hooks currently throw exceptions for business rule violations, which propagates to a 500 unless the HTTP layer catches it. Add a formal opt-in validation method as a non-breaking complement that callers can invoke before create/update.

- [ ] **Modify** `app/EntryTypes/AbstractEntryType.php` — add `validate()`:

```php
/**
 * Return field-keyed validation errors for the given data payload.
 * An empty array means the data is valid.
 * Concrete types override this; the repository does not call it automatically.
 * Invoke from a Form Request or controller before calling create/update.
 *
 * @return array<string, string>  ['field_handle' => 'error message']
 */
public function validate(array $data, ?Entry $entry = null): array
{
    return [];
}
```

---

## Layer 8 — EntryType Lifecycle Hook Implementations

With the infrastructure above in place, each class can be updated.

### `BlogPostEntryType`
- [ ] `beforeCreate` — stamp `published_at` to `now()` when `status === 'published'` and no date is set (matching `NewsArticleEntryType`)
- [ ] `beforeCreate` / `beforeUpdate` — compute `reading_time` from `str_word_count($data['fields']['body'] ?? '') / 200` (rounded up) and inject into `$data['fields']['reading_time']`

### `EventEntryType`
- [ ] `beforeUpdate` — throw `InvalidArgumentException` when `end_date` is set and earlier than `start_date`
- [ ] Existing `published_at` defaulting — no change

### `JobListingEntryType`
- [ ] `beforeUpdate` — when `closing_date` field is past `now()`, inject `$data['status'] = 'expired'` (now valid because `expired` exists in `job-status`)
- [ ] `validate()` — return error when neither `application_url` nor `application_email` is present on publish
- [ ] Existing `published_at` defaulting and expired/closed clearing — no change

### `NewsArticleEntryType`
- [ ] Existing `published_at` stamping — no change
- [ ] `validate()` — return error when `source_url` is set but `source` is empty

### `PageEntryType`
- [ ] `beforeCreate` — default `published_at` to `now()`

### `PodcastEpisodeEntryType`
- [ ] `beforeUpdate` — throw `InvalidArgumentException` when `episode_duration` is present and not a positive integer
- [ ] Existing `episode_number` locking and `published_at` defaulting — no change; will now persist correctly once `episode_number` is seeded and in the layout (previously silently dropped)

### `PortfolioItemEntryType`
- [ ] `beforeCreate` — default `published_at` to `now()`

### `ProductEntryType`
- [ ] `beforeCreate` / `beforeUpdate` — throw `InvalidArgumentException` when `price` is explicitly set and negative
- [ ] `beforeCreate` / `beforeUpdate` — when `sale_price` is set and `price > 0`, throw `InvalidArgumentException` if `sale_price >= price`; when `price === 0`, strip `sale_price` and throw `InvalidArgumentException` with a clear message (do not silently discard)
- [ ] `beforeUpdate` — when `stock_quantity` drops to zero, inject `$data['status'] = 'out-of-stock'`; use `existingFieldValue($entry, 'stock_quantity')` to read current stock when not present in `$data['fields']`
- [ ] `validate()` — return error when `sku` is empty and status is `published`

### `RecipeEntryType`
- [ ] `beforeCreate` — default `published_at` to `now()`
- [ ] `beforeCreate` / `beforeUpdate` — compute `total_time` from `prep_time + cook_time` when either is present in `$data['fields']` and inject into `$data['fields']['total_time']`

### `VideoEntryType`
- [ ] `beforeCreate` — default `published_at` to `now()`
- [ ] `validate()` — return error when both `platform_id` and `video_url` are empty on publish

---

## Layer 9 — Entry Metrics Table

`download_count` and `view_count` do not belong in `field_values`. They need a dedicated table with their own read path.

- [ ] **Create** migration `create_entry_metrics_table`:
  - `entry_id` (FK → entries, cascade delete)
  - `metric` (string — e.g. `'downloads'`, `'views'`, `'plays'`)
  - `value` (unsignedBigInteger, default 0)
  - `recorded_date` (date)
  - Unique constraint on `[entry_id, metric, recorded_date]`
- [ ] **Create** `app/Models/EntryMetric.php` — standard model with the above fillable
- [ ] **Add** `metrics(): HasMany` relationship to `app/Models/Entry.php`

> Templates read metrics via `$entry->metrics->where('metric', 'downloads')->sum('value')`. No changes to `field_values` or any FieldLayout are involved.

---

## DatabaseSeeder Load Order

No new seeder classes are required — all additions go into existing files. The call order in `DatabaseSeeder.php` remains unchanged:

```
RolesPermissionsSeeder
UsersSeeder
FieldTypeSeeder          ← Boolean added
StatusGroupSeeder        ← job-status, product-status added
CategoryGroupSeeder      ← cuisines, diet-types, event-types, employment-types, experience-levels added
FieldGroupSeeder         ← all domain field groups added
EntryGroupSeeder         ← blog + products updated
ExtendedEntryGroupSeeder ← all others updated
UserSchemaSeeder
```
