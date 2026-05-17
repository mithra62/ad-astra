# Multilingual Content Layer Plan

## The Core Idea

Every Fieldable model (Entry, Category, Media, User) becomes translatable into
multiple locales by extending the existing morphic Fieldable pattern with a
`locale` dimension on `field_values`, a small per-model translation table for the
canonical text columns (`title`, `handle`, `name`), and per-locale rows on
`entry_trees` so URL routing materialises one path per locale. Reads fall back
to a configured default locale transparently; writes go to whichever locale the
editor is currently editing.

Translation is **gated at the container level** — `entry_groups.is_translatable`,
`category_groups.is_translatable`, `media_libraries.is_translatable`, and a
site-wide Settings flag for User. Editors only see locale machinery on records
whose container has opted in, keeping the admin surface quiet by default.

Translation is **decoupled from EntryTree**: a translatable EntryGroup can mix
tree-bound and non-tree EntryTypes, and every entry in the group is translatable
regardless. Tree-bound entries additionally get one `entry_trees` row per locale
so they have locale-prefixed public URLs (`/en/about`, `/fr/a-propos`); non-tree
entries are still translated but consumed via API or embedded in other pages.

---

## Design Decisions

These decisions were settled during the initial design discussion. They are
recorded here so the rationale is not lost.

**Content varies per locale; metadata does not.**
`status`, `published_at`, `authors`, `categories`, and `parent_id` stay global
on the canonical row. Only `title`, `handle`/`name`, and field values vary by
locale. A French translation cannot publish on a different schedule than the
English version, cannot have different authors, and cannot live under a
different parent in the tree. This keeps the schema lean and matches the way
editorial teams typically translate: the same content, in another language. If
per-locale editorial workflow becomes a real requirement, an
`entry_locale_metadata` table can be added later without disturbing the v1
design.

**Read-time fallback to default locale, with per-field-value granularity.**
When a request asks for French and the French row for a given field is empty,
the system transparently reads the default-locale row for that single field.
Granularity is per field value, not per record — a half-translated entry shows
its translated fields in French and its untranslated fields in English on the
same page. Fallback strategy is a Settings value
(`localization.fallback_strategy`), and `none` is also supported (render empty)
for editorial workflows that want enforcement.

**Translation gate lives on the container, not on the record.**
A site-wide flag is too coarse; a per-record flag is too noisy. The
`entry_groups.is_translatable` boolean (and the matching flags on
`category_groups`, `media_libraries`) is the right granularity — editors enable
translation on the "Blog" group when they want their posts translated and leave
"Internal Snippets" alone. The same pattern applies across all three container
models, giving editors one mental model for the entire CMS. User has no group,
so its gate is a site-wide Settings flag (`localization.users_translatable`).

**Translation is independent of EntryTree.**
An early proposal tied translation to EntryTree presence — "only routable
entries can be translated." This was revised. Categories and Media plainly need
translation (category names rendered on listings, image alt text per locale)
and have no tree concept, so a single "tree-gates-translation" rule cannot
cover all three Fieldable container models. The cleaner rule is that the
container's `is_translatable` flag controls whether translation data exists;
`EntryType.has_entry_tree` independently controls whether the entry gets a
public URL per locale. The two compose orthogonally:

| EntryType `has_entry_tree` | Group `is_translatable` | What exists |
|---|---|---|
| true  | true  | Translation rows + per-locale `entry_trees` rows. URLs: `/en/about`, `/fr/a-propos`. |
| false | true  | Translation rows only. Reached via API or embedded. |
| true  | false | Canonical row + one `entry_trees` row. URL: `/about`. |
| false | false | Canonical row only. Embed/API. |

**Default-locale storage stays on the canonical row.**
`entries.title`/`entries.handle` (and equivalents on `categories`, `media`)
*are* the default-locale storage. Only non-default-locale data is written to
the `*_translations` tables. This avoids duplicating data, keeps the existing
`findByHandle()` lookups working, lets admin list views render without joining
a translation table, and means a freshly-seeded single-locale install has zero
rows in the translation tables. The translation tables are an extension, not a
replacement.

**Per-field translatability is a property of the Field.**
`fields.is_translatable` is a boolean on the field itself. When true (and the
container is translatable), the field's `field_values` rows are keyed by
locale; when false, the field stores once with `locale = default_locale`
regardless of which locale form was submitted. Type-based defaults are applied
at seed time:

