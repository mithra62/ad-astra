# Laravel CMS — System Basics

A concise orientation to the core concepts. For full detail, see `OVERVIEW.md`.

---

## What It Is

An **ExpressionEngine-inspired headless CMS** built on Laravel 12. All content
structure is defined at runtime through the admin — no code changes are needed
to add new content types, fields, or statuses. Entry types can be backed by
concrete PHP classes for lifecycle logic; when none is configured the system
falls back to `GeneralEntryType` automatically.

---

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
```

Start local development (server + queue + Vite):

```bash
composer run dev
```

Run tests:

```bash
composer test
```

The seeder creates a default super-admin user: `eric@mithra62.com` / `password`.

---

## Core Content Model

```
EntryGroup → EntryType → Entry
```

- **EntryGroup** — a named bucket (e.g. "Blog", "Products"). Owns a
  `StatusGroup`, an optional `FieldLayout`, and polymorphic `CategoryGroups`
  and `FieldGroups`.
- **EntryType** — a typed schema within a group. References a PHP class
  extending `AbstractEntryType`. Falls back to `GeneralEntryType` if no class
  is set.
- **Entry** — the actual record. Has `title`, `handle`, `status`,
  `published_at`, a creator user, editorial authors, categories, and dynamic
  custom field values.

Seeded entry groups: `blog`, `products`, `events`, `news`, `pages`, `jobs`,
`podcast`, `portfolio`, `videos`, `recipes`, `general`.

---

## Entry Groups and Entry Types in Plain English

### The big picture

Think of an **Entry Group** as a section of your site — a named container that
says "all the content in here belongs together." A **Blog** is an Entry Group.
So is **Products**, **Jobs**, or **Events**. The group answers the question
*where does this content live?*

An **Entry Type** lives inside a group and answers a more specific question:
*what kind of thing is this?* A Blog group might have a "Blog Post" type. A
Products group might have "Physical Product" and "Digital Download" as two
separate types, each with their own fields and business rules. You can have as
many types as you need inside a single group, or just one if the content is
uniform.

An **Entry** is the actual piece of content — the individual blog post, the
specific product, the one job listing. It always belongs to exactly one group
and one type.

### Why the two-level structure?

The group and type levels each control different things, and they work together.

The **group level** sets the shared rules for everything in that section. You
define which fields every entry in the group will have, which status workflow
applies (draft/published/archived or something custom), and which category
groups are available. If you want all blog posts to have an SEO field and a
body field, you put those on the group layout and every blog post gets them
automatically, regardless of type.

The **type level** adds or overrides on top of that. If you have a "Video Post"
type inside your blog, it can bring in its own extra fields (a video URL, a
transcript) on top of the shared blog fields. The type also carries a PHP class
that can run business logic — validating data, stamping dates, firing events,
computing derived values — things that only make sense for that specific kind of
content.

### What you actually get

When you define an Entry Group and an Entry Type, you get several things out of
the box with no extra code:

**Shared field inheritance.** Any field you attach at the group level flows down
to every entry in that group, no matter which type it is. Fields at the type
level layer on top and take precedence if there's a name collision. This means
you can have a universal "SEO" tab on every entry in your site just by attaching
it to each group, while still having type-specific tabs alongside it.

**Status control.** Each group has its own workflow. A "Blog" group might use
draft/published/archived. A "Jobs" group might use pending/active/expired/closed.
The status system is entirely separate from the field system, so you can mix and
match freely. The `published()` query scope automatically respects whichever
status is flagged as public for that group's workflow.

**Category organisation.** You can attach one or more category groups to an
entry group. Blog posts might be tagged with "Topics" and "Authors." Products
might be tagged with "Department" and "Brand." Categories are hierarchical and
support their own custom fields.

**Type-level business logic.** The PHP class behind an entry type can hook into
four moments in the content lifecycle: before an entry is created, after it is
created, before it is updated, and after it is updated. It can also add
validation rules that run as part of the normal save flow. This is where things
like "auto-assign an episode number," "require a SKU before publishing," or
"compute total cooking time from prep time plus cook time" live. If you don't
need any special logic, you don't write a class at all — the system falls back
to the generic type automatically.

**Tree-based URL routing.** Entry types can be configured with tree support,
which means entries of that type can be organised into a hierarchy and assigned
explicit public URLs. A "Pages" type with tree support enabled lets you build a
page tree like `/about`, `/about/team`, `/about/team/jane` entirely through the
admin, with each node choosing its own template.

### A concrete example

Imagine a site with a **Products** entry group. It has three types: "Physical
Product," "Digital Download," and "Bundle."

All three types share the same group-level fields: product name, description,
price, and an SEO tab. The Physical Product type adds its own fields: weight,
dimensions, and shipping class. Digital Download adds a file URL and a licence
key field. Bundle adds a "included products" relationship field pointing back to
other entries.

Each type has its own PHP class. `ProductEntryType` validates that price is a
positive number, requires a SKU before the status can be set to published, and
automatically flips the status to "out of stock" when the stock field reaches
zero. `DigitalDownloadEntryType` does none of that — it just validates that the
file URL is present at publish time. `BundleEntryType` checks that at least one
related product is attached.

When a developer queries products they use `Content::query()->inGroup('products')`,
and all three types come back together. When they need only digital downloads
they use `ofType('digital_download')`. Field values are read the same way on all
three: `$entry->field('price')`, `$entry->field('file_url')`, etc.

---

## Field System

Fields store values polymorphically via `field_values`. Any model using the
`Fieldable` trait gains a `fieldValues()` morphMany relation and the
`->field('handle')` read helper.

Field types live in `app/Field/Types/` and extend `AbstractField`. Each type
declares a `storageColumn()` — one of `value_text`, `value_integer`,
`value_float`, `value_date`, `value_boolean`, or `value_json`. The one
exception is `Relationship`, which sets `isRelational() = true` and stores
data in `entry_relationships` instead.

**Built-in field types:** Text, Textarea, Number, Date, EmailAddress, Url,
Telephone, ColorPicker, Boolean, Relationship.

Field layouts organise fields into tabs for the admin UI:

```
FieldLayout → FieldLayoutTab → FieldLayoutTabElement → Field
```

An entry's effective fields come from two layouts merged together: the
**EntryGroup layout** (shared across all types in the group) and the
**EntryType layout** (type-specific overrides). Type-level fields take
precedence on duplicates.

---

## Creating and Querying Entries

```php
use App\Facades\Content;

