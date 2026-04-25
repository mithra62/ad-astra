# Current Issues Review

This document replaces the broad historical critical-issues list with a narrower review of the issues that are still relevant in the current codebase, plus a small number of newly-identified implementation gaps.

The emphasis here is pragmatic:

- what is still broken or fragile now
- what the current repercussions are
- how to mitigate each issue
- what schema or seed changes are needed without introducing new migration or seeder classes

This is intentionally limited to issues that are either still open or only partially resolved.

---

## 1. Entry status values are still weakly enforced

### Current state

`entries.status` is still a plain string column, and request validation only checks that the field is a string:

- `app/Http/Requests/Entry/StoreEntryRequest.php`
- `app/Http/Requests/Entry/EditEntryRequest.php`

At the repository layer, `EntryRepository::applyStatus()` accepts any non-empty handle and assigns it directly.

### Repercussions

- A typo such as `publishd` is saved silently.
- A status from the wrong status group can be assigned to an entry.
- Status-based queries can silently miss entries that are present in the database.
- Bugs will appear as "missing content" rather than explicit failures.

### Mitigation

- Tighten request validation so `status` must exist in `statuses.handle` and belong to the current entry group's `status_group_id`.
- Add the same guard in `EntryRepository::applyStatus()` so non-HTTP callers cannot bypass it.
- Keep the repository as the final enforcement layer; request validation alone is not enough.

### Migration / Seeder guidance

Because this project is still in the resettable "run migrations and seed it" phase:

- Keep the existing `create_entries_table` migration as the schema source of truth.
- Do not create a new repair migration object for status cleanup.
- Update existing entry-related seeders so every seeded entry uses a valid status handle for its seeded entry group.
- If any seeder currently uses a hard-coded status that is not guaranteed to exist in that group's status group, fix the seeder now.

---

## 2. Morph map registration exists, but existing polymorphic data may still be stale

### Current state

The runtime morph map is registered in `app/Providers/AppServiceProvider.php`, and current write paths correctly use `getMorphClass()`.

That part is fixed.

What is not visible in the current codebase is a one-time backfill for legacy polymorphic type values already stored in tables such as:

- `field_values.fieldable_type`
- `fieldables.fieldable_type`
- `field_groupables.field_groupable_type`
- `category_groupables.category_groupable_type`
- `categorizables.categorizable_type`

### Repercussions

- Old rows containing `App\Models\...` strings may become unreadable or partially orphaned.
- New rows and old rows can coexist with different type formats.
- Reads may appear inconsistent depending on when the data was written.

### Mitigation

- Audit the live data first by grouping each polymorphic type column by its stored value.
- Convert all legacy FQCN values to the aliases defined in `AppServiceProvider`.
- Keep all future writes on `getMorphClass()` only.

### Migration / Seeder guidance

Because this project is still resettable:

- Do not add a new backfill migration object.
- Keep the existing morph-bearing migrations as the canonical schema definitions.
- Update existing seeders so they only create polymorphic rows through model, repository, or service paths that use `getMorphClass()`.
- Treat `migrate:fresh --seed` as the backfill strategy.

---

## 3. Entry group status-group invariants are only partially enforced

### Current state

The request layer now requires `status_group_id` on entry-group create/edit requests, and `EntryRepository::applyStatus()` throws if no status group or no default status exists.

However:

- `entry_groups.status_group_id` is still nullable in the schema
- `CreateNewEntryGroup` still falls back to `null`
- `EditEntryGroup` still falls back to `null`

### Repercussions

- Non-request callers can still create or mutate an entry group into an invalid state.
- Later entry creation fails at runtime instead of being impossible by construction.
- The invariant depends on controller/request behavior instead of the data model.

### Mitigation

- Remove the `?? null` fallback behavior from the entry-group actions.
- Treat missing `status_group_id` as invalid in all code paths, not just HTTP requests.
- Make the schema non-null if the project can tolerate a reset or controlled manual patch.

### Migration / Seeder guidance

Because this project is still resettable:

- Update the original `create_entry_groups_table` migration to make `status_group_id` non-null.
- Update all existing seeders that create entry groups so every seeded group sets `status_group_id`.
- Prefer fixing the foundational migration and seeders now rather than carrying a later repair step.