- **true**: `text`, `textarea`, `html`, `email_address`, `url`, `telephone`,
  `color_picker`
- **false**: `number`, `boolean`, `date`, `slider`, `select`, `radio_group`,
  `multi_select`, `relationship`, `users`, `file_upload`, `structured_rows`

Editors override per field in the Field settings UI. The effective rule is
`(container.is_translatable AND field.is_translatable)` — a field never becomes
locale-keyed unless both flags are true.

**Locale-prefixed URLs on the `entry_trees` table.**
Per-locale routing uses path prefixes: `/en/about`, `/fr/a-propos`. The
`entry_trees` table gains a `locale` column and one row per entry per locale,
each with its own `handle` and `uri`. URIs are stored locale-relative (e.g.
`/about`); the public URL is the locale prefix + URI, composed at render time.
A new `SetLocaleFromUri` middleware strips the prefix before the SiteRouter
runs. Path prefixes beat subdomains for v1 because every dev environment
handles them out of the box and SEO is straightforward.

**Parent-translation rule: refuse before fall back.**
When creating a translation of a tree-bound child entry whose parent has not
been translated to that locale yet, the operation refuses with a clear error
("You must translate {parent.title} first"). Editors translate top-down. The
alternative — auto-creating stub parent rows or mixing locales in a URI path —
creates ghost rows and unpredictable URLs.

**StructuredRows is not translatable in v1.**
The StructuredRows field type stores arbitrary JSON whose columns may be a mix
of text, numbers, dates, and selects. A single `is_translatable` flag on the
parent field is all-or-nothing — translating the whole row duplicates the
non-text columns unnecessarily, not translating it leaves the text columns
unaddressed. Per-column translatability is the right answer but adds noticeable
scope (settings UI, storage merge logic). v1 ships with
`is_translatable=false` and the toggle locked; v2 adds the per-column flag.

**API locale precedence: query > Accept-Language > default.**
A `?locale=fr` query parameter wins if present. Otherwise the system parses the
`Accept-Language` header. Otherwise the configured `default_locale` applies.
Single source of truth lives in a request macro
(`ResolveRequestLocale`) so resources, controllers, and form requests read
locale the same way.

**Slug pre-fill on translation creation.**
Opening "create translation" pre-fills `handle` with the default-locale handle
so editors who do not want to re-slug can save immediately. `Str::slug` is
called with the locale code so non-Latin scripts get appropriate
transliteration where Laravel supports it. Editors override the handle
manually for SEO-driven slugs.

---

## Data Model

### `field_values` additions

```
field_values (additions)
--------------------------------------------------
locale    string(10) NOT NULL

unique  (field_id, fieldable_id, fieldable_type, locale)
        ← replaces the existing (field_id, fieldable_id, fieldable_type) unique
```

Non-translatable fields write a single row with `locale = default_locale`;
reads ignore the locale parameter for those rows. Translatable fields can hold
one row per locale.

### `fields` additions

```
fields (additions)
--------------------------------------------------
is_translatable    boolean default false
```

Seeders apply type-based defaults at install time per the design decision above.

### Container-level flags

```
entry_groups       + is_translatable    boolean default false
category_groups    + is_translatable    boolean default false
media_libraries    + is_translatable    boolean default false
```

### Translation tables (new)

```
entry_translations
--------------------------------------------------
id              bigint primary
entry_id        bigint FK→entries cascadeOnDelete
entry_group_id  bigint FK→entry_groups cascadeOnDelete   (denormalised)
locale          string(10)
title           string
handle          string
timestamps

unique (entry_id, locale)
unique (entry_group_id, locale, handle)
```

```
category_translations
--------------------------------------------------
id            bigint primary
category_id   bigint FK→categories cascadeOnDelete
group_id      bigint FK→category_groups cascadeOnDelete  (denormalised)
locale        string(10)
name          string
handle        string
timestamps

unique (category_id, locale)
unique (group_id, locale, handle)
```

```
media_translations
--------------------------------------------------
id        bigint primary
media_id  bigint FK→media cascadeOnDelete
locale    string(10)
name      string
timestamps

unique (media_id, locale)
```

