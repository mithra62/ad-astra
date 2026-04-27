# Laravel CMS — Project Overview

> **Documentation status (2026-04-26).** This file has been re-synchronised
> against the actual code in `app/`, `database/`, `routes/`, and `config/`.
> Where prior documents (`CRITICAL_ISSUES.md`, `Phase1_ISSUES.md`,
> `CONCERNS.md`, `CURRENT_ISSUES_REVIEW.md`, `junie-thoughts.md`) disagree with
> the live source, the source wins and the disagreement is called out inline
> under **"Doc-vs-code drift"** notes. The biggest single drift is terminology:
> the codebase consistently uses **`handle`** (not `slug`) on every model that
> has a developer-facing identifier — `Field`, `FieldGroup`, `EntryGroup`,
> `EntryType`, `StatusGroup`, `Status`, `CategoryGroup`, `Category`, and
> `Entry`. Older docs and even some examples still say "slug"; that is stale.

## Table of Contents

- [Architecture at a Glance](#architecture-at-a-glance)
- [Setup](#setup)
- [Users, Roles, and Permissions](#users-roles-and-permissions)
  - [Built-in Roles](#built-in-roles)
  - [Built-in Permissions](#built-in-permissions)
  - [Creating Users Programmatically](#creating-users-programmatically)
  - [Checking Permissions](#checking-permissions)
  - [Creating a New Permission and Role](#creating-a-new-permission-and-role)
- [Field Types](#field-types)
  - [Built-in Types](#built-in-types)
  - [Creating a Custom Field Type](#creating-a-custom-field-type)
- [Field Groups and Fields](#field-groups-and-fields)
  - [Creating a Field Group with Fields](#creating-a-field-group-with-fields)
- [Status Groups and Statuses](#status-groups-and-statuses)
  - [Creating a Status Group](#creating-a-status-group)
  - [How an Entry Stores its Status](#how-an-entry-stores-its-status)
  - [StatusObserver — keeping `status_is_public` consistent](#statusobserver--keeping-status_is_public-consistent)
- [Category Groups and Categories](#category-groups-and-categories)
  - [Creating a Category Group and Categories](#creating-a-category-group-and-categories)
  - [Fetching Categories](#fetching-categories)
- [Accessing Entry Categories via the Content Facade](#accessing-entry-categories-via-the-content-facade)
- [Field Layouts](#field-layouts)
  - [Building a Layout Programmatically](#building-a-layout-programmatically)
  - [Getting All Fields from a Layout](#getting-all-fields-from-a-layout)
- [Entry Groups and Entry Types](#entry-groups-and-entry-types)
  - [Multiple Entry Types per Group](#multiple-entry-types-per-group)
  - [Field Layering: Group Fields + Type Fields](#field-layering-group-fields--type-fields)
  - [Setting Up Multiple Entry Types in One Group](#setting-up-multiple-entry-types-in-one-group)
  - [Entry Type Classes Can Share Logic via a Base Class](#entry-type-classes-can-share-logic-via-a-base-class)
  - [Lifecycle Hook Signatures](#lifecycle-hook-signatures)
  - [Creating Entries of Each Type](#creating-entries-of-each-type)
  - [Multiple Groups Sharing the Same Entry Type Class](#multiple-groups-sharing-the-same-entry-type-class)
  - [Creating an Entry Group](#creating-an-entry-group)
  - [Creating an Entry Type Class](#creating-an-entry-type-class)
  - [Registering the Entry Type in the Database](#registering-the-entry-type-in-the-database)
- [Creating and Updating Entries](#creating-and-updating-entries)
  - [Creating an Entry](#creating-an-entry)
  - [Updating an Entry](#updating-an-entry)
  - [Using the Relationship Field](#using-the-relationship-field)
- [Querying Entries](#querying-entries)
  - [Reading Field Values](#reading-field-values)
  - [Accessing Entry Authors](#accessing-entry-authors)
- [Deleting Entries](#deleting-entries)
- [User Extended Profile (UserSchema)](#user-extended-profile-userschema)
  - [Setting Up the User Schema](#setting-up-the-user-schema)
  - [Writing Field Values to a User](#writing-field-values-to-a-user)
  - [Reading Field Values from a User](#reading-field-values-from-a-user)
  - [Typical Controller Pattern](#typical-controller-pattern)
  - [Comparison: Users vs Entries](#comparison-users-vs-entries)
- [System Health and Data Integrity](#system-health-and-data-integrity)
- [UserService and the Users Facade](#userservice-and-the-users-facade)
  - [CRUD](#crud)
  - [Roles](#roles)
  - [Custom Fields](#custom-fields)
  - [Passwords](#passwords)
  - [Two-Factor Authentication](#two-factor-authentication)
  - [OAuth Token Management](#oauth-token-management)
  - [Action Classes Inventory](#action-classes-inventory)
- [Custom Field Groups on Category Groups](#custom-field-groups-on-category-groups)
- [Site Routing (Public-Facing URLs)](#site-routing-public-facing-urls)
- [Adding New Permissions](#adding-new-permissions)
- [Adding a New Entry Type End-to-End](#adding-a-new-entry-type-end-to-end)
- [Key Data Flow Summary](#key-data-flow-summary)
- [Doc-vs-Code Drift Reference](#doc-vs-code-drift-reference)

---

## Architecture at a Glance

This is an **ExpressionEngine-inspired headless CMS** built on Laravel 12. The
core philosophy: all content structure is admin-defined at runtime. Entry types
are concrete PHP classes; everything else (fields, layouts, statuses,
categories) is database-driven.

```
FieldType          — system-level type registry (Text, Textarea, Number, Date,
                     EmailAddress, Url, Telephone, ColorPicker, Relationship)
  └── Field        — admin-created field instances with settings (handle, label,
                     instructions, hidden, JSON settings)
        └── FieldGroup — reusable bundles of fields, attached to anything that
                         uses HasFieldGroups (EntryGroup, CategoryGroup,
                         UserSchema) via the polymorphic field_groupables pivot

StatusGroup
  └── Status       — named statuses with handle, color, is_default, is_public

CategoryGroup     — own a FieldLayout (HasFieldLayout) and FieldGroups
  └── Category    — hierarchical tree; uses Fieldable for custom values

FieldLayout
  └── Tab (field_layout_tabs)
        └── TabElement (field_layout_tab_elements) → Field

EntryGroup        — owns a FieldLayout, a StatusGroup, plus polymorphic
                    CategoryGroups and FieldGroups
  └── EntryType   — concrete PHP class extending AbstractEntryType.
                    Schema: name, handle, class, default_template,
                    has_entry_tree, max_depth, allowed_parent_types,
                    field_layout_id (optional override), sort_order
        └── Entry — title, handle, status_id + status_handle + status_is_public,
                    published_at, created_by_user_id
              ├── FieldValue        — scalar custom field data (polymorphic)
              ├── EntryRelationship — relational field data (M2M to other Entries)
              ├── entry_authors     — ordered M2M of editorial authors
              ├── categories        — polymorphic M2M (categorizables)
              └── EntryTree         — optional hierarchical URI tree node

UserSchema        — singleton (id=1) that owns a single FieldLayout and
                    one or more FieldGroups for ALL users
  └── User        — uses Fieldable, HasRoles (Spatie), HasTags (Spatie),
                    HasApiTokens (Sanctum), TwoFactorAuthenticatable (Fortify),
                    Notifiable; OAuth tokens via OauthToken HasMany
```

### Cross-cutting infrastructure

- **Polymorphic morph map.** `app/Providers/AppServiceProvider.php` registers
  short morph aliases via `Relation::morphMap([...])`. Stored aliases are
  `entry`, `entry_group`, `entry_type`, `category`, `category_group`,
  `field_group`, `media`, `media_library`, and `user`. This means
  `field_values.fieldable_type` will contain `'entry'` rather than
  `App\Models\Entry`. Always use `$model->getMorphClass()` (which returns the
  alias when a morph map is set) when writing polymorphic rows, **not** the
  FQCN. Old fixtures that still contain `App\Models\...` strings will not
  resolve correctly until they are converted to the alias.
- **Super-admin gate bypass.** `AppServiceProvider::boot()` registers a
  `Gate::before` callback that returns `true` for any user with the
  `super admin` role, short-circuiting all permission checks.
- **Sanctum + Fortify + Spatie Permission + Socialite** are wired together for
  auth: 2FA via Fortify's `TwoFactorAuthenticatable`, API tokens via Sanctum
  (`HasApiTokens`), RBAC via Spatie `HasRoles`, and OAuth login via
  `Laravel\Socialite\Socialite` (see `app/Http/Controllers/Login.php`).
- **TwigBridge** powers the admin views — `resources/views/admin/**/*.twig` —
  alongside Blade templates elsewhere.

---

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
```

The `DatabaseSeeder` runs in this order (all eight always run; the ninth runs
only in `local`/`testing`):

1. `RolesPermissionsSeeder` — permissions + 3 roles
2. `UsersSeeder` — seeds **a single super-admin** user (Eric Lamb,
   `eric@mithra62.com`, password `password`)
3. `FieldTypeSeeder` — 9 field type rows registered (Text, Textarea, Number,
   Date, Email Address, URL, Telephone, Color Picker, Relationship)
4. `StatusGroupSeeder` — `publication` status group (`draft`, `published`,
   `archived`)
5. `CategoryGroupSeeder` — category groups + categories
6. `FieldGroupSeeder` — field groups + fields (`content-fields`, `seo-fields`,
   `relationship-fields`, plus per-group field bundles)
7. `EntryGroupSeeder` — `blog` and `products` entry groups, layouts, and types
8. `ExtendedEntryGroupSeeder` — `events`, `news`, `pages`, `jobs`, `podcast`,
   `portfolio`, `videos`, `recipes` entry groups + types
9. `UserSchemaSeeder` — initialises the user profile schema (Profile and Bio
   tabs, fields like `first_name`, `last_name`, `gender`, `date_of_birth`,
   `website`, `bio`, `social_twitter`, `social_linkedin`)
10. `EntrySeeder` *(local/testing only)* — sample blog posts and products

> **Doc-vs-code drift.** Earlier copies of this document listed only eight
> seeders and called the seeded user "the default admin user." `UsersSeeder`
> actually assigns the **`super admin`** role, which bypasses every gate.

---

## Users, Roles, and Permissions

The system uses **Spatie Permission** (`spatie/laravel-permission`) with the
`HasRoles` trait on `User`. The `User` model also pulls in `Notifiable`,
`HasApiTokens` (Sanctum), `HasTags` (Spatie Tags),
`TwoFactorAuthenticatable` (Fortify), and the project's `Fieldable` trait so
users can store custom field values polymorphically.

### Built-in Roles

| Role | Access |
|---|---|
| `super admin` | Everything — bypasses all permission checks via `Gate::before` |
| `admin` | Admin panel + full CRUD for users, user tokens, categories/category groups, media libraries, plus the `api` permission |
| `user` | Admin panel access only (`access admin` permission only) |

### Built-in Permissions

The full permission list seeded by `RolesPermissionsSeeder` (each is created
with a `description` column on the `permissions` table thanks to the
`add_permission_detail_columns` migration):

```
api
access admin

view user
create user
edit user
delete user

view user token
create user token
edit user token
delete user token

create category group
edit category group
delete category group
reorder category group

create category
edit category
delete category
reorder category

create media library
edit media library
delete media library
reorder media library
```

> **Doc-vs-code drift.** No permissions exist for *entries*, *fields*,
> *field groups*, *field layouts*, *statuses*, or *roles* — those areas are
> currently gated only by `access admin` plus the super-admin bypass. This
> matches `Phase1_ISSUES.md` LOW-02. If you need granular control over those
> areas, add the permissions yourself (see [Adding New Permissions](#adding-new-permissions)).

### Creating Users Programmatically

Prefer the `Users` facade so roles, fields, and password hashing happen in one
place. Direct `User::create()` works but skips field/role wiring.

```php
use App\Facades\Users;

$user = Users::create([
    'name'     => 'Jane Doe',
    'email'    => 'jane@example.com',
    'password' => 'secret-passphrase', // hashed by UserService
    'roles'    => ['admin'],
    'fields'   => [
        'first_name' => 'Jane',
        'last_name'  => 'Doe',
    ],
]);
```

If you really do want raw Eloquent:

```php
use App\Models\User;
use Illuminate\Support\Facades\Hash;

$user = User::create([
    'name'     => 'Jane Doe',
    'email'    => 'jane@example.com',
    'password' => Hash::make('secret-passphrase'),
]);

$user->assignRole('admin');
```

### Checking Permissions

```php
// Gate/policy check
if ($user->can('edit category')) { /* ... */ }

// Role check
if ($user->hasRole('super admin')) { /* ... */ }

// Multiple roles
if ($user->hasAnyRole(['admin', 'super admin'])) { /* ... */ }
```

Because of the `Gate::before` callback, `super admin` always returns `true`
from `can()` regardless of which permission is being checked.

### Creating a New Permission and Role

```php
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

$permission = Permission::create([
    'name'        => 'publish entry',
    'description' => 'Allows user to set entries to published status',
]);

$role = Role::create(['name' => 'editor']);
$role->givePermissionTo(['access admin', 'publish entry']);

// Or give a permission directly to a user
$user->givePermissionTo('publish entry');
```

---

## Field Types

Field types are PHP classes that live in `app/Field/Types/` and extend
`AbstractField` (`app/Field/AbstractField.php`). They are registered in the
`field_types` table by `FieldTypeSeeder`. Each row stores the **fully-qualified
class name** in `field_types.object`; instantiation goes through
`Field\Type::instance()`, which validates `class_exists()` and
`is_subclass_of(AbstractField::class)` before constructing.

`AbstractField` has six relevant methods that subclasses can override:

| Method | Purpose |
|---|---|
| `storageColumn(): string` | Required. Returns one of `value_text`, `value_integer`, `value_float`, `value_date`, `value_boolean`, `value_json`. Not called for relational types. |
| `isRelational(): bool` | Default `false`. Return `true` to route writes to `entry_relationships` instead of `field_values`. |
| `cast(mixed $value): mixed` | Default identity. Convert raw stored value before returning. |
| `validate(mixed $value): bool\|string` | Default `true`. Return `true`, or a string error message. |
| `render(array $params): string` | Render a Blade partial for the admin form (e.g. `view('_fields.text', ...)`). |
| `getRules(): array` | Return Laravel validation rules for this type. |

### Built-in Types

| Class | `storageColumn()` | Notes |
|---|---|---|
| `Text` | `value_text` | Single-line input, `string` rule |
| `Textarea` | `value_text` | Multi-line |
| `Number` | `value_integer` *or* `value_float` | Branches on the `decimals` setting (`>0` → float, else integer) |
| `Date` | `value_date` | Cast as `datetime` on `FieldValue` so reads return `Carbon` |
| `EmailAddress` | `value_text` | |
| `Url` | `value_text` | |
| `Telephone` | `value_text` | |
| `ColorPicker` | `value_text` | Hex value |
| `Relationship` | *(unused — `isRelational() === true`)* | Stores in `entry_relationships`; `validate()` enforces array shape and an optional `limit` setting |

### Creating a Custom Field Type

```php
// app/Field/Types/Toggle.php
namespace App\Field\Types;

use App\Field\AbstractField;

class Toggle extends AbstractField
{
    protected string $handle = 'toggle';
    protected string $name   = 'Toggle';
    protected array  $rules  = ['boolean'];

    public function storageColumn(): string
    {
        return 'value_boolean';
    }

    public function cast(mixed $value): bool
    {
        return (bool) $value;
    }
}
```

Register it in a seeder (or migration):

```php
use App\Models\Field\Type as FieldType;

FieldType::firstOrCreate(
    ['object' => \App\Field\Types\Toggle::class],
    ['name'   => 'Toggle']
);
```

---

## Field Groups and Fields

**FieldGroups** are reusable bundles of fields attached to whatever uses the
`HasFieldGroups` trait — `EntryGroup`, `CategoryGroup`, and `UserSchema`. The
attachment uses a polymorphic pivot (`field_groupables`) with a `group_id`
foreign-key column.

`Field` itself has these columns (see `2026_04_14_000001_create_fields_table`):
`field_type_id` (nullable, set null on delete), `name`, **`handle` (unique)**,
`label`, `instructions`, `settings` (JSON), `hidden` (boolean).

> **Doc-vs-code drift.** Older snippets used a `slug` column. Fields,
> field groups, and category groups all use **`handle`** in the actual schema.

### Creating a Field Group with Fields

```php
use App\Models\Field;
use App\Models\Field\Group as FieldGroup;
use App\Models\Field\Type as FieldType;

$textType = FieldType::where('object', \App\Field\Types\Text::class)->firstOrFail();

$group = FieldGroup::firstOrCreate(
    ['handle' => 'product-details'],
    ['name'   => 'Product Details', 'description' => 'Core product information.']
);

$fields = [
    ['handle' => 'price',    'name' => 'Price',    'label' => 'Price'],
    ['handle' => 'sku',      'name' => 'SKU',      'label' => 'SKU Number'],
    ['handle' => 'in_stock', 'name' => 'In Stock', 'label' => 'In Stock'],
];

foreach ($fields as $def) {
    $field = Field::firstOrCreate(
        ['handle' => $def['handle']],
        array_merge($def, ['field_type_id' => $textType->id])
    );

    $group->fields()->syncWithoutDetaching([$field->id]);
}
```

`Field::groups()` is the inverse: `morphedByMany(Group::class, 'fieldable')`.
Field membership in a group is therefore polymorphic — the `field_groupables`
pivot can attach Fields to any group-like model.

---

## Status Groups and Statuses

Each `EntryGroup` references at most one `StatusGroup` via a nullable
foreign key. Statuses are owned by a status group; the same handle can exist in
multiple groups.

### Creating a Status Group

```php
use App\Models\Status;
use App\Models\StatusGroup;

$group = StatusGroup::create([
    'name'       => 'Review Workflow',
    'handle'     => 'review',           // unique on status_groups
    'sort_order' => 2,
]);

$statuses = [
    ['name' => 'Pending Review', 'handle' => 'pending',  'color' => '#F59E0B', 'is_default' => true,  'is_public' => false, 'sort_order' => 1],
    ['name' => 'Approved',       'handle' => 'approved', 'color' => '#10B981', 'is_default' => false, 'is_public' => true,  'sort_order' => 2],
    ['name' => 'Rejected',       'handle' => 'rejected', 'color' => '#EF4444', 'is_default' => false, 'is_public' => false, 'sort_order' => 3],
];

foreach ($statuses as $s) {
    Status::create(array_merge($s, ['status_group_id' => $group->id]));
}
```

### How an Entry Stores its Status

Unlike older versions of this document, `entries.status` is **not** a single
free-text column. The current schema is denormalised across three columns
maintained together by `EntryRepository::applyStatus()`:

| Column | Type | Notes |
|---|---|---|
| `status_id` | nullable FK to `statuses.id` | `nullOnDelete` |
| `status_handle` | string, indexed | denormalised handle for fast lookups |
| `status_is_public` | boolean, indexed | denormalised `is_public` flag |

`Entry::scopePublished()` filters on `status_is_public = true` AND
`published_at IS NOT NULL` AND `published_at <= now()`. `scopeWithStatus($handle)`
filters on `status_handle = ?`.

> **Doc-vs-code drift.** `CRITICAL_ISSUES.md` and `CONCERNS.md` describe a
> single free-text `status` column with no referential integrity. That has
> since been split into the three-column shape above, with HIGH-05 / BR-01
> marked `[RESOLVED]` in `Phase1_ISSUES.md`. The `entries` migration confirms
> the FK on `status_id`. Validation against the *correct* status group is
> still partial — see `CURRENT_ISSUES_REVIEW.md` §1.

### StatusObserver — keeping `status_is_public` consistent

Editing a `Status` record (e.g. flipping its `is_public` flag) cascades to all
referencing entries via `app/Observers/StatusObserver.php`, which is registered
in `AppServiceProvider::boot()`:

```php
public function updating(Status $status): void
{
    if ($status->isDirty('is_public')) {
        Entry::where('status_id', $status->id)
            ->update(['status_is_public' => $status->is_public]);
    }
}
```

This is what keeps the denormalised `entries.status_is_public` column truthful
when an admin toggles a status's public flag without rewriting every entry.

---

## Category Groups and Categories

Categories are hierarchical (parent/child tree). A `CategoryGroup` owns a
flat-or-tree set of categories. Multiple CategoryGroups can be attached to an
EntryGroup so entries can be tagged from each group's vocabulary, via the
polymorphic `category_groupables` pivot.

Schema notes:

- `category_groups`: `name`, `handle` (unique), `field_layout_id` (nullable, FK
  set null on delete), `sort_order`.
- `categories`: `group_id` (FK, cascade on delete), `parent_id` (nullable, FK
  to self, set null on delete), `name`, `handle`, `sort_order`. Unique on
  `(group_id, handle)`.

> **Doc-vs-code drift.** Categories use `handle` and `group_id` (not `slug` or
> `category_group_id`).

### Creating a Category Group and Categories

```php
use App\Models\Category;
use App\Models\Category\Group as CategoryGroup;

$group = CategoryGroup::firstOrCreate(
    ['handle' => 'regions'],
    ['name'   => 'Regions', 'sort_order' => 1]
);

// Root category
$europe = Category::create([
    'group_id'   => $group->id,
    'name'       => 'Europe',
    'handle'     => 'europe',
    'sort_order' => 1,
]);

// Child category
Category::create([
    'group_id'   => $group->id,
    'parent_id'  => $europe->id,
    'name'       => 'France',
    'handle'     => 'france',
    'sort_order' => 1,
]);
```

### Fetching Categories

```php
// Root categories with full recursive tree (default depth limit = 10)
$group->rootCategories()->with('childrenRecursive')->get();

// Scoped query
Category::inGroup($group)->roots()->with('childrenRecursive')->get();

// Cap recursion depth explicitly
$category->load(['childrenRecursive' => fn ($q) => $q->childrenRecursive(3)]);
```

`Category::childrenRecursive(int $maxDepth = 10)` returns an empty `HasMany`
once the depth budget is exhausted. `CategoryService::move()` further guards
against cycles by walking up the proposed parent chain.

---

## Accessing Entry Categories via the Content Facade

Entries carry a `categories()` `morphToMany` relationship via the
`HasCategories` trait (`App\Traits\Category\HasCategories`). The pivot table
is `categorizables`. Use eager loading to avoid N+1.

### Eager loading on a query

```php
use App\Facades\Content;

$entries = Content::query()
    ->inGroup('blog')
    ->published()
    ->with('categories')
    ->get();

foreach ($entries as $entry) {
    foreach ($entry->categories as $category) {
        echo $category->name;
        echo $category->handle;
    }
}
```

### Loading categories with their group

```php
$entries = Content::query()
    ->inGroup('blog')
    ->published()
    ->with('categories.group')   // also load each category's CategoryGroup
    ->get();

foreach ($entries as $entry) {
    foreach ($entry->categories as $category) {
        echo $category->group->name;   // e.g. "Topics"
        echo $category->name;
    }
}
```

### Single entry

```php
$entry = Content::query()
    ->inGroup('blog')
    ->where('handle', 'my-post')
    ->with('categories')
    ->firstOrFail();

// All categories
$entry->categories;                              // Collection<Category>

// Filter to a specific group's categories
$topics = $entry->categories->filter(
    fn ($c) => $c->group->handle === 'topics'
);

// Check membership
$entry->categories->contains('handle', 'php');   // bool
```

### Filtering entries by category

```php
use App\Models\Category;

$php = Category::where('handle', 'php')->firstOrFail();

$entries = Content::query()
    ->inGroup('blog')
    ->withCategory($php->id)
    ->published()
    ->with('categories')
    ->get();
```

### Accessing category field values on an entry's categories

`Category` uses the `Fieldable` trait, so scalar custom field values are
readable directly with `field()`. **Categories do not support relational
fields** — `Fieldable::field()` only inspects `fieldValues`, not
`entryRelationships`. Only `Entry::field()` adds the relational fallback.

```php
$entry->load('categories.fieldValues.field.fieldType');

foreach ($entry->categories as $category) {
    $category->field('cat_description'); // scalar custom field value
}
```

---

## Field Layouts

A `FieldLayout` organises fields into named tabs. Layouts are attached to
EntryGroups, EntryTypes, CategoryGroups, and UserSchema. `FieldLayout::tabs()`
is ordered by `sort_order`, and `Tab::elements()` is similarly ordered.

### Building a Layout Programmatically

```php
use App\Models\Field;
use App\Models\FieldLayout;
use App\Models\FieldLayout\Tab;
use App\Models\FieldLayout\TabElement;

$layout = FieldLayout::create(['name' => 'Article Layout']);

$contentTab = Tab::create([
    'field_layout_id' => $layout->id,
    'name'            => 'Content',
    'sort_order'      => 1,
]);

foreach (['body', 'excerpt', 'related_entries'] as $order => $handle) {
    $field = Field::where('handle', $handle)->firstOrFail();
    TabElement::create([
        'field_layout_tab_id' => $contentTab->id,
        'field_id'            => $field->id,
        'required'            => $handle === 'body',
        'sort_order'          => $order + 1,
    ]);
}

$seoTab = Tab::create([
    'field_layout_id' => $layout->id,
    'name'            => 'SEO',
    'sort_order'      => 2,
]);

foreach (['meta_title', 'meta_description'] as $order => $handle) {
    TabElement::create([
        'field_layout_tab_id' => $seoTab->id,
        'field_id'            => Field::where('handle', $handle)->value('id'),
        'required'            => false,
        'sort_order'          => $order + 1,
    ]);
}
```

### Getting All Fields from a Layout

```php
$layout->fields(); // Collection<Field>, flattened from all tabs in order
```

`FieldLayout::fields()` calls `loadMissing('tabs.elements.field')`, so it is
N+1-safe even from a fresh layout instance.

> **Doc-vs-code drift.** `Phase1_ISSUES.md` HIGH-08 about `fields()` triggering
> silent N+1 is marked `[RESOLVED]` precisely because of this `loadMissing()`
> call.

### `TabElement.required` is metadata-only at the schema level

The `required` boolean on a `TabElement` is *displayed* in the UI but is not
universally enforced server-side. `app/Http/Requests/FormRequest.php` honours
it, but entry validation only resolves the *group* layout via
`EntryGroup::resolvedFields()` — it does **not** also pull in the entry-type
layout. If a required field lives on the type layout it can be silently
omitted. `CURRENT_ISSUES_REVIEW.md` §4 captures the fix: introduce a single
"effective merged layout" resolver and use it in both validation and
persistence.

---

## Entry Groups and Entry Types

An **EntryGroup** is the section/channel (e.g. "Blog", "Products"). It ties
together a FieldLayout, a StatusGroup, polymorphic CategoryGroups, and
polymorphic FieldGroups (the latter two via the `HasCategoryGroups` and
`HasFieldGroups` traits).

An **EntryType** is a database row that maps a group-scoped handle to a
concrete PHP class extending `AbstractEntryType`. Its full schema:

| Column | Description |
|---|---|
| `entry_group_id` | FK → `entry_groups`, cascade on delete |
| `field_layout_id` | Optional override layout for this type |
| `name`, `handle` | Display name + dev handle (`(entry_group_id, handle)` unique) |
| `class` | FQCN of an `AbstractEntryType` subclass |
| `default_template` | Optional default template for the SiteRouter |
| `has_entry_tree` | Whether entries of this type participate in `entry_trees` |
| `max_depth`, `allowed_parent_types` (JSON) | Tree-traversal constraints |
| `sort_order` | Display order within the group |

### Multiple Entry Types per Group

An Entry Group can have any number of Entry Types. This is how variant content
is modelled within a single section:

```
Products (entry group)
  ├── product_digital      → ProductDigitalEntryType::class
  ├── product_shippable    → ProductShippableEntryType::class
  └── product_subscription → ProductSubscriptionEntryType::class
```

### Field Layering: Group Fields + Type Fields

Fields are defined at two levels and **merged** at write time:

| Level | Defined on | Applies to |
|---|---|---|
| Group-level layout | `EntryGroup.field_layout_id` | All entry types in the group |
| Type-level layout | `EntryType.field_layout_id` (nullable) | Only entries of that specific type |

`EntryRepository::resolveLayoutFields()` merges both layouts, with **type-level
fields taking precedence** when a handle appears in both:

```php
// EntryRepository::resolveLayoutFields()
$groupFields = $entry->entryGroup->fieldLayout?->fields() ?? collect();
$typeFields  = $entry->entryType->fieldLayout?->fields() ?? collect();

return $typeFields->merge($groupFields)->unique('id');
```

Example breakdown for the Products group:

```
Group layout — shared across all product types:
  Tab: Core
    - name, description, price, sku

ProductDigital type layout — additive:
  Tab: Digital Delivery
    - download_url, file_size, license_type

ProductShippable type layout — additive:
  Tab: Shipping
    - weight, dimensions

ProductSubscription type layout — additive:
  Tab: Subscription
    - billing_interval, trial_days
```

### Setting Up Multiple Entry Types in One Group

```php
use App\Models\EntryGroup;
use App\Models\EntryType;
use App\Models\Field;
use App\Models\Field\Type as FieldType;
use App\Models\FieldLayout;
use App\Models\FieldLayout\Tab;
use App\Models\FieldLayout\TabElement;

$textType = FieldType::where('object', \App\Field\Types\Text::class)->firstOrFail();

$productsGroup = EntryGroup::where('handle', 'products')->firstOrFail();

// --- ProductDigital ---
$digitalLayout = FieldLayout::create(['name' => 'Digital Product Fields']);
$tab = Tab::create([
    'field_layout_id' => $digitalLayout->id,
    'name'            => 'Digital Delivery',
    'sort_order'      => 1,
]);

foreach (['download_url', 'license_type'] as $i => $handle) {
    $field = Field::firstOrCreate(
        ['handle' => $handle],
        [
            'name'          => ucwords(str_replace('_', ' ', $handle)),
            'label'         => ucwords(str_replace('_', ' ', $handle)),
            'field_type_id' => $textType->id,
        ]
    );
    TabElement::create([
        'field_layout_tab_id' => $tab->id,
        'field_id'            => $field->id,
        'sort_order'          => $i + 1,
    ]);
}

EntryType::create([
    'entry_group_id'  => $productsGroup->id,
    'field_layout_id' => $digitalLayout->id,
    'name'            => 'Digital Product',
    'handle'          => 'product_digital',
    'class'           => \App\EntryTypes\ProductDigitalEntryType::class,
    'sort_order'      => 1,
]);

// --- ProductShippable ---
$shippableLayout = FieldLayout::create(['name' => 'Shippable Product Fields']);
$tab = Tab::create([
    'field_layout_id' => $shippableLayout->id,
    'name'            => 'Shipping',
    'sort_order'      => 1,
]);

foreach (['weight', 'dimensions'] as $i => $handle) {
    $field = Field::firstOrCreate(
        ['handle' => $handle],
        ['name' => ucwords($handle), 'label' => ucwords($handle), 'field_type_id' => $textType->id]
    );
    TabElement::create([
        'field_layout_tab_id' => $tab->id,
        'field_id'            => $field->id,
        'sort_order'          => $i + 1,
    ]);
}

EntryType::create([
    'entry_group_id'  => $productsGroup->id,
    'field_layout_id' => $shippableLayout->id,
    'name'            => 'Shippable Product',
    'handle'          => 'product_shippable',
    'class'           => \App\EntryTypes\ProductShippableEntryType::class,
    'sort_order'      => 2,
]);
```

### Entry Type Classes Can Share Logic via a Base Class

```php
// app/EntryTypes/BaseProductEntryType.php
namespace App\EntryTypes;

abstract class BaseProductEntryType extends AbstractEntryType
{
    public function beforeCreate(array $data): array
    {
        // Shared default for all product types
        if (empty($data['status'])) {
            $data['status'] = 'draft';
        }

        return $data;
    }
}

// app/EntryTypes/ProductDigitalEntryType.php
class ProductDigitalEntryType extends BaseProductEntryType
{
    public function afterCreate(\App\Models\Entry $entry, array $data): void
    {
        // Generate a license key, notify fulfilment service, etc.
    }
}

// app/EntryTypes/ProductShippableEntryType.php
class ProductShippableEntryType extends BaseProductEntryType
{
    public function afterCreate(\App\Models\Entry $entry, array $data): void
    {
        // Sync with inventory system, etc.
    }
}
```

### Lifecycle Hook Signatures

The base class is `App\EntryTypes\AbstractEntryType`. Hooks are *value-returning*
mutators for the data array — they do **not** take a reference and they do
**not** return `void`:

```php
public function beforeCreate(array $data): array { return $data; }
public function afterCreate(Entry $entry, array $data): void {}

public function beforeUpdate(Entry $entry, array $data): array { return $data; }
public function afterUpdate(Entry $entry, array $data): void {}
```

`EntryRepository::create()` wraps `beforeCreate()` and the new-entry persistence
in a transaction; `afterCreate()` runs **outside** the transaction so its
side-effects (emails, queue jobs, webhooks) cannot be rolled back. The same
applies to `applyData()` and `afterUpdate()`.

> **Doc-vs-code drift.** Earlier examples in this document used
> `beforeCreate(array &$data): void` — a passed-by-reference void method.
> That signature does not match `AbstractEntryType` and would be silently
> ignored by PHP at the override site. Use the value-return shape above. The
> live example in `app/EntryTypes/PodcastEpisodeEntryType.php` uses the
> correct signature, including a `lockForUpdate()` row lock to assign a
> sequential `episode_number` field.

### Creating Entries of Each Type

```php
use App\Facades\Content;

// Digital — receives group fields + digital-specific fields
Content::create('product_digital', [
    'title'  => 'Laravel eBook',
    'status' => 'published',
    'fields' => [
        'price'        => 2999,                                   // group field
        'sku'          => 'EBOOK-001',                            // group field
        'download_url' => 'https://cdn.example.com/laravel.pdf',  // type field
        'license_type' => 'single-user',                          // type field
    ],
]);

// Shippable — receives group fields + shipping-specific fields
Content::create('product_shippable', [
    'title'  => 'Merino Wool Sweater',
    'status' => 'published',
    'fields' => [
        'price'      => 8900,
        'sku'        => 'SWTR-M-BL',
        'weight'     => '0.4kg',
        'dimensions' => '30x20x5cm',
    ],
]);
```

Querying spans all types in the group regardless of type:

```php
// Returns every product entry — digital, shippable, and subscription
Content::query()->inGroup('products')->published()->get();

// Narrow to a specific type
Content::query()->ofType('product_digital')->published()->get();
```

### Multiple Groups Sharing the Same Entry Type Class

The same PHP class can back multiple Entry Type rows. The handle must be
unique *within* a group (`(entry_group_id, handle)` unique), but the `class`
column can repeat. There is **no DB-level uniqueness** on `class`, so the
same class can legitimately back multiple rows.

```php
EntryType::create([
    'entry_group_id' => EntryGroup::where('handle', 'electronics')->value('id'),
    'handle'         => 'electronics_product',
    'class'          => \App\EntryTypes\ProductEntryType::class,
    'name'           => 'Electronics Product',
]);

EntryType::create([
    'entry_group_id' => EntryGroup::where('handle', 'clothing')->value('id'),
    'handle'         => 'clothing_product',
    'class'          => \App\EntryTypes\ProductEntryType::class,
    'name'           => 'Clothing Product',
]);

Content::create('electronics_product', [/* ... */]);
Content::create('clothing_product',    [/* ... */]);
```

### Creating an Entry Group

```php
use App\Models\EntryGroup;
use App\Models\StatusGroup;
use App\Models\Category\Group as CategoryGroup;
use App\Models\Field\Group as FieldGroup;

$statusGroup   = StatusGroup::where('handle', 'review')->firstOrFail();
$categoryGroup = CategoryGroup::where('handle', 'regions')->firstOrFail();
$fieldGroup    = FieldGroup::where('handle', 'content-fields')->firstOrFail();

$group = EntryGroup::create([
    'name'            => 'News Articles',
    'handle'          => 'news',                 // unique on entry_groups
    'description'     => 'News and press releases.',
    'field_layout_id' => $layout->id,
    'status_group_id' => $statusGroup->id,
    'sort_order'      => 3,
]);

$group->fieldGroups()->syncWithoutDetaching([$fieldGroup->id]);
$group->categoryGroups()->syncWithoutDetaching([$categoryGroup->id]);
```

> **Doc-vs-code drift.** `entry_groups.status_group_id` is *nullable* in the
> migration and in the `CreateNewEntryGroup` action. `EntryRepository`,
> however, throws `RuntimeException` if it is missing during status
> resolution. Treat it as effectively required. See
> `CURRENT_ISSUES_REVIEW.md` §3 (still partially open).

### Creating an Entry Type Class

```php
// app/EntryTypes/NewsArticleEntryType.php
namespace App\EntryTypes;

use App\Models\Entry;

class NewsArticleEntryType extends AbstractEntryType
{
    public function beforeCreate(array $data): array
    {
        // Force all new articles into 'pending' status
        $data['status'] = 'pending';

        return $data;
    }

    public function afterCreate(Entry $entry, array $data): void
    {
        // SendReviewNotification::dispatch($entry);
    }

    public function beforeUpdate(Entry $entry, array $data): array
    {
        // Prevent reverting to draft after approval
        if ($entry->status_handle === 'approved' && ($data['status'] ?? null) === 'draft') {
            unset($data['status']);
        }

        return $data;
    }
}
```

### Registering the Entry Type in the Database

```php
use App\Models\EntryGroup;
use App\Models\EntryType;

$group = EntryGroup::where('handle', 'news')->firstOrFail();

EntryType::firstOrCreate(
    ['entry_group_id' => $group->id, 'handle' => 'news_article'],
    [
        'name'       => 'News Article',
        'class'      => \App\EntryTypes\NewsArticleEntryType::class,
        'sort_order' => 1,
    ]
);
```

---

## Creating and Updating Entries

All entry creation goes through one of two functionally identical facades:

- `App\Facades\Content` → `App\Services\ContentService` (a thin alias subclass
  of `EntryService` kept for backward compatibility).
- `App\Facades\Entries` → `App\Services\EntryService` directly. New code should
  prefer `Entries`.

`EntryService` delegates to `EntryRepository` for the heavy lifting (transaction,
status handling, polymorphic field upserts, relational sync).

### Creating an Entry

```php
use App\Facades\Content;
use App\Models\Category;
use App\Models\User;

$author   = User::find(1);
$category = Category::where('handle', 'france')->firstOrFail();

$entry = Content::create('news_article', [
    'title'        => 'Election Results 2026',
    'published_at' => now(),
    'status'       => 'published',
    'authors'      => [$author->id],            // ordered M2M, sort_order = array key
    'categories'   => [$category->id],
    'fields'       => [
        'body'             => 'Full article text...',
        'excerpt'          => 'Short summary.',
        'meta_title'       => 'Election Results 2026 | News',
        'meta_description' => 'Coverage of the 2026 election.',
    ],
]);

echo $entry->id;       // persisted Entry model
echo $entry->handle;   // auto-generated from title via Str::slug if not provided
```

`EntryRepository::applyCoreAttributes()` always sets `handle` — if the caller
provides one it is used as-is, otherwise it is generated as
`Str::slug($entry->title ?? '')`. Two entries cannot share a handle within the
same `entry_group_id` (`(entry_group_id, handle)` is unique).

### Updating an Entry

```php
// Via the facade (recommended)
$updated = Content::update($entry, [
    'title'  => 'Updated Title',
    'status' => 'approved',
    'fields' => [
        'excerpt' => 'Revised summary.',
    ],
]);
```

> **Doc-vs-code drift.** Earlier docs claimed `Entry::update()` was overridden
> on the model. That override has been removed (per `CRITICAL_ISSUES.md` §1
> `[RESOLVED]`) so that Eloquent observers, dirty-state tracking, and
> packages that hook update events behave normally. Direct `$entry->update([
> ...])` writes core attributes only — it does **not** sync authors,
> categories, or custom fields.

### Using the Relationship Field

Relationship fields store related entry IDs in the dedicated
`entry_relationships` table — **not** in `field_values`. The field type's
`isRelational()` returns `true`, which routes writes through
`EntryRepository::syncRelationshipField()` and reads through
`Entry::entryRelationships`.

#### Writing — create or update

Pass an array of related Entry IDs under the field handle. Array order is
preserved as `sort_order`. `syncRelationshipField()` filters out direct
self-references (A → A); indirect cycles (A → B → A) are *not* prevented at
write time.

```php
$relatedA = Content::query()->inGroup('products')->where('handle', 'widget-a')->value('id');
$relatedB = Content::query()->inGroup('products')->where('handle', 'widget-b')->value('id');

// On create
$post = Content::create('blog_post', [
    'title'            => 'My Post',
    'handle'           => 'my-post',
    'related_products' => [$relatedA, $relatedB],
]);

// On update — replaces all existing pivots for that field
Content::update($post, [
    'related_products' => [$relatedB], // removes $relatedA, keeps $relatedB
]);
```

#### Reading — returns `Collection<Entry>`

`Entry::field('handle')` first looks for a scalar `FieldValue` and falls back
to relational entries if no scalar exists for that handle:

```php
$post = Content::query()
    ->inGroup('blog')
    ->where('handle', 'my-post')
    ->firstOrFail();

$relatedProducts = $post->field('related_products'); // Collection<Entry>

foreach ($relatedProducts as $product) {
    echo $product->title;
    echo $product->field('price'); // scalar field on the related entry
}
```

> **Doc-vs-code drift.** `Fieldable::field()` (used by `User` and `Category`)
> only inspects `fieldValues`. The `Entry` model's overridden `field()` adds
> the relational fallback. So users and categories cannot use relationship
> fields, even if a relationship-type Field is somehow attached to them.

#### Eager loading (N+1 prevention)

`EntryRepository::defaultEagerLoad()` and `EntryQueryBuilder::eagerLoad()` both
include `entryRelationships.field` and `entryRelationships.relatedEntry`, so
relationship pivots and target entries are loaded automatically on standard
queries. To also load scalar fields on the related entries:

```php
$posts = Content::query()
    ->inGroup('blog')
    ->published()
    ->with([
        'entryRelationships.field',
        'entryRelationships.relatedEntry.fieldValues.field.fieldType',
    ])
    ->get();
```

#### Deeper recursion with cycle detection

When you need to walk relationships across multiple hops, use
`EntryService::loadRelatedRecursive()` (also reachable as `Entries::resolveFields`
adjacent helper). It is depth-limited (`$maxDepth = 3` by default) and tracks
seen IDs to break cycles:

```php
use App\Facades\Entries;

$tree = Entries::loadRelatedRecursive($post, 'related_products', maxDepth: 3);
// Returns a flat Collection<Entry> in traversal order, deduplicated by ID.
```

#### Accessing the raw pivot (sort order, field metadata)

```php
$post->entryRelationships
    ->where('field.handle', 'related_products')
    ->sortBy('sort_order')
    ->each(function ($pivot) {
        echo $pivot->sort_order;
        echo $pivot->relatedEntry->title;
    });
```

#### Checking emptiness

```php
$related = $post->field('related_products'); // Collection<Entry> or null

if ($related && $related->isNotEmpty()) {
    // has related entries
}
```

> **Scalar vs relationship fields:** `$entry->field('handle')` returns a
> single value for scalar fields (text, integer, date, …) and a
> `Collection<Entry>` for relationship fields. The distinction is determined
> by the field type's `isRelational()` flag.

---

## Querying Entries

Use `Content::query()` (or `Entries::query()`) for a fluent, chainable query
builder backed by `App\Builders\EntryQueryBuilder`. Everything goes through the
`EntryRepository` for read-side eager loading.

> **`inGroup()` vs `ofType()`:** `inGroup('blog')` matches the **EntryGroup
> handle** (e.g. `'blog'`, `'products'`). `ofType('blog_post')` matches the
> **EntryType handle** (e.g. `'blog_post'`, `'product'`). Passing a type
> handle to `inGroup()` will silently return no results.

```php
use App\Facades\Content;
use App\Models\Category;

// All published blog posts, newest first
$posts = Content::query()
    ->inGroup('blog')
    ->published()
    ->latest()
    ->get();

// Filter by entry type
$articles = Content::query()
    ->ofType('news_article')
    ->withStatus('approved')
    ->paginate(20);

// Filter by author
$myPosts = Content::query()
    ->inGroup('blog')
    ->withAuthor(Auth::id())
    ->latest()
    ->get();

// Filter by category
$technology = Category::where('handle', 'technology')->firstOrFail();

$techPosts = Content::query()
    ->inGroup('blog')
    ->withCategory($technology->id)
    ->published()
    ->orderBy('published_at', 'desc')
    ->paginate(10);

// Single entry
$entry = Content::get(42);   // throws ModelNotFoundException if missing
$entry = Content::find(42);  // returns null if missing

// Single entry by handle within a group
$entry = Entries::query()->inGroup('blog')->where('handle', 'my-post')->firstOrFail();
```

### Full `EntryQueryBuilder` surface

| Method | Notes |
|---|---|
| `inGroup($group)` | `string\|int\|EntryGroup` |
| `ofType($type)` | `string\|int\|EntryType` |
| `published()` | `status_is_public = true AND published_at <= now()` |
| `withStatus($handle)` | matches `status_handle` |
| `withAuthor(int $userId)` | `whereHas('authors', users.id)` |
| `withCategory(int $categoryId)` | `whereHas('categories', categories.id)` |
| `where($column, $op, $value = null)` | passthrough |
| `orderBy($column, $direction = 'asc')` | passthrough |
| `latest()` | `orderBy('created_at', 'desc')` |
| `get()` / `paginate(int)` / `first()` / `firstOrFail()` | terminal methods, eager-loading every time |
| `count()` | does **not** apply eager loads |

### Reading Field Values

```php
// Scalar fields — returns the cast value directly
echo $entry->field('body');
echo $entry->field('meta_title');
echo $entry->field('price');        // integer or float depending on Number's `decimals` setting

// Date field — returns Carbon (FieldValue casts value_date as datetime)
$entry->field('event_date')?->format('Y-m-d');

// Relationship field — returns Collection<Entry>
$related = $entry->field('related_entries');
foreach ($related as $rel) {
    echo $rel->title . ' (' . $rel->handle . ')';
}
```

> **Performance note:** `fieldValues.field.fieldType`,
> `entryRelationships.field`, and `entryRelationships.relatedEntry` are all
> included in `EntryRepository::defaultEagerLoad()`, so `Content::get()` and
> `Content::find()` never produce N+1 queries. The query builder's
> `get()`/`paginate()`/`first()` likewise eager-load both scalar and relational
> field data by default. Note also that the `Field` model uses
> `protected $with = ['fieldType']` and `FieldValue` uses
> `protected $with = ['field']`, which means a fresh `Field` query always
> joins its type. This is convenient but, per `Phase1_ISSUES.md` MED-02, can
> over-fetch in places where the type is not needed.

### Accessing Entry Authors

Entries have two distinct author concepts, both eager-loaded by default on
every `Content::query()` call.

| Relationship | Type | Description |
|---|---|---|
| `creator` | `BelongsTo User` | The user who created the entry record (`created_by_user_id`, set from `Auth::id()`) |
| `authors` | `BelongsToMany User` | Editorial byline, ordered by pivot `sort_order` |

```php
$post = Content::query()
    ->inGroup('blog')
    ->where('handle', 'the-pragmatic-programmer')
    ->firstOrFail();

// User who created the record
$post->creator->name;
$post->creator->email;

// Editorial authors (ordered by sort_order)
foreach ($post->authors as $author) {
    echo $author->name;
    echo $author->email;
    echo $author->pivot->sort_order;
}

// Check if a specific user is an author
$post->authors->contains('id', $userId); // bool

// Just the names
$post->authors->pluck('name'); // Collection ["Alice", "Bob"]

// Primary author (first by sort_order)
$post->authors->first()?->name;
```

Filter entries by author using `withAuthor()`:

```php
$myPosts = Content::query()
    ->inGroup('blog')
    ->withAuthor(Auth::id())
    ->published()
    ->get();
```

---

## Deleting Entries

```php
// Via plain Eloquent — fires observer events normally now that Entry::delete()
// is no longer overridden. FK cascades remove field_values, entry_relationships,
// entry_authors, categorizables, and the entry_tree node.
$entry->delete();

// Via the facade — currently equivalent
\App\Facades\Entries::delete($entry);
```

> **Doc-vs-code drift.** The `Content` facade docblock is missing a
> `delete()` annotation. Calling `Content::delete($entry)` still works at
> runtime because `ContentService` extends `EntryService::delete()`, but the
> static analyser will not know about it. Prefer `Entries::delete()` (its
> docblock includes `bool delete(Entry $entry)`).

---

## User Extended Profile (UserSchema)

`UserSchema` is a singleton (`user_schema.id = 1`) that owns a single
`FieldLayout` applied to all users. Users use the `Fieldable` trait so they can
store custom field values polymorphically. The read API
(`$user->field('handle')`) is identical to entries; the write side goes through
`Users::setField()` / `Users::setFields()` (or the `PersistsFieldValues`
concern), since there is no `UserRepository` equivalent.

```php
UserSchema::instance();              // public alias for ::resolved()
UserSchema::resolved();              // singleton with full layout eager-loaded, request-scoped cache
UserSchema::flushResolved();         // clear the request cache (useful in tests)
```

> **Doc-vs-code drift.** `Phase1_ISSUES.md` LOW-01 notes the in-process
> static cache can leak across long-lived processes (queue workers, tests)
> unless `flushResolved()` is called. The model exposes `flushResolved()`
> precisely for that case.

### Setting Up the User Schema

`UserSchemaSeeder` creates two FieldGroups and a two-tab layout:

| FieldGroup | `handle` | Fields |
|---|---|---|
| User Profile | `user-profile` | `first_name`, `last_name`, `gender`, `date_of_birth`, `website` |
| User Bio | `user-bio` | `bio`, `social_twitter`, `social_linkedin` |

Run the seeder to initialise the schema:

```bash
php artisan db:seed --class=UserSchemaSeeder
```

To set this up manually (e.g. in a custom seeder):

```php
use App\Models\Field;
use App\Models\Field\Group as FieldGroup;
use App\Models\Field\Type as FieldType;
use App\Models\FieldLayout;
use App\Models\FieldLayout\Tab;
use App\Models\FieldLayout\TabElement;
use App\Models\UserSchema;

$text = FieldType::where('object', \App\Field\Types\Text::class)->firstOrFail();

// 1. Create fields (note: handle, NOT slug)
$firstName = Field::firstOrCreate(['handle' => 'first_name'], ['field_type_id' => $text->id, 'name' => 'First Name', 'label' => 'First Name']);
$lastName  = Field::firstOrCreate(['handle' => 'last_name'],  ['field_type_id' => $text->id, 'name' => 'Last Name',  'label' => 'Last Name']);
$gender    = Field::firstOrCreate(['handle' => 'gender'],     ['field_type_id' => $text->id, 'name' => 'Gender',     'label' => 'Gender']);

// 2. Create a FieldGroup and attach the fields
$group = FieldGroup::firstOrCreate(
    ['handle' => 'user-profile'],
    ['name' => 'User Profile', 'description' => 'Core identity fields for all users.']
);
$group->fields()->syncWithoutDetaching([$firstName->id, $lastName->id, $gender->id]);

// 3. Build a FieldLayout with a tab
$layout = FieldLayout::create(['name' => 'User Profile Layout']);
$tab    = Tab::create(['field_layout_id' => $layout->id, 'name' => 'Profile', 'sort_order' => 1]);

foreach ([$firstName, $lastName, $gender] as $i => $field) {
    TabElement::create([
        'field_layout_tab_id' => $tab->id,
        'field_id'            => $field->id,
        'required'            => false,
        'sort_order'          => $i + 1,
    ]);
}

// 4. Wire layout and FieldGroup to the singleton
$schema = UserSchema::instance();
$schema->field_layout_id = $layout->id;
$schema->save();

$schema->fieldGroups()->syncWithoutDetaching([$group->id]);
```

### Reading the layout back

```php
$schema = UserSchema::instance();   // already eager-loads fieldLayout.tabs.elements.field

foreach ($schema->fieldLayout->tabs as $tab) {
    echo $tab->name; // "Profile", "Bio"
    foreach ($tab->elements as $el) {
        echo $el->field->handle; // "first_name", "last_name", ...
    }
}
```

### Writing Field Values to a User

The recommended path is `Users::setField()` / `Users::setFields()`, which uses
the `PersistsFieldValues` concern and routes through `getMorphClass()`
(important — see the morph-map note in [Architecture at a Glance](#architecture-at-a-glance)):

```php
use App\Facades\Users;

Users::setFields($user, [
    'first_name' => 'Jane',
    'last_name'  => 'Doe',
    'gender'     => 'female',
]);
```

If you must write `FieldValue` rows by hand, mirror what
`PersistsFieldValues::setField()` does — and **do not** hard-code `User::class`
as `fieldable_type`, because the morph map stores the alias `'user'` instead:

```php
use App\Models\Field;
use App\Models\FieldValue;

$user  = User::find(1);
$field = Field::where('handle', 'first_name')->firstOrFail();

FieldValue::updateOrCreate(
    [
        'field_id'       => $field->id,
        'fieldable_id'   => $user->getKey(),
        'fieldable_type' => $user->getMorphClass(),     // 'user', not User::class
    ],
    [$field->fieldType->instance()->storageColumn() => 'Jane']
);
```

### Reading Field Values from a User

```php
$user = User::with('fieldValues.field.fieldType')->find(1);

echo $user->field('first_name'); // 'Jane'
echo $user->field('last_name');  // 'Doe'
echo $user->field('gender');     // 'female'
```

`field()` comes from the `Fieldable` trait. As long as
`fieldValues.field.fieldType` is eager-loaded, no additional queries are
issued.

### Typical Controller Pattern

```php
// Reading a user's profile
public function show(User $user): array
{
    $user->load('fieldValues.field.fieldType');

    return [
        'name'       => $user->name,
        'email'      => $user->email,
        'first_name' => $user->field('first_name'),
        'last_name'  => $user->field('last_name'),
        'gender'     => $user->field('gender'),
    ];
}

// Updating a user's profile fields
public function update(Request $request, User $user): void
{
    \App\Facades\Users::update($user, [
        'fields' => $request->input('fields', []),
    ]);
}
```

### Comparison: Users vs Entries

| | Entries | Users |
|---|---|---|
| Write API | `Content::create()` / `Content::update()` (or `Entries::*`) | `Users::create()` / `Users::update()` |
| Read API | `$entry->field('handle')` | `$user->field('handle')` |
| Schema | Per-group FieldLayout + per-type FieldLayout (merged with type precedence) | Single `UserSchema` singleton FieldLayout |
| Lifecycle hooks | `beforeCreate`, `afterCreate`, `beforeUpdate`, `afterUpdate` on EntryType class | None — plain Eloquent + `UserService` orchestration |
| Custom fields | Scalar + Relational | **Scalar only** (`Fieldable::field()` does not inspect `entry_relationships`) |

---

## System Health and Data Integrity

The system includes a dedicated command to validate that all class-name strings
stored in the database (for Entry Types and Field Types) still resolve to live
classes that satisfy the expected base type. This is critical because both
`entry_types.class` and `field_types.object` are plain `VARCHAR` columns with
no compile-time link to the class.

```bash
# Validate all class references in the database
php artisan app:validate-class-references
```

> **Doc-vs-code drift.** Earlier docs called this command
> `system:validate-class-references`. The actual signature in
> `app/Console/Commands/ValidateClassReferences.php` is
> `app:validate-class-references`. The command exits with status `FAILURE` if
> any reference is broken — wire it into CI before deploys.

Polymorphic stability is provided by **Eloquent Morph Maps**, configured in
`AppServiceProvider::boot()`. The active aliases are:

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

This means polymorphic columns like `field_values.fieldable_type`,
`fieldables.fieldable_type`, `field_groupables.field_groupable_type`,
`category_groupables.category_groupable_type`, and `categorizables.categorizable_type`
contain the alias (e.g. `'entry'`), not the FQCN. Always rely on
`$model->getMorphClass()` for new writes. Per
`CURRENT_ISSUES_REVIEW.md` §2, any historical rows that still hold
`App\Models\...` strings need to be backfilled — the project's intended
remediation is `migrate:fresh --seed` rather than a backfill migration.

---

## UserService and the Users Facade

User operations are centralised in `App\Services\UserService`, exposed via the
`App\Facades\Users` facade. Each public method on the service is documented
on the facade's `@method` docblock.

### CRUD

```php
use App\Facades\Users;

// Create — accepts core attributes plus optional roles and fields in one call
$user = Users::create([
    'name'     => 'Jane Doe',
    'email'    => 'jane@example.com',
    'password' => 'secret',
    'roles'    => ['admin'],
    'fields'   => [
        'first_name' => 'Jane',
        'last_name'  => 'Doe',
        'gender'     => 'female',
    ],
]);

// Update — only keys present in $data are touched
$user = Users::update($user, [
    'name'   => 'Jane Smith',
    'roles'  => ['user'],           // replaces all existing roles
    'fields' => ['gender' => 'non-binary'],
]);

// Delete
Users::delete($user);
```

`UserService::create()` hashes the password (when supplied), calls
`syncRoles()` from Spatie's `HasRoles` trait, and then routes the
`fields` array through the `PersistsFieldValues` concern (`setFields()`).
`UserService::update()` is shaped the same way: it strips `password`, `roles`,
and `fields` from the attribute mass-assignment, applies the rest via
`$user->update($attributes)`, then re-syncs roles and fields if those keys are
present.

### Roles

```php
Users::assignRoles($user, 'editor');               // additive — keeps existing roles
Users::assignRoles($user, ['editor', 'writer']);

Users::syncRoles($user, ['admin']);                // replaces all roles

Users::revokeRole($user, 'editor');                // removes one role
```

### Custom Fields

```php
// Single field
Users::setField($user, 'bio', 'Staff engineer at Acme.');

// Multiple fields — batched into one Field::whereIn lookup
Users::setFields($user, [
    'first_name' => 'Jane',
    'last_name'  => 'Smith',
    'gender'     => 'female',
]);

// Reading (via Fieldable trait — eager-load to avoid N+1)
$user->load('fieldValues.field.fieldType');
echo $user->field('first_name'); // 'Jane'
echo $user->field('bio');        // 'Staff engineer at Acme.'
```

> **Doc-vs-code drift.** `setField()` / `setFields()` come from
> `App\Concerns\PersistsFieldValues` (used by `UserService` and
> `CategoryService`). Per `CURRENT_ISSUES_REVIEW.md` §6, this concern still
> calls `FieldValue::updateOrCreate()` directly without the `23000` retry
> that `EntryRepository::upsertFieldValue()` and
> `CategoryRepository::upsertFieldValue()` apply. Concurrent user-field
> writes can therefore surface raw `QueryException` errors instead of being
> retried.

### Passwords

```php
// Admin force-set — no current-password verification, hashes via Hash::make
Users::setPassword($user, 'newpassword123');

// User-initiated change (verifies current password using Fortify's
// UpdatesUserPasswords contract)
app(\App\Actions\User\UpdateUserPassword::class)->update($user, [
    'current_password'      => 'oldpassword',
    'password'              => 'newpassword123',
    'password_confirmation' => 'newpassword123',
]);
```

### Two-Factor Authentication

2FA is provided by Laravel Fortify. The `User` model uses the
`TwoFactorAuthenticatable` trait. `UserService` thinly wraps the Fortify
actions so callers get a consistent surface.

```php
// Step 1 — enable 2FA; returns QR code SVG and plain-text secret for display
$setup = Users::enableTwoFactor($user);
// $setup['qr_code_svg'] — embed in the UI
// $setup['secret']      — plaintext OTP secret for fallback manual entry

// Step 2 — user submits a TOTP code
// Throws ValidationException via Fortify if the code is wrong
Users::confirmTwoFactor($user, '123456');

// Check whether 2FA is active (i.e. two_factor_confirmed_at is set)
Users::hasTwoFactor($user); // true after confirmation

// Recovery codes
$codes    = Users::getRecoveryCodes($user);          // array of strings
$newCodes = Users::regenerateRecoveryCodes($user);   // invalidate + reissue

// Disable 2FA — clears secret + recovery codes
Users::disableTwoFactor($user);
```

### OAuth Token Management

OAuth tokens live in `user_oauth_tokens` (`App\Models\User\OauthToken`). They
are *not* the same as Sanctum personal access tokens.

```php
// Store a new token — revokes any existing active token for the same provider
$token = Users::upsertOauthToken($user, 'google', [
    'access_token'     => 'ya29.xxx',
    'refresh_token'    => '1//xxx',
    'expires_at'       => now()->addHour(),
    'scopes'           => ['email', 'profile'],
    'provider_user_id' => '1234567890',
]);

// Get the most recently issued active token for a provider
$token = Users::getActiveOauthToken($user, 'google');

if ($token?->isExpired()) {
    // refresh via your OAuth client and call upsertOauthToken again
}

// Revoke a single token
Users::revokeOauthToken($token);

// Revoke all tokens for a specific provider
Users::revokeAllOauthTokens($user, 'google');

// Revoke all tokens across all providers
Users::revokeAllOauthTokens($user);

// List active tokens (optionally filtered by provider)
$tokens       = Users::listOauthTokens($user);
$googleTokens = Users::listOauthTokens($user, 'google');
```

### Action Classes Inventory

User-facing operations are also exposed as standalone classes under
`app/Actions/User/`. The catalogue is **smaller than older docs claimed** — it
is not a 1:1 mapping of every `UserService` method. The actual files are:

| Action class | Purpose |
|---|---|
| `App\Actions\User\CreateNewUser` | Implements `Laravel\Fortify\Contracts\CreatesNewUsers`. Wraps `Users::create()` so Fortify's registration flow funnels through `UserService`. |
| `App\Actions\User\UpdateUserPassword` | Implements `UpdatesUserPasswords`. Verifies current password, hashes, saves. |
| `App\Actions\User\UpdateUserProfileInformation` | Implements Fortify's profile update contract. |
| `App\Actions\User\ResetUserPassword` | Implements Fortify's password-reset contract. |
| `App\Actions\User\Token\CreateNewUserToken` | Issue a Sanctum personal access token for a user. |

> **Doc-vs-code drift.** Earlier versions of this document listed
> `CreateUser`, `UpdateUser`, `DeleteUser`, `AssignRoles`, `SyncRoles`,
> `RevokeRole`, `SetUserField`, `SetUserFields`, `SetPassword`,
> `EnableTwoFactor`, `ConfirmTwoFactor`, `DisableTwoFactor`,
> `GetRecoveryCodes`, `RegenerateRecoveryCodes`, `UpsertOauthToken`,
> `GetActiveOauthToken`, `RevokeOauthToken`, `RevokeAllOauthTokens`, and
> `ListOauthTokens` as standalone action classes. **They do not exist in
> `app/Actions/User/`** — those operations live exclusively as methods on
> `UserService` (and behind the `Users` facade). If you want them as
> first-class invokables, you have to author the action classes yourself.

There is, however, a comprehensive inventory of `App\Actions\*` classes for
the *content* side — `Entry`, `EntryGroup`, `EntryType`, `EntryTree`,
`FieldLayout` (and its `Tab` / `TabElement` subtree), `Field` and `FieldGroup`,
`Status` and `StatusGroup`, `Category` and its `Group`, `Media\Library`, and
`Role`. Those follow the `CreateNew…` / `Edit…` / `Delete…` naming convention
and are wired into the admin controllers under `app/Http/Controllers/Admin/`.

---

## Custom Field Groups on Category Groups

CategoryGroups use the `HasFieldGroups` and `HasFieldLayout` traits, so you can
attach extra fields to categories themselves. The `Category` model uses
`Fieldable`, giving each category record its own field-value storage.

There are two layers:

| Layer | Model | Purpose |
|---|---|---|
| Schema definition | `CategoryGroup` (`HasFieldGroups`, `HasFieldLayout`) | Defines *which* fields exist for categories in that group |
| Value storage | `Category` (`Fieldable`) | Stores actual field values per category record |

### Step 1 — Create Fields and attach them to the CategoryGroup

```php
use App\Models\Category\Group as CategoryGroup;
use App\Models\Field;
use App\Models\Field\Group as FieldGroup;

$categoryGroup = CategoryGroup::where('handle', 'topics')->firstOrFail();

// Create the fields
$descField  = Field::create(['name' => 'Description',  'handle' => 'cat_description',  'field_type_id' => $textareaTypeId]);
$imageField = Field::create(['name' => 'Banner Image', 'handle' => 'cat_banner_image', 'field_type_id' => $textTypeId]);

// Bundle them into a FieldGroup
$fieldGroup = FieldGroup::create(['name' => 'Category Details', 'handle' => 'category-details']);
$fieldGroup->fields()->attach([$descField->id, $imageField->id]);

// Attach the FieldGroup to the CategoryGroup (polymorphic pivot)
$categoryGroup->fieldGroups()->syncWithoutDetaching([$fieldGroup->id]);
```

You can also attach a pre-existing FieldGroup (e.g. shared SEO fields):

```php
$seoGroup = FieldGroup::where('handle', 'seo-fields')->firstOrFail();
$categoryGroup->fieldGroups()->syncWithoutDetaching([$seoGroup->id]);
```

### Step 2 — Write field values to a Category

The cleanest path is the `Categories` facade (`App\Facades\Categories`), which
exposes `setField()` / `setFields()` from the same `PersistsFieldValues`
concern that `UserService` uses:

```php
use App\Facades\Categories;

$category = \App\Models\Category::where('handle', 'php')->firstOrFail();

Categories::setField($category, 'cat_description', 'All things PHP.');

Categories::setFields($category, [
    'cat_description'  => 'All things PHP — tutorials, packages, and news.',
    'cat_banner_image' => '/images/php-banner.jpg',
]);
```

If you prefer manual writes, mirror the morph-map-aware shape:

```php
use App\Models\Category;
use App\Models\Field;
use App\Models\FieldValue;

$category = Category::where('handle', 'php')->firstOrFail();
$field    = Field::where('handle', 'cat_description')->firstOrFail();
$instance = $field->fieldType->instance();

if (! $instance->isRelational()) {
    FieldValue::updateOrCreate(
        [
            'field_id'       => $field->id,
            'fieldable_id'   => $category->getKey(),
            'fieldable_type' => $category->getMorphClass(),  // 'category' (alias), not Category::class
        ],
        [$instance->storageColumn() => 'All things PHP.']
    );
}
```

### Step 3 — Read field values back

```php
// Eager-load to avoid N+1
$category->load('fieldValues.field.fieldType');

$description = $category->field('cat_description');
$banner      = $category->field('cat_banner_image');
```

Categories — like users — only support **scalar** field values. Relationship
fields are not resolved by `Fieldable::field()`.

---

## Site Routing (Public-Facing URLs)

Public web requests are funnelled through a single catch-all route in
`routes/web.php`:

```php
Route::get('/{uri?}', [SiteController::class, 'show'])
    ->where('uri', '.*')
    ->name('site.show');
```

`SiteController::show()` delegates to `App\Services\SiteRouting\SiteRouter`,
which iterates over a configurable list of route drivers in priority order
(`config/site.php → routing.priority`):

```php
return [
    'routing' => [
        'priority' => [
            'entry_tree',
            'template',
        ],
    ],
    // ...
];
```

The available drivers, both implementing `RouteDriverInterface`, live in
`app/Services/SiteRouting/RouteDrivers/`:

| Driver | Resolution strategy |
|---|---|
| `EntryTreeRouteDriver` | Looks up the request URI in the `entry_trees` table (`uri` is unique). When a node is found, it returns a `RouteResult` whose `template` and `data` are derived from the entry's type and stored fields. |
| `TemplateRouteDriver` | Falls back to a Twig/Blade template under `resources/templates/...`, using the `templates::` view namespace registered in `AppServiceProvider`. |

`SiteRouter::resolve()` throws `Symfony\Component\HttpKernel\Exception\NotFoundHttpException`
if no driver claims the URI.

`EntryTree` itself is a self-referential tree:

| Column | Purpose |
|---|---|
| `entry_id` | unique FK to `entries` (cascade on delete) |
| `parent_id` | nullable FK to itself (set null on delete) |
| `handle` | URL segment for this node, normalised via `Str::slug` |
| `uri` | full normalised URI, **unique** in the table |
| `depth`, `sort_order` | tree placement |
| `template` | optional override template path |
| `is_home` | whether this node is the site root |

> **Doc-vs-code drift.** `Phase1_ISSUES.md` MED-06 / BR-04 are still open at
> the time of writing: `entry_trees.is_home` has no uniqueness constraint
> (so two nodes can both claim to be the homepage), and `parent_id`'s
> `nullOnDelete` can promote a deeply-nested node to a root, silently
> changing its URI. Treat both as application invariants you must enforce in
> `App\Actions\Entry\Tree\*` rather than at the DB layer.

The Socialite-driven login routes also live in `routes/web.php`:

```php
Route::get('login/{provider}',          [Login::class, 'redirectToProvider'])->name('social.login.provider');
Route::get('login/{provider}/callback', [Login::class, 'handleProviderCallback'])->name('social.login.callback');
```

> **Doc-vs-code drift.** `app/Http/Controllers/Login.php`'s
> `handleProviderCallback()` currently catches `InvalidStateException` with
> `echo "broken"; exit;` and references an undefined `$this->redirectTo`.
> That code path will fail badly on a real provider error — flagged for
> repair, not used as a model.

---

## Adding New Permissions

```php
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

// Create the permission
Permission::create([
    'name'        => 'publish entry',
    'description' => 'Allows publishing entries to live status',
]);

// Attach to an existing role
Role::findByName('editor')->givePermissionTo('publish entry');

// Check in a controller or policy
$this->authorize('publish entry');      // via Gate
$request->user()->can('publish entry'); // direct check
```

Remember that `super admin` short-circuits these checks via the `Gate::before`
callback in `AppServiceProvider`, so any new permission is already implicitly
granted to that role.

---

## Adding a New Entry Type End-to-End

The complete sequence for standing up a new content section:

```php
use App\Models\Category;
use App\Models\Category\Group as CategoryGroup;
use App\Models\EntryGroup;
use App\Models\EntryType;
use App\Models\Field;
use App\Models\Field\Group as FieldGroup;
use App\Models\Field\Type as FieldType;
use App\Models\FieldLayout;
use App\Models\FieldLayout\Tab;
use App\Models\FieldLayout\TabElement;
use App\Models\Status;
use App\Models\StatusGroup;

// 1. Status group
$statusGroup = StatusGroup::create(['name' => 'Event Status', 'handle' => 'events', 'sort_order' => 3]);
Status::create(['status_group_id' => $statusGroup->id, 'name' => 'Draft',     'handle' => 'draft',     'color' => '#9CA3AF', 'is_default' => true,  'is_public' => false, 'sort_order' => 1]);
Status::create(['status_group_id' => $statusGroup->id, 'name' => 'Scheduled', 'handle' => 'scheduled', 'color' => '#3B82F6', 'is_default' => false, 'is_public' => false, 'sort_order' => 2]);
Status::create(['status_group_id' => $statusGroup->id, 'name' => 'Live',      'handle' => 'live',      'color' => '#10B981', 'is_default' => false, 'is_public' => true,  'sort_order' => 3]);

// 2. Category group
$catGroup = CategoryGroup::create(['name' => 'Event Types', 'handle' => 'event-types', 'sort_order' => 3]);
Category::create(['group_id' => $catGroup->id, 'name' => 'Conference', 'handle' => 'conference', 'sort_order' => 1]);
Category::create(['group_id' => $catGroup->id, 'name' => 'Workshop',   'handle' => 'workshop',   'sort_order' => 2]);

// 3. Fields and field group
$dateType = FieldType::where('object', \App\Field\Types\Date::class)->firstOrFail();
$textType = FieldType::where('object', \App\Field\Types\Text::class)->firstOrFail();

$fieldGroup = FieldGroup::create(['name' => 'Event Details', 'handle' => 'event-details']);
foreach ([
    ['handle' => 'event_date',     'name' => 'Event Date',     'field_type_id' => $dateType->id],
    ['handle' => 'event_location', 'name' => 'Event Location', 'field_type_id' => $textType->id],
    ['handle' => 'ticket_url',     'name' => 'Ticket URL',     'field_type_id' => $textType->id],
] as $def) {
    $field = Field::firstOrCreate(['handle' => $def['handle']], $def);
    $fieldGroup->fields()->syncWithoutDetaching([$field->id]);
}

// 4. Field layout
$layout = FieldLayout::create(['name' => 'Events Layout']);
$tab    = Tab::create(['field_layout_id' => $layout->id, 'name' => 'Details', 'sort_order' => 1]);
foreach (['event_date', 'event_location', 'ticket_url'] as $i => $handle) {
    TabElement::create([
        'field_layout_tab_id' => $tab->id,
        'field_id'            => Field::where('handle', $handle)->value('id'),
        'required'            => false,
        'sort_order'          => $i + 1,
    ]);
}

// 5. Entry group
$group = EntryGroup::create([
    'name'            => 'Events',
    'handle'          => 'events',
    'field_layout_id' => $layout->id,
    'status_group_id' => $statusGroup->id,
    'sort_order'      => 4,
]);
$group->fieldGroups()->syncWithoutDetaching([$fieldGroup->id]);
$group->categoryGroups()->syncWithoutDetaching([$catGroup->id]);

// 6. Entry type record in DB
EntryType::create([
    'entry_group_id' => $group->id,
    'name'           => 'Event',
    'handle'         => 'event',
    'class'          => \App\EntryTypes\EventEntryType::class,
    'sort_order'     => 1,
]);
```

Then create the PHP class — note the value-returning lifecycle hook signature:

```php
// app/EntryTypes/EventEntryType.php
namespace App\EntryTypes;

use App\Models\Entry;
use Illuminate\Support\Str;

class EventEntryType extends AbstractEntryType
{
    public function beforeCreate(array $data): array
    {
        // Auto-generate excerpt from body if not provided
        if (empty($data['fields']['excerpt']) && ! empty($data['fields']['body'])) {
            $data['fields']['excerpt'] = Str::limit($data['fields']['body'], 160);
        }

        return $data;
    }

    public function afterCreate(Entry $entry, array $data): void
    {
        // Dispatch indexing job, send notifications, etc.
    }
}
```

Create an event:

```php
use App\Facades\Content;

$event = Content::create('event', [
    'title'        => 'Laravel Conference 2026',
    'published_at' => now(),
    'status'       => 'scheduled',
    'authors'      => [1],
    'categories'   => [Category::where('handle', 'conference')->value('id')],
    'fields'       => [
        'event_date'       => '2026-09-15',
        'event_location'   => 'Amsterdam, Netherlands',
        'ticket_url'       => 'https://tickets.example.com/laravel-2026',
        'body'             => 'Join us for three days of talks...',
        'meta_title'       => 'Laravel Conference 2026',
        'meta_description' => 'Join 800 Laravel developers in Amsterdam.',
    ],
]);
```

---

## Key Data Flow Summary

```
Content::create('event', $data)              // App\Facades\Content (or Entries)
  → ContentService::create()                 // alias subclass of EntryService
    → EntryService::create()
      → EntryTypeRegistry::resolveByHandle('event')
          → Fetch EntryType row, validate class_exists() & is_subclass_of(AbstractEntryType)
          → Instantiate EventEntryType, cache by handle and id
      → EntryRepository::create(EventEntryType, $data)
          → DB::transaction(...)
              ▸ EventEntryType::beforeCreate($data)         ← lifecycle hook (returns array)
              ▸ Eager-load entryGroup.statusGroup.statuses + layouts
              ▸ new Entry — applyCoreAttributes (title/handle/published_at)
              ▸ applyStatus — resolves status_id, status_handle, status_is_public
              ▸ entry->save()
              ▸ syncAuthors (entry_authors with sort_order)
              ▸ syncCategories (categorizables pivot)
              ▸ applyFieldValues:
                    scalar fields     → FieldValue::updateOrCreate
                                         (value_text/integer/float/date/boolean/json)
                                         with retry on SQLSTATE 23000
                    relational fields → entry_relationships delete + re-insert in order,
                                         skipping direct A→A self-refs
              ▸ entry->refresh()
          → EventEntryType::afterCreate($entry, $data)      ← runs OUTSIDE transaction
          → return Entry with default eager-loads applied
```

The same shape is mirrored in `EntryRepository::applyData()` for updates, with
`beforeUpdate` / `afterUpdate` instead of the create variants. `afterUpdate`
runs at the very end of `applyData()` (after `entry->refresh()`), but it is
inside the same call stack rather than wrapped in its own transaction —
follow `applyData()` line by line if you need exact ordering for a side-effect.

---

## Doc-vs-Code Drift Reference

This section gathers the cases where prior docs in the repo disagree with the
live source. Where these read like recommendations, the recommendation is to
trust the code; where they read like open issues, the recommendation matches
`CURRENT_ISSUES_REVIEW.md`.

| Topic | Stated by older doc | Actual code | Action |
|---|---|---|---|
| Identifier column | `slug` on Field/FieldGroup/EntryGroup/EntryType/StatusGroup/Status/CategoryGroup/Category/Entry | `handle` everywhere | Already fixed throughout this document. Migrate any remaining call sites. |
| `Entry::update()` / `Entry::delete()` | Overridden on the model | Plain Eloquent (override removed; CRIT-01 `[RESOLVED]`) | Use the facade only when you need authors/categories/fields; raw model calls are safe again. |
| Lifecycle hooks | `beforeCreate(array &$data): void` | `beforeCreate(array $data): array { return $data; }` | Update overrides to return the data array; the live `PodcastEpisodeEntryType` is the canonical example. |
| `entries.status` | Free-text string | Three-column denormalisation: `status_id`, `status_handle`, `status_is_public`; observer keeps `status_is_public` consistent | Read from `status_handle` for handle-equality checks; never edit the columns directly outside `EntryRepository::applyStatus()`. |
| Validate command | `system:validate-class-references` | `app:validate-class-references` | Run the correct command in CI. |
| Action class inventory | ~18 `App\Actions\User\*` invokables (`CreateUser`, `SetUserField`, etc.) | Only `CreateNewUser`, `UpdateUserPassword`, `UpdateUserProfileInformation`, `ResetUserPassword`, `Token\CreateNewUserToken` | Treat `UserService` / `Users` facade as the public API. Author additional actions only if you need separate invokables. |
| `Content` facade `delete()` | Implied by docs and tests | Not declared in `App\Facades\Content` docblock; works at runtime via inherited `EntryService::delete()` | Prefer `Entries::delete()`. |
| `field_values.fieldable_type` | FQCN (`App\Models\User`) | Morph-map alias (`'user'`, `'entry'`, `'category'`, …) | Always use `$model->getMorphClass()` for new writes; backfill any legacy FQCN rows. |
| `entry_groups.status_group_id` | Required | Nullable in schema, falls back to `null` in `CreateNewEntryGroup` / `EditEntryGroup`, but `EntryRepository::applyStatus()` throws if missing | Do not let it become null in practice; tighten requests + actions per `CURRENT_ISSUES_REVIEW.md` §3. |
| `TabElement.required` | Enforced server-side | Honoured for the *group* layout only — the merged group+type layout is not used during request validation | Resolve required fields from the merged effective layout (§4 of the current review). |
| `PersistsFieldValues` retry | Implied to retry on `23000` | Plain `updateOrCreate` with no retry; only repository upserts retry | Funnel hot user/category writes through repository helpers, or fold the retry into the concern. |
| `entry_trees.is_home` uniqueness | Implied unique | No DB constraint; cycles via `parent_id` `nullOnDelete` are also possible | Enforce in `App\Actions\Entry\Tree\*`. |
| `Login::handleProviderCallback` | Robust OAuth callback | Catches `InvalidStateException` with `echo "broken"; exit;` and references undefined `$this->redirectTo` | Repair before exposing public OAuth login. |

If you spot a new disagreement, prefer fixing the code over re-editing this
file — but if the drift is not mechanical, add a row to this table so future
readers know which side is authoritative.