---

## 4. Required field validation still ignores type-level layout fields

### Current state

This issue changed shape from the original document.

The base request helper in `app/Http/Requests/FormRequest.php` does enforce `TabElement.required`, but entry requests resolve only the entry group schema:

- `StoreEntryRequest` uses `EntryGroup::resolvedFields(...)`
- `EditEntryRequest` uses `EntryGroup::resolvedFields(...)`

Meanwhile, actual field persistence resolves the effective layout by merging:

- entry-group layout
- entry-type layout

with type-level precedence in `EntryRepository::resolveLayoutFields()`.

### Repercussions

- Required fields defined only on the entry type can be omitted with no validation error.
- The request layer and persistence layer disagree on what fields an entry actually has.
- Users can save entries that violate the intended entry-type contract.

### Mitigation

- Stop resolving validation fields from `EntryGroup::resolvedFields()` alone.
- Introduce one shared "effective entry layout" resolver and use it in both validation and persistence.
- Ensure required flags are derived from the merged effective layout, not just the group layout.

### Migration / Seeder guidance

No migration or seeder changes are needed for this issue.

This is an application-layer consistency fix only.

---

## 5. Default status selection is application-enforced, not data-enforced

### Current state

The create/edit status actions do unset previous defaults when a new default is chosen.

That resolves the common path, but there is still no database-level guarantee that only one default status exists per group.

### Repercussions

- Concurrent writes can still produce multiple defaults in the same group.
- Any logic using `firstWhere('is_default', true)` becomes order-dependent.
- New entries may receive a different default status depending on collection ordering.

### Mitigation

- Wrap default-switching in a transaction.
- Lock the group's statuses before clearing and setting the default.
- Keep the action-layer clearing logic, but make it transactional so it is safe under concurrent writes.

### Migration / Seeder guidance

Because this project is still resettable:

- Update existing status-related seeders so each seeded status group creates exactly one default.
- If you later choose to encode DB-level enforcement, fold that into the existing `create_statuses_table` migration instead of adding a new migration object.
- For the current pass, the main implementation still belongs in the application layer.

---

## 6. FieldValue write conflict handling is inconsistent across code paths

### Current state

The repository paths are hardened:

- `EntryRepository::upsertFieldValue()` retries once on SQLSTATE `23000`
- `CategoryRepository::upsertFieldValue()` does the same

But `app/Concerns/PersistsFieldValues.php` still calls `FieldValue::updateOrCreate()` directly with no retry handling.

### Repercussions

- Most field-value writes are resilient, but some code paths still surface raw unique-constraint exceptions.
- Behavior depends on which write path a caller happens to use.
- The same logical operation can be stable in one part of the app and brittle in another.

### Mitigation

- Reuse the same guarded upsert behavior everywhere.
- Either move the shared logic into one helper/service or replicate the same retry behavior in `PersistsFieldValues`.
- Keep all field-value writes consistent in their use of `getMorphClass()` and retry handling.

### Migration / Seeder guidance

No migration or seeder changes are needed.

This is a write-path consistency issue in application code.

---

## 7. The historical "slug ambiguity" issue is now really a handle-lookup policy issue

### Current state

The old document still talks about slug ambiguity, but the current codebase uses `handle`.

The repository and service lookup APIs are already group-scoped:

- `EntryRepository::findByHandle()`
- `EntryRepository::findOrFailByHandle()`
- `EntryService::findByHandle()`
- `EntryService::findOrFailByHandle()`

### Repercussions

- The core abstraction is safe.
- The remaining risk is ad hoc direct queries elsewhere in the codebase that use `Entry::where('handle', ...)` without group scoping.

### Mitigation

- Treat the service/repository lookup methods as the only supported handle lookup API.
- Audit and eliminate any direct, cross-group `handle` queries outside that API.
- Update the old issue wording from `slug` to `handle` if the historical document is retained.

### Migration / Seeder guidance

No migration or seeder changes are required.

This is a query-discipline issue, not a schema issue.

---

## 8. The critical-issues document itself has drifted from the code

