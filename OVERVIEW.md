# Laravel CMS — Project Overview

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
- [Category Groups and Categories](#category-groups-and-categories)
  - [Creating a Category Group and Categories](#creating-a-category-group-and-categories)
  - [Fetching Categories](#fetching-categories)
- [Field Layouts](#field-layouts)
  - [Building a Layout Programmatically](#building-a-layout-programmatically)
  - [Getting All Fields from a Layout](#getting-all-fields-from-a-layout)
- [Entry Groups and Entry Types](#entry-groups-and-entry-types)
  - [Multiple Entry Types per Group](#multiple-entry-types-per-group)
  - [Field Layering: Group Fields + Type Fields](#field-layering-group-fields--type-fields)
  - [Setting Up Multiple Entry Types in One Group](#setting-up-multiple-entry-types-in-one-group)
  - [Entry Type Classes Can Share Logic via a Base Class](#entry-type-classes-can-share-logic-via-a-base-class)
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
- [Deleting Entries](#deleting-entries)
- [User Extended Profile (UserSchema)](#user-extended-profile-userschema)
  - [Setting Up the User Schema](#setting-up-the-user-schema)
  - [Writing Field Values to a User](#writing-field-values-to-a-user)
  - [Reading Field Values from a User](#reading-field-values-from-a-user)
  - [Typical Controller Pattern](#typical-controller-pattern)
  - [Comparison: Users vs Entries](#comparison-users-vs-entries)
- [UserService and the Users Facade](#userservice-and-the-users-facade)
  - [CRUD](#crud)
  - [Roles](#roles)
  - [Custom Fields](#custom-fields)
  - [Passwords](#passwords)
  - [Two-Factor Authentication](#two-factor-authentication)
  - [OAuth Token Management](#oauth-token-management)
  - [Using Actions Directly](#using-actions-directly)
- [Custom Field Groups on Category Groups](#custom-field-groups-on-category-groups)
  - [Step 1 — Create Fields and attach them to the CategoryGroup](#step-1--create-fields-and-attach-them-to-the-categorygroup)
  - [Step 2 — Write field values to a Category](#step-2--write-field-values-to-a-category)
  - [Step 3 — Read field values back](#step-3--read-field-values-back)
  - [Writing multiple fields at once](#writing-multiple-fields-at-once)
- [Adding New Permissions](#adding-new-permissions)
- [Adding a New Entry Type End-to-End](#adding-a-new-entry-type-end-to-end)
- [Key Data Flow Summary](#key-data-flow-summary)

---

## Architecture at a Glance

This is an **ExpressionEngine-inspired headless CMS** built on Laravel. The core philosophy: all content structure is admin-defined at runtime. Entry types are concrete PHP classes; everything else (fields, layouts, statuses, categories) is database-driven.

```
FieldType          — system-level type registry (Text, Textarea, Date, Relationship…)
  └── Field        — admin-created field instances with settings
        └── FieldGroup  — reusable bundles of fields

StatusGroup
  └── Status       — named statuses with handles and colours

CategoryGroup
  └── Category     — hierarchical tree of categories

FieldLayout
  └── Tab
        └── TabElement → Field

EntryGroup         — owns a FieldLayout, a StatusGroup, CategoryGroups, FieldGroups
  └── EntryType    — concrete PHP class, extends AbstractEntryType
        └── Entry  — the content row (title, slug, status, published_at, authors)
              ├── FieldValue    — scalar custom field data
              └── EntryRelationship  — relational field data (M2M to other entries)

UserSchema         — singleton that owns a FieldLayout for all users
  └── User         — extended with Fieldable and HasRoles
```

---

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
```

The `DatabaseSeeder` runs in this order:

1. `RolesPermissionsSeeder` — permissions + 3 roles
2. `UsersSeeder` — default admin user
3. `FieldTypeSeeder` — 9 field type classes registered
4. `StatusGroupSeeder` — publication status group
5. `CategoryGroupSeeder` — category groups + categories
6. `FieldGroupSeeder` — field groups + fields
7. `EntryGroupSeeder` — entry groups, layouts, entry types
8. `UserSchemaSeeder` — user profile schema
9. `EntrySeeder` *(local/testing only)* — sample blog posts and products

---

## Users, Roles, and Permissions

The system uses **Spatie Permission** (`spatie/laravel-permission`) with the `HasRoles` trait on `User`.

### Built-in Roles

| Role | Access |
|---|---|
| `super admin` | Everything — bypasses all permission checks |
| `admin` | Admin panel + full CRUD for users, categories, media, fields |
| `user` | Admin panel access only |

### Built-in Permissions

```
api                      
access admin
view/create/edit/delete user
view/create/edit/delete user token
create/edit/delete/reorder category group
create/edit/delete/reorder category
create/edit/delete/reorder media library
```

### Creating Users Programmatically

```php
use App\Models\User;
use Illuminate\Support\Facades\Hash;

// Create a user and assign a role
$user = User::create([
    'name'     => 'Jane Doe',
    'email'    => 'jane@example.com',
    'password' => Hash::make('password'),
]);

$user->assignRole('admin');
```

### Checking Permissions

```php
// Gate/policy check
if ($user->can('edit category')) { ... }

// Role check
if ($user->hasRole('super admin')) { ... }

// Multiple roles
if ($user->hasAnyRole(['admin', 'super admin'])) { ... }
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

Field types are PHP classes that live in `app/Field/Types/` and extend `AbstractField`. They are registered in the `field_types` table by `FieldTypeSeeder`.

### Built-in Types

| Class | Storage column | Notes |
|---|---|---|
| `Text` | `value_text` | Single line |
| `Textarea` | `value_text` | Multi-line |
| `Number` | `value_integer` or `value_float` | Uses `decimals` setting |
| `Date` | `value_date` | Cast to Carbon |
| `EmailAddress` | `value_text` | |
| `Url` | `value_text` | |
| `Telephone` | `value_text` | |
| `ColorPicker` | `value_text` | Hex value |
| `Relationship` | — | M2M via `entry_relationships` |

### Creating a Custom Field Type

```php
// app/Field/Types/Toggle.php
namespace App\Field\Types;

use App\Field\AbstractField;

class Toggle extends AbstractField
{
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
use App\Models\Field\Type;

Type::firstOrCreate(
    ['object' => \App\Field\Types\Toggle::class],
    ['name'   => 'Toggle']
);
```

---

## Field Groups and Fields

**FieldGroups** are reusable bundles of fields that get assigned to EntryGroups, CategoryGroups, or UserSchema. Fields belong to one or more groups via a polymorphic M2M.

### Creating a Field Group with Fields

```php
use App\Models\Field;
use App\Models\Field\Group as FieldGroup;
use App\Models\Field\Type as FieldType;

$textType = FieldType::where('object', \App\Field\Types\Text::class)->firstOrFail();

$group = FieldGroup::firstOrCreate(
    ['slug' => 'product-details'],
    ['name' => 'Product Details', 'description' => 'Core product information.']
);

$fields = [
    ['slug' => 'price',    'name' => 'Price',    'label' => 'Price'],
    ['slug' => 'sku',      'name' => 'SKU',      'label' => 'SKU Number'],
    ['slug' => 'in_stock', 'name' => 'In Stock', 'label' => 'In Stock'],
];

foreach ($fields as $def) {
    $field = Field::firstOrCreate(
        ['slug' => $def['slug']],
        array_merge($def, ['field_type_id' => $textType->id])
    );
    $group->fields()->syncWithoutDetaching([$field->id]);
}
```

---

## Status Groups and Statuses

Each `EntryGroup` owns exactly one `StatusGroup`. Entries store the status as a plain string handle (no FK) for fast indexed reads.

### Creating a Status Group

```php
use App\Models\Status;
use App\Models\StatusGroup;

$group = StatusGroup::create([
    'name'       => 'Review Workflow',
    'handle'     => 'review',
    'sort_order' => 2,
]);

$statuses = [
    ['name' => 'Pending Review', 'handle' => 'pending',  'color' => '#F59E0B', 'is_default' => true,  'sort_order' => 1],
    ['name' => 'Approved',       'handle' => 'approved', 'color' => '#10B981', 'is_default' => false, 'sort_order' => 2],
    ['name' => 'Rejected',       'handle' => 'rejected', 'color' => '#EF4444', 'is_default' => false, 'sort_order' => 3],
];

foreach ($statuses as $s) {
    Status::create(array_merge($s, ['status_group_id' => $group->id]));
}
```

---

## Category Groups and Categories

Categories are hierarchical (parent/child tree). A `CategoryGroup` owns a flat-to-tree set of categories. Multiple CategoryGroups can be assigned to an EntryGroup so entries can be tagged from each group's vocabulary.

### Creating a Category Group and Categories

```php
use App\Models\Category;
use App\Models\Category\Group as CategoryGroup;

$group = CategoryGroup::firstOrCreate(
    ['slug' => 'regions'],
    ['name' => 'Regions', 'sort_order' => 1]
);

// Root category
$europe = Category::create([
    'group_id'   => $group->id,
    'name'       => 'Europe',
    'slug'       => 'europe',
    'sort_order' => 1,
]);

// Child category
Category::create([
    'group_id'   => $group->id,
    'parent_id'  => $europe->id,
    'name'       => 'France',
    'slug'       => 'france',
    'sort_order' => 1,
]);
```

### Fetching Categories

```php
// Root categories with full recursive tree
$group->rootCategories()->with('childrenRecursive')->get();

// Scoped query
Category::inGroup($group)->roots()->with('childrenRecursive')->get();
```

---

## Field Layouts

A `FieldLayout` organises fields into named tabs. Layouts are attached to EntryGroups, EntryTypes, CategoryGroups, and UserSchema.

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

foreach (['body', 'excerpt', 'related_entries'] as $order => $slug) {
    $field = Field::where('slug', $slug)->firstOrFail();
    TabElement::create([
        'field_layout_tab_id' => $contentTab->id,
        'field_id'            => $field->id,
        'required'            => $slug === 'body',
        'sort_order'          => $order + 1,
    ]);
}

$seoTab = Tab::create([
    'field_layout_id' => $layout->id,
    'name'            => 'SEO',
    'sort_order'      => 2,
]);

foreach (['meta_title', 'meta_description'] as $order => $slug) {
    TabElement::create([
        'field_layout_tab_id' => $seoTab->id,
        'field_id'            => Field::where('slug', $slug)->value('id'),
        'required'            => false,
        'sort_order'          => $order + 1,
    ]);
}
```

### Getting All Fields from a Layout

```php
$layout->fields(); // Collection<Field> flattened from all tabs
```

---

## Entry Groups and Entry Types

An **EntryGroup** is the section/channel (e.g. "Blog", "Products"). It ties together a FieldLayout, a StatusGroup, CategoryGroups, and FieldGroups.

An **EntryType** is a hardcoded PHP class that implements content-specific logic. Its handle is used when creating entries.

### Multiple Entry Types per Group

An Entry Group can have **any number of Entry Types**. This is the primary way to model variant content within a single section. For example, a Products group can have `ProductDigital`, `ProductShippable`, and `ProductSubscription` types — each with its own fields layered on top of the shared group fields.

```
Products (entry group)
  ├── product_digital      → ProductDigitalEntryType::class
  ├── product_shippable    → ProductShippableEntryType::class
  └── product_subscription → ProductSubscriptionEntryType::class
```

### Field Layering: Group Fields + Type Fields

Fields are defined at two levels and **merged** when reading or writing:

| Level | Defined on | Applies to |
|---|---|---|
| Group-level layout | `EntryGroup.field_layout_id` | All entry types in the group |
| Type-level layout | `EntryType.field_layout_id` (nullable) | Only entries of that specific type |

`EntryRepository` merges both layouts transparently:

```php
// Group fields (shared)     + Type fields (specific) = all available fields for this entry
$groupFields->merge($typeFields)->unique('id');
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

$textType   = FieldType::where('object', \App\Field\Types\Text::class)->firstOrFail();
$numberType = FieldType::where('object', \App\Field\Types\Number::class)->firstOrFail();

$productsGroup = EntryGroup::where('handle', 'products')->firstOrFail();

// --- ProductDigital ---
$digitalLayout = FieldLayout::create(['name' => 'Digital Product Fields']);
$tab = Tab::create(['field_layout_id' => $digitalLayout->id, 'name' => 'Digital Delivery', 'sort_order' => 1]);

foreach (['download_url', 'license_type'] as $i => $slug) {
    $field = Field::firstOrCreate(
        ['slug' => $slug],
        ['name' => ucwords(str_replace('_', ' ', $slug)), 'field_type_id' => $textType->id, 'label' => ucwords(str_replace('_', ' ', $slug))]
    );
    TabElement::create(['field_layout_tab_id' => $tab->id, 'field_id' => $field->id, 'sort_order' => $i + 1]);
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
$tab = Tab::create(['field_layout_id' => $shippableLayout->id, 'name' => 'Shipping', 'sort_order' => 1]);

foreach (['weight', 'dimensions'] as $i => $slug) {
    $field = Field::firstOrCreate(
        ['slug' => $slug],
        ['name' => ucwords($slug), 'field_type_id' => $textType->id, 'label' => ucwords($slug)]
    );
    TabElement::create(['field_layout_tab_id' => $tab->id, 'field_id' => $field->id, 'sort_order' => $i + 1]);
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
    public function beforeCreate(array &$data): void
    {
        // Shared logic for all product types
        if (empty($data['status'])) {
            $data['status'] = 'draft';
        }
    }
}

// app/EntryTypes/ProductDigitalEntryType.php
class ProductDigitalEntryType extends BaseProductEntryType
{
    public function afterCreate(\App\Models\Entry $entry, array $data): void
    {
        // Type-specific: generate a license key, notify fulfilment service, etc.
    }
}

// app/EntryTypes/ProductShippableEntryType.php
class ProductShippableEntryType extends BaseProductEntryType
{
    public function afterCreate(\App\Models\Entry $entry, array $data): void
    {
        // Type-specific: sync with inventory system, etc.
    }
}
```

### Creating Entries of Each Type

```php
use App\Facades\Content;

// Digital — receives group fields + digital-specific fields
Content::create('product_digital', [
    'title'  => 'Laravel eBook',
    'status' => 'published',
    'fields' => [
        'price'        => 2999,          // group field
        'sku'          => 'EBOOK-001',   // group field
        'download_url' => 'https://cdn.example.com/laravel-ebook.pdf', // type field
        'license_type' => 'single-user', // type field
    ],
]);

// Shippable — receives group fields + shipping-specific fields
Content::create('product_shippable', [
    'title'  => 'Merino Wool Sweater',
    'status' => 'published',
    'fields' => [
        'price'      => 8900,        // group field
        'sku'        => 'SWTR-M-BL', // group field
        'weight'     => '0.4kg',     // type field
        'dimensions' => '30x20x5cm', // type field
    ],
]);
```

Querying works across all types in the group regardless of type:

```php
// Returns all product entries — digital, shippable, and subscription
Content::query()->inGroup('products')->published()->get();

// Narrow to a specific type
Content::query()->ofType('product_digital')->published()->get();
```

### Multiple Groups Sharing the Same Entry Type Class

The same PHP class can back multiple Entry Type database rows (useful when you have structurally identical groups — e.g. `electronics_products` and `clothing_products` both using `ProductEntryType`). The handle must be unique per group, but the `class` column can repeat:

```php
EntryType::create([
    'entry_group_id' => EntryGroup::where('handle', 'electronics')->value('id'),
    'handle'         => 'electronics_product',
    'class'          => \App\EntryTypes\ProductEntryType::class,
    ...
]);

EntryType::create([
    'entry_group_id' => EntryGroup::where('handle', 'clothing')->value('id'),
    'handle'         => 'clothing_product',
    'class'          => \App\EntryTypes\ProductEntryType::class,
    ...
]);

Content::create('electronics_product', [...]);
Content::create('clothing_product', [...]);
```

### Creating an Entry Group

```php
use App\Models\EntryGroup;
use App\Models\StatusGroup;
use App\Models\Category\Group as CategoryGroup;
use App\Models\Field\Group as FieldGroup;

$statusGroup   = StatusGroup::where('handle', 'review')->firstOrFail();
$categoryGroup = CategoryGroup::where('slug', 'regions')->firstOrFail();
$fieldGroup    = FieldGroup::where('slug', 'content-fields')->firstOrFail();

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

### Creating an Entry Type Class

```php
// app/EntryTypes/NewsArticleEntryType.php
namespace App\EntryTypes;

use App\Models\Entry;

class NewsArticleEntryType extends AbstractEntryType
{
    /**
     * Called before an entry is persisted — mutate $data if needed.
     */
    public function beforeCreate(array &$data): void
    {
        // Force all new articles into 'pending' status
        $data['status'] = 'pending';
    }

    /**
     * Called after an entry is created — side-effects, notifications, etc.
     */
    public function afterCreate(Entry $entry, array $data): void
    {
        // SendReviewNotification::dispatch($entry);
    }

    public function beforeUpdate(Entry $entry, array &$data): void
    {
        // Prevent reverting to draft after approval
        if ($entry->status === 'approved' && ($data['status'] ?? null) === 'draft') {
            unset($data['status']);
        }
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

All entry creation goes through the `Content` facade (backed by `ContentService` → `EntryRepository`).

### Creating an Entry

```php
use App\Facades\Content;
use App\Models\Category;
use App\Models\User;

$author   = User::find(1);
$category = Category::where('slug', 'france')->firstOrFail();

$entry = Content::create('news_article', [
    'title'        => 'Election Results 2026',
    'published_at' => now(),
    'status'       => 'published',
    'authors'      => [$author->id],       // ordered M2M
    'categories'   => [$category->id],
    'fields'       => [
        'body'             => 'Full article text...',
        'excerpt'          => 'Short summary.',
        'meta_title'       => 'Election Results 2026 | News',
        'meta_description' => 'Coverage of the 2026 election.',
    ],
]);

echo $entry->id;    // persisted Entry model
echo $entry->slug;  // auto-generated from title if not provided
```

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

// Or via the model's fluent update (calls the same repository)
$entry->update(['status' => 'archived']);
```

### Using the Relationship Field

The `related_entries` field stores related entry IDs in the `entry_relationships` pivot table:

```php
$postA = Content::create('blog_post', ['title' => 'Post A', ...]);
$postB = Content::create('blog_post', ['title' => 'Post B', ...]);
$postC = Content::create('blog_post', ['title' => 'Post C', ...]);

// Link related posts (array order determines sort_order)
Content::update($postA, [
    'fields' => ['related_entries' => [$postB->id, $postC->id]],
]);

// Reading — returns Collection<Entry>
$postA->load('entryRelationships.field', 'entryRelationships.relatedEntry');
$related = $postA->field('related_entries');

foreach ($related as $rel) {
    echo $rel->title;
}
```

---

## Querying Entries

Use `Content::query()` for a fluent, chainable query builder.

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
$technology = Category::where('slug', 'technology')->firstOrFail();

$techPosts = Content::query()
    ->inGroup('blog')
    ->withCategory($technology->id)
    ->published()
    ->orderBy('published_at', 'desc')
    ->paginate(10);

// Single entry
$entry = Content::get(42);   // throws ModelNotFoundException if missing
$entry = Content::find(42);  // returns null if missing
```

### Reading Field Values

```php
// Scalar fields — returns the cast value directly
echo $entry->field('body');
echo $entry->field('meta_title');
echo $entry->field('price');        // integer or float

// Date field — returns Carbon instance
$entry->field('event_date')?->format('Y-m-d');

// Relationship field — returns Collection<Entry>
$related = $entry->field('related_entries');
foreach ($related as $rel) {
    echo $rel->title . ' (' . $rel->slug . ')';
}
```

> **Performance note:** `fieldValues.field.fieldType` and `entryRelationships.field` +
> `entryRelationships.relatedEntry` are included in `EntryRepository::defaultEagerLoad()`,
> so `Content::get()` and `Content::find()` never produce N+1 queries. The query builder's
> `get()`/`paginate()`/`first()` also eager-load `fieldValues.field.fieldType` but not
> `entryRelationships` — add that manually when needed:
>
> ```php
> Content::query()->inGroup('blog')->get()->load(
>     'entryRelationships.field',
>     'entryRelationships.relatedEntry'
> );
> ```

---

## Deleting Entries

```php
// Via the model (delegates to EntryRepository)
$entry->delete();

// Via the repository directly
app(\App\Repositories\EntryRepository::class)->delete($entry);
```

---

## User Extended Profile (UserSchema)

`UserSchema` is a singleton that owns a single `FieldLayout` applied to all users. Users implement `Fieldable` so they can store custom field values. The read API (`$user->field('slug')`) is identical to entries — the write side goes directly to `FieldValue` since there is no `UserRepository` equivalent of `EntryRepository`.

### Setting Up the User Schema

Create the fields, a field group, a layout, and attach them all to `UserSchema::instance()`. This is typically done once in a seeder:

```php
use App\Models\Field;
use App\Models\Field\Group as FieldGroup;
use App\Models\Field\Type as FieldType;
use App\Models\FieldLayout;
use App\Models\FieldLayout\Tab;
use App\Models\FieldLayout\TabElement;
use App\Models\UserSchema;

$textType = FieldType::where('object', \App\Field\Types\Text::class)->firstOrFail();

// Create the fields
$fieldDefs = [
    ['slug' => 'first_name', 'name' => 'First Name', 'label' => 'First Name'],
    ['slug' => 'last_name',  'name' => 'Last Name',  'label' => 'Last Name'],
    ['slug' => 'gender',     'name' => 'Gender',     'label' => 'Gender'],
];

foreach ($fieldDefs as $def) {
    Field::firstOrCreate(
        ['slug' => $def['slug']],
        array_merge($def, ['field_type_id' => $textType->id])
    );
}

// Create a field group and attach the fields
$group = FieldGroup::firstOrCreate(
    ['slug' => 'user-profile'],
    ['name' => 'User Profile', 'description' => 'Extended user profile fields.']
);

foreach ($fieldDefs as $def) {
    $group->fields()->syncWithoutDetaching([
        Field::where('slug', $def['slug'])->value('id'),
    ]);
}

// Build a layout with a Profile tab
$layout = FieldLayout::create(['name' => 'User Profile Layout']);

$tab = Tab::create([
    'field_layout_id' => $layout->id,
    'name'            => 'Profile',
    'sort_order'      => 1,
]);

foreach (['first_name', 'last_name', 'gender'] as $i => $slug) {
    TabElement::create([
        'field_layout_tab_id' => $tab->id,
        'field_id'            => Field::where('slug', $slug)->value('id'),
        'required'            => false,
        'sort_order'          => $i + 1,
    ]);
}

// Attach layout and field group to the singleton UserSchema
$schema = UserSchema::instance();
$schema->field_layout_id = $layout->id;
$schema->save();

$schema->fieldGroups()->syncWithoutDetaching([$group->id]);
```

### Writing Field Values to a User

```php
use App\Models\Field;
use App\Models\FieldValue;
use App\Models\User;

$user = User::find(1);

$values = [
    'first_name' => 'Jane',
    'last_name'  => 'Doe',
    'gender'     => 'female',
];

foreach ($values as $slug => $value) {
    $field = Field::where('slug', $slug)->firstOrFail();

    FieldValue::updateOrCreate(
        [
            'field_id'       => $field->id,
            'fieldable_id'   => $user->id,
            'fieldable_type' => User::class,
        ],
        ['value_text' => $value]
    );
}
```

### Reading Field Values from a User

```php
$user = User::with('fieldValues.field.fieldType')->find(1);

echo $user->field('first_name'); // 'Jane'
echo $user->field('last_name');  // 'Doe'
echo $user->field('gender');     // 'female'
```

The `field()` method comes from the `Fieldable` trait. As long as `fieldValues.field.fieldType` is eager-loaded, it resolves with no additional queries.

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
    $fieldSlugs = ['first_name', 'last_name', 'gender'];
    $fields     = Field::whereIn('slug', $fieldSlugs)->get()->keyBy('slug');

    foreach ($fieldSlugs as $slug) {
        if (! $request->has($slug) || ! isset($fields[$slug])) {
            continue;
        }

        FieldValue::updateOrCreate(
            [
                'field_id'       => $fields[$slug]->id,
                'fieldable_id'   => $user->id,
                'fieldable_type' => User::class,
            ],
            ['value_text' => $request->input($slug)]
        );
    }
}
```

### Comparison: Users vs Entries

| | Entries | Users |
|---|---|---|
| Write API | `Content::create()` / `Content::update()` | `Users::create()` / `Users::update()` |
| Read API | `$entry->field('slug')` | `$user->field('slug')` (same trait) |
| Schema | Per-group FieldLayout + per-type FieldLayout | Single `UserSchema` singleton |
| Lifecycle hooks | `beforeCreate`, `afterCreate`, etc. | None — plain Eloquent |

---

## UserService and the Users Facade

All user operations go through `UserService`, exposed via the `Users` facade (`App\Facades\Users`). Each method on the service is backed by a dedicated invokable Action class under `app/Actions/User/`, which can also be used standalone via dependency injection.

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

### Roles

```php
Users::assignRoles($user, 'editor');              // additive — keeps existing roles
Users::assignRoles($user, ['editor', 'writer']);

Users::syncRoles($user, ['admin']);               // replaces all roles

Users::revokeRole($user, 'editor');               // removes one role
```

### Custom Fields

```php
// Single field
Users::setField($user, 'bio', 'Staff engineer at Acme.');

// Multiple fields — batched in one query
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

### Passwords

```php
// Admin force-set — no current-password verification
Users::setPassword($user, 'newpassword123');

// User-initiated change (verifies current password, uses Fortify's UpdateUserPassword action)
app(\App\Actions\User\UpdateUserPassword::class)->update($user, [
    'current_password'      => 'oldpassword',
    'password'              => 'newpassword123',
    'password_confirmation' => 'newpassword123',
]);
```

### Two-Factor Authentication

2FA is provided by Laravel Fortify. The `User` model uses the `TwoFactorAuthenticatable` trait.

```php
// Step 1 — enable 2FA; returns QR code SVG and plain-text secret for display
$setup = Users::enableTwoFactor($user);
// $setup['qr_code_svg'] — embed in the UI for the user to scan
// $setup['secret']      — display as a fallback manual entry code

// Step 2 — user scans QR code in their authenticator app, then submits a TOTP code
// Throws ValidationException if the code is wrong
Users::confirmTwoFactor($user, '123456');

// Check whether 2FA is active (confirmed)
Users::hasTwoFactor($user); // true after confirmation

// Recovery codes — one-time use codes for account recovery
$codes = Users::getRecoveryCodes($user);  // array of strings

// Invalidate existing codes and issue fresh ones
$newCodes = Users::regenerateRecoveryCodes($user);

// Disable 2FA entirely — clears secret and recovery codes
Users::disableTwoFactor($user);
```

### OAuth Token Management

```php
// Store a new token, revoking any existing active token for the same provider
$token = Users::upsertOauthToken($user, 'google', [
    'access_token'  => 'ya29.xxx',
    'refresh_token' => '1//xxx',
    'expires_at'    => now()->addHour(),
    'scopes'        => ['email', 'profile'],
    'provider_user_id' => '1234567890',
]);

// Get the current active token for a provider (null if expired/revoked/missing)
$token = Users::getActiveOauthToken($user, 'google');

if ($token?->isExpired()) {
    // refresh via your OAuth client and upsert again
}

// Revoke a single token
Users::revokeOauthToken($token);

// Revoke all tokens for a specific provider
Users::revokeAllOauthTokens($user, 'google');

// Revoke all tokens across all providers
Users::revokeAllOauthTokens($user);

// List active tokens (optionally filtered by provider)
$tokens = Users::listOauthTokens($user);
$googleTokens = Users::listOauthTokens($user, 'google');
```

### Using Actions Directly

Every operation is a standalone invokable action that can be injected into controllers or jobs:

```php
use App\Actions\User\CreateUser;
use App\Actions\User\SetUserFields;
use App\Actions\User\EnableTwoFactor;

// Via the container
app(CreateUser::class)(['name' => 'Jane', 'email' => 'jane@example.com', ...]);

// Via constructor injection
class UserController extends Controller
{
    public function __construct(
        private readonly CreateUser    $createUser,
        private readonly SetUserFields $setUserFields,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $user = ($this->createUser)($request->validated());
        return response()->json($user);
    }
}
```

**Full action inventory:**

| Action class | Purpose |
|---|---|
| `CreateUser` | Create user with roles + fields |
| `UpdateUser` | Update core attrs, roles, and/or fields |
| `DeleteUser` | Delete a user |
| `AssignRoles` | Add roles (additive) |
| `SyncRoles` | Replace all roles |
| `RevokeRole` | Remove one role |
| `SetUserField` | Write a single custom field value |
| `SetUserFields` | Write multiple custom field values (batched) |
| `SetPassword` | Admin force-set password |
| `EnableTwoFactor` | Begin 2FA setup, return QR + secret |
| `ConfirmTwoFactor` | Confirm 2FA with TOTP code |
| `DisableTwoFactor` | Disable 2FA and clear secret |
| `GetRecoveryCodes` | Get current recovery codes |
| `RegenerateRecoveryCodes` | Invalidate and re-issue recovery codes |
| `UpsertOauthToken` | Store a new OAuth token (revokes old) |
| `GetActiveOauthToken` | Get current active token for a provider |
| `RevokeOauthToken` | Revoke a single token |
| `RevokeAllOauthTokens` | Revoke all tokens (optionally by provider) |
| `ListOauthTokens` | List active tokens (optionally by provider) |

---

## Custom Field Groups on Category Groups

CategoryGroups support the `HasFieldGroups` and `HasFieldLayout` traits, so you can attach extra fields to categories themselves. The `Category` model uses the `Fieldable` trait, giving each category record its own field value storage.

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
use App\Models\FieldLayout;

$categoryGroup = CategoryGroup::where('slug', 'topics')->firstOrFail();

// Create the fields
$descField  = Field::create(['name' => 'Description',   'slug' => 'cat_description',   'field_type_id' => $textareaTypeId]);
$imageField = Field::create(['name' => 'Banner Image',  'slug' => 'cat_banner_image',  'field_type_id' => $fileTypeId]);

// Bundle them into a FieldGroup
$fieldGroup = FieldGroup::create(['name' => 'Category Details']);
$fieldGroup->fields()->attach([$descField->id, $imageField->id]);

// Attach the FieldGroup to the CategoryGroup
$categoryGroup->fieldGroups()->syncWithoutDetaching([$fieldGroup->id]);
```

You can also attach a pre-existing FieldGroup (e.g. shared SEO fields):

```php
$seoGroup = FieldGroup::where('slug', 'seo-fields')->firstOrFail();
$categoryGroup->fieldGroups()->syncWithoutDetaching([$seoGroup->id]);
```

### Step 2 — Write field values to a Category

```php
use App\Models\Category;
use App\Models\Field;
use App\Models\FieldValue;

$category = Category::where('slug', 'php')->firstOrFail();

$field  = Field::where('slug', 'cat_description')->firstOrFail();
$column = $field->fieldType->instance()->storageColumn(); // e.g. 'text_value'

FieldValue::updateOrCreate(
    [
        'field_id'       => $field->id,
        'fieldable_id'   => $category->id,
        'fieldable_type' => Category::class,
    ],
    [$column => 'All things PHP — tutorials, packages, and news.']
);
```

### Step 3 — Read field values back

```php
// Load field values eagerly to avoid N+1
$category->load('fieldValues.field');

$description = $category->field('cat_description');
$banner      = $category->field('cat_banner_image');
```

### Writing multiple fields at once

For bulk writes, pre-load all fields to avoid N+1 queries:

```php
use App\Models\Field;
use App\Models\FieldValue;

$category = Category::where('slug', 'php')->firstOrFail();

$fieldData = [
    'cat_description'  => 'All things PHP.',
    'cat_banner_image' => '/images/php-banner.jpg',
];

$fieldModels = Field::whereIn('slug', array_keys($fieldData))
    ->with('fieldType')
    ->get()
    ->keyBy('slug');

foreach ($fieldData as $slug => $value) {
    $field  = $fieldModels->get($slug);
    $column = $field->fieldType->instance()->storageColumn();

    FieldValue::updateOrCreate(
        [
            'field_id'       => $field->id,
            'fieldable_id'   => $category->id,
            'fieldable_type' => Category::class,
        ],
        [$column => $value]
    );
}
```

---

## Adding New Permissions

```php
use Spatie\Permission\Models\Permission;
use App\Models\Role;

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

---

## Adding a New Entry Type End-to-End

The complete sequence for standing up a new content section:

```php
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
use App\Models\Category;
use App\Models\Category\Group as CategoryGroup;

// 1. Status group
$statusGroup = StatusGroup::create(['name' => 'Event Status', 'handle' => 'events', 'sort_order' => 3]);
Status::create(['status_group_id' => $statusGroup->id, 'name' => 'Draft',     'handle' => 'draft',     'color' => '#9CA3AF', 'is_default' => true,  'sort_order' => 1]);
Status::create(['status_group_id' => $statusGroup->id, 'name' => 'Scheduled', 'handle' => 'scheduled', 'color' => '#3B82F6', 'is_default' => false, 'sort_order' => 2]);
Status::create(['status_group_id' => $statusGroup->id, 'name' => 'Live',      'handle' => 'live',      'color' => '#10B981', 'is_default' => false, 'sort_order' => 3]);

// 2. Category group
$catGroup = CategoryGroup::create(['name' => 'Event Types', 'slug' => 'event-types', 'sort_order' => 3]);
Category::create(['group_id' => $catGroup->id, 'name' => 'Conference', 'slug' => 'conference', 'sort_order' => 1]);
Category::create(['group_id' => $catGroup->id, 'name' => 'Workshop',   'slug' => 'workshop',   'sort_order' => 2]);

// 3. Fields and field group
$dateType = FieldType::where('object', \App\Field\Types\Date::class)->firstOrFail();
$textType = FieldType::where('object', \App\Field\Types\Text::class)->firstOrFail();

$fieldGroup = FieldGroup::create(['name' => 'Event Details', 'slug' => 'event-details']);
foreach ([
    ['slug' => 'event_date',     'name' => 'Event Date',     'field_type_id' => $dateType->id],
    ['slug' => 'event_location', 'name' => 'Event Location', 'field_type_id' => $textType->id],
    ['slug' => 'ticket_url',     'name' => 'Ticket URL',     'field_type_id' => $textType->id],
] as $def) {
    $field = Field::firstOrCreate(['slug' => $def['slug']], $def);
    $fieldGroup->fields()->syncWithoutDetaching([$field->id]);
}

// 4. Field layout
$layout = FieldLayout::create(['name' => 'Events Layout']);
$tab = Tab::create(['field_layout_id' => $layout->id, 'name' => 'Details', 'sort_order' => 1]);
foreach (['event_date', 'event_location', 'ticket_url'] as $i => $slug) {
    TabElement::create([
        'field_layout_tab_id' => $tab->id,
        'field_id'            => Field::where('slug', $slug)->value('id'),
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

Then create the PHP class:

```php
// app/EntryTypes/EventEntryType.php
namespace App\EntryTypes;

use App\Models\Entry;
use Illuminate\Support\Str;

class EventEntryType extends AbstractEntryType
{
    public function beforeCreate(array &$data): void
    {
        // Auto-generate excerpt from body if not provided
        if (empty($data['fields']['excerpt']) && ! empty($data['fields']['body'])) {
            $data['fields']['excerpt'] = Str::limit($data['fields']['body'], 160);
        }
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
    'categories'   => [Category::where('slug', 'conference')->value('id')],
    'fields'       => [
        'event_date'     => '2026-09-15',
        'event_location' => 'Amsterdam, Netherlands',
        'ticket_url'     => 'https://tickets.example.com/laravel-2026',
        'body'           => 'Join us for three days of talks...',
        'meta_title'     => 'Laravel Conference 2026',
        'meta_description' => 'Join 800 Laravel developers in Amsterdam.',
    ],
]);
```

---

## Key Data Flow Summary

```
Content::create('event', $data)
  → EntryTypeRegistry::resolveByHandle('event')
      → Fetches EntryType row, validates class, instantiates EventEntryType
  → EntryRepository::create(EventEntryType, $data)
      → EventEntryType::beforeCreate($data)        ← lifecycle hook
      → new Entry — core attributes + status
      → entry_authors sync (with sort_order)
      → categories sync
      → applyFieldValues:
          scalar fields     → FieldValue::updateOrCreate (value_text/integer/float/date/boolean/json)
          relational fields → entry_relationships delete + re-insert in order
      → EventEntryType::afterCreate($entry, $data) ← lifecycle hook
      → return Entry::refresh() with all eager loads
```