`entry_group_id` and `group_id` are denormalised so the per-locale handle
uniqueness constraint can be enforced at the DB level. Only non-default-locale
rows live in these tables; the default locale's title/handle/name stays on the
canonical row.

### `entry_trees` additions

```
entry_trees (additions)
--------------------------------------------------
locale    string(10) NOT NULL

unique  (parent_id, locale, handle)
        ← replaces the existing (parent_id, handle) unique
unique  (locale, uri)
        ← replaces the existing global unique on uri

is_home semantics become per-locale: at most one is_home=true per locale,
enforced at the service layer.
```

URIs are stored locale-relative (e.g. `/about`); the public URL is the locale
prefix + URI, composed at render time. `SetLocaleFromUri` middleware strips the
prefix before lookup.

### Not changing

- `entry_relationships` — relations stay structural and shared across locales.
  A French entry "relates to" the same target entry as the English; the target
  renders in whatever locale the viewer is in.
- `mediables` — file attachments stay shared. (FieldValueObserver behaviour
  must be fixed; see **Critical Fixes** below.)
- `users` — no `locale` column. UI-language preference lives in the Settings
  system as `localization.ui_locale` (user-overridable). User field
  translations are gated by site-wide `localization.users_translatable`.

---

## Settings Domain

Add a `localization` domain in `config/settings.php` (config-only, no
migration — the existing settings system stores values in the
`setting_values` table):

```php
'localization' => [
  'name' => 'Localization',
  'fields' => [
    ['handle' => 'available_locales',           'type' => 'json',    'default' => [['code'=>'en', 'name'=>'English']]],
    ['handle' => 'default_locale',              'type' => 'text',    'default' => 'en'],
    ['handle' => 'fallback_strategy',           'type' => 'text',    'default' => 'default_locale'], // 'default_locale' | 'none'
    ['handle' => 'strip_default_locale_prefix', 'type' => 'boolean', 'default' => true],
    ['handle' => 'users_translatable',          'type' => 'boolean', 'default' => false],
    ['handle' => 'ui_locale',                   'type' => 'text',    'default' => 'en', 'user_overridable' => true],
  ],
],
```

`strip_default_locale_prefix = true` means default-locale URLs render without a
prefix (`/about` rather than `/en/about`); set to false to always prefix.
`ui_locale` is user-overridable so individual editors can pick their admin UI
language independently of which content locale they're editing.

---

## Critical Fixes That Ship With This Work

These are latent bugs that the multilingual rollout exposes. They are not
optional — they must ship in lockstep with the rest of the work.

### Fix 1: `FieldValueObserver::syncMediables` orphans shared media

`app/Observers/FieldValueObserver.php` prunes `mediables` rows scoped only by
`(fieldable_type, fieldable_id, field_id)`. Once a FileUpload field has both
an EN row and a FR row in `field_values`, saving FR with a different media-id
set deletes mediables rows the EN row still references — and the EN gallery
silently loses items.

**Fix.** Rewrite `syncMediables()` and `deleted()` to compute the **union of
media IDs across all locales** for the (field, fieldable) pair, and only
delete `mediables` rows for media IDs that no locale still references. The
upsert path is already idempotent under the existing unique key on
`(media_id, mediable_type, mediable_id, field_id)`, so no new column is needed
on `mediables`.

### Fix 2: `EntryService::syncTreeNode` uses `$entry->handle` directly

`app/Services/EntryService.php::syncTreeNode` and `treeBuildUri` read
`$entry->handle` when rebuilding tree URIs. With translations, `$entry->handle`
is the canonical (default-locale) handle, so saving a French translation would
overwrite the English tree row's handle.

**Fix.** Tree sync becomes locale-aware. `syncTreeNode($entry, $locale)`
writes to the `entry_trees` row matching `(entry_id, locale)`. `treeBuildUri`
walks parents only across same-locale rows. If the walk encounters a parent
with no row for the target locale, throw a `ParentNotTranslatedException` that
the admin UI catches and surfaces as a clear error per the parent-translation
rule.

### Fix 3: Form-request uniqueness rules are locale-blind

`StoreEntryRequest`, `EditEntryRequest`, and `EditCategoryRequest` use
`Rule::unique('entries', 'handle')` and `unique('entry_trees', 'uri')` without
any locale scope.

