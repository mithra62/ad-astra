# Entry Layer Overview

> Current-state reference for the Laravel CMS Entry layer. This document was
> compiled from `CLAUDE.md`, `AGENTS.md`, `docs/OVERVIEW.md`, the live models,
> migrations, services, repositories, requests, API resources, route drivers,
> seeders, and tests.

## Purpose

Entries are the primary content records in the system. The Entry layer combines
database-defined structure with optional PHP classes for type-specific behavior.
In broad terms:

```text
StatusGroup -> Status
FieldType -> Field -> FieldLayout -> FieldLayoutTab -> FieldLayoutTabElement
EntryGroup -> EntryType -> Entry
Entry -> FieldValue / EntryRelationship / EntryTree / EntryMetric
```

The core design goal is to let administrators define most content structure at
runtime while still allowing developers to attach lifecycle hooks, validation,
and behavior through `AbstractEntryType` subclasses.

## Main Tables

| Table | Purpose |
|---|---|
| `entry_groups` | Top-level content buckets such as Blog, Pages, Products. Owns a status group and usually a field layout. |
| `entry_types` | Typed schemas within a group. Each row points to an `AbstractEntryType` PHP class or falls back to `GeneralEntryType` when the class is empty or missing. |
| `entries` | Concrete content records. Stores core attributes, denormalized status data, publication date, creator, group, and type. |
| `entry_authors` | Registry of eligible author identities, linked back to users. |
| `entry_author_entry` | Ordered pivot assigning eligible authors to entries. |
| `entry_relationships` | Relationship-field storage for Entry-to-Entry links. |
| `entry_trees` | Optional hierarchical routing tree for public URLs. One tree node per routed entry. |
| `entry_metrics` | Daily counters such as views, downloads, plays. |
| `status_groups` | Named collections of statuses. |
| `statuses` | Workflow/publication states. `is_public` controls whether entries are publishable/public. |
| `field_types` | Registry of PHP field type classes. |
| `fields` | Admin-created field instances. Handles are globally unique. |
| `field_layouts` | Layout containers assigned to entry groups and optionally entry types. |
| `field_layout_tabs` | Ordered tabs within a layout. |
| `field_layout_tab_elements` | A field placed in a layout context, with required/sort metadata and several currently underused contextual columns. |
| `field_values` | Polymorphic scalar field value storage for entries, categories, users, media, etc. |

## Entry Groups

`App\Models\EntryGroup` represents a content collection. It has:

- `field_layout_id`: optional layout for group-wide fields.
- `status_group_id`: workflow/status collection used by entries in this group.
- `name`, `handle`, `description`, `sort_order`.

Relations:

- `statusGroup()`: the owning `StatusGroup`.
- `statuses()`: statuses reachable through the group status group.
- `entryTypes()`: ordered `EntryType` rows in the group.
- `entries()`: entries in the group.
- `fieldLayout()`: from `HasFieldLayout`.
- `fieldGroups()`: polymorphic field group attachments.
- `categoryGroups()`: polymorphic category group attachments.

`EntryGroupService::create()` automatically creates a `FieldLayout` named after
the group and stores its ID on the new group. This is newer than some older
planning notes that still list automatic layout creation as a TODO.

Entry group handles are globally unique today. In the tenancy plan they are
expected to become unique per tenant.

## Entry Types

`App\Models\EntryType` defines a type of entry inside an entry group. Important
columns:

- `entry_group_id`
- `field_layout_id`: optional type-level layout.
- `name`, `handle`, `sort_order`.
- `class`: fully qualified class name for an `AbstractEntryType` subclass.
- `default_template`: used by public Entry Tree routing when the tree node does
  not specify a template.
- `has_entry_tree`: whether entries of this type participate in the public URI
  tree.
- `max_depth`, `allowed_parent_types`: stored and cast but not currently
  enforced by `EntryService`.

Database uniqueness is `entry_group_id + handle`, so the same type handle can
exist in different groups. However, several service and registry paths currently
resolve types by handle alone; see the issues section.

### PHP Type Classes

Entry type classes live in `app/EntryTypes/` and extend
`App\EntryTypes\AbstractEntryType`.

The base lifecycle contract is:

```php
public function beforeCreate(array $data): array;
public function afterCreate(Entry $entry, array $data): void;
public function beforeUpdate(Entry $entry, array $data): array;
public function afterUpdate(Entry $entry, array $data): void;
public function validate(array $data, ?Entry $entry = null): array;
```

`validate()` returns a field-keyed error array. A non-empty array becomes a
Laravel `ValidationException` from `EntryService`.

`AbstractEntryType` explicitly warns concrete classes not to store per-call
state on `$this`, because `EntryTypeRegistry` caches instances for the lifetime
of the process.

### Registry

`EntryTypeRegistry` resolves entry type records into PHP objects and caches them
by handle and ID. Resolution behavior:

- Empty `class`: logs a warning and returns `GeneralEntryType`.
- Missing class: logs a warning and returns `GeneralEntryType`.
- Class that does not extend `AbstractEntryType`: throws `RuntimeException`.

The registry loads `entryGroup` and the type field layout chain when resolving by
handle. The cache key is currently the handle, which is risky because type
handles are only unique inside a group.

## Entries

`App\Models\Entry` is the concrete content record. Fillable/core columns:

- `entry_group_id`
- `entry_type_id`
- `status_id`
- `status_handle`
- `status_is_public`
- `created_by_user_id`
- `title`
- `handle`
- `published_at`

Important traits:

- `Fieldable`: scalar dynamic field values.
- `HasCategories`: category assignments.
- `HasEntryTree`: tree helpers.
- `HasMedia`: direct media attachments.
- `HasFactory`.

Relations:

- `entryGroup()`
- `entryType()`
- `status()`
- `creator()`
- `authors()`
- `categories()`
- `fieldValues()`
- `entryRelationships()`
- `entryTree()`
- `metrics()`

Entry handles are unique within an entry group. `published()` scopes to
`status_is_public = true`, `published_at IS NOT NULL`, and `published_at <= now()`.

## Write Path

Entry creation should go through `EntryService` or the `Entries`/`Content`
facades, not directly through the model.

```text
Content::create($typeHandle, $data)
  EntryService::create()
    EntryTypeRegistry::resolveByHandle($typeHandle)
    AbstractEntryType::validate($data)
    EntryRepository::create($entryType, $data)
      DB transaction:
        beforeCreate($data)
        apply core attributes
        apply default or requested status
        save entry
        sync authors
        sync categories
        apply field values
      afterCreate($entry, $data)
    if has_entry_tree and handle is filled:
      create tree node
```

Entry update path:

```text
Content::update($entry, $data)
  EntryService::update()
    load entry type
    AbstractEntryType::validate($data, $entry)
    outer DB transaction:
      EntryRepository::applyData()
        beforeUpdate($entry, $data)
        inner DB transaction:
          apply core attributes
          apply status when provided
          save entry
          optionally sync authors/categories/fields
        afterUpdate($entry, $data)
      if type has_entry_tree:
        sync tree node
```

This layering is the intended extension point:

- Entry type class: validation and lifecycle semantics.
- Repository: core persistence, status, authors, categories, fields,
  relationships.
- Service: orchestration, transactions, tree sync, query entry point.

## Core Attribute Behavior

`EntryRepository::applyCoreAttributes()` applies title, handle, and
`published_at`.

Entry handles are explicit identifiers. They are required for HTTP create and
update requests, and create-time repository writes reject missing or blank
handles. The Entry repository no longer derives handles from titles. On update,
the repository changes the handle only when the `handle` key is present, which
keeps internal partial updates from accidentally rewriting existing handles.

## Status Layer

`StatusGroup` owns ordered `Status` records. `EntryGroup.status_group_id`
selects which statuses are valid for entries in that group.

`Status` fields:

- `status_group_id`
- `name`
- `handle`
- `color`
- `is_default`
- `is_public`
- `sort_order`

Status handles are unique inside a status group. There is no database-level
constraint enforcing a single default status per group.

When an entry is created:

- If `status` is provided, it must be in the entry group's status group.
- If `status` is not provided, the group's default status is used.
- `entries.status_id`, `entries.status_handle`, and
  `entries.status_is_public` are copied from the chosen status.
- If the status is public and `published_at` is empty, `published_at` is set to
  `now()`.