### Current state

Several items in `CRITICAL_ISSUES.md` no longer match reality:

- some are resolved
- some are partially resolved
- some still describe old `slug` terminology
- some overstate the current failure mode

### Repercussions

- Future implementation work may target the wrong abstraction.
- Time can be wasted fixing already-fixed issues.
- Real remaining issues can be underestimated because they are buried in stale language.

### Mitigation

- Keep `CRITICAL_ISSUES.md` only as historical context.
- Use this document as the current implementation reference.
- If the old document remains in the repo, annotate stale items as superseded rather than silently leaving them in place.

### Migration / Seeder guidance

None.

---

# Recommended Order Of Work

1. Fix status validation and repository-level status enforcement.
2. Remove nullable entry-group fallback behavior.
3. Unify entry validation with the effective merged layout.
4. Repair any stale polymorphic type data already stored.
5. Make default-status switching transactional.
6. Normalize all FieldValue writes to the same guarded upsert behavior.

---

# Prioritized Implementation Checklist

This section translates the issue review into a concrete patch order, grouped by file, with the aim of reducing risk while keeping the codebase in a runnable state after each step.

## Phase 1. Status Integrity

### Goal

Make invalid entry status assignment impossible through both HTTP and service/repository paths.

### Files to change

- `app/Http/Requests/Entry/StoreEntryRequest.php`
- `app/Http/Requests/Entry/EditEntryRequest.php`
- `app/Repositories/EntryRepository.php`
- optionally `tests/Unit/Http/...` and `tests/Unit/Repositories/...` depending on existing test conventions

### Changes

- In `StoreEntryRequest`, validate `status` against the selected entry group's `status_group_id`.
- In `EditEntryRequest`, validate `status` against the existing entry's group.
- In `EntryRepository::applyStatus()`, reject any explicit status handle that does not belong to the entry group's status group.
- Preserve current defaulting behavior for valid `null` status inputs when `applyDefault` is `true`.

### Current excerpt

From `app/Http/Requests/Entry/StoreEntryRequest.php`:

```php
  18:         $schema = EntryGroup::resolvedFields($this->route()->parameter('group_id'));
  19:         return array_merge(
  20:             [
  21:                 'type_handle' => ['required', 'string', 'exists:entry_types,handle'],
  22:                 'title' => ['required', 'string', 'max:255'],
  23:                 'handle' => ['nullable', 'string', 'max:255'],
  24:                 'status' => ['nullable', 'string', 'max:100'],
```

From `app/Http/Requests/Entry/EditEntryRequest.php`:

```php
  19:         $group = Entry::find($this->route()->parameter('entry'));
  20:         $schema = EntryGroup::resolvedFields($group->entryGroup()->first()->id);
  21:         return array_merge(
  22:             [
  23:                 'title' => ['required', 'string', 'max:255'],
  24:                 'handle' => ['nullable', 'string', 'max:255'],
  25:                 'status' => ['nullable', 'string', 'max:100'],
```

From `app/Repositories/EntryRepository.php`:

```php
 131:     private function applyStatus(Entry $entry, ?string $handle, bool $applyDefault): void
 132:     {
 133:         if ($handle) {
 134:             $entry->status = $handle;
 135: 
 136:             return;
 137:         }
```

### Example shape of the change

Request-level validation can be tightened in this direction:

```php
use Illuminate\Validation\Rule;

$group = EntryGroup::with('statusGroup')->findOrFail($this->route()->parameter('group_id'));
$statusGroupId = $group->status_group_id;

'status' => [
    'nullable',
    'string',
    'max:100',
    Rule::exists('statuses', 'handle')->where(
        fn ($query) => $query->where('status_group_id', $statusGroupId)
    ),
],
```

Repository-level enforcement should mirror it:

```php
if ($handle) {
    $statusGroup = $entry->entryGroup?->statusGroup;

    if (! $statusGroup) {
        throw new \RuntimeException(
            "EntryGroup [{$entry->entryGroup?->handle}] has no status group configured."
        );
    }

    $statusGroup->loadMissing('statuses');

    if (! $statusGroup->statuses->contains('handle', $handle)) {
        throw new \InvalidArgumentException(
            "Status [{$handle}] does not belong to EntryGroup [{$entry->entryGroup?->handle}]."
        );
    }

    $entry->status = $handle;

    return;
}
```