**Fix.** Existing requests scope uniqueness to default-locale rows only. New
`StoreEntryTranslationRequest` / `EditEntryTranslationRequest` (and Category
and Media equivalents) validate against `*_translations` with
`(group_id, locale, handle)` uniqueness and against `entry_trees` with
`(locale, uri)` uniqueness.

---

## Code Touchpoints

### Read path

- **`app/Traits/Field/Fieldable.php`** — `field($handle, $locale = null)` and
  `fieldArray($locale = null)`. Default `$locale` to `app()->getLocale()`.
  Implement per-value fallback: query `(field_id, fieldable, locale)`; if no
  row is found, the field is translatable, and the fallback strategy is
  `default_locale`, re-query with the default locale.
- **`app/Models/FieldValue.php`** — add `locale` to `$fillable`.
  `resolvedValue()` is unchanged.
- **New accessors on Entry, Category, Media**: `transTitle($locale=null)`,
  `transHandle($locale=null)`, `transName($locale=null)`. Each looks up the
  translation row and falls back to the canonical column.
- **`app/Models/EntryTree.php`** — `scopeByUri($builder, $uri, $locale = null)`
  adds a locale filter. `normalizeHandle` receives the locale code so
  `Str::slug` can apply locale-appropriate transliteration.

### Write path

- **`app/Repositories/AbstractFieldableRepository.php`** —
  `applyFieldValues($model, $fields, ?string $locale = null)` and
  `upsertFieldValue(..., string $locale)`. For fields with
  `is_translatable=false`, force `locale = default_locale` regardless of the
  request.
- **`app/Repositories/EntryRepository.php`** — `applyCoreAttributes` writes to
  `entries` when locale is default, to `entry_translations` when not. New
  `createTranslation($entry, $locale, $data)` and
  `updateTranslation($entry, $locale, $data)`.
- **`app/Repositories/CategoryRepository.php`** — same pattern. Auto-slug
  derives from the translation's `name`, not the canonical.
- **`app/Services/EntryService.php`** — `createTreeNode` and `syncTreeNode`
  accept a locale. New `createTranslation` and `updateTranslation` service
  methods. `treeAssertUniqueHandleWithinParent` is scoped by locale.
- **`app/Observers/FieldValueObserver.php`** — union-based mediables sync
  (Fix 1).

### Routing

- **New `app/Http/Middleware/SetLocaleFromUri.php`** (site routes): peel
  `/{locale}` from the request URI if it matches `available_locales`, set
  `App::setLocale($locale)`, attach `$request->attributes->set('locale', $locale)`,
  rewrite the request URI for downstream resolution. Falls back to
  `default_locale` if absent.
- **New `app/Http/Middleware/SetAdminLocale.php`** (admin routes): read the
  user's `localization.ui_locale` setting and call `App::setLocale()`.
- **`app/Services/SiteRouting/RouteDrivers/EntryTreeRouteDriver.php`** —
  passes the current locale into the EntryTree lookup; rendering uses
  `transTitle` etc.
- **`app/Http/Controllers/SiteController.php`** — passes locale to drivers.

### Admin UI

- **New `resources/views/admin/_inc/_locale-switcher.twig`** — locale picker
  rendered at the top of edit screens when the container is translatable.
  Submits as `?locale=fr` query param.
- **`resources/views/admin/entries/edit.twig`**,
  `resources/views/admin/categories/edit.twig`,
  `resources/views/admin/media/edit.twig`,
  `resources/views/admin/users/edit.twig` — include the locale switcher
  conditionally.
- **`resources/views/admin/_inc/_schema-tab-elements.twig`** — `field.render()`
  receives a `locale` param. Non-translatable fields render with a `disabled`
  class and a "shared across locales" hint when the user is viewing a
  non-default locale.
- **Field-type partials in `resources/views/_fields/*.twig`** — accept a
  `locale` param. The form field name pattern stays `fields[{handle}]`; the
  controller pulls the active locale from the request.
- **EntryGroup, CategoryGroup, MediaLibrary settings screens** — add an
  "Enable translation" checkbox bound to `is_translatable`.
- **"Copy from {default_locale}" button** per tab on the edit form,
  AJAX-fills inputs from default-locale values.
- **New Settings admin tab "Localization"** — config-driven by
  `config/settings.php`; no controller work needed.