When a `Status.is_public` value changes, `StatusObserver` updates
`entries.status_is_public` for entries with that `status_id`.

The denormalized `status_handle` and `status_is_public` fields make common entry
queries cheaper and preserve historical status metadata if a status row is
deleted or changed. That denormalization also means status mutations need careful
guards.

## Field Layer

The field system is shared across entries, categories, users, media, and other
future fieldable models.

### Field Types

`field_types.object` stores a PHP class extending `App\Field\AbstractField`.
Built-in field types live in `app/Field/Types/`:

- `Text`
- `Textarea`
- `Html`
- `Number`
- `Date`
- `EmailAddress`
- `Url`
- `Telephone`
- `ColorPicker`
- `Boolean`
- `Relationship`
- `FileUpload`

Each type implements `storageColumn()` returning one of:

- `value_text`
- `value_integer`
- `value_float`
- `value_date`
- `value_boolean`
- `value_json`

`Relationship` overrides `isRelational()` and stores links in
`entry_relationships` instead of `field_values`.

`FileUpload` stores ordered media IDs in `field_values.value_json`; the
`FieldValueObserver` keeps matching rows in the `mediables` pivot in sync.

### Fields

`Field` rows are admin-created instances with globally unique handles. Fields
belong to a field type and can carry JSON settings. The `Field` model always
eager-loads `fieldType` because nearly every use needs the type instance.

### Field Layouts

A field layout contains ordered tabs; tabs contain ordered tab elements; each tab
element points to a field.

```text
FieldLayout
  FieldLayout\Tab
    FieldLayout\TabElement -> Field -> Field\Type -> AbstractField
```

`FieldLayout::fields()` returns a flattened collection of fields from all tabs.

For entries, the effective field set is a merge of:

1. Type layout fields, first.
2. Group layout fields, backfilled.

The repository deduplicates by field ID, so a type-level layout takes precedence
when it includes the same field as the group layout.

### Field Values

Scalar field values are stored in `field_values` using the fieldable morph alias.
For entries the alias is `entry`, registered in `AppServiceProvider`.

`EntryRepository::applyFieldValues()`:

- Resolves the entry's effective layout fields.
- Finds each submitted field handle in that layout.
- Silently skips unknown handles or fields without a field type.
- Routes relational fields to `entry_relationships`.
- Routes scalar fields to `FieldValue::updateOrCreate()`.

`FieldValue::resolvedValue()` uses the field type's `storageColumn()` and then
calls the field type's `value()` method. For `FileUpload`, this returns a
collection of `Media` models instead of raw IDs.

## Relationship Fields

Entry relationship fields are entry-only and use `entry_relationships`.

The relationship row stores:

- `entry_id`
- `related_entry_id`
- `field_id`
- `sort_order`

Direct self-reference is filtered out. Indirect cycles are allowed by storage and
handled by `EntryService::loadRelatedRecursive()` using depth limiting and a
visited-ID list.

`Entry::field($handle)` handles both scalar and relational fields:

- Scalar: returns the resolved field value.
- Relational: returns an ordered `Collection<Entry>` or `null`.

## Authors

Entries do not assign arbitrary users as authors directly. They assign
`EntryAuthor` records through the `entry_author_entry` pivot. `EntryRepository`
receives user IDs, resolves active `EntryAuthor` rows, and silently drops
ineligible users.

The pivot includes `sort_order`, so author order is stable.

## Categories

Entries use `HasCategories`, a polymorphic many-to-many relation through
`categorizables`. `EntryRepository::syncCategories()` currently accepts category
IDs and syncs them directly.

Entry groups can also be associated with category groups via the
`category_groupables` pivot. That association is useful for admin UI/schema
context, but current low-level category sync does not itself enforce that a
submitted category belongs to one of the entry group's category groups.

## Entry Tree And Public Routing

Entry types with `has_entry_tree = true` can have one `EntryTree` node per entry.
The tree stores:

- `entry_id`
- `parent_id`
- `handle`
- `uri`
- `depth`
- `sort_order`
- `template`
- `is_home`

`EntryService::createTreeNode()`:

- Requires the entry type to support Entry Tree.
- Slugifies the handle.
- Enforces home-node-at-root and only one home node globally.
- Enforces unique handle within a parent.
- Builds URI from ancestors.

