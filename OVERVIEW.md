# Laravel CMS — Project Overview

> **Documentation status (2026-04-27).** This file is synchronised against
> the live source in `app/`, `database/`, `routes/`, and `config/`. All
> code snippets are copy-paste accurate against the current codebase.
> The codebase consistently uses **`handle`** (not `slug`) on every model
> that carries a developer-facing identifier — `Field`, `FieldGroup`,
> `EntryGroup`, `EntryType`, `StatusGroup`, `Status`, `CategoryGroup`,
> `Category`, and `Entry`.

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
  - [StatusObserver — keeping status_is_public consistent](#statusobserver--keeping-status_is_public-consistent)
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
- [Adding Custom Fields to Media Uploads](#adding-custom-fields-to-media-uploads)
  - [How the Field Layer Works](#how-the-field-layer-works)
  - [Step 1 — Add the Fieldable Trait to Media](#step-1--add-the-fieldable-trait-to-media)
  - [Step 2 — Create Fields and a Field Layout](#step-2--create-fields-and-a-field-layout)
  - [Step 3 — Write Field Values to a Media Item](#step-3--write-field-values-to-a-media-item)
  - [Step 4 — Read Field Values from a Media Item](#step-4--read-field-values-from-a-media-item)
  - [Step 5 — Attach Field Groups to a Library](#step-5--attach-field-groups-to-a-library)
  - [Morph Map Note](#morph-map-note)
- [Site Routing (Public-Facing URLs)](#site-routing-public-facing-urls)
- [Adding New Permissions](#adding-new-permissions)
- [Adding a New Entry Type End-to-End](#adding-a-new-entry-type-end-to-end)
- [Key Data Flow Summary](#key-data-flow-summary)

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
                         UserSchema, Media\Library) via the polymorphic
                         field_groupables pivot

StatusGroup
  └── Status       — named statuses with handle, color, is_default, is_public

CategoryGroup     — owns a FieldLayout (HasFieldLayout) and FieldGroups
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

Media\Library     — upload container (adapter, allowed types, max size).
                    Owns polymorphic CategoryGroups and FieldGroups.
  └── Media       — extends Spatie MediaLibrary BaseMedia; has HasTags,
                    categories() morphToMany, and can be given the Fieldable
                    trait for custom field values (see dedicated section below)

UserSchema        — singleton (id=1) that owns a single FieldLayout and
                    one or more FieldGroups for ALL users
  └── User        — uses Fieldable, HasRoles (Spatie), HasTags (Spatie),
                    HasApiTokens (Sanctum), TwoFactorAuthenticatable (Fortify),
                    Notifiable; OAuth tokens via OauthToken HasMany
```

### Cross-cutting infrastructure

**Polymorphic morph map.** `app/Providers/AppServiceProvider.php` registers
short morph aliases via `Relation::morphMap([...])`. Stored aliases are
`entry`, `entry_group`, `entry_type`, `category`, `category_group`,
`field_group`, `media`, `media_library`, and `user`. This means
`field_values.fieldable_type` will contain `'entry'` rather than
`App\Models\Entry`. Always use `$model->getMorphClass()` when writing
polymorphic rows, **not** the FQCN.

**Super-admin gate bypass.** `AppServiceProvider::boot()` registers a
`Gate::before` callback that returns `true` for any user with the
`super admin` role, short-circuiting all permission checks.

**Sanctum + Fortify + Spatie Permission + Socialite** are wired together for
auth: 2FA via Fortify's `TwoFactorAuthenticatable`, API tokens via Sanctum
(`HasApiTokens`), RBAC via Spatie `HasRoles`, and OAuth login via
`Laravel\Socialite\Socialite` (see `app/Http/Controllers/Login.php`).

**TwigBridge** powers the admin views — `resources/views/admin/**/*.twig` —
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

The `DatabaseSeeder` runs in this order (nine always run; the tenth runs
only in `local`/`testing`):

1. `RolesPermissionsSeeder` — permissions + 3 roles
2. `UsersSeeder` — seeds a single **super-admin** user (Eric Lamb,
   `eric@mithra62.com`, password `password`)
3. `FieldTypeSeeder` — 9 field type rows (Text, Textarea, Number, Date,
   Email Address, URL, Telephone, Color Picker, Relationship)
4. `StatusGroupSeeder` — `publication` status group (`draft`, `published`, `archived`)
5. `CategoryGroupSeeder` — category groups + categories
6. `FieldGroupSeeder` — field groups + fields (`content-fields`, `seo-fields`,
   `relationship-fields`, plus per-group field bundles)
7. `EntryGroupSeeder` — `blog` and `products` entry groups, layouts, and types
8. `ExtendedEntryGroupSeeder` — `events`, `news`, `pages`, `jobs`, `podcast`,
   `portfolio`, `videos`, `recipes` entry groups + types
9. `UserSchemaSeeder` — user profile schema (Profile and Bio tabs, fields like
   `first_name`, `last_name`, `gender`, `date_of_birth`, `website`, `bio`,
   `social_twitter`, `social_linkedin`)
10. `EntrySeeder` *(local/testing only)* — sample blog posts and products

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

The full permission list seeded by `RolesPermissionsSeeder`:

```
api
access admin

view user / create user / edit user / delete user
view user token / create user token / edit user token / delete user token

create category group / edit category group / delete category group / reorder category group
create category / edit category / delete category / reorder category

create media library / edit media library / delete media library / reorder media library
```

No permissions exist yet for entries, fields, field groups, field layouts,
statuses, or roles — those areas are gated only by `access admin` plus the
super-admin bypass. Add them as needed (see
[Adding New Permissions](#adding-new-permissions)).

### Creating Users Programmatically

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

If you need raw Eloquent:

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
if ($user->can('edit category')) { /* ... */ }
if ($user->hasRole('super admin')) { /* ... */ }
if ($user->hasAnyRole(['admin', 'super admin'])) { /* ... */ }
```

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

Field types are PHP classes in `app/Field/Types/` that extend
`AbstractField` (`app/Field/AbstractField.php`). They are registered in the
`field_types` table. Each row stores the **fully-qualified class name** in
`field_types.object`; instantiation goes through `Field\Type::instance()`,
which validates `class_exists()` and `is_subclass_of(AbstractField::class)`.

`AbstractField` methods subclasses can override:

| Method | Purpose |
|---|---|
| `storageColumn(): string` | Required. One of `value_text`, `value_integer`, `value_float`, `value_date`, `value_boolean`, `value_json`. |
| `isRelational(): bool` | Default `false`. Return `true` to route writes to `entry_relationships`. |
| `cast(mixed $value): mixed` | Default identity. Convert raw stored value before returning. |
| `validate(mixed $value): bool\|string` | Default `true`. Return error string on failure. |
| `render(array $params): string` | Render a Blade partial for the admin form. |
| `getRules(): array` | Return Laravel validation rules. |

### Built-in Types

| Class | `storageColumn()` | Notes |
|---|---|---|
| `Text` | `value_text` | Single-line input |
| `Textarea` | `value_text` | Multi-line |
| `Number` | `value_integer` or `value_float` | Branches on `decimals` setting |
| `Date` | `value_date` | Cast as `datetime`; reads return `Carbon` |
| `EmailAddress` | `value_text` | |
| `Url` | `value_text` | |
| `Telephone` | `value_text` | |
| `ColorPicker` | `value_text` | Hex value |
| `Relationship` | *(unused)* | `isRelational() === true`; stores in `entry_relationships` |

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

    public function storageColumn(): string { return 'value_boolean'; }
    public function cast(mixed $value): bool { return (bool) $value; }
}
```

Register it in a seeder:

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
`HasFieldGroups` trait — `EntryGroup`, `CategoryGroup`, `UserSchema`, and
`Media\Library`. The attachment uses a polymorphic pivot (`field_groupables`)
with a `group_id` foreign-key column.

`Field` columns: `field_type_id` (nullable FK), `name`, **`handle` (globally
unique)**, `label`, `instructions`, `settings` (JSON), `hidden` (boolean).

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

foreach ([
    ['handle' => 'price', 'name' => 'Price', 'label' => 'Price'],
    ['handle' => 'sku',   'name' => 'SKU',   'label' => 'SKU Number'],
] as $def) {
    $field = Field::firstOrCreate(
        ['handle' => $def['handle']],
        array_merge($def, ['field_type_id' => $textType->id])
    );
    $group->fields()->syncWithoutDetaching([$field->id]);
}
```

---

## Status Groups and Statuses

### Creating a Status Group

```php
use App\Models\Status;
use App\Models\StatusGroup;

$group = StatusGroup::create([
    'name'       => 'Review Workflow',
    'handle'     => 'review',
    'sort_order' => 2,
]);

foreach ([
    ['name' => 'Pending Review', 'handle' => 'pending',  'color' => '#F59E0B', 'is_default' => true,  'is_public' => false, 'sort_order' => 1],
    ['name' => 'Approved',       'handle' => 'approved', 'color' => '#10B981', 'is_default' => false, 'is_public' => true,  'sort_order' => 2],
    ['name' => 'Rejected',       'handle' => 'rejected', 'color' => '#EF4444', 'is_default' => false, 'is_public' => false, 'sort_order' => 3],
] as $s) {
    Status::create(array_merge($s, ['status_group_id' => $group->id]));
}
```

### How an Entry Stores its Status

The status is denormalised across three columns maintained together by
`EntryRepository::applyStatus()`:

| Column | Notes |
|---|---|
| `status_id` | nullable FK to `statuses.id`, `nullOnDelete` |
| `status_handle` | indexed string for fast lookups |
| `status_is_public` | indexed boolean |

`Entry::scopePublished()` filters on `status_is_public = true AND published_at IS NOT NULL AND published_at <= now()`.

### StatusObserver — keeping status_is_public consistent

```php
// app/Observers/StatusObserver.php — registered in AppServiceProvider::boot()
public function updating(Status $status): void
{
    if ($status->isDirty('is_public')) {
        Entry::where('status_id', $status->id)
            ->update(['status_is_public' => $status->is_public]);
    }
}
```

---

## Category Groups and Categories

Schema notes: `category_groups` has `handle` (unique), `field_layout_id`
(nullable), `sort_order`. `categories` has `group_id` (FK, cascade delete),
`parent_id` (nullable, self-FK), `name`, `handle`, `sort_order`. Unique on
`(group_id, handle)`.

### Creating a Category Group and Categories

```php
use App\Models\Category;
use App\Models\Category\Group as CategoryGroup;

$group = CategoryGroup::firstOrCreate(
    ['handle' => 'regions'],
    ['name'   => 'Regions', 'sort_order' => 1]
);

$europe = Category::create([
    'group_id'   => $group->id,
    'name'       => 'Europe',
    'handle'     => 'europe',
    'sort_order' => 1,
]);

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
// Root categories with full recursive tree (default depth = 10)
$group->rootCategories()->with('childrenRecursive')->get();

// Scoped query
Category::inGroup($group)->roots()->with('childrenRecursive')->get();
```

---

## Accessing Entry Categories via the Content Facade

Entries carry a `categories()` `morphToMany` via the `HasCategories` trait.
Categories are **always eager-loaded** by `EntryQueryBuilder`'s terminal
methods (`get()`, `first()`, `firstOrFail()`, `paginate()`). No additional
`with('categories')` call is needed on the query builder.

### Reading categories on a result set

```php
use App\Facades\Content;

$entries = Content::query()->inGroup('blog')->published()->get();
// categories are already available — no extra with() needed

foreach ($entries as $entry) {
    foreach ($entry->categories as $category) {
        echo $category->name;
        echo $category->handle;
    }
}
```

### Loading a category's group on already-fetched entries

`categories.group` is not in the default eager-load. Load it explicitly:

```php
$entries = Content::query()->inGroup('blog')->published()->get();
$entries->load('categories.group');

foreach ($entries as $entry) {
    foreach ($entry->categories as $category) {
        echo $category->group->name; // e.g. "Topics"
    }
}
```

### Filtering entries by category

```php
use App\Models\Category;

$php = Category::where('handle', 'php')->firstOrFail();

$entries = Content::query()
    ->inGroup('blog')
    ->withCategory($php->id)
    ->published()
    ->orderBy('published_at', 'desc')
    ->paginate(10);
```

### Accessing category field values

```php
$entries = Content::query()->inGroup('blog')->get();
$entries->load('categories.fieldValues.field.fieldType');

foreach ($entries as $entry) {
    foreach ($entry->categories as $category) {
        echo $category->field('cat_description');
    }
}
```

> Categories support **scalar fields only**. `Fieldable::field()` inspects
> `fieldValues` but not `entryRelationships`. Only `Entry::field()` adds the
> relational fallback.

---

## Field Layouts

A `FieldLayout` organises fields into named tabs. `FieldLayout::tabs()` is
ordered by `sort_order`; `Tab::elements()` likewise.

### Building a Layout Programmatically

```php
use App\Models\Field;
use App\Models\FieldLayout;
use App\Models\FieldLayout\Tab;
use App\Models\FieldLayout\TabElement;

$layout     = FieldLayout::create(['name' => 'Article Layout']);
$contentTab = Tab::create(['field_layout_id' => $layout->id, 'name' => 'Content', 'sort_order' => 1]);

foreach (['body', 'excerpt'] as $order => $handle) {
    TabElement::create([
        'field_layout_tab_id' => $contentTab->id,
        'field_id'            => Field::where('handle', $handle)->value('id'),
        'required'            => $handle === 'body',
        'sort_order'          => $order + 1,
    ]);
}

$seoTab = Tab::create(['field_layout_id' => $layout->id, 'name' => 'SEO', 'sort_order' => 2]);

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
$layout->fields(); // Collection<Field>, flattened from all tabs in sort order
```

`FieldLayout::fields()` calls `loadMissing('tabs.elements.field')` — N+1-safe.

---

## Entry Groups and Entry Types

An **EntryGroup** is the section/channel (e.g. "Blog", "Products") tying
together a FieldLayout, a StatusGroup, polymorphic CategoryGroups, and
polymorphic FieldGroups.

An **EntryType** row maps a group-scoped handle to a PHP class:

| Column | Description |
|---|---|
| `entry_group_id` | FK to `entry_groups`, cascade on delete |
| `field_layout_id` | Optional override layout for this type |
| `name`, `handle` | `(entry_group_id, handle)` unique |
| `class` | FQCN of an `AbstractEntryType` subclass |
| `default_template` | Optional default template for SiteRouter |
| `has_entry_tree`, `max_depth`, `allowed_parent_types` | Tree config |
| `sort_order` | Display order within the group |

### Field Layering: Group Fields + Type Fields

```php
// From EntryRepository::resolveLayoutFields()
$groupFields = $entry->entryGroup->fieldLayout?->fields() ?? collect();
$typeFields  = $entry->entryType->fieldLayout?->fields() ?? collect();

return $typeFields->merge($groupFields)->unique('id'); // type-level fields take precedence
```

### Lifecycle Hook Signatures

```php
public function beforeCreate(array $data): array { return $data; }
public function afterCreate(Entry $entry, array $data): void {}

public function beforeUpdate(Entry $entry, array $data): array { return $data; }
public function afterUpdate(Entry $entry, array $data): void {}
```

`beforeCreate()` runs inside the DB transaction; `afterCreate()` runs outside
so its side-effects cannot be rolled back.

### Creating an Entry Group

```php
use App\Models\EntryGroup;
use App\Models\StatusGroup;

$statusGroup = StatusGroup::where('handle', 'publication')->firstOrFail();

$group = EntryGroup::create([
    'name'            => 'News Articles',
    'handle'          => 'news',
    'description'     => 'News and press releases.',
    'field_layout_id' => $layout->id,
    'status_group_id' => $statusGroup->id,
    'sort_order'      => 3,
]);

$group->fieldGroups()->syncWithoutDetaching([$fieldGroup->id]);
$group->categoryGroups()->syncWithoutDetaching([$categoryGroup->id]);
```

`status_group_id` is nullable in the schema but `EntryRepository` throws
`RuntimeException` if it is missing during status resolution — treat as
required.

### Creating an Entry Type Class

```php
// app/EntryTypes/NewsArticleEntryType.php
namespace App\EntryTypes;

use App\Models\Entry;

class NewsArticleEntryType extends AbstractEntryType
{
    public function beforeCreate(array $data): array
    {
        $data['status'] = 'pending';
        return $data;
    }

    public function afterCreate(Entry $entry, array $data): void
    {
        // SendReviewNotification::dispatch($entry);
    }

    public function beforeUpdate(Entry $entry, array $data): array
    {
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

- `App\Facades\Content` — `App\Services\ContentService` (kept for backward
  compatibility).
- `App\Facades\Entries` — `App\Services\EntryService` directly. Prefer this
  in new code.

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
    'authors'      => [$author->id],      // ordered M2M — sort_order = array key
    'categories'   => [$category->id],
    'fields'       => [
        'body'             => 'Full article text...',
        'excerpt'          => 'Short summary.',
        'meta_title'       => 'Election Results 2026 | News',
        'meta_description' => 'Coverage of the 2026 election.',
    ],
]);

echo $entry->id;     // persisted Entry model
echo $entry->handle; // auto-generated via Str::slug($title) if not provided
```

### Updating an Entry

```php
use App\Facades\Content;

$updated = Content::update($entry, [
    'title'  => 'Updated Title',
    'status' => 'approved',
    'fields' => ['excerpt' => 'Revised summary.'],
]);
```

Direct `$entry->update([...])` writes core attributes only — it does **not**
sync authors, categories, or custom fields.

### Using the Relationship Field

Relationship fields store related entry IDs in `entry_relationships`, **not**
in `field_values`. Pass related IDs **inside the `fields` key** as an array —
array order is preserved as `sort_order`.

#### Writing

```php
$relatedA = Content::query()->inGroup('products')->where('handle', 'widget-a')->value('id');
$relatedB = Content::query()->inGroup('products')->where('handle', 'widget-b')->value('id');

// On create
$post = Content::create('blog_post', [
    'title'  => 'My Post',
    'handle' => 'my-post',
    'fields' => [
        'related_products' => [$relatedA, $relatedB],
    ],
]);

// On update — replaces all existing pivots for that field
Content::update($post, [
    'fields' => [
        'related_products' => [$relatedB], // removes $relatedA
    ],
]);
```

#### Reading

```php
$post = Content::query()->inGroup('blog')->where('handle', 'my-post')->firstOrFail();

$related = $post->field('related_products'); // Collection<Entry>

foreach ($related as $product) {
    echo $product->title;
    echo $product->field('price');
}
```

> `$entry->field('handle')` returns a single value for scalar fields and a
> `Collection<Entry>` for relationship fields.

#### Recursion with cycle detection

```php
use App\Facades\Entries;

$tree = Entries::loadRelatedRecursive($post, 'related_products', maxDepth: 3);
// Returns a flat Collection<Entry>, depth-limited and cycle-safe.
```

#### Raw pivot access

```php
$post->entryRelationships
    ->where('field.handle', 'related_products')
    ->sortBy('sort_order')
    ->each(fn ($pivot) => echo $pivot->relatedEntry->title);
```

---

## Querying Entries

Use `Content::query()` (or `Entries::query()`) for a fluent query builder
backed by `App\Builders\EntryQueryBuilder`. All terminal methods apply the
full eager-load automatically.

> **`inGroup()` vs `ofType()`:** `inGroup('blog')` matches the **EntryGroup
> handle**. `ofType('blog_post')` matches the **EntryType handle**. Mixing
> them silently returns no results.

```php
use App\Facades\Content;
use App\Models\Category;

// Published blog posts, newest first
$posts = Content::query()->inGroup('blog')->published()->latest()->get();

// By entry type
$articles = Content::query()->ofType('news_article')->withStatus('approved')->paginate(20);

// By author
$myPosts = Content::query()->inGroup('blog')->withAuthor(Auth::id())->latest()->get();

// By category
$technology = Category::where('handle', 'technology')->firstOrFail();
$techPosts  = Content::query()
    ->inGroup('blog')
    ->withCategory($technology->id)
    ->published()
    ->orderBy('published_at', 'desc')
    ->paginate(10);

// By ID
$entry = Content::get(42);   // throws ModelNotFoundException if missing
$entry = Content::find(42);  // returns null if missing

// By handle within a group
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
| `where($column, $op, $value = null)` | passthrough to Eloquent Builder |
| `orderBy($column, $direction = 'asc')` | passthrough |
| `latest()` | `orderBy('created_at', 'desc')` |
| `get()` / `paginate(int)` / `first()` / `firstOrFail()` | terminal; always eager-load |
| `count()` | does **not** apply eager loads |

### Reading Field Values

```php
echo $entry->field('body');           // string
echo $entry->field('price');          // int or float
$entry->field('event_date')?->format('Y-m-d'); // Carbon from Date field

$related = $entry->field('related_entries'); // Collection<Entry>
foreach ($related as $rel) {
    echo $rel->title;
}
```

### Accessing Entry Authors

| Relationship | Type | Description |
|---|---|---|
| `creator` | `BelongsTo User` | User who created the record (`created_by_user_id`) |
| `authors` | `BelongsToMany User` | Editorial byline, ordered by pivot `sort_order` |

```php
$post = Content::query()->inGroup('blog')->where('handle', 'my-post')->firstOrFail();

$post->creator->name;
foreach ($post->authors as $author) {
    echo $author->name;
    echo $author->pivot->sort_order;
}
$post->authors->first()?->name; // primary author
```

---

## Deleting Entries

```php
// FK cascades remove field_values, entry_relationships, entry_authors,
// categorizables, and the entry_tree node automatically.
$entry->delete();

// Via the facade — preferred for consistency
\App\Facades\Entries::delete($entry);
```

---

## User Extended Profile (UserSchema)

`UserSchema` is a singleton (`user_schema.id = 1`) that owns a single
`FieldLayout` applied to all users. The read API (`$user->field('handle')`)
is identical to entries; writes go through `Users::setField()` /
`Users::setFields()` (or the `PersistsFieldValues` concern).

```php
UserSchema::instance();      // eager-loads fieldLayout, request-scoped cache
UserSchema::flushResolved(); // clear cache (useful in tests)
```

### Setting Up the User Schema

`UserSchemaSeeder` creates two FieldGroups and a two-tab layout:

| FieldGroup | `handle` | Fields |
|---|---|---|
| User Profile | `user-profile` | `first_name`, `last_name`, `gender`, `date_of_birth`, `website` |
| User Bio | `user-bio` | `bio`, `social_twitter`, `social_linkedin` |

```bash
php artisan db:seed --class=UserSchemaSeeder
```

Manual setup:

```php
use App\Models\Field;
use App\Models\Field\Group as FieldGroup;
use App\Models\Field\Type as FieldType;
use App\Models\FieldLayout;
use App\Models\FieldLayout\Tab;
use App\Models\FieldLayout\TabElement;
use App\Models\UserSchema;

$text = FieldType::where('object', \App\Field\Types\Text::class)->firstOrFail();

$firstName = Field::firstOrCreate(['handle' => 'first_name'], ['field_type_id' => $text->id, 'name' => 'First Name', 'label' => 'First Name']);
$lastName  = Field::firstOrCreate(['handle' => 'last_name'],  ['field_type_id' => $text->id, 'name' => 'Last Name',  'label' => 'Last Name']);

$group = FieldGroup::firstOrCreate(['handle' => 'user-profile'], ['name' => 'User Profile']);
$group->fields()->syncWithoutDetaching([$firstName->id, $lastName->id]);

$layout = FieldLayout::create(['name' => 'User Profile Layout']);
$tab    = Tab::create(['field_layout_id' => $layout->id, 'name' => 'Profile', 'sort_order' => 1]);

foreach ([$firstName, $lastName] as $i => $field) {
    TabElement::create([
        'field_layout_tab_id' => $tab->id,
        'field_id'            => $field->id,
        'required'            => false,
        'sort_order'          => $i + 1,
    ]);
}

$schema = UserSchema::instance();
$schema->field_layout_id = $layout->id;
$schema->save();
$schema->fieldGroups()->syncWithoutDetaching([$group->id]);
```

### Writing Field Values to a User

```php
use App\Facades\Users;

Users::setFields($user, [
    'first_name' => 'Jane',
    'last_name'  => 'Doe',
]);
```

Do **not** hard-code `User::class` as `fieldable_type` — the morph map stores
`'user'` instead. Use `$user->getMorphClass()`:

```php
use App\Models\Field;
use App\Models\FieldValue;

$field = Field::where('handle', 'first_name')->firstOrFail();

FieldValue::updateOrCreate(
    [
        'field_id'       => $field->id,
        'fieldable_id'   => $user->getKey(),
        'fieldable_type' => $user->getMorphClass(), // 'user'
    ],
    [$field->fieldType->instance()->storageColumn() => 'Jane']
);
```

### Reading Field Values from a User

```php
$user = User::with('fieldValues.field.fieldType')->find(1);

echo $user->field('first_name'); // 'Jane'
echo $user->field('last_name');  // 'Doe'
```

### Typical Controller Pattern

```php
public function show(User $user): array
{
    $user->load('fieldValues.field.fieldType');
    return [
        'name'       => $user->name,
        'first_name' => $user->field('first_name'),
        'last_name'  => $user->field('last_name'),
    ];
}

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
| Write API | `Content::create()` / `Content::update()` | `Users::create()` / `Users::update()` |
| Read API | `$entry->field('handle')` | `$user->field('handle')` |
| Schema | Per-group FieldLayout + per-type FieldLayout (merged) | Single `UserSchema` singleton |
| Lifecycle hooks | `beforeCreate`, `afterCreate`, etc. on EntryType class | None |
| Custom fields | Scalar + Relational | **Scalar only** |

---

## System Health and Data Integrity

```bash
php artisan app:validate-class-references
```

Checks that every class name in `entry_types.class` and `field_types.object`
resolves to a live class satisfying the expected base type. Exits with
`FAILURE` if any reference is broken — wire into CI before deploys.

Polymorphic stability via **Eloquent Morph Maps** in `AppServiceProvider::boot()`:

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

Always rely on `$model->getMorphClass()` for new writes.

---

## UserService and the Users Facade

### CRUD

```php
use App\Facades\Users;

$user = Users::create([
    'name'     => 'Jane Doe',
    'email'    => 'jane@example.com',
    'password' => 'secret',
    'roles'    => ['admin'],
    'fields'   => ['first_name' => 'Jane', 'last_name' => 'Doe'],
]);

$user = Users::update($user, [
    'name'   => 'Jane Smith',
    'roles'  => ['user'],
    'fields' => ['last_name' => 'Smith'],
]);

Users::delete($user);
```

### Roles

```php
Users::assignRoles($user, 'editor');           // additive
Users::assignRoles($user, ['editor', 'writer']);
Users::syncRoles($user, ['admin']);            // replaces all
Users::revokeRole($user, 'editor');
```

### Custom Fields

```php
Users::setField($user, 'bio', 'Staff engineer at Acme.');
Users::setFields($user, ['first_name' => 'Jane', 'last_name' => 'Smith']);

$user->load('fieldValues.field.fieldType');
echo $user->field('first_name');
```

### Passwords

```php
Users::setPassword($user, 'newpassword123'); // admin force-set

app(\App\Actions\User\UpdateUserPassword::class)->update($user, [
    'current_password'      => 'oldpassword',
    'password'              => 'newpassword123',
    'password_confirmation' => 'newpassword123',
]);
```

### Two-Factor Authentication

```php
$setup = Users::enableTwoFactor($user);
// $setup['qr_code_svg'], $setup['secret']

Users::confirmTwoFactor($user, '123456'); // throws ValidationException if wrong
Users::hasTwoFactor($user);              // true after confirmation

$codes    = Users::getRecoveryCodes($user);
$newCodes = Users::regenerateRecoveryCodes($user);
Users::disableTwoFactor($user);
```

### OAuth Token Management

```php
$token = Users::upsertOauthToken($user, 'google', [
    'access_token'     => 'ya29.xxx',
    'refresh_token'    => '1//xxx',
    'expires_at'       => now()->addHour(),
    'scopes'           => ['email', 'profile'],
    'provider_user_id' => '1234567890',
]);

$token = Users::getActiveOauthToken($user, 'google');
if ($token?->isExpired()) { /* refresh */ }

Users::revokeOauthToken($token);
Users::revokeAllOauthTokens($user, 'google');
Users::revokeAllOauthTokens($user);

$tokens = Users::listOauthTokens($user);
```

### Action Classes Inventory

**Entry** (`app/Actions/Entry/`)

| Class | Method |
|---|---|
| `CreateNewEntry` | `create(array $input): Entry` — reads `$input['type_handle']`, delegates to `Content::create()` |
| `UpdateEntry` | `update(Entry $entry, array $input): Entry` |
| `Group/CreateNewEntryGroup` | `create(array $input): EntryGroup` |
| `Group/EditEntryGroup` | `edit(EntryGroup $group, array $input): bool` |
| `Type/CreateNewEntryType` | `create(array $input): EntryType` |
| `Type/EditEntryType` | `edit(EntryType $type, array $input): bool` |
| `Tree/CreateEntryTreeNode` | `create(Entry $entry, array $input): EntryTree` |
| `Tree/MoveEntryTreeNode` | `move(EntryTree $node, array $input): EntryTree` |
| `Tree/RebuildEntryTreeUri` | `rebuild(EntryTree $node): void` |

**Category** (`app/Actions/Category/`)

| Class | Method |
|---|---|
| `CreateNewCategory` | `create(CategoryGroup $group, array $input): Category` |
| `EditCategory` | `edit(Category $category, array $input): Category` |
| `Group/CreateNewCategoryGroup` | `create(array $input): CategoryGroup` |
| `Group/EditCategoryGroup` | `edit(CategoryGroup $group, array $input): bool` |

**Field** (`app/Actions/Field/`)

| Class | Method |
|---|---|
| `CreateNewField` | `create(array $input): Field` |
| `EditField` | `edit(Field $field, array $input): bool` |
| `Group/CreateNewFieldGroup` | `create(array $input): FieldGroup` |
| `Group/EditFieldGroup` | `edit(FieldGroup $group, array $input): bool` |

**FieldLayout** (`app/Actions/FieldLayout/`)

| Class | Method |
|---|---|
| `CreateNewFieldLayout` | `create(array $input): FieldLayout` |
| `EditFieldLayout` | `edit(FieldLayout $layout, array $input): bool` |
| `DeleteFieldLayout` | `delete(FieldLayout $layout): bool` |
| `Tab/CreateNewTab` | `create(FieldLayout $layout, array $input): Tab` |
| `Tab/EditTab` | `edit(Tab $tab, array $input): bool` |
| `Tab/DeleteTab` | `delete(Tab $tab): bool` |
| `Tab/Element/CreateTabElement` | `create(Tab $tab, array $input): TabElement` |
| `Tab/Element/EditTabElement` | `edit(TabElement $element, array $input): bool` |
| `Tab/Element/DeleteTabElement` | `delete(TabElement $element): bool` |

**Media** (`app/Actions/Media/Library/`)

| Class | Method |
|---|---|
| `CreateNewMediaLibrary` | `create(array $input): Library` — attaches `$input['category_groups']` |
| `EditMediaLibrary` | `edit(Library $library, array $input): bool` — re-syncs category groups and field groups |
| `DeleteMediaLibrary` | `delete(Library $library): bool` |
| `UploadMedia` | `upload(FormRequest $request, Library $library): Media` |

**Status** (`app/Actions/Status/`)

| Class | Method |
|---|---|
| `CreateNewStatus` | `create(StatusGroup $group, array $input): Status` |
| `EditStatus` | `edit(Status $status, array $input): bool` |
| `Group/CreateNewStatusGroup` | `create(array $input): StatusGroup` |
| `Group/EditStatusGroup` | `edit(StatusGroup $group, array $input): bool` |

**Role** (`app/Actions/Role/`)

| Class | Method |
|---|---|
| `CreateNewRole` | `create(array $input): Role` |
| `EditRole` | `edit(Role $role, array $input): bool` |

**User** (`app/Actions/User/`)

| Class | Method |
|---|---|
| `CreateNewUser` | `create(array $input): User` |
| `UpdateUserProfileInformation` | `update(User $user, array $input): void` — implements Fortify's contract |
| `UpdateUserPassword` | `update(User $user, array $input): void` — verifies current password |
| `ResetUserPassword` | `reset(User $user, array $input): void` — no current-password check |
| `Token/CreateNewUserToken` | `create(User $user, array $input): NewAccessToken` |

---

## Custom Field Groups on Category Groups

`CategoryGroup` supports two parallel field systems. **FieldGroups** (via
`HasFieldGroups` / `field_groupables`) define which fields are available to
categories within the group. **FieldLayout** (via `HasFieldLayout` /
`field_layout_id`) controls how those fields are displayed in the admin UI.

```php
use App\Models\Category;
use App\Models\Category\Group as CategoryGroup;
use App\Models\Field;
use App\Models\Field\Group as FieldGroup;
use App\Models\Field\Type as FieldType;
use App\Models\FieldLayout;
use App\Models\FieldLayout\Tab;
use App\Models\FieldLayout\TabElement;
use App\Services\CategoryService;

$textType = FieldType::where('object', \App\Field\Types\Text::class)->firstOrFail();

// 1. Create fields
$description = Field::firstOrCreate(
    ['handle' => 'cat_description'],
    ['name' => 'Description', 'label' => 'Description', 'field_type_id' => $textType->id]
);
$imageUrl = Field::firstOrCreate(
    ['handle' => 'cat_image_url'],
    ['name' => 'Image URL', 'label' => 'Image URL', 'field_type_id' => $textType->id]
);

// 2. FieldGroup
$fieldGroup = FieldGroup::firstOrCreate(['handle' => 'category-extras'], ['name' => 'Category Extras']);
$fieldGroup->fields()->syncWithoutDetaching([$description->id, $imageUrl->id]);

// 3. Layout
$layout = FieldLayout::create(['name' => 'Topic Category Layout']);
$tab    = Tab::create(['field_layout_id' => $layout->id, 'name' => 'Details', 'sort_order' => 1]);
foreach ([$description, $imageUrl] as $i => $field) {
    TabElement::create([
        'field_layout_tab_id' => $tab->id,
        'field_id'            => $field->id,
        'sort_order'          => $i + 1,
    ]);
}

// 4. Attach to CategoryGroup
$categoryGroup = CategoryGroup::where('handle', 'topics')->firstOrFail();
$categoryGroup->field_layout_id = $layout->id;
$categoryGroup->save();
$categoryGroup->fieldGroups()->syncWithoutDetaching([$fieldGroup->id]);

// 5. Write fields via CategoryService
$categoryService = app(CategoryService::class);

$category = $categoryService->create($categoryGroup, [
    'name'   => 'PHP',
    'handle' => 'php',
    'fields' => [
        'cat_description' => 'Articles about the PHP language.',
        'cat_image_url'   => 'https://example.com/php.png',
    ],
]);

// 6. Update
$categoryService->update($category, [
    'fields' => ['cat_description' => 'Updated description.'],
]);

// 7. Read
$category = Category::with('fieldValues.field.fieldType')->where('handle', 'php')->firstOrFail();
echo $category->field('cat_description'); // 'Updated description.'
echo $category->field('cat_image_url');   // 'https://example.com/php.png'
```

---

## Adding Custom Fields to Media Uploads

The custom field layer used by Entries, Categories, and Users is built on two
pieces that can be dropped onto any Eloquent model:

1. **`Fieldable` trait** (`app/Traits/Fieldable.php`) — adds `fieldValues()`
   (morphMany to `field_values`), `field(string $handle): mixed`, and
   `fieldArray(): array`.
2. **`PersistsFieldValues` concern** (`app/Concerns/PersistsFieldValues.php`) —
   adds `setField()` and `setFields()` for writing.

Because `Media` already has the morph alias `'media'` in the morph map, adding
this layer to uploaded media requires only a trait addition plus the standard
field/layout setup.

### How the Field Layer Works

Every `Fieldable` model stores values in the shared `field_values` table, keyed
on `(field_id, fieldable_id, fieldable_type)`. The `fieldable_type` column holds
the morph alias (`'media'`, `'entry'`, `'user'`, etc.), which is why
`getMorphClass()` must be used for writes.

### Step 1 — Add the Fieldable Trait to Media

Open `app/Models/Media.php` and add `Fieldable`:

```php
// app/Models/Media.php
namespace App\Models;

use App\Traits\Fieldable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Spatie\MediaLibrary\MediaCollections\Models\Media as BaseMedia;
use Spatie\Tags\HasTags;

class Media extends BaseMedia
{
    use Fieldable, HasTags;

    public function media_library(): BelongsTo
    {
        return $this->belongsTo(Media\Library::class);
    }

    public function categories(): MorphToMany
    {
        return $this->morphToMany(Category::class, 'categorizable')
            ->withTimestamps();
    }
}
```

This adds three methods: `fieldValues(): MorphMany`, `field(string $handle): mixed`,
and `fieldArray(): array`.

### Step 2 — Create Fields and a Field Layout

Run in a seeder or Artisan command. Use a prefix (e.g. `media_`) to keep
handles globally unique.

```php
use App\Models\Field;
use App\Models\Field\Group as FieldGroup;
use App\Models\Field\Type as FieldType;
use App\Models\FieldLayout;
use App\Models\FieldLayout\Tab;
use App\Models\FieldLayout\TabElement;

$textType = FieldType::where('object', \App\Field\Types\Text::class)->firstOrFail();
$textArea = FieldType::where('object', \App\Field\Types\Textarea::class)->firstOrFail();

$altText = Field::firstOrCreate(
    ['handle' => 'media_alt_text'],
    ['name' => 'Alt Text',     'label' => 'Alt Text',     'field_type_id' => $textType->id]
);
$caption = Field::firstOrCreate(
    ['handle' => 'media_caption'],
    ['name' => 'Caption',      'label' => 'Caption',      'field_type_id' => $textArea->id]
);
$credit  = Field::firstOrCreate(
    ['handle' => 'media_credit'],
    ['name' => 'Photo Credit', 'label' => 'Photo Credit', 'field_type_id' => $textType->id]
);

$fieldGroup = FieldGroup::firstOrCreate(
    ['handle' => 'media-metadata'],
    ['name'   => 'Media Metadata', 'description' => 'Custom fields for uploaded media.']
);
$fieldGroup->fields()->syncWithoutDetaching([$altText->id, $caption->id, $credit->id]);

$layout = FieldLayout::create(['name' => 'Media Field Layout']);
$tab    = Tab::create(['field_layout_id' => $layout->id, 'name' => 'Metadata', 'sort_order' => 1]);

foreach ([$altText, $caption, $credit] as $i => $field) {
    TabElement::create([
        'field_layout_tab_id' => $tab->id,
        'field_id'            => $field->id,
        'required'            => false,
        'sort_order'          => $i + 1,
    ]);
}
```

### Step 3 — Write Field Values to a Media Item

`$media->getMorphClass()` returns `'media'` (the morph alias), which is the
correct value for `fieldable_type`.

```php
use App\Models\Field;use App\Models\FieldValue;use App\Models\Media;use App\Traits\PersistsFieldValues;

// Option A — via PersistsFieldValues in a service
class MediaFieldService
{
    use PersistsFieldValues;

    public function saveFields(Media $media, array $fields): void
    {
        $this->setFields($media, $fields);
    }
}

$service = new MediaFieldService();
$service->saveFields($media, [
    'media_alt_text' => 'A red barn in a snowy field',
    'media_caption'  => 'Photograph taken in Vermont, January 2026.',
    'media_credit'   => 'Jane Doe Photography',
]);

// Option B — write directly (mirrors what PersistsFieldValues does internally)

$field = Field::where('handle', 'media_alt_text')->firstOrFail();

FieldValue::updateOrCreate(
    [
        'field_id'       => $field->id,
        'fieldable_id'   => $media->getKey(),
        'fieldable_type' => $media->getMorphClass(), // 'media'
    ],
    [$field->fieldType->instance()->storageColumn() => 'A red barn in a snowy field']
);
```

To write field values at upload time, extend `UploadMedia` with
`PersistsFieldValues`:

```php
// app/Actions/Media/Library/UploadMedia.php
namespace App\Actions\Media\Library;

use App\Actions\AbstractAction;use App\Http\Requests\FormRequest;use App\Models\Media\Library as LibraryModel;use App\Traits\PersistsFieldValues;

class UploadMedia extends AbstractAction
{
    use PersistsFieldValues;

    public function upload(FormRequest $request, LibraryModel $library)
    {
        $media = $library
            ->addMedia($request->file('file'))
            ->toMediaCollection($library->handle);

        $media->library_id = $library->id;
        $media->name       = $request->input('name');

        $media->categories()->detach();
        foreach ($request->input('categories', []) as $catId) {
            $media->categories()->attach($catId);
        }

        $media->save();

        if ($request->filled('fields')) {
            $this->setFields($media, $request->input('fields'));
        }

        return $media;
    }
}
```

### Step 4 — Read Field Values from a Media Item

```php
use App\Models\Media;

// Single item — eager-load the full field chain
$media = Media::with('fieldValues.field.fieldType')->findOrFail($id);

echo $media->field('media_alt_text'); // 'A red barn in a snowy field'
echo $media->field('media_caption');  // 'Photograph taken in Vermont, January 2026.'
echo $media->field('media_credit');   // 'Jane Doe Photography'

// As an associative array
$media->fieldArray();
// ['media_alt_text' => '...', 'media_caption' => '...', 'media_credit' => '...']

// Collection — avoid N+1 with loadMissing
$items = Media::where('collection_name', 'hero-images')->get();
$items->loadMissing('fieldValues.field.fieldType');

foreach ($items as $item) {
    echo $item->field('media_alt_text');
}
```

### Step 5 — Attach Field Groups to a Library

`Media\Library` already has `field_groups()` via `field_groupables`. Attaching
a FieldGroup registers which fields are available when uploading to that library:

```php
use App\Models\Field\Group as FieldGroup;
use App\Models\Media\Library;

$library    = Library::where('handle', 'site-images')->firstOrFail();
$fieldGroup = FieldGroup::where('handle', 'media-metadata')->firstOrFail();

$library->field_groups()->syncWithoutDetaching([$fieldGroup->id]);

// Inspect attached field groups (for admin UI rendering)
$library->load('field_groups.fields.fieldType');
foreach ($library->field_groups as $group) {
    foreach ($group->fields as $field) {
        echo $field->handle; // 'media_alt_text', 'media_caption', ...
    }
}
```

### Morph Map Note

`'media'` is already registered in `AppServiceProvider`. Any `FieldValue` rows
written for media store `fieldable_type = 'media'`. If you see
`App\Models\Media` in that column from old code, those rows will not resolve
correctly until converted to the alias.

---

## Site Routing (Public-Facing URLs)

The public-facing site uses a two-driver routing pipeline in
`App\Services\SiteRouting\SiteRouter`. Drivers are tried in priority order
(config key `site.routing.priority`, default `['entry_tree', 'template']`).
The first driver returning a non-null `RouteResult` wins; no match throws
`NotFoundHttpException`.

### EntryTree Driver

`EntryTreeRouteDriver` resolves a URI against `entry_trees`. Only entries
passing `published()` are served. Template precedence: `EntryTree.template`
→ `EntryType.default_template` → `'entries.show'`.

```php
use App\Services\SiteRouting\SiteRouter;

$view = app(SiteRouter::class)->render('/blog/my-post');
// Template receives: $entry, $entryType, $node
```

### Template Driver

`TemplateRouteDriver` maps URL segments to views under `resources/templates/`.
Reserved first segments (`api`, `admin`, `login`, `logout`, `register`,
`password`, `sanctum`, `storage`, `assets`, `vendor`) are blocked.

| URL | Resolved view |
|---|---|
| `/` | `templates::site.index` |
| `/blog` | `templates::blog.index` |
| `/blog/my-post` | `templates::blog.entry` (with `$handle = 'my-post'`) |
| `/blog/archive` | `templates::blog.archive` (if the file exists) |

Key/value pairs after the second segment are parsed into `$params`:

```
/blog/my-post/page/2  →  $params = ['page' => '2']
```

Example template:

```php
{{-- resources/templates/blog/entry.blade.php --}}
@php
    $entry = \App\Facades\Content::query()
        ->inGroup('blog')
        ->published()
        ->where('handle', $handle)
        ->firstOrFail();
@endphp

<h1>{{ $entry->title }}</h1>
<div>{!! $entry->field('body') !!}</div>
```

### Configuring Driver Priority

```php
// config/site.php
return [
    'routing'   => ['priority' => ['entry_tree', 'template']],
    'templates' => ['default_template' => 'templates::site.index'],
];
```

---

## Adding New Permissions

```php
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

// php artisan db:seed --class=EntryPermissionsSeeder
class EntryPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['name' => 'create entry',  'description' => 'Create new entries'],
            ['name' => 'edit entry',    'description' => 'Edit existing entries'],
            ['name' => 'delete entry',  'description' => 'Delete entries'],
            ['name' => 'publish entry', 'description' => 'Set entries to published status'],
        ];

        foreach ($permissions as $p) {
            Permission::firstOrCreate(['name' => $p['name']], $p);
        }

        Role::findByName('admin')->givePermissionTo([
            'create entry', 'edit entry', 'publish entry',
        ]);

        $editor = Role::firstOrCreate(['name' => 'editor']);
        $editor->givePermissionTo([
            'access admin', 'create entry', 'edit entry', 'publish entry',
        ]);
    }
}
```

Gate checks work immediately after seeding:

```php
$this->authorize('publish entry');             // controller
if (auth()->user()->can('publish entry')) { }  // inline
```

The `super admin` role bypasses every permission via `Gate::before`.

---

## Adding a New Entry Type End-to-End

The following walkthrough adds a "Recipes" section with two types: Standard
Recipe and Video Recipe.

### 1. Create fields and a FieldGroup

```php
use App\Models\Field;
use App\Models\Field\Group as FieldGroup;
use App\Models\Field\Type as FieldType;

$text     = FieldType::where('object', \App\Field\Types\Text::class)->firstOrFail();
$textarea = FieldType::where('object', \App\Field\Types\Textarea::class)->firstOrFail();
$number   = FieldType::where('object', \App\Field\Types\Number::class)->firstOrFail();

$fieldDefs = [
    ['handle' => 'ingredients',    'name' => 'Ingredients',    'label' => 'Ingredients',    'type' => $textarea],
    ['handle' => 'instructions',   'name' => 'Instructions',   'label' => 'Instructions',   'type' => $textarea],
    ['handle' => 'prep_time_mins', 'name' => 'Prep Time',      'label' => 'Prep Time (min)','type' => $number],
    ['handle' => 'servings',       'name' => 'Servings',       'label' => 'Servings',       'type' => $number],
    ['handle' => 'video_url',      'name' => 'Video URL',      'label' => 'Video URL',      'type' => $text],
];

foreach ($fieldDefs as $def) {
    Field::firstOrCreate(
        ['handle' => $def['handle']],
        ['name' => $def['name'], 'label' => $def['label'], 'field_type_id' => $def['type']->id]
    );
}

$recipeGroup = FieldGroup::firstOrCreate(['handle' => 'recipe-core'], ['name' => 'Recipe Core Fields']);
$coreIds = Field::whereIn('handle', ['ingredients', 'instructions', 'prep_time_mins', 'servings'])->pluck('id');
$recipeGroup->fields()->syncWithoutDetaching($coreIds->all());
```

### 2. Create FieldLayouts

```php
use App\Models\FieldLayout;
use App\Models\FieldLayout\Tab;
use App\Models\FieldLayout\TabElement;

// Shared group layout
$groupLayout = FieldLayout::create(['name' => 'Recipe Group Layout']);
$recipeTab   = Tab::create(['field_layout_id' => $groupLayout->id, 'name' => 'Recipe', 'sort_order' => 1]);

foreach (Field::whereIn('handle', ['ingredients', 'instructions', 'prep_time_mins', 'servings'])->get() as $i => $field) {
    TabElement::create([
        'field_layout_tab_id' => $recipeTab->id,
        'field_id'            => $field->id,
        'required'            => in_array($field->handle, ['ingredients', 'instructions']),
        'sort_order'          => $i + 1,
    ]);
}

// Video-specific type layout
$videoLayout = FieldLayout::create(['name' => 'Video Recipe Layout']);
$videoTab    = Tab::create(['field_layout_id' => $videoLayout->id, 'name' => 'Video', 'sort_order' => 1]);
TabElement::create([
    'field_layout_tab_id' => $videoTab->id,
    'field_id'            => Field::where('handle', 'video_url')->value('id'),
    'required'            => true,
    'sort_order'          => 1,
]);
```

### 3. Create the EntryGroup

```php
use App\Models\EntryGroup;
use App\Models\StatusGroup;

$statusGroup = StatusGroup::where('handle', 'publication')->firstOrFail();

$entryGroup = EntryGroup::create([
    'name'            => 'Recipes',
    'handle'          => 'recipes',
    'description'     => 'Step-by-step cooking guides.',
    'field_layout_id' => $groupLayout->id,
    'status_group_id' => $statusGroup->id,
    'sort_order'      => 10,
]);

$entryGroup->fieldGroups()->syncWithoutDetaching([$recipeGroup->id]);
```

### 4. Write the EntryType PHP classes

```php
// app/EntryTypes/RecipeEntryType.php
namespace App\EntryTypes;

use App\Models\Entry;

class RecipeEntryType extends AbstractEntryType
{
    public function beforeCreate(array $data): array
    {
        $data['status'] = $data['status'] ?? 'draft';
        return $data;
    }
}

// app/EntryTypes/VideoRecipeEntryType.php
class VideoRecipeEntryType extends RecipeEntryType
{
    public function afterCreate(Entry $entry, array $data): void
    {
        // ProcessVideoRecipe::dispatch($entry);
    }
}
```

### 5. Register the EntryType rows

```php
use App\Models\EntryType;

EntryType::firstOrCreate(
    ['entry_group_id' => $entryGroup->id, 'handle' => 'recipe'],
    ['name' => 'Standard Recipe', 'class' => \App\EntryTypes\RecipeEntryType::class, 'sort_order' => 1]
);

EntryType::firstOrCreate(
    ['entry_group_id' => $entryGroup->id, 'handle' => 'video_recipe'],
    [
        'name'            => 'Video Recipe',
        'class'           => \App\EntryTypes\VideoRecipeEntryType::class,
        'field_layout_id' => $videoLayout->id,
        'sort_order'      => 2,
    ]
);
```

### 6. Validate and create entries

```bash
php artisan app:validate-class-references
```

```php
use App\Facades\Content;

$recipe = Content::create('recipe', [
    'title'  => 'Classic Carbonara',
    'status' => 'published',
    'fields' => [
        'ingredients'    => "200g spaghetti\n2 eggs\n100g pancetta",
        'instructions'   => "1. Boil pasta...\n2. Fry pancetta...",
        'prep_time_mins' => 15,
        'servings'       => 2,
    ],
]);

$videoRecipe = Content::create('video_recipe', [
    'title'  => 'Carbonara in 60 Seconds',
    'status' => 'published',
    'fields' => [
        'ingredients'    => "200g spaghetti\n2 eggs",
        'instructions'   => 'Watch the video.',
        'prep_time_mins' => 5,
        'servings'       => 2,
        'video_url'      => 'https://youtube.com/watch?v=example',
    ],
]);

// Query
$allRecipes = Content::query()->inGroup('recipes')->published()->get();
$videos     = Content::query()->ofType('video_recipe')->published()->latest()->paginate(12);

echo $recipe->field('ingredients');
echo $videoRecipe->field('video_url');
```

---

## Key Data Flow Summary

### Write path (entry creation)

```
Controller
  └── Content::create('type_handle', $data)
        └── EntryService::create()
              ├── EntryTypeRegistry::resolveByHandle('type_handle')
              │     └── resolves EntryType row → instantiates PHP class
              └── EntryRepository::create(AbstractEntryType, $data)
                    ├── DB::transaction {
                    │     AbstractEntryType::beforeCreate($data) → $data
                    │     Load entryGroup (statusGroup, fieldLayout)
                    │     Entry::save()
                    │     syncAuthors()
                    │     syncCategories()
                    │     applyFieldValues()
                    │       ├── resolveLayoutFields() (type + group merged)
                    │       ├── scalar  → FieldValue::updateOrCreate()
                    │       └── relational → EntryRelationship::create()
                    │   }
                    └── AbstractEntryType::afterCreate($entry, $data) — outside tx
```

### Read path (entry query)

```
Content::query()
  └── EntryQueryBuilder
        ├── Chainable: inGroup, ofType, published, withStatus,
        │   withAuthor, withCategory, where, orderBy, latest
        └── Terminal: get() / paginate() / first() / firstOrFail()
              └── ->with([
                    'entryGroup', 'entryType', 'creator', 'authors',
                    'categories', 'fieldValues.field.fieldType',
                    'entryRelationships.field',
                    'entryRelationships.relatedEntry'
                  ])

$entry->field('handle')
  ├── Scalar:     fieldValues → resolvedValue()
  └── Relational: entryRelationships → Collection<Entry> sorted by sort_order
```

### Field value storage

```
field_values
  field_id / fieldable_id / fieldable_type (morph alias)
  value_text | value_integer | value_float | value_date | value_boolean | value_json

entry_relationships  (relational fields only)
  entry_id / related_entry_id / field_id / sort_order
```

### Morph map aliases at a glance

| Alias | Model | Fieldable |
|---|---|---|
| `entry` | `App\Models\Entry` | Scalar + Relational |
| `user` | `App\Models\User` | Scalar only |
| `category` | `App\Models\Category` | Scalar only |
| `media` | `App\Models\Media` | Scalar only (after adding `Fieldable` trait) |
| `entry_group` | `App\Models\EntryGroup` | No |
| `entry_type` | `App\Models\EntryType` | No |
| `category_group` | `App\Models\Category\Group` | No |
| `field_group` | `App\Models\Field\Group` | No |
| `media_library` | `App\Models\Media\Library` | No |