### Tests to add

- creating an entry with a valid status in the correct group succeeds
- creating an entry with a status from another group fails
- updating an entry with an invalid status fails
- repository/service-level create and update reject cross-group status handles

### Why first

This closes a silent data-integrity gap that can affect every entry workflow and is isolated enough to implement without schema changes.

## Phase 2. Entry Group Invariant Cleanup

### Goal

Make the code stop treating `status_group_id` as optional when entry groups are created or edited.

### Files to change

- `app/Actions/Entry/Group/CreateNewEntryGroup.php`
- `app/Actions/Entry/Group/EditEntryGroup.php`
- existing create-table migration for entry groups only if this project still resets from scratch frequently
- existing seeders that create entry groups

### Changes

- Remove `?? null` fallbacks in both actions.
- Assume `status_group_id` is required everywhere, matching the request layer.
- Update the original entry-group migration to make `status_group_id` non-null.
- Update the existing seeders that create entry groups so they always provide `status_group_id`.

### Current excerpt

From `app/Actions/Entry/Group/CreateNewEntryGroup.php`:

```php
  12:         $group = EntryGroup::create([
  13:             'name'            => $input['name'],
  14:             'handle'          => $input['handle'],
  15:             'description'     => $input['description'] ?? null,
  16:             'sort_order'      => $input['sort_order'] ?? 0,
  17:             'status_group_id' => $input['status_group_id'] ?? null,
  18:             'field_layout_id' => $input['field_layout_id'] ?? null,
```

From `app/Actions/Entry/Group/EditEntryGroup.php`:

```php
  12:         $group->update([
  13:             'name'            => $input['name'],
  14:             'handle'          => $input['handle'],
  15:             'description'     => $input['description'] ?? null,
  16:             'sort_order'      => $input['sort_order'] ?? 0,
  17:             'status_group_id' => $input['status_group_id'] ?? null,
  18:             'field_layout_id' => $input['field_layout_id'] ?? null,
```

### Example shape of the change

These actions should stop silently nulling the foreign key:

```php
'status_group_id' => $input['status_group_id'],
```

In this workflow, the original entry-group table definition should move from nullable:

```php
$table->foreignId('status_group_id')
    ->nullable()
    ->constrained('status_groups')
    ->nullOnDelete();
```

to required:

```php
$table->foreignId('status_group_id')
    ->constrained('status_groups')
    ->restrictOnDelete();
```

### Tests to add

- create action requires `status_group_id`
- edit action cannot blank out `status_group_id`

### Why second

This makes the action layer consistent with the request layer before moving on to more structural layout and morph issues.

## Phase 3. Effective Layout Validation

### Goal

Make request validation use the same effective field/layout resolution that entry persistence already uses.

### Files to change

- `app/Http/Requests/FormRequest.php`
- `app/Http/Requests/Entry/StoreEntryRequest.php`
- `app/Http/Requests/Entry/EditEntryRequest.php`
- `app/Repositories/EntryRepository.php` or `app/Services/EntryService.php`
- possibly `app/Traits/HasFieldLayout.php` if you decide to stop using `resolvedFields()` for entry requests

### Changes

- Add one shared resolver for the effective entry layout or effective entry field elements.
- Make that resolver merge group and type layouts with the same precedence already used in `EntryRepository::resolveLayoutFields()`.
- Refactor entry requests to build rules from the effective layout instead of the group-only layout.
- Keep `FormRequest::schemaFieldRules()` only if it can operate on the merged effective schema; otherwise split entry validation into its own logic.

### Current excerpt

From `app/Http/Requests/FormRequest.php`:

```php
  10:     protected function schemaFieldRules(Model $schema): array
  11:     {
  12:         $rules = [];
  13:         if (! $schema->fieldLayout) {
  14:             return $rules;
  15:         }
  16: 
  17:         foreach ($schema->fieldLayout->tabs as $tab) {
  18:             foreach ($tab->elements as $element) {
  19:                 $field = $element->field;
  20:                 $key = "fields.{$field->handle}";
  21:                 $fieldRules = $element->required ? ['required'] : ['nullable'];
```