`EntryService::moveTreeNode()` prevents self-parenting, moving below a
descendant, and moving the home node below another node. It rebalances sibling
sort order and rebuilds URIs/depths for the moved subtree.

`EntryTreeObserver` handles deletion by promoting direct children to root nodes
and rebuilding their URIs after the database nulls `parent_id`.

Public routing is handled by `SiteRouter`, which tries route drivers in
`config('site.routing.priority')`. The `entry_tree` driver:

- Normalizes the requested URI.
- Looks up an `entry_trees.uri`.
- Requires the related entry to be `published()`.
- Chooses template in this order:
  1. tree node `template`
  2. entry type `default_template`
  3. `entries.show`

## Metrics

`EntryService::recordMetric()` increments a daily metric row using an upsert.
The unique key is `entry_id + metric + recorded_date`, and the update expression
adds the incoming value to the existing total.

`Entry::metricTotal($metric, ?Carbon $from)` sums metric values in the database,
optionally from a date forward.

## Querying

`EntryService::query()` returns `EntryQueryBuilder`, a small fluent wrapper
around Eloquent.

Supported chain methods include:

- `inGroup()`
- `ofType()`
- `published()`
- `withStatus()`
- `withAuthor()`
- `withCategory()`
- `where()`
- `whereField()`
- `latest()`
- `orderBy()`

Terminal methods:

- `get()`
- `paginate()`
- `first()`
- `firstOrFail()`
- `count()`

The builder eager-loads the standard relations needed for field access:

- `entryGroup`
- `entryType`
- `creator`
- `authors`
- `categories`
- `fieldValues.field.fieldType`
- `entryRelationships.field`
- `entryRelationships.relatedEntry`

`whereField()` supports scalar fields only. It resolves the field's storage
column through the field type, then applies a `whereHas('fieldValues')` clause.
Relationship fields throw an `InvalidArgumentException`.

## API Layer

API controllers live in `App\Http\Controllers\Api\v1`.

`Api\v1\Entries` now implements:

- `index()`
- `store()`
- `show()`
- `update()`
- `destroy()`

The API uses `StoreEntryRequest` and `EditEntryRequest` for shape and dynamic
field validation. The `EntryResource` returns entry-shaped data including core
attributes, `fields`, authors, and categories.

Older docs still say this layer is mostly scaffolded; that is partly stale.
There are still API risks listed below, especially around entry type handle
resolution and field eager loading in `index()`.

## Admin Layer

Admin controllers live under `App\Http\Controllers\Admin`. Admin views are Twig
templates under `resources/views/admin`, using the `admin::` namespace.

Entry-related admin routes are under `/admin/entries`. Request classes authorize
specific permissions such as:

- `create entry group`
- `create entry type`
- `create entry`
- `edit entry`
- `delete entry`

New code should delegate writes to services/facades rather than writing directly
to `Entry`.

## Validation Layers

Validation happens in several places:

1. Form requests validate HTTP payload shape and dynamic field Laravel rules.
2. `AbstractEntryType::validate()` runs in `EntryService` before repository
   writes.
3. Repository methods validate status membership and default status presence.
4. Field type classes expose `getRules()` for Laravel validation rules. Some
   field types also still expose ad hoc `validate()` methods, but the preferred
   direction is to consolidate field validation into `getRules()`.

The current field validation path primarily uses `AbstractField::getRules()`.
Settings-aware field validation such as `Relationship` limits and `FileUpload`
library/MIME/min/max checks should move into Laravel validation rules returned
from `getRules()`. Custom `Rule` objects should be encouraged for richer checks
that cannot be expressed cleanly as string rules.

## Extension Checklist

When adding or changing Entry behavior, update all relevant layers:

1. Add or update the `AbstractEntryType` subclass in `app/EntryTypes/`.
2. Register the type row in seeders/admin with the fully qualified class name.
3. Add fields, field groups, and field layouts if the schema changes.
4. Ensure the entry group has the correct status group.
5. Use `EntryService`/`Entries`/`Content` for writes.
6. Add tests around lifecycle hooks, status behavior, field persistence, and tree
   behavior if touched.
7. Run `vendor/bin/pint --dirty`.
8. Run `composer test`.
9. Run `php artisan app:validate-class-references` after touching stored class
   names.