// Create
$entry = Content::create('blog_post', [
    'title'  => 'Hello World',
    'status' => 'published',
    'fields' => ['body' => 'My content.'],
]);

// Update
Content::update($entry, ['title' => 'Updated Title', 'fields' => ['body' => 'New text.']]);

// Query
$posts = Content::query()->inGroup('blog')->published()->latest()->get();

// Read a field value
echo $entry->field('body');
```

`inGroup('blog')` matches the **EntryGroup handle**. `ofType('blog_post')`
matches the **EntryType handle**. Do not mix them on the same query.

`EntryQueryBuilder` terminal methods (`get()`, `first()`, `paginate()`) always
apply the full eager-load set, so field access never causes N+1 queries.

---

## EntryType Lifecycle Hooks

Concrete `AbstractEntryType` subclasses can override these methods:

```php
public function beforeCreate(array $data): array   // mutate data before write
public function afterCreate(Entry $entry, array $data): void  // side effects after commit
public function beforeUpdate(Entry $entry, array $data): array
public function afterUpdate(Entry $entry, array $data): void
public function validate(array $data, ?Entry $entry): array   // return field-keyed errors
```

---

## Users, Roles, and Permissions

Built on **Spatie Permission** (`HasRoles` on `User`). Three seeded roles:

| Role          | Access |
|---------------|--------|
| `super admin` | Bypasses all permission checks via `Gate::before` |
| `admin`       | Full admin panel + all seeded CRUD permissions |
| `user`        | Admin panel access only (`access admin`) |

```php
use App\Facades\Users;

$user = Users::create([
    'name'     => 'Jane Doe',
    'email'    => 'jane@example.com',
    'password' => 'secret',
    'roles'    => ['admin'],
    'fields'   => ['first_name' => 'Jane'],
]);

if ($user->can('edit entry')) { /* ... */ }
if ($user->hasRole('super admin')) { /* ... */ }
```

Users also support custom profile fields via `UserSchema` (a singleton that
owns a single `FieldLayout` applied to all users), two-factor authentication
via Fortify, API tokens via Sanctum, and OAuth social login via Socialite.

---

## Settings

Settings are schema-defined in `config/settings.php` — adding a new setting
requires only a config entry, no migration. Values are stored in `setting_values`
with typed columns. Resolution order: **user override → system value → config default**.
Both layers are cached for one hour.

```php
use App\Settings;