### API

- **`EntryResource`, `CategoryResource`, `MediaResource`, `UserResource`**,
  plus the group/library resources — call `transTitle()`, `transName()`,
  `fieldArray($locale)`. Locale is resolved by a new request helper
  `ResolveRequestLocale` that reads query → `Accept-Language` → default.
- **Optional:** add a `translations` summary to single-record responses
  listing which locales have translation rows, so clients can build locale
  switchers without a second request.

### Twig integration

- **New `app/Twig/LocaleExtension.php`** registers:
  - `current_locale()` function
  - `available_locales()` function
  - `default_locale()` function
  - `locale_url(uri, locale)` function — prefixes locale, honours
    `strip_default_locale_prefix`
  - `trans_field(model, handle, locale=null)` filter for explicit-locale reads
- **`config/twigbridge.php`** registers the extension.

### Reverse-relation rendering

- **`app/Field/Types/Relationship.php`** render uses
  `entry.transTitle(current_locale)` in option labels so dropdowns show the
  current-locale title with fallback.

---

## Migration Plan

Because the system is pre-production, the existing migrations are edited in
place rather than layered with additive ALTERs. No backfill steps, no two-stage
column-nullable dance — `php artisan migrate:fresh` rebuilds the schema cleanly.

**Existing migration files modified:**

1. `2026_04_14_000001_create_fields_table.php` — adds `is_translatable`.
2. `2025_12_27_160903_create_media_library_table.php` — adds `is_translatable`.
3. `2026_04_18_000007_create_entry_groups_table.php` — adds `is_translatable`.
4. `2026_04_18_000012_create_field_values_table.php` — adds `locale` (NOT
   NULL); unique becomes the 4-tuple.
5. `2026_04_18_000013_create_category_groups_table.php` — adds
   `is_translatable`.
6. `2026_04_23_200641_create_entry_tree_table.php` — adds `locale` (NOT
   NULL); swaps the two unique constraints.

**New migration files created:**

1. `2026_04_18_000009a_create_entry_translations_table.php` (runs after
   entries).
2. `2026_04_18_000014a_create_category_translations_table.php` (runs after
   categories).
3. `2026_05_07_000004_create_media_translations_table.php` (runs after media
   foreign keys).

**Seeders** (no migration changes):

- The fields seeder/factory sets `is_translatable` per type per the defaults
  map.
- The entry-groups, category-groups, and media-libraries seeders default
  `is_translatable` to false; the install demo data flips on whichever
  seeded groups should ship as translatable.
- The settings seeder writes the `localization` domain defaults
  (`available_locales = [{code:'en', name:'English'}]`,
  `default_locale = 'en'`, `fallback_strategy = 'default_locale'`,
  `strip_default_locale_prefix = true`, `users_translatable = false`).

**Deployment order** (rollout, not schema):

1. **Schema** — edit migrations + add new translation table migrations +
   `php artisan migrate:fresh`.
2. **Settings + middleware + Twig** — `localization` domain, Twig extension,
   `SetLocaleFromUri` (site) and `SetAdminLocale` (admin) registered. No
   behavioural effect until `available_locales` has more than one entry.
3. **Read path** — Fieldable trait, accessors, EntryTree scope, route
   drivers, API resources.
4. **Write path** — repositories, services, form requests (existing + new
   translation variants), controllers.
5. **Observer fix** — ships in the same release as the write-path changes to
   avoid the mediables-orphaning race for any group that flips on translation.
6. **Admin UI** — locale switcher partial, group/library settings
   checkboxes, "Copy from default locale" affordance.

---

## Verification

### Automated

- **Unit (Fieldable):** `Entry::field('summary', 'fr')` returns the FR row
  when present, the EN row when FR is empty, and null when both are empty.
- **Unit (Observer Fix 1):** with two locales' field_values pointing to
  overlapping media-id sets, saving one locale does not delete `mediables`
  rows the other still references.
- **Unit (Tree Fix 2):** saving a FR translation does not mutate
  `entry_trees` rows for any other locale; saving a tree-bound child
  translation when the parent has no FR row raises
  `ParentNotTranslatedException`.
- **Feature (Entry translation lifecycle):** create entry in default locale →
  create FR translation → update FR → fetch by API with `?locale=fr` returns
  FR fields with EN fallback for missing values.