10. Run `php artisan optimize:clear` after touching config, routes, or service
    providers.

## Potential Issues And Risks

### High Priority

1. **Entry type handles are scoped in the database but resolved globally in code.**
   `entry_types` enforces uniqueness on `(entry_group_id, handle)`, but
   `EntryTypeRegistry::resolveByHandle()` queries by `handle` only and caches by
   handle only. `EntryService::create()` also accepts only a type handle. If two
   groups use the same type handle, creation and lifecycle resolution can select
   the wrong type. The API route includes `{group_id}`, but the service path does
   not use it.

   **Recommended solution:** Make entry type resolution group-aware. Add
   `EntryTypeRegistry::resolveByHandleInGroup(string $handle, int|EntryGroup $group)`
   and cache by `"{$groupId}:{$handle}"`, or resolve by entry type ID once the
   request has validated the group/type pair. Update `EntryService::create()` to
   accept a group ID or `EntryGroup` alongside the type handle, then update API
   and admin callers to pass route group context. Keep a deprecated
   `resolveByHandle()` wrapper only for seeded/global one-off usages, and add a
   test with two groups sharing the same type handle.

2. **Handle generation must stay disabled.**
   This used to be a risk because `EntryRepository::applyCoreAttributes()`
   derived a missing handle from the title. That behavior has been removed:
   create-time repository writes require an explicit handle, HTTP create/update
   requests require handles, and update writes only touch the handle when the
   `handle` key is present.

   **Recommended solution:** Keep this covered with regression tests. Any future
   UI convenience should generate a suggested handle in the browser/form layer
   before submit, not inside `EntryRepository` or `EntryService`.

3. **Tree-enabled entry creation is not atomic with the entry write.**
   `EntryRepository::create()` commits the entry and runs `afterCreate()` before
   `EntryService::create()` creates the tree node. If tree creation fails because
   of parent, uniqueness, or home-node rules, the entry remains committed and
   after-create side effects may already have fired.

   **Recommended solution:** Move the whole create operation, including tree node
   creation, under one service-owned transaction. Do not silently move the
   existing `afterCreate()` hook to `DB::afterCommit()`: that would change the
   semantic contract for every existing `AbstractEntryType` subclass because
   callers currently regain control only after `afterCreate()` has run. Instead,
   keep `afterCreate()` synchronous for compatibility and introduce an explicit
   committed hook such as `afterCreateCommitted(Entry $entry, array $data): void`
   for webhooks, emails, jobs, and other external side effects. Validate tree
   placement before persisting where possible so duplicate handles, invalid
   parents, and home-node violations fail early. Add a migration note explaining
   the hook timing distinction, and add a test proving failed tree creation
   leaves no entry row.

4. **`afterUpdate()` side effects can run before the outer update transaction commits.**
   `EntryService::update()` wraps repository application and tree sync in an
   outer transaction. `EntryRepository::applyData()` runs `afterUpdate()` after
   its inner transaction, but still inside the service's outer transaction. If a
   hook sends a webhook/email and later tree sync fails, the database can roll
   back while the side effect has already happened.

   **Recommended solution:** Add explicit post-commit lifecycle hooks rather than
   changing the timing of `afterUpdate()`. Keep `afterUpdate()` synchronous for
   compatibility, because existing entry type classes may rely on its side
   effects completing before `Content::update()` returns. Add a new committed
   hook such as `afterUpdateCommitted(Entry $entry, array $data): void` and run
   it through `DB::afterCommit()` after the service completes repository writes
   and tree sync. Document that synchronous `afterUpdate()` is for in-process
   behavior that must complete before the caller resumes, while committed hooks
   are for external side effects. Include a migration note and tests covering
   both hook timings and rollback behavior.

5. **Create lifecycle data is inconsistent between `beforeCreate()` and `afterCreate()`.**
   `EntryRepository::create()` mutates `$data` inside the transaction with
   `beforeCreate()`, but `afterCreate($entry, $data)` receives the original
   outer `$data`, not the mutated data returned by `beforeCreate()`.

   **Recommended solution:** Preserve the post-`beforeCreate()` payload and pass
   that same payload to `afterCreate()`. If both original and mutated data are
   useful, introduce a small lifecycle context object, but avoid changing the
   public hook signature unless necessary. Add a lifecycle test where
   `beforeCreate()` adds a field/core value and `afterCreate()` asserts it can
   see the mutated value.