$settings = app(Settings::class);

$timezone = $settings->get('general', 'timezone', 'UTC');
$all      = $settings->all('general');
```

Current domains: `general`, `media`, `email`, `content`.

---

## Status Groups and Statuses

Every `EntryGroup` must be assigned a `StatusGroup`. A `StatusGroup` holds one
or more `Status` records that represent the workflow states available to entries
in that group (e.g. `draft`, `published`, `archived`).

**Status columns:** `name`, `handle`, `color` (hex), `is_default` (bool),
`is_public` (bool), `sort_order`.

An entry's status is denormalised across three columns kept in sync by
`EntryRepository::applyStatus()`:

| Column             | Purpose |
|--------------------|---------|
| `status_id`        | Nullable FK to `statuses.id` |
| `status_handle`    | Indexed string for fast lookups |
| `status_is_public` | Indexed boolean — drives `published()` scope |

`Entry::scopePublished()` filters on `status_is_public = true AND published_at IS NOT NULL AND published_at <= now()`.

When a `Status` row's `is_public` flag changes, `StatusObserver` automatically
back-fills `status_is_public` on every entry currently assigned that status, so
entries never go stale.

```php
use App\Models\Status;
use App\Models\StatusGroup;

$group = StatusGroup::create(['name' => 'Review Workflow', 'handle' => 'review', 'sort_order' => 2]);

foreach ([
    ['name' => 'Pending Review', 'handle' => 'pending',  'color' => '#F59E0B', 'is_default' => true,  'is_public' => false, 'sort_order' => 1],
    ['name' => 'Approved',       'handle' => 'approved', 'color' => '#10B981', 'is_default' => false, 'is_public' => true,  'sort_order' => 2],
    ['name' => 'Rejected',       'handle' => 'rejected', 'color' => '#EF4444', 'is_default' => false, 'is_public' => false, 'sort_order' => 3],
] as $s) {
    Status::create(array_merge($s, ['status_group_id' => $group->id]));
}
```

The seeded `publication` status group provides `draft`, `published`, and
`archived` statuses and is shared by most entry groups.

---

## Category Groups and Categories

Categories are hierarchical and polymorphic — any number of `CategoryGroup`
instances can be attached to an `EntryGroup`, a `MediaLibrary`, etc. Entries
carry a `categories()` `morphToMany` via the `HasCategories` trait, and
`EntryQueryBuilder` always eager-loads them so no extra `with()` call is needed.

`CategoryGroup` columns: `handle` (unique), `field_layout_id` (nullable),
`sort_order`. `Category` columns: `group_id`, `parent_id` (nullable self-FK),
`name`, `handle`, `sort_order`. Handle is unique within a group.

```php
use App\Models\Category;
use App\Models\Category\Group as CategoryGroup;

// Create a group and a two-level tree
$group  = CategoryGroup::firstOrCreate(['handle' => 'topics'], ['name' => 'Topics']);
$parent = Category::create(['group_id' => $group->id, 'name' => 'Europe', 'handle' => 'europe', 'sort_order' => 1]);
Category::create(['group_id' => $group->id, 'parent_id' => $parent->id, 'name' => 'France', 'handle' => 'france', 'sort_order' => 1]);

// Fetch root categories with full recursive children
$group->rootCategories()->with('childrenRecursive')->get();

// Filter entries by category
$php = Category::where('handle', 'php')->firstOrFail();
$entries = Content::query()->inGroup('blog')->withCategory($php->id)->published()->get();