- **Feature (Group flag):** when `entry_groups.is_translatable=false`,
  attempting `POST /admin/entries/{id}/translations/fr` returns 422 with a
  clear message.
- **Feature (Field flag):** when `fields.is_translatable=false` on a Number
  field in a translatable group, the field value is stored once with
  `locale=default_locale` regardless of which locale form was submitted.
- **Feature (Routing):** `GET /fr/a-propos` resolves to the same entry as
  `GET /en/about` and renders FR content; `GET /a-propos` 404s when
  `strip_default_locale_prefix=false`.

### Manual smoke

- Run `composer run dev`. In the browser: enable translation on an
  EntryGroup, add a FR locale in Localization settings, edit an existing
  entry, switch to FR in the picker, fill `title` and one HTML field, save,
  reload — verify FR persists and EN is unchanged.
- Toggle a field from translatable → not-translatable and back; confirm
  stale FR rows are ignored on read (not deleted) and become visible again
  when toggled back on.
- Visit `/fr/<canonical-FR-handle>` and verify the entry tree resolves.
- Edit a Category; verify the locale switcher appears iff
  `category_group.is_translatable`.
- Edit a User with `localization.users_translatable=false`; verify no locale
  switcher; flip the setting; verify it appears.

### Validation tooling

- Existing `php artisan app:validate-class-references` keeps passing — no
  field-class API changes.
- Run `vendor/bin/pint --preset psr12 --dirty` before committing each phase.

---

## Out of Scope (v1)

- **StructuredRows per-column translatability** — v2.
- **Per-locale `status` / `published_at` / `authors`** — would require an
  `entry_locale_metadata` table; revisit only if real editorial workflow
  demands it.
- **`entry_relationships.locale`** — relations stay structural. Add only if
  a concrete "different related set per locale" requirement appears.
- **Locale subdomains or domain-per-locale** — path-prefix is the v1
  strategy.
- **Admin UI translations beyond English** — `lang/en/*.php` is the only
  language pack. Adding `lang/fr` etc. is a separate (small) task.
- **Search, sitemap, SEO meta indexing per locale** — none of those layers
  exist yet; they pick up locale-awareness as they land.
- **Automated translation backends (DeepL, Google Translate)** — a
  "Translate from EN" button is plausible in v2, calling an external service.

---

## Critical Files (quick index)

- `app/Traits/Field/Fieldable.php`
- `app/Repositories/AbstractFieldableRepository.php`
- `app/Repositories/EntryRepository.php`
- `app/Repositories/CategoryRepository.php`
- `app/Services/EntryService.php`
- `app/Observers/FieldValueObserver.php`
- `app/Models/FieldValue.php`
- `app/Models/EntryTree.php`
- `app/Models/Entry.php`, `app/Models/Category.php`, `app/Models/Media.php`,
  `app/Models/User.php`
- `app/Services/SiteRouting/RouteDrivers/EntryTreeRouteDriver.php`
- `app/Http/Controllers/SiteController.php`
- `app/Http/Requests/Entry/StoreEntryRequest.php`, `EditEntryRequest.php`,
  Category and Media siblings
- `resources/views/admin/_inc/_schema-tab-elements.twig`
- `resources/views/admin/entries/edit.twig` and Category, Media, User edit
  views
- `config/settings.php`, `config/twigbridge.php`
- New PHP and Twig: `app/Http/Middleware/SetLocaleFromUri.php`,
  `app/Http/Middleware/SetAdminLocale.php`, `app/Twig/LocaleExtension.php`,
  `resources/views/admin/_inc/_locale-switcher.twig`
- Existing migrations edited in place:
  `2026_04_14_000001_create_fields_table.php`,
  `2025_12_27_160903_create_media_library_table.php`,
  `2026_04_18_000007_create_entry_groups_table.php`,
  `2026_04_18_000012_create_field_values_table.php`,
  `2026_04_18_000013_create_category_groups_table.php`,
  `2026_04_23_200641_create_entry_tree_table.php`
- New migrations:
  `2026_04_18_000009a_create_entry_translations_table.php`,
  `2026_04_18_000014a_create_category_translations_table.php`,
  `2026_05_07_000004_create_media_translations_table.php`