6. **Status handle changes are not propagated or blocked.**
   Entries denormalize `status_handle`, but `StatusObserver` only propagates
   `is_public` changes. Renaming a status handle leaves existing entries with
   the old `status_handle`. `TODOS.md` already calls for blocking status handle
   changes when entries are assigned.

   **Recommended solution:** Prefer blocking handle changes once a status has
   assigned entries, matching the TODO and preserving handles as stable workflow
   identifiers. Enforce this in the status update action/request layer and back
   it with a service-level guard. If renames must be allowed later, perform them
   in one transaction that updates both `statuses.handle` and
   `entries.status_handle`, then clear relevant caches.

7. **There is no strong single-default-status invariant.**
   The schema allows multiple `is_default = true` statuses in one group. Entry
   creation uses the first loaded default, which can make default status behavior
   order-dependent.

   **Recommended solution:** Enforce exactly one default per status group in the
   status service/action layer. When setting a status as default, unset all other
   defaults in the same group inside the same transaction. Add validation that a
   status group cannot be left with zero defaults if entries can be created in
   groups using it. Where supported by the database, consider a partial unique
   index; because SQLite/MySQL support differs, keep application enforcement and
   tests as the portable baseline.

8. **Field type settings-aware validation is split across two paradigms.**
   Form requests use `getRules()` from field types, while richer checks on
   `Relationship` and `FileUpload` currently live in ad hoc `validate()` methods.
   This creates two validation paths and makes it easy for settings-aware checks
   such as limits, library restrictions, MIME restrictions, and relationship
   constraints to be bypassed.

   **Recommended solution:** Make `getRules()` the single canonical field-type
   validation contract and allow it to return full Laravel validation rules,
   including custom `Rule` objects. Move settings-aware checks from field
   `validate()` methods into rules generated by `getRules()` from the field
   instance/settings. Add a central layout field validator used by both
   FormRequests and `EntryService`; it should resolve the effective layout, add
   required/nullable from `FieldLayoutTabElement`, merge the field type's
   `getRules()`, and return field-keyed validation messages before repository
   writes. Once migrated, deprecate and remove field-type `validate()` methods.

### Medium Priority

9. **Unknown or out-of-layout field handles are silently ignored.**
   This is convenient for partial payloads but can hide API/client mistakes and
   migration problems. Consider strict mode for API writes or warnings in
   validation.

   **Recommended solution:** Add strict validation for external writes. For API
   and admin requests, reject any `fields.*` handle that is not present in the
   effective entry layout. Keep repository-level silent skipping only as a final
   defensive layer, or add an explicit `$strict = true` option to
   `setFieldValue()`/`applyFieldValues()` for service callers.

10. **Direct service writes can bypass FormRequest required-field validation.**
    `EntryService` calls only the entry type's `validate()` method, not a central
    field-layout validator. Code paths that call services directly can omit
    required field layout values unless the entry type class handles them.

    **Recommended solution:** Move required field validation into the service
    layer through the same central layout field validator described above.
    FormRequests can still provide early HTTP feedback, but `EntryService`
    should be the final consistency gate before repository writes.

11. **Entry category sync does not enforce the group's allowed category groups.**
    Requests validate that category IDs exist, and the repository syncs them.
    There is no repository-level check that submitted categories belong to a
    category group attached to the entry group.

    **Recommended solution:** Validate category IDs against the entry group's
    attached category groups before syncing. Put the reusable check in
    `EntryRepository` or an `EntryCategoryValidator`, and call it from both
    create and update. Decide whether an empty attached-category-groups set means
    "no categories allowed" or "all categories allowed"; document and test that
    rule.

12. **Entry author sync silently drops ineligible users.**
    This is a useful safety net, but API callers may think an author was saved
    when it was filtered out. Consider returning validation errors when submitted
    author IDs do not resolve to active `EntryAuthor` records.

    **Recommended solution:** Treat unresolved/inactive submitted author IDs as
    validation errors in FormRequests and service-level validation. Keep the
    filtering in `syncAuthors()` as a second safety net, but expose a clear 422
    response for API/admin writes so callers know their author assignment failed.