// Read categories on fetched entries (already eager-loaded)
foreach ($entries as $entry) {
    foreach ($entry->categories as $category) {
        echo $category->name;
    }
}
```

Categories support scalar custom fields (not relational) using the same
`Fieldable` trait and `$category->field('handle')` API as entries. Attach a
`FieldGroup` and `FieldLayout` to a `CategoryGroup` to enable them, then write
values through `CategoryService::create()` / `CategoryService::update()`.

---

## Key Facades

| Facade        | Backs |
|---------------|-------|
| `Content`     | `EntryService` (alias `ContentService`) |
| `Entries`     | `EntryService` |
| `EntryTypes`  | `EntryTypeService` |
| `Categories`  | `CategoryService` |
| `EntryGroups` | `EntryGroupService` |
| `Users`       | `UserService` |

---

## Public Routing

All public traffic enters through `SiteController → SiteRouter`, which tries
registered drivers in order (configured in `config/site.php` under
`routing.priority`). Two built-in drivers:

- **`entry_tree`** — matches the URI against `entry_trees.uri`; renders the
  node's `template` (or falls back to the entry type's `default_template`,
  then `entries.show`). Only published entries are served.
- **`template`** — maps URL segments to Twig/Blade template files under
  `resources/templates/`.

`entry_tree` takes priority over `template` by default. Admin routes live under
`/admin` (Fortify auth). API routes live under `/api/v1` (Sanctum).

---

## Authentication Stack

- **Fortify** — login, registration, password reset, two-factor authentication
- **Sanctum** — API tokens (`HasApiTokens` on `User`)
- **Spatie Permission** — RBAC (`HasRoles` on `User`)
- **Socialite** — OAuth / social login (`OauthToken` HasMany on `User`)

---

## Polymorphic Morph Map

Stored type strings are decoupled from class names. Short aliases registered in
`AppServiceProvider::boot()`:

```
'entry'          => Entry::class
'entry_group'    => EntryGroup::class
'entry_type'     => EntryType::class
'category'       => Category::class
'category_group' => CategoryGroup::class
'field_group'    => FieldGroup::class
'media'          => Media::class
'media_library'  => MediaLibrary::class
'user'           => User::class
```

Always use `$model->getMorphClass()` when writing polymorphic rows — never
hard-code the FQCN.

---

## Template and View Layer

The system uses two separate view stacks that never overlap.

**Admin views** are Twig templates under `resources/views/admin/**/*.twig`,
powered by TwigBridge. Admin screens are rendered through standard Laravel
controller/view flows — there is no SPA or client-side routing in the admin.

**Public templates** live under `resources/templates/` and use the `templates::`
view namespace. They are resolved server-side by `SiteRouter` through its two
built-in drivers (see Public Routing above). Templates can be written as Twig
(`.twig`) or Blade (`.blade.php`).

### Template Driver URL → file mapping

| Public URL      | Resolved template                   |
|-----------------|-------------------------------------|
| `/`             | `templates::site.index`             |
| `/blog`         | `templates::blog.index`             |
| `/blog/my-post` | `templates::blog.entry` + `$handle = 'my-post'` |

Key/value pairs after the second URL segment become `$params`:

```
/blog/my-post/page/2  →  $params = ['page' => '2']
```

Variables available in every template-driver template:

| Variable    | Value |
|-------------|-------|
| `$segments` | All URL segments as an array |
| `$params`   | Key/value pairs from segments after the second |
| `$get`      | Query string array |
| `$handle`   | Second segment when on a `{group}.entry` route |

### Entry Tree template variables

When a URL is resolved by the `entry_tree` driver, the selected template receives:

| Variable     | Value |
|--------------|-------|
| `$entry`     | Matched `Entry` model |
| `$entryType` | Matched entry's `EntryType` |
| `$node`      | Matched `EntryTree` node |

Template precedence for tree routes: `EntryTree.template` → `EntryType.default_template` → `entries.show`.

### Example public template

```twig
{# resources/templates/blog/entry.twig #}
{% set entry = content.query().inGroup('blog').published().where('handle', handle).firstOrFail() %}

<h1>{{ entry.title }}</h1>
<div>{{ entry.field('body')|raw }}</div>
```

### Asset pipeline

```bash
npm run dev    # Vite dev server with HMR
npm run build  # production build
```

Vite 7 + Tailwind CSS 4. Assets are **not** part of the SiteRouter — they are
served directly by the web server or Vite's dev middleware.

---

## Key Commands

```bash
composer run dev                     # start server + queue + Vite
composer test                        # run full test suite
vendor/bin/pint                      # code style (run before committing)
php artisan migrate
php artisan db:seed
php artisan optimize:clear           # clear all caches
php artisan app:validate-class-references  # verify DB-stored class names
php artisan l5-swagger:generate      # regenerate OpenAPI docs
php artisan route:list --except-vendor
```

**Windows note:** PHP is at `C:\php\php.exe`. Prefix artisan calls with
`C:\php\php.exe` if `php` is not in PATH.
