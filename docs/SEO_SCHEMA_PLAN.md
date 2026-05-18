# SEO — Schema.org JSON-LD Generation Plan

## The Core Idea

Emit structured `<script type="application/ld+json">` blocks from Twig templates
by resolving a per-Entry `schema_type` against a registry of generator classes.
Each generator reads field values whose schema mapping is declared at the
`FieldLayoutTabElement` level — the same junction table where Search V2 declares
`is_searchable` and `search_weight`. The field object itself stays schema-agnostic;
schema behaviour is a layout concern, not a field concern.

The output is always one or two JSON-LD blocks per page: the primary schema type
(e.g. `Article`, `WebPage`, `Event`) and, when the entry has an `EntryTree` node,
a `BreadcrumbList` derived from the ancestor chain. Both blocks are emitted by a
single Twig function call.

---

## Design Decisions

These decisions were settled during the initial design discussion. They are recorded
here so the rationale is not lost.

**`schema_type` lives on `entries`, not `entry_types`.**
An entry of a "General Page" type needs to declare itself as `Article`, `WebPage`,
or `Event` independently of other entries of the same type. Locking schema type to
`entry_type` would force a new type for every schema variant, defeating the purpose
of flexible field layouts. `entry_types.default_schema_type` provides a convenient
seed value so new entries inherit a sensible default, but the entry's own column
is the only value read at generation time.

**Null `schema_type` means no schema — no inheritance fallback.**
If `entries.schema_type` is null, the generator exits silently. There is no
group-level or type-level fallback at read time. The `default_schema_type` on
`entry_types` only controls what value is written to `entries.schema_type` when a
new entry is created; after that the entry owns its value.

**`schema_property` lives on `field_layout_tab_elements`, not on `fields`.**
A `Field` is a global object. The same field can appear in a Blog layout, a User
layout, and an Event layout. The `FieldLayoutTabElement` is the "field in context"
junction — the right place to say "in this layout, this field maps to
`schema_property = description`." This mirrors exactly how Search V2 places
`is_searchable` and `search_weight` on the same table. A single `description`
field can map to `description` in one layout and `abstract` in another without
duplicating the field.

**`schemaValue()` is added to `AbstractField`, not to the generator.**
Field types know their own storage format best. A `Date` field knows to emit
ISO 8601. A future `MediaField` will know to emit an `ImageObject` with `@type`,
`url`, `width`, and `height`. Keeping the formatting concern inside the field type
means generator classes stay thin and schema quality improves automatically when
field types become richer — no changes to the generator layer required.

**The `Relationship` field type emits null from `schemaValue()` for now.**
The Relationship field type is slated for a broader redesign. Rather than
special-casing it in the schema layer, `schemaValue()` returns null and the
field is silently excluded from schema output until the redesign lands. The author
relationship — the most common relational schema concern — is handled separately
via the core `entry.authors` BelongsToMany, not through the field mapping loop.

**BreadcrumbList is conditional, not a separate generator.**
If `$entry->entryTree` exists, the `AbstractSchemaGenerator` appends a
`BreadcrumbList` block to the output alongside the primary schema type. If not,
nothing is appended. No configuration required — the tree presence is the signal.