13. **Field value storage can leave stale typed-column data.**
    `FieldValue::updateOrCreate()` writes only the active storage column. If a
    field changes type or a `Number` field changes decimal settings, old values
    can remain in the previously used column. Type changes with existing values
    are blocked in `EditField`, but settings-driven storage-column changes are
    still possible.

    **Recommended solution:** When writing a field value, clear all non-active
    value columns in the same update. Also block settings changes that would
    change `storageColumn()` when values already exist, unless a migration tool
    explicitly moves the data. For `Number`, changing `decimals` from `0` to
    positive, or back, should be treated like a storage migration.

14. **`FieldService::getFieldType()` queries a non-existent `handle` column.**
    The `field_types` table has `name`, `object`, and `settings`, not `handle`.
    The method appears unused, but it will fail if called.

    **Recommended solution:** Either remove the unused method or change its
    contract to match the schema. Practical options are
    `getFieldTypeByClass(string $class)`, `getFieldTypeByObject(string $object)`,
    or resolving by instantiated `AbstractField::handle()` in memory, as
    `EditField` already does. Add a small unit test if the method remains public.

15. **`EntryTypeService::delete()` documentation contradicts the schema.**
    The service comment says associated entries cascade, but
    `entries.entry_type_id` is `restrictOnDelete()`. Deleting an entry type with
    entries should fail rather than cascade.

    **Recommended solution:** Update the documentation comment and add an
    explicit guard that throws a domain-friendly validation exception when the
    type has entries. If cascading entry deletion is desired someday, that should
    be a deliberate product decision and migration change, not an accidental
    service behavior.

16. **`EntryGroupService::create()` is not wrapped in a transaction.**
    It creates a field layout, then creates the group, then syncs pivots. A
    failure after layout creation can leave an orphaned field layout.

    **Recommended solution:** Wrap layout creation, group creation, and pivot
    syncs in a single `DB::transaction()`. Return the fresh group with layout and
    pivots loaded if the admin UI needs them.

17. **`EntryGroupService::create()` ignores caller-provided `field_layout_id`.**
    The request permits `field_layout_id`, but the service always creates a new
    layout and uses that. This may be intentional, but the request/service
    contract is inconsistent.

    **Recommended solution:** Pick one contract. If auto-layout creation is the
    intended behavior, remove `field_layout_id` from the create request and UI.
    If reusing an existing layout should be allowed, use the provided
    `field_layout_id` and create a layout only when it is absent. In either case,
    document the behavior in this file and cover it with a service test.

18. **Store and edit requests treat Entry Tree parent validation differently.**
    Store allows `parent_id` to be any existing entry ID, while edit requires the
    parent entry to already have an `entry_trees.entry_id`. On store, a parent
    without a tree node resolves to `null` and the new node becomes root.

    **Recommended solution:** Make create validation match update validation:
    `parent_id` should reference `entry_trees.entry_id` when the selected entry
    type has tree routing enabled. Also add a service guard in
    `resolveTreeParentNode()` or `createTreeNode()` that rejects a requested
    parent ID with no tree node instead of silently treating it as root.

19. **`max_depth` and `allowed_parent_types` are not enforced.**
    These columns are present on `entry_types` and exposed by requests/services,
    but `createTreeNode()` and `moveTreeNode()` do not enforce them yet.

    **Recommended solution:** Add placement validation to `createTreeNode()` and
    `moveTreeNode()`. `max_depth` should compare the target depth against the
    moving/created entry type's configured limit. `allowed_parent_types` should
    validate the parent node's `entry.entryType.handle` or ID against the child
    type's allow-list. For moves, validate the full moved subtree so descendants
    do not exceed their own depth constraints after relocation.

20. **API entry index eager-loads less than `EntryResource` expects.**
    `Api\v1\Entries::index()` eager-loads `fieldValues`, `authors`, and
    `categories`, but `EntryResource::fieldArray()` benefits from
    `fieldValues.field.fieldType`. Because `FieldValue` and `Field` have `$with`
    defaults this may work today, but the query shape is less explicit and more
    fragile than `Content::query()` or repository eager loads.

    **Recommended solution:** Reuse the content query/repository eager-load set
    for API index responses, or update the controller to eager-load
    `fieldValues.field.fieldType`, `entryRelationships.field`, and
    `entryRelationships.relatedEntry` explicitly. Prefer routing list queries
    through `EntryService::query()` where possible so API, admin, and service
    reads stay consistent.