From `app/Http/Requests/Entry/StoreEntryRequest.php`:

```php
  18:         $schema = EntryGroup::resolvedFields($this->route()->parameter('group_id'));
  32:             $this->schemaFieldRules($schema)
```

From `app/Repositories/EntryRepository.php`:

```php
 257:     public function resolveLayoutFields(Entry $entry): Collection
 258:     {
 259:         $entry->loadMissing([
 260:             'entryGroup.fieldLayout.tabs.elements.field.fieldType',
 261:             'entryType.fieldLayout.tabs.elements.field.fieldType',
 262:         ]);
 263: 
 264:         $groupFields = $entry->entryGroup->fieldLayout?->fields() ?? collect();
 265:         $typeFields = $entry->entryType->fieldLayout?->fields() ?? collect();
 266: 
 267:         // Type-level fields take precedence: start with type fields, then backfill
 268:         // group fields that don't share an ID with any type-level field.
 269:         return $typeFields->merge($groupFields)->unique('id');
```

### Example shape of the change

The entry request path needs a merged element resolver, not group-only resolution. One workable direction:

```php
public function resolveLayoutElements(Entry $entry): Collection
{
    $entry->loadMissing([
        'entryGroup.fieldLayout.tabs.elements.field.fieldType',
        'entryType.fieldLayout.tabs.elements.field.fieldType',
    ]);

    $groupElements = $entry->entryGroup->fieldLayout?->tabs
        ->flatMap(fn ($tab) => $tab->elements) ?? collect();

    $typeElements = $entry->entryType->fieldLayout?->tabs
        ->flatMap(fn ($tab) => $tab->elements) ?? collect();

    return $typeElements
        ->merge($groupElements)
        ->unique(fn ($element) => $element->field_id)
        ->values();
}
```

Then requests can build rules from elements instead of `EntryGroup::resolvedFields(...)`:

```php
foreach ($elements as $element) {
    $field = $element->field;
    $key = "fields.{$field->handle}";
    $presence = $element->required ? ['required'] : ['nullable'];

    $rules[$key] = array_merge($presence, $field->fieldType->instance()->getRules());
}
```

### Tests to add

- group-layout required field is enforced
- type-layout required field is enforced
- overlap between group and type layout uses the intended precedence

### Why third

This fixes a real mismatch between validation and persistence, but it is easier to do after status handling is stable.

## Phase 4. Polymorphic Data Backfill

### Goal

Align all existing polymorphic rows with the already-registered morph aliases.

### Files to change

- no new runtime code is strictly required unless alias coverage expands
- existing foundational migrations only if your project tolerates editing historical migrations
- possibly existing seeders if they embed or depend on stale polymorphic type values

### Changes

- First inspect live values in:
  - `field_values.fieldable_type`
  - `fieldables.fieldable_type`
  - `field_groupables.field_groupable_type`
  - `category_groupables.category_groupable_type`
  - `categorizables.categorizable_type`
- Ensure fresh seeded data is written only through code paths that emit morph aliases.
- Use `migrate:fresh --seed` as the repair strategy rather than adding a backfill migration object.

### Current excerpt

From `app/Providers/AppServiceProvider.php`:

```php
  58:         // Decouple stored polymorphic type strings from class names so that
  59:         // model renames do not silently orphan rows in polymorphic tables.
  60:         Relation::morphMap([
  61:             'entry'          => Entry::class,
  62:             'entry_group'    => EntryGroup::class,
  63:             'entry_type'     => EntryType::class,
  64:             'category'       => Category::class,
  65:             'category_group' => CategoryGroup::class,
  66:             'field_group'    => FieldGroup::class,
  67:             'media'          => Media::class,
  68:             'media_library'  => MediaLibrary::class,
  69:             'user'           => User::class,
```

The schema still stores raw polymorphic type strings in several tables:

```php
database/migrations/2026_04_18_000014_create_field_values_table.php
database/migrations/2026_04_14_000002_create_fieldables_table.php
database/migrations/2026_04_09_153821_create_field_groupables_table.php
database/migrations/2026_04_18_000019_create_category_groupables_table.php
database/migrations/2026_04_18_000017_create_categorizables_table.php
```

### Example shape of the change

In this workflow, the important change is to ensure all seeded writes use alias-emitting paths:

```php
FieldValue::updateOrCreate(
    [
        'field_id' => $field->id,
        'fieldable_id' => $model->getKey(),
        'fieldable_type' => $model->getMorphClass(),
    ],
    [$column => $value]
);
```

If every seeded polymorphic write uses repository/model/trait code like this, a fresh rebuild produces alias-based data automatically.

### Tests to add

- writing new polymorphic rows stores aliases, not FQCNs
- reading aliased polymorphic rows still hydrates correctly

### Why fourth

The runtime code is already mostly correct; this is a data repair step and can be handled after the higher-risk write-path fixes.

## Phase 5. Default Status Transaction Safety

### Goal

Prevent races that can leave multiple default statuses in one group.

### Files to change

- `app/Actions/Status/CreateNewStatus.php`
- `app/Actions/Status/EditStatus.php`
- status-related tests

### Changes

- Wrap the "clear existing default" plus "save new default" flow in a transaction.
- Lock the relevant group's statuses before mutating defaults.
- Keep the current behavior of clearing prior defaults, but make it safe under concurrent writes.

### Current excerpt

From `app/Actions/Status/CreateNewStatus.php`:

```php
  11:     public function create(array $input): Status
  12:     {
  13:         if (! empty($input['is_default'])) {
  14:             Status::where('status_group_id', $input['status_group_id'])
  15:                 ->where('is_default', true)
  16:                 ->update(['is_default' => false]);
  17:         }
  18: 
  19:         return Status::create($input);
```

From `app/Actions/Status/EditStatus.php`:

```php
  10:     public function edit(Status $status, array $input): bool
  11:     {
  12:         $input['is_default'] = ! empty($input['is_default']);
  13: 
  14:         if ($input['is_default']) {
  15:             Status::where('status_group_id', $status->status_group_id)
  16:                 ->where('id', '!=', $status->getKey())
  17:                 ->where('is_default', true)
  18:                 ->update(['is_default' => false]);
```

### Example shape of the change

The simplest hardening is transactional locking around the clear-and-set sequence:

```php
return DB::transaction(function () use ($input) {
    if (! empty($input['is_default'])) {
        Status::where('status_group_id', $input['status_group_id'])
            ->lockForUpdate()
            ->get();

        Status::where('status_group_id', $input['status_group_id'])
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }

    return Status::create($input);
});
```

The edit path should follow the same structure.

### Tests to add

- creating a new default clears the old default
- editing a status to default clears the old default
- only one default remains after each action

### Why fifth

This is important, but the normal path already behaves reasonably. It follows the higher-frequency data-integrity fixes.

## Phase 6. FieldValue Upsert Consistency

### Goal

Make all FieldValue write paths share the same conflict-handling and morph-type behavior.

### Files to change

- `app/Concerns/PersistsFieldValues.php`
- optionally extract a shared helper used by:
  - `app/Repositories/EntryRepository.php`
  - `app/Repositories/CategoryRepository.php`
  - `app/Concerns/PersistsFieldValues.php`

### Changes

- Add the same retry-on-`23000` behavior already present in the repositories.
- Keep `getMorphClass()` as the single source of truth for polymorphic write identity.
- If you want to reduce duplication, extract a shared guarded-upsert method.

### Current excerpt

The repository path is already guarded:

```php
app/Repositories/EntryRepository.php

 217:         try {
 218:             FieldValue::updateOrCreate($key, [$column => $value]);
 219:         } catch (QueryException $e) {
 220:             if ($e->getCode() !== '23000') {
 221:                 throw $e;
 222:             }
 223: 
 224:             FieldValue::updateOrCreate($key, [$column => $value]);
```

The trait path is not:

```php
app/Concerns/PersistsFieldValues.php

  16:         FieldValue::updateOrCreate(
  17:             [
  18:                 'field_id' => $field->id,
  19:                 'fieldable_id' => $model->getKey(),
  20:                 'fieldable_type' => $model->getMorphClass(),
  21:             ],
  22:             [$column => $value]
  23:         );
```

### Example shape of the change

Bring the trait into alignment with the repository:

```php
try {
    FieldValue::updateOrCreate(
        [
            'field_id' => $field->id,
            'fieldable_id' => $model->getKey(),
            'fieldable_type' => $model->getMorphClass(),
        ],
        [$column => $value]
    );
} catch (QueryException $e) {
    if ($e->getCode() !== '23000') {
        throw $e;
    }

    FieldValue::updateOrCreate(
        [
            'field_id' => $field->id,
            'fieldable_id' => $model->getKey(),
            'fieldable_type' => $model->getMorphClass(),
        ],
        [$column => $value]
    );
}
```

### Tests to add

- trait-backed field writes store the expected morph alias
- trait-backed field writes behave consistently with repository-backed writes

### Why sixth

This is worth cleaning up, but the main repository paths are already hardened, so it is lower urgency than the earlier phases.

## Optional Cleanup Phase. Historical Document Alignment

### Goal

Prevent future confusion from stale issue language.

### Files to change

- `CRITICAL_ISSUES.md`

### Changes

- mark stale items as superseded
- rewrite old `slug` references to `handle` where the issue is still conceptually relevant
- mark resolved items clearly instead of leaving them ambiguous

### Why optional

This does not change runtime behavior, but it makes future maintenance easier.

---

## Migration And Seeder Update Map

Because the project is still in the foundational schema phase, the preferred path for upcoming work is to update the existing migration and seeder objects below instead of creating new ones.

### Migrations to update

- `database/migrations/2026_04_18_000008_create_entry_groups_table.php`
Purpose:
make `status_group_id` non-null and align the schema with the request/action invariant.

- `database/migrations/2026_04_18_000010_create_entries_table.php`
Purpose:
keep this as the canonical schema reference for entry status handling while the stricter rules are implemented in requests and repositories.

- `database/migrations/2026_04_18_000006_create_statuses_table.php`
Purpose:
if DB-level default-status enforcement is added during this phase, fold it into this foundational migration instead of creating a separate one.

- `database/migrations/2026_04_18_000014_create_field_values_table.php`
- `database/migrations/2026_04_14_000002_create_fieldables_table.php`
- `database/migrations/2026_04_09_153821_create_field_groupables_table.php`
- `database/migrations/2026_04_18_000019_create_category_groupables_table.php`
- `database/migrations/2026_04_18_000017_create_categorizables_table.php`
Purpose:
these are the canonical morph-bearing tables to keep in view while auditing seeded polymorphic writes. They may not require structural edits, but they are the migration files that define the affected tables.

### Seeders to update

- `database/seeders/StatusGroupSeeder.php`
Purpose:
ensure each seeded status group creates one and only one default status.

- `database/seeders/EntryGroupSeeder.php`
- `database/seeders/ExtendedEntryGroupSeeder.php`
Purpose:
ensure every seeded entry group sets a valid `status_group_id`.

- `database/seeders/EntrySeeder.php`
- `database/seeders/SandboxedEntryTreeSeeder.php`
Purpose:
ensure seeded entries use valid status handles for their groups and that seeded writes pass through code paths that emit morph aliases.

- any existing seeder that creates polymorphic rows directly
Purpose:
replace raw type-string writes with model/repository/service writes so seeded data always uses `getMorphClass()`.

---

# Issues That Appear Resolved Enough To Drop From Active Work

These do not need to stay on the active list unless new regressions appear:

- observer bypass from `Entry::update()` / `Entry::delete()`
- class-string validation and class-reference health checking
- hook-by-reference mutation
- podcast episode-number race condition
- field type change corruption guard
- layout merge precedence in `EntryRepository`
- category cycle prevention
- `FieldLayout.name` nullability
- recursive entry relationship traversal safety helper
- `EntryTypeRegistry` caching
- `Entry::field()` eager-load warning
- lightweight entry metadata loading