**`resolveLayoutElements` is a new method; `resolveLayoutFields` is untouched.**
`resolveLayoutFields` has two live call sites (both internal to the repository) and
is exposed publicly via `EntryService::resolveFields()`. Changing its return type
from `Field` to `FieldLayoutTabElement` would break those callers silently — and
access to flat Field collections from a layout is a genuinely useful primitive worth
keeping. Instead, a parallel `resolveLayoutElements` method is added to
`EntryRepository`, and a parallel `elements()` method is added to `FieldLayout`
alongside the existing `fields()`. The schema and search layers call
`resolveLayoutElements`; all existing callers of `resolveLayoutFields` are unchanged.
Deduplication in the new method uses `field_id` (the foreign key on the element)
rather than `id` (the element's own primary key), preserving the same type-over-group
precedence logic.

**Twig rendering only; no REST API schema output.**
Schema.org JSON-LD is a browser/crawler concern. Exposing it via `EntryResource`
adds payload to a REST API that has no use for it. The Twig function is the only
rendering surface.

**FAQPage is a known gap.**
`FAQPage` requires a `mainEntity` array of `Question` / `Answer` objects — a
structured, repeating shape that flat field mapping cannot produce cleanly. It is
deferred until a `RepeaterField` type exists that can emit its rows as structured
JSON from `schemaValue()`.

---

## Data Model

### `entries` additions

```
entries (additions)
--------------------------------------------------
schema_type    string nullable    e.g. 'Article', 'WebPage', 'Event'
```

Null means no schema emitted for this entry. Seeded from `entry_types.default_schema_type`
at entry-creation time by the repository; the entry owns the value from that point.

### `entry_types` additions

```
entry_types (additions)
--------------------------------------------------
default_schema_type    string nullable    seed value written to entries.schema_type on create
```

Not read at generation time. Purely a UX convenience so new entries of a given type
start with a sensible schema type without manual selection.

### `field_layout_tab_elements` additions

```
field_layout_tab_elements (additions)
--------------------------------------------------
schema_property    string nullable    schema.org property name, e.g. 'description', 'articleBody'
```

Null means this element does not participate in schema output. The same field can
carry different `schema_property` values across different layouts.

No new tables are introduced. All three columns are additive and nullable — no
existing behaviour changes.

---

## Architecture

```
App\Schema\
  Contracts\
    SchemaGeneratorInterface
  Generators\
    AbstractSchemaGenerator        common fields, author block, BreadcrumbList
    ArticleSchemaGenerator
    BlogPostingSchemaGenerator
    WebPageSchemaGenerator
    EventSchemaGenerator
  Registry\
    SchemaGeneratorRegistry        maps schema_type string → generator class
  EntrySchemaService               public API; resolves generator, drives output
App\Twig\Extensions\
  SchemaExtension                  registers schema_json(entry) Twig function
```

---

## Generator Layer

### SchemaGeneratorInterface

```php
interface SchemaGeneratorInterface
{
    public function generate(Entry $entry, string $baseUrl): array;
}
```

Returns an array of schema blocks — typically one primary block, optionally followed
by a `BreadcrumbList` block. The Twig layer iterates the array and emits one
`<script>` tag per block.

### AbstractSchemaGenerator

Handles everything shared across schema types:

**Core fields** — populated directly from the Entry model without field mapping:

| Schema property   | Source                          |
|-------------------|---------------------------------|
| `@context`        | `"https://schema.org"` (static) |
| `@type`           | `$entry->schema_type`           |
| `name`            | `$entry->title`                 |
| `url`             | `$baseUrl . $entry->entryTree->uri` (if tree exists) or `$baseUrl . '/' . $entry->handle` |
| `datePublished`   | `$entry->published_at` ISO 8601 |
| `dateModified`    | `$entry->updated_at` ISO 8601   |

**Author block** — populated from `$entry->authors` (BelongsToMany → User), not
from field mapping. Each author is emitted as a `Person` object:

```json
"author": [
  { "@type": "Person", "name": "Jane Smith" }
]
```

Omitted entirely when `$entry->authors` is empty.

**Field mapping loop** — iterates the resolved layout elements:

```php
$elements = $this->repository->resolveLayoutElements($entry);

foreach ($elements as $element) {
    if (! $element->schema_property) continue;

    $value    = $entry->field($element->field->handle);
    $rendered = $element->field->fieldType->instance()->schemaValue($value);

    if ($rendered !== null) {
        $schema[$element->schema_property] = $rendered;
    }
}
```

**BreadcrumbList** — appended as a second block when `$entry->entryTree` is
present. See the BreadcrumbList section below.

### Concrete Generators

Concrete generators extend `AbstractSchemaGenerator` and may:

- Override the `@type` string if the entry's `schema_type` value does not exactly
  match a schema.org type name (e.g. an internal alias).
- Remap or enforce specific properties (e.g. `ArticleSchemaGenerator` ensures
  `headline` is present and sourced from `$entry->title` even if the field mapping
  loop would otherwise overwrite it).
- Add type-specific static values (e.g. `BlogPostingSchemaGenerator` could add
  a default `publisher` block from site settings).

**Initial set:**

| Generator                  | schema.org type   | Notes                                      |
|----------------------------|-------------------|--------------------------------------------|
| `WebPageSchemaGenerator`   | `WebPage`         | Generic fallback; broadest applicability   |
| `ArticleSchemaGenerator`   | `Article`         | News, editorial, long-form                 |
| `BlogPostingSchemaGenerator` | `BlogPosting`   | Extends Article; same generator, different type string |
| `EventSchemaGenerator`     | `Event`           | Expects `startDate`, `endDate`, `location` from field mapping |

### SchemaGeneratorRegistry

Maps `schema_type` string → generator class name. Registered via a service provider.

```php
$registry->register('WebPage',     WebPageSchemaGenerator::class);
$registry->register('Article',     ArticleSchemaGenerator::class);
$registry->register('BlogPosting', BlogPostingSchemaGenerator::class);
$registry->register('Event',       EventSchemaGenerator::class);
```

Returns null for unrecognised types; `EntrySchemaService` exits silently in that case.

---

## Field Layer — `schemaValue()`

`AbstractField` gains one method:

```php
public function schemaValue(mixed $value): mixed
{
    return $value; // default: pass through as-is
}
```

Concrete field types override where the storage format and the schema format differ:

| Field type      | `schemaValue()` behaviour                                        |
|-----------------|------------------------------------------------------------------|
| `Text`          | returns string as-is (default)                                   |
| `Textarea`      | returns string as-is (default)                                   |
| `Html`          | returns string as-is; caller decides whether to strip tags       |
| `Date`          | formats to ISO 8601 string (`Y-m-d` or `Y-m-d\TH:i:sP`)        |
| `Boolean`       | returns PHP bool                                                 |
| `Number`        | returns int or float (already cast)                              |
| `EmailAddress`  | returns string as-is (default)                                   |
| `Url`           | returns string as-is (default)                                   |
| `ColorPicker`   | returns null — no meaningful schema.org mapping                  |
| `Relationship`  | returns null — deferred until Relationship field redesign        |

Future `MediaField` (post media-refactor) will return an `ImageObject` array:

```json
{ "@type": "ImageObject", "url": "...", "width": 1200, "height": 630 }
```

---

## BreadcrumbList

When `$entry->entryTree` exists, `AbstractSchemaGenerator` calls a protected
`buildBreadcrumbList()` method and appends the result as a second schema block.

### Ancestor Walk

The walk is iterative: start at the entry's `EntryTree` node, follow `parent_id`
until null, collect nodes in reverse order. Each node needs the related Entry's
`title` for the `name` property, so `entry` is eager-loaded at each step.

```
entry_trees node chain (root → leaf):
  depth 0  is_home=true  uri=""          Entry: "Home"
  depth 1               uri="blog"       Entry: "Blog"
  depth 2               uri="blog/tips"  Entry: "Laravel Tips"
  depth 3               uri="blog/tips/query-scopes"  Entry: (current)
```

Output:

```json
{
  "@context": "https://schema.org",
  "@type": "BreadcrumbList",
  "itemListElement": [
    { "@type": "ListItem", "position": 1, "name": "Home",         "item": "https://example.com/" },
    { "@type": "ListItem", "position": 2, "name": "Blog",         "item": "https://example.com/blog" },
    { "@type": "ListItem", "position": 3, "name": "Laravel Tips", "item": "https://example.com/blog/tips" },
    { "@type": "ListItem", "position": 4, "name": "How to Use Query Scopes", "item": "https://example.com/blog/tips/query-scopes" }
  ]
}
```

### Query Strategy

**Phase 1: iterative parent walk.** Simple to implement; one query per ancestor
level. Acceptable for sites with shallow trees (≤ 5 levels deep), which covers
most use cases. `max_depth` on `EntryType` gives an upper bound.

**Phase 2 (if needed): recursive CTE.** A single raw query walks the full chain
in one round trip. Swap in when deep trees become common. The interface does not
change; only the implementation of `buildBreadcrumbList()` changes.

The home node (`is_home = true`) maps to `item: $baseUrl . '/'` regardless of its
`uri` value (which is typically an empty string for the root).

---

## EntrySchemaService

The public API. Lives alongside `EntryService` in `App\Services`.

```php
class EntrySchemaService
{
    public function __construct(
        protected SchemaGeneratorRegistry $registry,
        protected EntryRepository $repository,
    ) {}

    /**
     * Returns an array of schema blocks for the entry.
     * Returns an empty array if schema_type is null or unregistered.
     */
    public function generate(Entry $entry, string $baseUrl): array
    {
        if (! $entry->schema_type) return [];

        $generator = $this->registry->resolve($entry->schema_type);
        if (! $generator) return [];

        return $generator->generate($entry, $baseUrl);
    }
}
```

The `$baseUrl` parameter is passed in rather than read from config inside the
service, keeping the service testable without environment setup.

---

## Repository — `resolveLayoutElements` (New Method)

`resolveLayoutFields` is left entirely unchanged. A new parallel method is added
to `EntryRepository` and a new parallel primitive is added to `FieldLayout`.

### `FieldLayout::elements()`

Sits alongside the existing `fields()` method. Returns a flat Collection of
`FieldLayoutTabElement` models (with `field` and `field.fieldType` eager-loaded)
instead of unwrapping to Field models.

```php
// existing — unchanged
public function fields(): Collection
{
    $this->loadMissing('tabs.elements.field');
    return $this->tabs->flatMap(fn($tab) => $tab->elements->map(fn($el) => $el->field));
}

// new
public function elements(): Collection
{
    $this->loadMissing('tabs.elements.field.fieldType');
    return $this->tabs->flatMap(fn($tab) => $tab->elements);
}
```

### `EntryRepository::resolveLayoutElements()`

Mirrors `resolveLayoutFields` exactly but calls `elements()` instead of `fields()`,
and deduplicates on `field_id` (the foreign key on the element) rather than `id`
(the element's own primary key) to preserve the same type-over-group precedence.

```php
public function resolveLayoutElements(Entry $entry): Collection
{
    $entry->loadMissing([
        'entryGroup.fieldLayout.tabs.elements.field.fieldType',
        'entryType.fieldLayout.tabs.elements.field.fieldType',
    ]);

    $groupElements = $entry->entryGroup->fieldLayout?->elements() ?? collect();
    $typeElements  = $entry->entryType->fieldLayout?->elements() ?? collect();

    return $typeElements->merge($groupElements)->unique('field_id');
}
```

No existing callers are touched. `resolveLayoutFields` and `EntryService::resolveFields()`
continue to return flat Field Collections as before. Search V2 will use
`resolveLayoutElements` by the same contract when it lands.

---

## Twig Rendering Layer

`SchemaExtension` registers a single Twig function:

```twig
{{ schema_json(entry) }}
```

The function calls `EntrySchemaService::generate()`, iterates the returned blocks,
and emits one `<script type="application/ld+json">` tag per block. Returns an
empty string (not null) when no schema is configured, so it is safe in a base
template unconditionally.

```html
<script type="application/ld+json">
{"@context":"https://schema.org","@type":"Article","name":"How to Use Query Scopes",...}
</script>
<script type="application/ld+json">
{"@context":"https://schema.org","@type":"BreadcrumbList","itemListElement":[...]}
</script>
```

Separate blocks are preferred over a JSON array for debuggability and because
Google's Rich Results Test handles them independently.

The `$baseUrl` passed to the service is resolved from the `site.url` config key
inside the extension, keeping that concern out of templates.

---

## Known Gaps and Deferred Work

**FAQPage.**
`FAQPage` requires `mainEntity` — a repeating array of `Question` / `Answer`
objects. Flat field mapping cannot produce this shape. Deferred until a
`RepeaterField` type is available whose `schemaValue()` can emit the structured
array directly.

**Relationship field.**
`schemaValue()` returns null until the Relationship field type is redesigned.
Entries that use a Relationship field to drive a schema property (e.g. `mentions`,
`about`) will silently omit that property until then.

**`ImageObject` output.**
Until the media refactor lands and a `MediaField` type exists, image properties
in schema output will be bare URL strings (or absent). A bare URL string is valid
per the schema.org spec; an `ImageObject` is richer. Upgrade `MediaField`'s
`schemaValue()` as part of the media refactor.

**`BreadcrumbList` recursive CTE.**
The iterative parent walk is Phase 1. If query counts become problematic for deep
trees, replace with a recursive CTE. No interface changes required.

**Admin UI.**
The schema_type selector (on the entry edit form) and the schema_property selector
(on the field layout element admin) are not planned in detail here. They are
standard select inputs wired to the new columns; scope them as part of the admin
UI work that follows the core implementation.

---

## Delivery Phases

### Phase 1 — Data Model
Three additive nullable columns in a single migration:
- `entries.schema_type`
- `entry_types.default_schema_type`
- `field_layout_tab_elements.schema_property`

Seed `entry_types.default_schema_type` with null for all existing types.
No backfill of `entries.schema_type` needed — null is the correct default (no
schema emitted).

### Phase 2 — Repository and Model Additions
Add `FieldLayout::elements()` alongside the existing `fields()` method. Add
`EntryRepository::resolveLayoutElements()` alongside the existing
`resolveLayoutFields()`. No existing methods or callers are changed. Write unit
tests confirming the eager-load chain (`field.fieldType` present), that element
metadata (`schema_property`, `required`, `sort_order`) is accessible, that
deduplication on `field_id` preserves type-over-group precedence, and that an
empty Collection is returned when no layout is set.

Note: `resolveLayoutElements` is the same primitive Search V2 will need for
`is_searchable` and `search_weight`. If Search V2 has already landed by the time
this phase runs, this method may already exist — verify before duplicating.

### Phase 3 — Field Layer
Add `schemaValue(mixed $value): mixed` to `AbstractField` with a pass-through
default. Override in all concrete field types per the table in the Field Layer
section above. Add unit tests per type.

### Phase 4 — Generator Foundation
Implement `SchemaGeneratorInterface`, `AbstractSchemaGenerator` (core fields +
author block + field mapping loop; BreadcrumbList stub returning empty array),
`SchemaGeneratorRegistry`, and `EntrySchemaService`. Write unit tests with a
minimal concrete generator subclass. Confirm empty array returned for null
`schema_type` and unregistered types.

### Phase 5 — Concrete Generators
Implement `WebPageSchemaGenerator`, `ArticleSchemaGenerator`,
`BlogPostingSchemaGenerator`, and `EventSchemaGenerator`. Register all four in
the service provider. Write integration tests for each using seeded entries with
field values and schema_property mappings.

### Phase 6 — BreadcrumbList
Implement the iterative parent walk in `AbstractSchemaGenerator::buildBreadcrumbList()`.
Wire it into the `generate()` return array. Write tests for: single-node tree
(home only), shallow tree (2-3 levels), maximum plausible depth, entry with no
tree (BreadcrumbList omitted), home node URL handling.

### Phase 7 — Twig Layer
Implement `SchemaExtension`. Register via `AppServiceProvider` or a dedicated
`TwigServiceProvider`. Write tests confirming: correct `<script>` tag output,
empty string for no schema, two blocks when BreadcrumbList present, HTML safety
(JSON properly escaped).

### Phase 8 — Admin UI
Add `schema_type` as a select input on the entry edit form. Add `schema_property`
as a select input on the field layout element row in the admin. Populate both with
the supported schema.org types/properties as labelled options. These are additive
UI changes with no backend logic beyond saving to the new columns.

### Phase 9 — Tests and Documentation
Integration tests covering the full stack: entry with schema_type → Twig function
→ expected JSON-LD output. Update `OVERVIEW.md` to document the schema system.
Add usage examples to the relevant section (Entry Groups and Entry Types, and a
new SEO section).

---

## Ordering Within the Broader Plan

This plan has minimal overlap with Media, Tenancy, Search V2, and Shop:

- `field_layout_tab_elements` gains `schema_property` (this plan) alongside
  `is_searchable` and `search_weight` (Search V2). The two can land in separate
  migrations without conflict. If Search V2 lands first, Phase 2 of this plan
  (the `resolveLayoutElements` refactor) may already be partially done — check
  before duplicating work.
- Tenancy adds `tenant_id` to `entries`, `entry_types`, and
  `field_layout_tab_elements`. The schema columns are nullable strings that carry
  no tenant-specific logic; they require no per-tenant scoping. No conflict.
- The media refactor does not affect this plan's implementation. It upgrades
  `MediaField::schemaValue()` from a stub (null) to a real `ImageObject` emitter
  as a byproduct of that work — no changes to the schema layer itself.
- Shop entries will participate in schema output automatically once Shop tables
  are in place and `schema_type` is set on those entries; no schema layer changes
  needed for Shop.

Per `ACTION_PLAN.md`, this plan runs last — after Shop — as it has no blocking
dependencies on the other plans and the other plans have no dependency on it.