### Lower Priority / Design Debt

21. **`EntryRepository` duplicates fieldable persistence logic instead of using `AbstractFieldableRepository`.**
    This is partly because entries have relationship fields, but scalar field
    upsert and layout resolution patterns now differ between repositories.

    **Recommended solution:** Extract the shared scalar-field write path into a
    reusable helper or make `EntryRepository` extend `AbstractFieldableRepository`
    while overriding the entry-specific relationship branch. Keep relationship
    sync entry-specific, but share column clearing, race-safe upsert, and layout
    lookup conventions.

22. **`field_layout_tab_elements` has contextual columns that are not fully surfaced.**
    The migration includes `hidden`, `readonly`, `disabled`, `schema_property`,
    `label`, and `instructions`, but the model fillable/actions/requests mostly
    use only `required` and `sort_order`. Future Search/SEO work is already
    planned to rely on this table as the "field in context" layer.

    **Recommended solution:** Promote `FieldLayoutTabElement` as the official
    "field in layout context" model. Add the missing columns to `$fillable`,
    requests, actions, resources, and admin forms as needed. For field rendering
    and validation, prefer element-level label/instructions/visibility over the
    base field when present. Coordinate this with Search/SEO migrations so the
    table does not get redesigned twice.

23. **Status deletion behavior is weak for publishing workflows.**
    `entries.status_id` nulls on status delete while denormalized status fields
    remain. There are TODOs for soft deletes, preventing default status deletion,
    and guarding status handle changes.

    **Recommended solution:** Add soft deletes to statuses, block deletion of the
    default status, and block deletion of statuses assigned to entries unless the
    request includes an explicit replacement status in the same group. If
    replacement is provided, update entries in one transaction before deleting or
    archiving the old status.

24. **Entry Tree URI and home-node uniqueness are global.**
    This is fine pre-tenancy, but `TenantPlan.md` will need to scope URI and home
    uniqueness by tenant.

    **Recommended solution:** During tenancy, add `tenant_id` to `entry_trees`,
    replace global `uri` uniqueness with `(tenant_id, uri)`, and enforce one home
    node per tenant at the application layer or with a database-specific partial
    unique index where available. Update `EntryTreeRouteDriver` to rely on tenant
    scoping before lookup.

25. **Service singletons will need review before tenancy.**
    `ContentServiceProvider` binds `EntryTypeRegistry`, `EntryRepository`, and
    `ContentService` as singletons. The tenancy plans already call out singleton
    bindings as dangerous once tenant context becomes request-scoped.

    **Recommended solution:** Before enabling tenant context, convert services
    and registries that depend on request/tenant state from `singleton` to
    `scoped` or transient bindings. For `EntryTypeRegistry`, include tenant/group
    context in cache keys or make the registry request-scoped so cached records
    cannot bleed across tenants.

26. **The route driver's template config support is incomplete.**
    Existing docs note that `site.templates.base_path` and
    `site.templates.not_found_template` are configured but not currently used by
    route drivers.

    **Recommended solution:** Either wire these config values into
    `TemplateRouteDriver`/`SiteRouter` or remove the unused keys. If wired,
    centralize template name normalization so `entry_tree` and `template` drivers
    resolve templates consistently and the not-found template is used before
    throwing `NotFoundHttpException`.

27. **Older documentation is partially stale.**
    `docs/OVERVIEW.md` says the Entries API and `EntryResource` are mostly
    scaffolded/user-shaped. Current code is more complete. Future doc updates
    should reconcile this file, `OVERVIEW.md`, `CLAUDE.md`, and `ACTION_PLAN.md`.

    **Recommended solution:** Treat this file as the Entry-layer source of truth
    and update `docs/OVERVIEW.md` to link here instead of duplicating detailed
    Entry internals. Keep `CLAUDE.md` and `AGENTS.md` concise, pointing agents to
    this document for deep Entry guidance. Add a short documentation maintenance
    note whenever the Entry API, field lifecycle, or tree behavior changes.
