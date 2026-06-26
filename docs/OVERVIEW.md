# Laravel CMS — Project Overview

> **Documentation status (2026-05-26 — Refresh Pass).** This file has been
> reconciled against the live source in `app/`, `database/`, `routes/`,
> `config/`, and `resources/`. Sections marked **[Accuracy Note]** call out
> places where prior copy had drifted from the code, or where current code
> is incomplete. See also **Recommendations & Remedies** and **Ambivalences
> for Review** at the end of the document.
>
> The codebase consistently uses **`handle`** (not `slug`) on every model
> that carries a developer-facing identifier — `Field`, `FieldGroup`,
> `EntryGroup`, `EntryType`, `EntryBehavior`, `StatusGroup`, `Status`,
> `CategoryGroup`, `Category`, `Entry`, and `Media\Library`.
>
> **When this document and the code disagree, the code wins.** Items still
> uncertain are listed in the Ambivalences section rather than asserted.

## Table of Contents

- [Architecture at a Glance](#architecture-at-a-glance)
    - [Cross-cutting infrastructure](#cross-cutting-infrastructure)
- [Setup](#setup)
- [Operational Commands and Deployment Notes](#operational-commands-and-deployment-notes)
- [Testing Strategy](#testing-strategy)
- [System Health and Data Integrity](#system-health-and-data-integrity)
- [Users, Roles, and Permissions](#users-roles-and-permissions)
    - [Built-in Roles](#built-in-roles)
    - [Built-in Permissions](#built-in-permissions)
    - [Creating Users Programmatically](#creating-users-programmatically)
    - [Checking Permissions](#checking-permissions)
    - [Creating a New Permission and Role](#creating-a-new-permission-and-role)
- [Adding New Permissions](#adding-new-permissions)
- [User Account Status](#user-account-status)
    - [Status Values](#status-values)
    - [Parallel Lock Column](#parallel-lock-column)
    - [Model Helpers](#model-helpers)
    - [Authentication Gate](#authentication-gate)
    - [Access-Enforcement Middleware](#access-enforcement-middleware)
    - [Status Change Events and Audit Log](#status-change-events-and-audit-log)
    - [Admin UI](#admin-ui)
- [User Extended Profile (UserSchema)](#user-extended-profile-userschema)
    - [Setting Up the User Schema](#setting-up-the-user-schema)
    - [Writing Field Values to a User](#writing-field-values-to-a-user)
    - [Reading Field Values from a User](#reading-field-values-from-a-user)
    - [Typical Controller Pattern](#typical-controller-pattern)
    - [Comparison: Users vs Entries](#comparison-users-vs-entries)
- [Author Eligibility](#author-eligibility)
    - [Schema](#schema)
    - [EntryAuthorService and EntryAuthors Facade](#entryauthorservice-and-entryauthors-facade)
    - [Promoting and Demoting Authors](#promoting-and-demoting-authors)
    - [Author Eligibility via UserService](#author-eligibility-via-userservice)
    - [Querying Eligible Authors](#querying-eligible-authors)
- [UserService and the Users Facade](#userservice-and-the-users-facade)
    - [CRUD](#crud)
    - [Roles](#roles)
    - [Custom Fields](#custom-fields)
    - [Passwords](#passwords)
    - [Status Management](#status-management)
    - [Two-Factor Authentication](#two-factor-authentication)
    - [OAuth Token Management](#oauth-token-management)
    - [Action Classes Inventory](#action-classes-inventory)
- [OAuth and Social Login](#oauth-and-social-login)
- [System and User Settings](#system-and-user-settings)
    - [Settings Domains](#settings-domains)
    - [Value Storage and Resolution](#value-storage-and-resolution)
    - [Reading Settings](#reading-settings)
    - [Writing System Settings](#writing-system-settings)
    - [Writing User Preferences](#writing-user-preferences)
    - [Adding a Setting](#adding-a-setting)
- [Field Types](#field-types)
    - [Built-in Types](#built-in-types)
        - [Write pipeline at a glance](#write-pipeline-at-a-glance)
        - [Per-type reference](#per-type-reference)
        - [Money field — design notes](#money-field--design-notes)
    - [Creating a Custom Field Type](#creating-a-custom-field-type)
- [Field Groups and Fields](#field-groups-and-fields)
    - [Creating a Field Group with Fields](#creating-a-field-group-with-fields)
- [Field Layouts](#field-layouts)
    - [Building a Layout Programmatically](#building-a-layout-programmatically)
    - [Getting All Fields from a Layout](#getting-all-fields-from-a-layout)
- [Status Groups and Statuses](#status-groups-and-statuses)
    - [Creating a Status Group](#creating-a-status-group)
    - [How an Entry Stores its Status](#how-an-entry-stores-its-status)
    - [StatusObserver — keeping status_is_public consistent](#statusobserver--keeping-status_is_public-consistent)
- [Category Groups and Categories](#category-groups-and-categories)
    - [Creating a Category Group and Categories](#creating-a-category-group-and-categories)
    - [Fetching Categories](#fetching-categories)
- [Custom Field Groups on Category Groups](#custom-field-groups-on-category-groups)
- [Entry Groups and Entry Types](#entry-groups-and-entry-types)
    - [Seeded Entry Groups and Types](#seeded-entry-groups-and-types)
    - [Available Entry Type Classes](#available-entry-type-classes)
    - [Registry Resolution and Admin Constraints](#registry-resolution-and-admin-constraints)
    - [Field Layering: Group Fields + Type Fields](#field-layering-group-fields--type-fields)
    - [Lifecycle Hook Signatures](#lifecycle-hook-signatures)
    - [Creating an Entry Group](#creating-an-entry-group)
    - [Creating an Entry Type Class](#creating-an-entry-type-class)
    - [Registering the Entry Type in the Database](#registering-the-entry-type-in-the-database)
- [Adding a New Entry Type End-to-End](#adding-a-new-entry-type-end-to-end)
    - [1. Create fields and a FieldGroup](#1-create-fields-and-a-fieldgroup)
    - [2. Create FieldLayouts](#2-create-fieldlayouts)
    - [3. Create the EntryGroup](#3-create-the-entrygroup)
    - [4. Write the EntryType PHP classes](#4-write-the-entrytype-php-classes)
    - [5. Register the EntryType rows](#5-register-the-entrytype-rows)
    - [6. Validate and create entries](#6-validate-and-create-entries)
- [Creating and Updating Entries](#creating-and-updating-entries)
    - [Creating an Entry](#creating-an-entry)
    - [Updating an Entry](#updating-an-entry)
    - [Using the Relationship Field](#using-the-relationship-field)
- [Querying Entries](#querying-entries)
    - [Full `EntryQueryBuilder` surface](#full-entryquerybuilder-surface)
    - [Reading Field Values](#reading-field-values)
    - [Accessing Entry Authors](#accessing-entry-authors)
- [Accessing Entry Categories via the Content Facade](#accessing-entry-categories-via-the-content-facade)
    - [Reading categories on a result set](#reading-categories-on-a-result-set)
    - [Loading a category's group on already-fetched entries](#loading-a-categorys-group-on-already-fetched-entries)
    - [Filtering entries by category](#filtering-entries-by-category)
    - [Accessing category field values](#accessing-category-field-values)
- [Entry Metrics](#entry-metrics)
- [Deleting Entries](#deleting-entries)
- [Media Library](#media-library)
    - [Libraries](#libraries)
    - [Uploads](#uploads)
    - [Attachments and Field Usage](#attachments-and-field-usage)
    - [Media Picker Endpoint](#media-picker-endpoint)
    - [Categories, Fields, Transformations, and Cleanup](#categories-fields-transformations-and-cleanup)
- [Site Routing (Public-Facing URLs)](#site-routing-public-facing-urls)
    - [Frontend Catch-All Route](#frontend-catch-all-route)
    - [RouteResult](#routeresult)
    - [Entry Tree Layer](#entry-tree-layer)
    - [EntryTree Driver](#entrytree-driver)
    - [Template Driver](#template-driver)
    - [Configuring Driver Priority](#configuring-driver-priority)
- [Template and View Stack](#template-and-view-stack)
- [API Layer](#api-layer)
    - [API Routes](#api-routes)
    - [API Resources and Current Limitations](#api-resources-and-current-limitations)
    - [API Request/Response Logging](#api-requestresponse-logging)
- [Admin Route Map](#admin-route-map)
- [Validation Strategy](#validation-strategy)
- [Bot Blocking, Webhooks, and External Integrations](#bot-blocking-webhooks-and-external-integrations)
- [Known Gaps and Implementation Status](#known-gaps-and-implementation-status)
- [Key Data Flow Summary](#key-data-flow-summary)
    - [Write path (entry creation)](#write-path-entry-creation)
    - [Read path (entry query)](#read-path-entry-query)
    - [Field value storage](#field-value-storage)
    - [Morph map aliases at a glance](#morph-map-aliases-at-a-glance)
- [Media Status Governance](#media-status-governance)
- [HasStatus and the Status Sync Registry](#hasstatus-and-the-status-sync-registry)
- [Technical Tutorials](#technical-tutorials)
    - [Adding Custom Fields to Any Model](#adding-custom-fields-to-any-model)
    - [How the Field Layer Works](#how-the-field-layer-works)
    - [Step 1 - Add a Morph Map Alias](#step-1---add-a-morph-map-alias)
    - [Step 2 - Add the Fieldable Trait to the Model](#step-2---add-the-fieldable-trait-to-the-model)
    - [Step 3 - Create Fields and a Field Layout](#step-3---create-fields-and-a-field-layout)
    - [Step 4 - Write Field Values](#step-4---write-field-values)
    - [Step 5 - Read Field Values](#step-5---read-field-values)
    - [Step 6 - Attach Field Groups to the Owning Configuration Model](#step-6---attach-field-groups-to-the-owning-configuration-model)
    - [Morph Map Note](#morph-map-note)
- [Recommendations & Remedies](#recommendations--remedies)
- [Ambivalences for Review](#ambivalences-for-review)
- [Accuracy Notes Log](#accuracy-notes-log)

---

## Architecture at a Glance

This is an **ExpressionEngine-inspired headless CMS** built on Laravel 12. The
core philosophy: all content structure is admin-defined at runtime. Entry types
are backed by concrete PHP classes through the **EntryBehavior registry**
(see [Accuracy Note] below); when no behavior is configured the registry falls
back to `GeneralEntryType`. Everything else (fields, layouts, statuses,
categories) is database-driven.

> **[Accuracy Note: EntryType binding column].** Prior copy of this document
> said `entry_types` had a `class` column storing a fully-qualified class
> name. **It does not.** `entry_types.entry_behavior_id` is a foreign key to
> the `entry_behaviors` table. `entry_behaviors.class` stores a **morph
> alias** (e.g. `behavior.blog-post`) registered in
> `AppServiceProvider::boot()` and resolved through `Relation::getMorphedModel()`.
> See [Entry Groups and Entry Types](#entry-groups-and-entry-types) for the
> corrected flow.

```
FieldType          — system-level type registry. 23 types are seeded
                     (Text, Textarea, Html, Number, Date, EmailAddress, Url,
                      Telephone, ColorPicker, Relationship, Boolean, FileUpload,
                      Media, Select, MultiSelect, RadioGroup, Slider, Users,
                      StructuredRows, Money, Country, StateProvince, Time)
                     Each row stores the FQCN in `field_types.object`.
  └── Field        — admin-created field instances with settings (handle, label,
                     instructions, hidden, JSON settings)
        └── FieldGroup — reusable bundles of fields, attached to anything that
                         uses HasFieldGroups (EntryGroup, CategoryGroup,
                         UserSchema, Media\Library) via the polymorphic
                         field_groupables pivot

StatusGroup
  └── Status       — named statuses with handle, color, is_default, is_public.
                     Seeded groups: `publication`, `job-status`, `product-status`.

CategoryGroup     — owns a FieldLayout (HasFieldLayout) and FieldGroups
  └── Category    — hierarchical tree; uses Fieldable for custom values

FieldLayout
  └── Tab (field_layout_tabs)
        └── TabElement (field_layout_tab_elements) → Field

EntryGroup        — owns a FieldLayout, a StatusGroup, plus polymorphic
                    CategoryGroups and FieldGroups
  └── EntryType   — Schema: name, handle, entry_behavior_id (FK),
                    default_template, default_schema_type (SEO),
                    has_entry_tree, max_depth, allowed_parent_types,
                    field_layout_id (optional override), sort_order
        │   The PHP behaviour comes from the joined EntryBehavior row whose
        │   `class` column is a morph alias.
        └── Entry — title, handle, status_id + status_handle + status_is_public,
                    published_at, schema_type, created_by_user_id
              ├── FieldValue        — scalar custom field data (polymorphic)
              ├── EntryRelationship — relational field data (M2M to other Entries)
              ├── EntryAuthor (entry_authors) + entry_author_entry pivot
              │                   — explicit eligibility registry; only promoted
              │                     users appear in author pickers; pivot stores sort_order
              ├── categories        — polymorphic M2M (categorizables)
              └── EntryTree         — optional hierarchical URI tree node

Media\Library     — native upload container (adapter, allowed types, max size).
                    Owns polymorphic CategoryGroups, FieldGroups, and optional
                    FieldLayout.
  └── Media       — native file record with Fieldable, transformations,
                    categories() morphToMany, storage helpers, and soft deletes

UserSchema        — singleton (id=1) that owns a single FieldLayout and
                    one or more FieldGroups for ALL users
  └── User        — uses Fieldable, HasRoles (Spatie),
                    HasApiTokens (Sanctum), TwoFactorAuthenticatable (Fortify),
                    Notifiable; OAuth tokens via OauthToken HasMany;
                    account access controlled by five status values
                    (active / inactive / pending / suspended / banned)
                    plus a parallel locked_until column; status changes
                    audited in UserStatusLog (user_status_logs)
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

The `DatabaseSeeder` runs in this order (twelve always run; three more run
only in `local`/`testing`):

1. `RolesPermissionsSeeder` — permissions + 3 roles (`super admin`, `admin`, `user`)
2. `UsersSeeder` — seeds a single **super-admin** user (Eric Lamb,
   `eric@mithra62.com`, password `password`)
3. `EntryBehaviorSeeder` — 11 behaviour rows that bind `entry_types` to PHP
   classes via morph alias (must run before any `entry_types` row is created)
4. `FieldTypeSeeder` — **23 field type rows** (see [Built-in Types](#built-in-types))
5. `MediaLibrarySeeder` — `avatars` library used by `User::avatar()`, plus
   any other seeded libraries
6. `StatusGroupSeeder` — three status groups: `publication`
   (`draft`, `published`, `archived`), `job-status`
   (`draft`, `published`, `expired`, `closed`), and `product-status`
   (`draft`, `published`, `out-of-stock`, `pre-order`, `discontinued`)
7. `CategoryGroupSeeder` — category groups + categories
8. `FieldGroupSeeder` — field groups + fields (`content-fields`, `seo-fields`,
   `relationship-fields`, plus per-group field bundles)
9. `EntryGroupSeeder` — `blog` and `products` entry groups, layouts, and types
10. `ExtendedEntryGroupSeeder` — `events`, `news`, `pages`, `jobs`, `podcast`,
    `portfolio`, `videos`, `recipes`, plus a fallback `general` entry group
11. `UserSchemaSeeder` — user profile schema (Profile and Bio tabs, fields like
    `first_name`, `last_name`, `gender`, `date_of_birth`, `website`, `bio`,
    `social_twitter`, `social_linkedin`)
12. `SettingsDomainSeeder` — settings domains and system-level default values

Local/testing only:

13. `EntrySeeder` — sample blog posts and products
14. `SandboxedEntryTreeSeeder` — sample entry-tree nodes for routing tests
15. `SampleApiTokenSeeder` — sample Sanctum tokens

> **[Accuracy Note: seeder list].** Prior copy listed 10 seeders and missed
> `EntryBehaviorSeeder`, `MediaLibrarySeeder`, `SandboxedEntryTreeSeeder`,
> and `SampleApiTokenSeeder`. Verified against
> `database/seeders/DatabaseSeeder.php`.

---

## Operational Commands and Deployment Notes

Useful commands:

```bash
composer install
npm install
php artisan migrate --force
php artisan db:seed
php artisan storage:link
php artisan app:validate-class-references
php artisan l5-swagger:generate
npm run build
composer test
```

`composer run dev` starts three local processes through `concurrently`:
Laravel's dev server, `queue:listen --tries=1`, and Vite.

Production deployments should run the Laravel scheduler every minute so the
configured `ApiLog` prune job can execute at 02:00:

```cron
* * * * * cd /var/www/html && php artisan schedule:run >> /dev/null 2>&1
```

For queue-backed features, run a queue worker appropriate for the target
environment. The included `PruneApiLogs` job only runs if it is explicitly
dispatched; the scheduler-based prune is the configured default.

`app:validate-class-references` checks class-name strings stored in the
database. It iterates `entry_behaviors.class` (morph alias keys) — resolving
each via `Relation::getMorphedModel()` and verifying the resulting class
extends `AbstractEntryType` — and then iterates `field_types.object`
(FQCN strings) verifying each class extends `AbstractField`. Exits with
`FAILURE` if any reference is broken.

> **[Accuracy Note: validate command].** Prior copy claimed the command
> checks `entry_types.class`. That column does not exist. The command
> actually checks `entry_behaviors.class` (morph aliases) and
> `field_types.object` (FQCNs). Verified against
> `app/Console/Commands/ValidateClassReferences.php`.

---

## Testing Strategy

Tests are split into PHPUnit Unit and Feature suites in `phpunit.xml`.
The test environment forces `APP_ENV=testing`, uses `database/testing.sqlite`,
sets queues to `sync`, sessions/cache to array drivers, and lowers bcrypt
rounds for speed.

Run the suite with:

```bash
composer test
```

Coverage currently includes:

- Action classes for users, roles, entries, field groups, field layouts,
  categories, statuses, settings, and media libraries.
- Entry type classes and `EntryTypeRegistry`.
- Models for entries, entry trees, metrics, fields, field values, settings,
  OAuth tokens, media, categories, statuses, users, roles, and `EntryAuthor`
  (scopes, `display_name` accessor, relationships).
- Services for entries, users, category groups, entry groups/types, settings,
  site routing, and `EntryAuthorService` (`promote`, `demote`, `sync`,
  `getEligible`, `findByUser`).
- `User` model — `entryAuthor()` HasOne relation and `isAuthorEligible()` helper.
- `UserService` author eligibility paths — `is_author` / `author_display_name`
  keys in `create()` and `update()`.
- Middleware tests for API logging.
- Feature tests for settings, entry status validation, and entry type hooks.
- Feature tests for user account status: `canAccessSystem()` model assertions for all five status values, expired suspension and lock auto-expiry, and `EnforceUserStatus` middleware logout/redirect behaviour (`tests/Feature/LoginTest.php`).

---

## System Health and Data Integrity

```bash
php artisan app:validate-class-references
```

Checks that every class-name string stored in the database still resolves
to a real class. Iterates `entry_behaviors.class` (each row holds a morph
alias such as `behavior.blog-post`) — resolves via `Relation::getMorphedModel()`
and verifies the result extends `AbstractEntryType` — then iterates
`field_types.object` (FQCN strings) and verifies each extends
`AbstractField`. Exits with `FAILURE` if any reference is broken — wire
into CI before deploys.

Polymorphic stability via **Eloquent Morph Maps** in `AppServiceProvider::boot()`:

```
// Polymorphic model aliases (used by *_type morph columns)
'entry' => Entry::class
'entry_group' => EntryGroup::class
'entry_type' => EntryType::class
'category' => Category::class
'category_group' => CategoryGroup::class
'field_group' => FieldGroup::class
'media' => Media::class
'media_library' => MediaLibrary::class
'user' => User::class

// EntryBehavior class aliases (stored in entry_behaviors.class)
'behavior.general' => GeneralEntryType::class
'behavior.blog-post' => BlogPostEntryType::class
'behavior.product' => ProductEntryType::class
'behavior.page' => PageEntryType::class
'behavior.event' => EventEntryType::class
'behavior.job-listing' => JobListingEntryType::class
'behavior.news-article' => NewsArticleEntryType::class
'behavior.podcast-episode' => PodcastEpisodeEntryType::class
'behavior.portfolio-item' => PortfolioItemEntryType::class
'behavior.recipe' => RecipeEntryType::class
'behavior.video' => VideoEntryType::class
```

Always rely on `$model->getMorphClass()` for new writes to polymorphic
columns. The `behavior.*` aliases are a separate lookup table used only by
`EntryBehavior::instance()` (see [Entry Groups and Entry Types](#entry-groups-and-entry-types)).

> **[Accuracy Note: morph map].** Prior copy omitted the `behavior.*`
> entries entirely. Verified against `app/Providers/AppServiceProvider.php`.

---

## Users, Roles, and Permissions

The system uses **Spatie Permission** (`spatie/laravel-permission`) with the
`HasRoles` trait on `User`. The `User` model also pulls in `Notifiable`,
`HasApiTokens` (Sanctum), `TwoFactorAuthenticatable` (Fortify), and the
project's `Fieldable` trait so
users can store custom field values polymorphically.

### Built-in Roles

| Role          | Access                                                                                                                 |
|---------------|------------------------------------------------------------------------------------------------------------------------|
| `super admin` | Everything — bypasses all permission checks via `Gate::before`                                                         |
| `admin`       | Admin panel + seeded CRUD permissions for users, user tokens, roles, categories, entries, fields, field layouts, statuses, media libraries, settings, plus the `api` permission |
| `user`        | Admin panel access only (`access admin` permission only)                                                               |

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

create role / edit role / delete role

create entry group / edit entry group / delete entry group
create entry type / edit entry type / delete entry type
create entry / edit entry / delete entry

create field group / edit field group / delete field group
create field / edit field / delete field
create field layout / edit field layout / delete field layout

create status / edit status / delete status

manage user status

edit setting
```

The seeded `admin` role receives every permission above. The seeded `user` role
receives only `access admin`. The `super admin` role bypasses all checks via
`Gate::before`, so it does not need explicit permission assignments.

### Creating Users Programmatically

```php
use App\Facades\Users;

$user = Users::create([
    'name' => 'Jane Doe',
    'email' => 'jane@example.com',
    'password' => 'secret-passphrase', // hashed by UserService
    'roles' => ['admin'],
    'fields' => [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
    ],
]);
```

If you need raw Eloquent:

```php
use App\Models\User;
use Illuminate\Support\Facades\Hash;

$user = User::create([
    'name' => 'Jane Doe',
    'email' => 'jane@example.com',
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
    'name' => 'publish entry',
    'description' => 'Allows user to set entries to published status',
]);

$role = Role::create(['name' => 'editor']);
$role->givePermissionTo(['access admin', 'publish entry']);

// Or give a permission directly to a user
$user->givePermissionTo('publish entry');
```

---

## Adding New Permissions

Core admin permissions are seeded in `RolesPermissionsSeeder`. Add a new
permission only for behavior that is not already covered by the built-in list,
such as workflow-specific capabilities.

```php
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

// php artisan db:seed --class=WorkflowPermissionsSeeder
class WorkflowPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['name' => 'publish entry', 'description' => 'Set entries to published status'],
            ['name' => 'archive entry', 'description' => 'Move entries to archived status'],
        ];

        foreach ($permissions as $p) {
            Permission::firstOrCreate(['name' => $p['name']], $p);
        }

        Role::findByName('admin')->givePermissionTo([
            'publish entry', 'archive entry',
        ]);

        $editor = Role::firstOrCreate(['name' => 'editor']);
        $editor->givePermissionTo([
            'access admin', 'create entry', 'edit entry', 'publish entry', 'archive entry',
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

## User Account Status

### Status Values

The `User` model carries a `status` column with five administrative values
defined in `App\Enums\UserStatus`:

| Value       | Constant                | Meaning                                                                    |
|-------------|-------------------------|----------------------------------------------------------------------------|
| `active`    | `UserStatus::ACTIVE`    | Full access                                                                |
| `inactive`  | `UserStatus::INACTIVE`  | Disabled by an admin; cannot log in                                        |
| `pending`   | `UserStatus::PENDING`   | Awaiting approval; cannot log in                                           |
| `suspended` | `UserStatus::SUSPENDED` | Time-limited block; lifts automatically when `suspended_until` passes      |
| `banned`    | `UserStatus::BANNED`    | Permanent block; `banned_at` timestamp is recorded                         |

Status is **separate from roles**. Roles define what an active user may do;
status governs whether they can enter the system at all. The default status for
newly created users is driven by the `users.default_status` setting (fallback:
`active`). Social-login registrations use `users.social_default_status`
(fallback: `pending`).

`UserStatus::ALL` contains every valid value and is used with
`Rule::in(UserStatus::ALL)` in validation. `UserStatus::CREATION_ALLOWED`
(`active`, `inactive`, `pending`) lists values permitted at creation time;
`suspended` and `banned` are post-creation admin actions only.

### Parallel Lock Column

`locked_until` is a nullable datetime column orthogonal to status. An `active`
user with a future `locked_until` cannot access the system. The lock expires
automatically at runtime — no cron or scheduler is needed.

| Column            | Purpose                                                           |
|-------------------|-------------------------------------------------------------------|
| `status`          | Administrative state (`UserStatus::ALL`)                          |
| `suspended_until` | Set by `suspend()`; checked at runtime for auto-expiry            |
| `banned_at`       | Timestamp set when status becomes `banned`, cleared otherwise     |
| `locked_until`    | Parallel lock independent of status; checked on every request     |

### Model Helpers

```php
$user->canAccessSystem();    // true if active + not locked (auto-expires)
$user->isLocked();           // true if locked_until is in the future
$user->isSuspended();        // true if suspended and suspended_until is future
$user->statusLabel();        // human-readable label e.g. "Pending Approval"
$user->statusColour();       // Tailwind token: 'emerald', 'amber', 'orange'…
$user->accessDeniedReason(); // lang key string, or null if access is allowed
$user->statusLogs();         // HasMany → UserStatusLog, newest-first
```

`canAccessSystem()` handles suspension auto-expiry inline: a suspended user
whose `suspended_until` has passed is treated as active without any database
write. The column is cleaned up on the next explicit status change.

### Authentication Gate

`FortifyServiceProvider` registers an `authenticateUsing` callback that calls
`canAccessSystem()` before completing login. Non-active users receive a
`ValidationException` with a translated error key from `lang/en/auth.php` —
they are never authenticated and no session is created.

```
auth.account_inactive   — inactive
auth.account_pending    — pending
auth.account_suspended  — suspended (within window)
auth.account_banned     — banned
auth.account_locked     — locked (regardless of status)
```

### Access-Enforcement Middleware

Two middleware classes enforce status on every request after login:

| Middleware             | Stack | Blocked response                                    |
|------------------------|-------|-----------------------------------------------------|
| `EnforceUserStatus`    | `web` | Logout + redirect to login with error flash         |
| `EnforceUserStatusApi` | `api` | `403 JSON`; stateful fallback: logout + redirect    |

Both are appended in `bootstrap/app.php` and fire on every request, so a
status change takes effect immediately on the user's next page load or API call.

### Status Change Events and Audit Log

Every status or lock change fires an event caught by `WriteUserStatusLog`,
which records a row in `user_status_logs`:

| Event               | Fired by                                        |
|---------------------|-------------------------------------------------|
| `UserStatusChanged` | `UserService::setStatus()`, `UserService::suspend()` |
| `UserLockChanged`   | `UserService::lockUser()`, `UserService::unlockUser()` |

`user_status_logs` stores `user_id`, `changed_by_user_id`, `previous_status`,
`new_status`, `previous_locked_until`, `new_locked_until`, `reason`, `context`
(JSON — e.g. `suspended_until` timestamp), and `created_at`. The model is
`App\Models\UserStatusLog` with `actor()` (the admin who made the change) and
`user()` belongs-to relations.

### Admin UI

The user `show` view (`resources/views/admin/users/show.twig`) includes a
**Status & Access** card visible only to users with the `manage user status`
permission. It provides:

- Current status badge with suspension expiry or lock-expiry details.
- A status `<select>` that dynamically reveals a reason textarea and a
  "Suspended Until" datetime picker (with one-click presets: +1h, +24h, +3d,
  +7d, +30d) when "Suspended" is chosen. The reason field is required for all
  non-active statuses.
- A "Remove Account Lock" section — only shown when the account is locked —
  with a DELETE form that calls `users.lock.destroy` immediately.
- A status history log of the 10 most recent `user_status_logs` entries.

Admin routes:

| Method   | URL                          | Route name            | Action                    |
|----------|------------------------------|-----------------------|---------------------------|
| `PATCH`  | `/admin/users/{id}/status`   | `users.status.update` | Change status / suspend   |
| `DELETE` | `/admin/users/{id}/lock`     | `users.lock.destroy`  | Remove lock immediately   |

Both routes are handled by `App\Http\Controllers\Admin\UserStatusController`
and gated on the `manage user status` permission via
`App\Http\Requests\User\UserStatusRequest`.

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

| FieldGroup   | `handle`       | Fields                                                          |
|--------------|----------------|-----------------------------------------------------------------|
| User Profile | `user-profile` | `first_name`, `last_name`, `gender`, `date_of_birth`, `website` |
| User Bio     | `user-bio`     | `bio`, `social_twitter`, `social_linkedin`                      |

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

$firstName = Field::firstOrCreate(
    ['handle' => 'first_name'],
    [
        'field_type_id' => $text->id,
        'name' => 'First Name',
        'label' => 'First Name',
    ]
);

$lastName = Field::firstOrCreate(
    ['handle' => 'last_name'],
    [
        'field_type_id' => $text->id,
        'name' => 'Last Name',
        'label' => 'Last Name',
    ]
);

$group = FieldGroup::firstOrCreate(
    ['handle' => 'user-profile'],
    ['name' => 'User Profile']
);

$group->fields()->syncWithoutDetaching([$firstName->id, $lastName->id]);

$layout = FieldLayout::create(['name' => 'User Profile Layout']);
$tab = Tab::create(['field_layout_id' => $layout->id, 'name' => 'Profile', 'sort_order' => 1]);

foreach ([$firstName, $lastName] as $i => $field) {
    TabElement::create([
        'field_layout_tab_id' => $tab->id,
        'field_id' => $field->id,
        'required' => false,
        'sort_order' => $i + 1,
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
    'last_name' => 'Doe',
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
        'field_id' => $field->id,
        'fieldable_id' => $user->getKey(),
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
        'name' => $user->name,
        'first_name' => $user->field('first_name'),
        'last_name' => $user->field('last_name'),
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

|                 | Entries                                                | Users                                 |
|-----------------|--------------------------------------------------------|---------------------------------------|
| Write API       | `Content::create()` / `Content::update()`              | `Users::create()` / `Users::update()` |
| Read API        | `$entry->field('handle')`                              | `$user->field('handle')`              |
| Schema          | Per-group FieldLayout + per-type FieldLayout (merged)  | Single `UserSchema` singleton         |
| Lifecycle hooks | `beforeCreate`, `afterCreate`, etc. on EntryType class | None                                  |
| Custom fields   | Scalar + Relational                                    | **Scalar only**                       |

---

## Author Eligibility

Not every user can be assigned as an entry author. The **Author Eligibility Layer** decouples the concept of "a user who may write content" from the plain `users` table. A user becomes eligible for entry author pickers only when an `EntryAuthor` record exists for them with `status = 'active'`. This record is the single source of truth; it is never inferred from roles or any other attribute.

### Schema

Two tables implement the layer:

| Table               | Purpose                                                                                   |
|---------------------|-------------------------------------------------------------------------------------------|
| `entry_authors`     | **Eligibility registry.** One row per eligible user. Columns: `user_id` (unique FK), `display_name` (nullable), `status` (`active` / `pending` / `disabled`). |
| `entry_author_entry`| **Pivot.** Links entries to their assigned authors. Columns: `entry_id`, `entry_author_id`, `sort_order`, timestamps. |

`entry_authors` is intentionally separate from `users`. Demoting a user sets `status = 'disabled'` but preserves the row (and all existing entry assignments) so historical data is never orphaned.

The `EntryAuthor` model (`App\Models\EntryAuthor`) provides three query scopes:

```php
EntryAuthor::active()->get();    // status = 'active'
EntryAuthor::pending()->get();   // status = 'pending'
EntryAuthor::disabled()->get();  // status = 'disabled'
```

The `display_name` accessor falls back gracefully:

```
EntryAuthor::display_name → explicit stored value → user->name → ''
```

### EntryAuthorService and EntryAuthors Facade

`App\Services\EntryAuthorService` is the only place that writes to `entry_authors`. Inject it or use the `EntryAuthors` facade (`App\Facades\EntryAuthors`):

| Method | Signature | Description |
|---|---|---|
| `getEligible()` | `(): Collection` | All active records with `user` eager-loaded, ordered by `display_name`. **The only source entry author pickers should ever read from.** |
| `findByUser()` | `(User $user): ?EntryAuthor` | Look up the eligibility record for a user regardless of status. |
| `promote()` | `(User $user, ?string $displayName = null): EntryAuthor` | Create or reactivate the record. Pass `null` to leave an existing `display_name` untouched; pass `''` to clear it. |
| `demote()` | `(User $user): void` | Set `status = 'disabled'`. Does not delete the record or touch existing entry assignments. |
| `sync()` | `(User $user, bool $eligible, ?string $displayName = null): ?EntryAuthor` | Idempotent upsert — calls `promote()` when `$eligible` is `true`, `demote()` otherwise. |

### Promoting and Demoting Authors

```php
use App\Facades\EntryAuthors;

// Promote a user (creates the record if needed, or reactivates it)
$entryAuthor = EntryAuthors::promote($user);

// Promote with a display name override
$entryAuthor = EntryAuthors::promote($user, 'Jane Doe, Staff Writer');

// Demote (disables but does not delete)
EntryAuthors::demote($user);

// Idempotent sync — use this from admin forms
EntryAuthors::sync($user, eligible: true, displayName: 'Pen Name');
EntryAuthors::sync($user, eligible: false);

// Check eligibility on a User instance
$user->load('entryAuthor');
$user->isAuthorEligible(); // true only when status === 'active'
```

The seeder promotes `eric@mithra62.com` automatically:

```php
// UsersSeeder
$user->assignRole('super admin');
app(EntryAuthorService::class)->promote($user);
```

### Author Eligibility via UserService

`UserService::create()` and `UserService::update()` accept two optional keys that are **stripped from the user attributes** and delegated to `EntryAuthorService::sync()`:

| Key | Type | Behaviour |
|---|---|---|
| `is_author` | `bool` | When `true`, calls `promote()`; when `false`, calls `demote()`. Absent = no-op. |
| `author_display_name` | `?string` | Passed as the display name to `promote()`. Only used when `is_author` is also present. |

```php
use App\Facades\Users;

// Create a user and immediately promote them as an author
$user = Users::create([
    'name' => 'Jane Doe',
    'email' => 'jane@example.com',
    'password' => 'secret',
    'is_author' => true,
    'author_display_name' => 'J. Doe',
]);

// Update an existing user, removing author eligibility
Users::update($user, ['is_author' => false]);

// Update name without touching eligibility (key absent = no sync)
Users::update($user, ['name' => 'Jane Smith']);
```

The admin Create User and Edit User forms wire these keys through their Twig views and `StoreUserRequest` / `EditUserRequest` validation rules.

### Querying Eligible Authors

The admin entry form (Create / Edit) populates its author picker by calling `EntryAuthors::getEligible()`:

```php
use App\Facades\EntryAuthors;

$authors = EntryAuthors::getEligible();
// Returns: Collection of EntryAuthor, only status='active', with user eager-loaded
// Ordered by display_name ASC

foreach ($authors as $author) {
    echo $author->user_id;       // FK back to users
    echo $author->display_name;  // falls back to user->name
}
```

Entry author assignment passes **user IDs** (not `entry_author_id` values) through request validation. `EntryRepository::syncAuthors()` resolves them to active `EntryAuthor` IDs before writing to the `entry_author_entry` pivot — this is the **double-gate**: a user who has been demoted between page-load and form submission is silently dropped from the sync.

Validation rule in `StoreEntryRequest` / `EditEntryRequest`:

```php
'authors.*' => ['integer', Rule::exists('entry_authors', 'user_id')->where('status', 'active')],
```

---

## UserService and the Users Facade

### CRUD

```php
use App\Facades\Users;

$user = Users::create([
    'name' => 'Jane Doe',
    'email' => 'jane@example.com',
    'password' => 'secret',
    'roles' => ['admin'],
    'fields' => ['first_name' => 'Jane', 'last_name' => 'Doe'],
    // Optional author eligibility keys (stripped before User::create()):
    'is_author' => true,
    'author_display_name' => 'J. Doe',
]);

$user = Users::update($user, [
    'name' => 'Jane Smith',
    'roles' => ['user'],
    'fields' => ['last_name' => 'Smith'],
    // Optional — omit to leave eligibility unchanged:
    'is_author' => false,
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
    'current_password' => 'oldpassword',
    'password' => 'newpassword123',
    'password_confirmation' => 'newpassword123',
]);
```

### Status Management

```php
use App\Enums\UserStatus;

// Set any non-suspension status; manages banned_at automatically
Users::setStatus($user, UserStatus::INACTIVE, 'Account deactivated');
Users::setStatus($user, UserStatus::BANNED, 'Violated terms of service');
Users::setStatus($user, UserStatus::ACTIVE);   // reason optional for active

// Suspend for a fixed window
Users::suspend($user, new DateTime('+7 days'), 'Repeated spam posts');

// Lock account temporarily (parallel to status — does not change status)
Users::lockUser($user, new DateTime('+30 minutes'), 'Too many failed logins');
Users::unlockUser($user);                          // clear lock immediately
```

`setStatus()` fires `UserStatusChanged` and keeps `banned_at` and
`suspended_until` in sync automatically. `suspend()` fires `UserStatusChanged`
with `context['suspended_until']`. `lockUser()` / `unlockUser()` fire
`UserLockChanged`. All four methods write to `user_status_logs` via the
`WriteUserStatusLog` listener registered in `AppServiceProvider`.

Status changes made through `UserService::update()` are intentionally ignored —
the `status`, `suspended_until`, `banned_at`, and `locked_until` keys are
stripped from the update payload. Always use the dedicated status methods above.

### Two-Factor Authentication

```php
$setup = Users::enableTwoFactor($user);
// $setup['qr_code_svg'], $setup['secret']

Users::confirmTwoFactor($user, '123456'); // throws ValidationException if wrong
Users::hasTwoFactor($user);              // true after confirmation

$codes = Users::getRecoveryCodes($user);
$newCodes = Users::regenerateRecoveryCodes($user);
Users::disableTwoFactor($user);
```

### OAuth Token Management

```php
$token = Users::upsertOauthToken($user, 'google', [
    'access_token' => 'ya29.xxx',
    'refresh_token' => '1//xxx',
    'expires_at' => now()->addHour(),
    'scopes' => ['email', 'profile'],
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

| Class                       | Method                                                                                          |
|-----------------------------|-------------------------------------------------------------------------------------------------|
| `CreateNewEntry`            | `create(array $input): Entry` — reads `$input['type_handle']`, delegates to `Content::create()` |
| `UpdateEntry`               | `update(Entry $entry, array $input): Entry`                                                     |
| `Group/CreateNewEntryGroup` | `create(array $input): EntryGroup`                                                              |
| `Group/EditEntryGroup`      | `edit(EntryGroup $group, array $input): EntryGroup`                                             |
| `Type/CreateNewEntryType`   | `create(string\|int $groupId, array $input): EntryType`                                        |
| `Type/EditEntryType`        | `edit(EntryType $type, array $input): EntryType`                                                |
| `Tree/CreateEntryTreeNode`  | `create(Entry $entry, string $handle, ?EntryTree $parent = null, ?string $template = null, bool $isHome = false): EntryTree` |
| `Tree/MoveEntryTreeNode`    | `handle(EntryTree $node, ?EntryTree $newParent, int $sortOrder = 0): EntryTree`                 |
| `Tree/RebuildEntryTreeUri`  | `handle(EntryTree $node): void`                                                                 |
| `RecordEntryMetric`         | `record(Entry $entry, string $metric, int $value = 1, ?Carbon $date = null): EntryMetric`       |

**Category** (`app/Actions/Category/`)

| Class                          | Method                                                 |
|--------------------------------|--------------------------------------------------------|
| `CreateNewCategory`            | `create(array $input): Category`                       |
| `EditCategory`                 | `edit(Category $category, array $input): Category`     |
| `Group/CreateNewCategoryGroup` | `create(array $input): CategoryGroup`                  |
| `Group/EditCategoryGroup`      | `edit(CategoryGroup $group, array $input): bool`       |

**Field** (`app/Actions/Field/`)

| Class                       | Method                                        |
|-----------------------------|-----------------------------------------------|
| `CreateNewField`            | `create(array $input): Field`; `createByGroup(array $input): Field` |
| `EditField`                 | `edit(Field $field, array $input): bool`      |
| `Group/CreateNewFieldGroup` | `create(array $input): FieldGroup`            |
| `Group/EditFieldGroup`      | `edit(FieldGroup $group, array $input): bool` |

**FieldLayout** (`app/Actions/FieldLayout/`)

| Class                          | Method                                           |
|--------------------------------|--------------------------------------------------|
| `CreateNewFieldLayout`         | `create(array $input): FieldLayout`              |
| `EditFieldLayout`              | `edit(FieldLayout $layout, array $input): FieldLayout` |
| `DeleteFieldLayout`            | `delete(FieldLayout $layout): bool`              |
| `Tab/CreateNewTab`             | `create(FieldLayout $layout, array $input): Tab` |
| `Tab/EditTab`                  | `edit(Tab $tab, array $input): Tab`              |
| `Tab/DeleteTab`                | `delete(Tab $tab): bool`                         |
| `Tab/Element/CreateTabElement` | `create(Tab $tab, array $input): TabElement`     |
| `Tab/Element/EditTabElement`   | `edit(TabElement $element, array $input): TabElement` |
| `Tab/Element/DeleteTabElement` | `delete(TabElement $element): bool`              |

**Media** (`app/Actions/Media/Library/`)

| Class                   | Method                                                                                   |
|-------------------------|------------------------------------------------------------------------------------------|
| `CreateNewMediaLibrary` | `create(array $input): Library` — attaches `$input['category_groups']`                   |
| `EditMediaLibrary`      | `edit(Library $library, array $input): bool` — re-syncs category groups and field groups |
| `DeleteMediaLibrary`    | `delete(Library $library): bool`                                                         |
| `UploadMedia`           | `upload(FormRequest $request, Library $library): Media`                                  |

**Status** (`app/Actions/Status/`)

| Class                        | Method                                             |
|------------------------------|----------------------------------------------------|
| `CreateNewStatus`            | `create(array $input): Status`; `createByGroup(array $input): Status` |
| `EditStatus`                 | `edit(Status $status, array $input): bool`         |
| `Group/CreateNewStatusGroup` | `create(array $input): StatusGroup`                |
| `Group/EditStatusGroup`      | `edit(StatusGroup $group, array $input): bool`     |

**Role** (`app/Actions/Role/`)

| Class           | Method                                 |
|-----------------|----------------------------------------|
| `CreateNewRole` | `create(array $input): Role`           |
| `EditRole`      | `edit(Role $role, array $input): void` |

**User** (`app/Actions/User/`)

| Class                          | Method                                                                   |
|--------------------------------|--------------------------------------------------------------------------|
| `CreateNewUser`                | `create(array $input): User`                                             |
| `UpdateUserProfileInformation` | `update(User $user, array $input): User`                                 |
| `UpdateUserPassword`           | `update(User $user, array $input): void` — verifies current password     |
| `ResetUserPassword`            | `reset(User $user, array $input): void` — no current-password check      |
| `Token/CreateNewUserToken`     | `create(User $user, array $input): NewAccessToken`                       |

**Settings** (`app/Actions/Settings/`)

| Class                  | Method                                      |
|------------------------|---------------------------------------------|
| `UpdateDomainSettings` | `execute(string $handle, array $data): void` |
| `UpdateUserSettings`   | `execute(User $user, array $data): void`     |

---

## OAuth and Social Login

Social login is handled through Laravel Socialite. The `User` model owns
OAuth records through `user_oauth_tokens`, represented by
`App\Models\User\OauthToken`.

`OauthToken` stores provider identity (`provider`, `provider_account`,
`provider_user_id`), OIDC identity (`issuer`, `subject`, `id_token`), token
values (`access_token`, `refresh_token`, `token_type`, `expires_at`), scopes,
metadata, and revocation/usage timestamps.

`TokenRefreshService` refreshes tokens when they are expired or close to
expiry. It requires:

- An active, non-revoked token.
- A stored `refresh_token`.
- Provider values available through `config("oauth_providers.{provider}")`, or
  token metadata containing a token endpoint.
- Provider client ID and client secret.

`tryRefresh()` logs and swallows refresh failures. `refresh()` throws
`TokenRefreshException` on revoked tokens, missing refresh tokens, missing
provider config, HTTP failures, or invalid provider responses.

The `app:refresh-tokens` console command is currently a scaffold with commented
example code. It does not refresh tokens until implementation code is added.

---

## System and User Settings

Settings are config-defined and value-backed. Domain and field definitions live
in `config/settings.php`; persisted values live in `setting_values`; domain
navigation metadata lives in `setting_domains`.

Do not use `App\Models\Settings`. That class is a tombstone and throws at
runtime. Use the `App\Settings` service through constructor injection,
`app(\App\Settings::class)`, or the container alias `app('settings')`.

### Settings Domains

Each top-level key in `config/settings.php` is a domain handle. The current
domains are:

| Domain    | Purpose                                      |
|-----------|----------------------------------------------|
| `general` | Site name, timezone, date/time formats, admin pagination |
| `media`   | Upload size, allowed extensions, image quality |
| `email`   | Outbound sender and reply-to defaults        |
| `content` | Content listing and default entry behavior   |

`SettingsDomainSeeder` creates the `setting_domains` rows and pre-seeds
system-level values for fields with non-null defaults. It uses
`Settings::set()`, so values are written to the correct typed column.

Field definitions support these keys:

| Key                 | Notes                                                                  |
|---------------------|------------------------------------------------------------------------|
| `handle`            | Unique within the domain; stored as `setting_values.field_handle`      |
| `label`             | Admin UI label and validation attribute name                           |
| `type`              | `text`, `integer`, `float`, `boolean`, or `json`                       |
| `default`           | Returned when no stored system or user value exists                    |
| `rules`             | Laravel validation rules; `nullable` is prepended unless `required` exists |
| `instructions`      | Admin UI helper text                                                   |
| `group`             | Optional admin UI grouping label                                       |
| `hidden`            | Hidden fields are excluded from the admin forms                        |
| `user_overridable`  | Allows authenticated users to save a personal override                 |

### Value Storage and Resolution

`setting_values` stores one row per `(domain, field_handle, user_id)`:

```
domain / field_handle / user_id
value_text | value_integer | value_float | value_boolean | value_json
```

`user_id = NULL` means system-level value. A non-null `user_id` means a
per-user override. The `App\Settings` service resolves values in this order:

```
user override -> system value -> config default
```

Typed storage is routed by `Settings::columnFor()`:

| Type      | Column          |
|-----------|-----------------|
| `text`    | `value_text`    |
| `integer` | `value_integer` |
| `float`   | `value_float`   |
| `boolean` | `value_boolean` |
| `json`    | `value_json`    |

System values are cached for one hour under `settings.system.{domain}`. User
overrides are cached for one hour under `settings.user.{userId}.{domain}`.
`set()` and `setMany()` bust only the relevant cache key; `bustDomain()` clears
the system cache and every known user cache for that domain.

### Reading Settings

```php
use App\Settings;

$settings = app(Settings::class);

// Current authenticated user overrides are applied automatically.
$timezone = $settings->get('general', 'timezone', 'UTC');

// Resolve all values for the current user.
$general = $settings->all('general');

// Resolve all values for a specific user.
$generalForUser = $settings->all('general', $user);

// System values only; user overrides are ignored.
$systemGeneral = $settings->system('general');
```

`Settings::get()` accepts a default fallback for unknown handles. For known
handles without stored values, the config field default wins.

### Writing System Settings

System settings are managed under `/admin/settings`:

| Route                 | Purpose                         | Authorization |
|-----------------------|---------------------------------|---------------|
| `GET /admin/settings` | List setting domains            | `access admin` via admin controller |
| `GET /admin/settings/{handle}` | Edit one domain       | `access admin` via admin controller |
| `PUT /admin/settings/{handle}` | Save one domain       | `edit setting` via `UpdateDomainSettingsRequest` |

The request builds validation rules from `config/settings.php`. Boolean fields
are normalised from checkbox presence and skipped during validation; optional
non-boolean fields automatically receive `nullable`.

```php
use App\Actions\Settings\UpdateDomainSettings;

app(UpdateDomainSettings::class)->execute('general', [
    'site_name' => 'Laravel Base',
    'timezone' => 'America/Phoenix',
    'date_format' => 'Y-m-d',
    'time_format' => 'H:i',
    'items_per_page' => 25,
]);
```

Equivalent lower-level service call:

```php
app(\App\Settings::class)->setMany('general', [
    'site_name' => 'Laravel Base',
], user: null);
```

### Writing User Preferences

User preferences are managed under `/admin/settings/user`:

| Route                          | Purpose                         | Authorization |
|--------------------------------|---------------------------------|---------------|
| `GET /admin/settings/user`     | Edit current user's preferences | authenticated admin route |
| `PUT /admin/settings/user`     | Save current user's preferences | authenticated user via `UpdateUserSettingsRequest` |

Only fields with `user_overridable => true` and `hidden => false` are shown,
validated, and written. Submitted values for non-overridable fields are ignored.
Current user-overridable fields are `timezone`, `date_format`, `time_format`,
and `items_per_page` in the `general` domain.

```php
use App\Actions\Settings\UpdateUserSettings;

app(UpdateUserSettings::class)->execute($user, [
    'timezone' => 'America/Phoenix',
    'date_format' => 'm/d/Y',
    'time_format' => 'g:i A',
    'items_per_page' => 50,
]);
```

To write a single override directly:

```php
app(\App\Settings::class)->set('general', 'timezone', 'America/Phoenix', $user);
```

### Adding a Setting

Add a field definition to the appropriate domain in `config/settings.php`.
No migration is required. If the setting needs a pre-seeded system value, give
it a non-null `default` and run:

```bash
php artisan db:seed --class=SettingsDomainSeeder
```

For a new domain, add a new top-level config key with `name`, `description`,
`icon`, `sort_order`, and `fields`, then run the same seeder so the
`setting_domains` row is created. Mark `user_overridable` as `true` only for
settings that should appear on the current user's preferences screen.

---

## Field Types

Field types are PHP classes in `app/Field/Types/` that extend
`AbstractField` (`app/Field/AbstractField.php`). They are registered in the
`field_types` table. Each row stores the **fully-qualified class name** in
`field_types.object`; instantiation goes through `Field\Type::instance()`,
which validates `class_exists()` and `is_subclass_of(AbstractField::class)`.

`AbstractField` methods subclasses can override:

| Method                                 | Purpose                                                                                                     |
|----------------------------------------|-------------------------------------------------------------------------------------------------------------|
| `storageColumn(): string`              | Required. One of `value_text`, `value_integer`, `value_float`, `value_date`, `value_boolean`, `value_json`. |
| `isRelational(): bool`                 | Default `false`. Return `true` to route writes to `entry_relationships`.                                    |
| `cast(mixed $value): mixed`            | Default identity. Convert raw stored value before returning.                                                |
| `validate(mixed $value): bool\|string` | Default `true`. Return error string on failure.                                                             |
| `render(array $params): string`        | Render a Blade partial for the admin form.                                                                  |
| `getRules(): array`                    | Return Laravel validation rules.                                                                            |

### Built-in Types

All 23 types are registered in `database/seeders/FieldTypeSeeder.php`. Each
row stores the **fully-qualified class name** in `field_types.object`.

| Class            | Twig partial                       | `storageColumn()`                | Notes                                                      |
|------------------|------------------------------------|----------------------------------|------------------------------------------------------------|
| `Text`           | `_fields/text.twig`                | `value_text`                     | Single-line input                                          |
| `Textarea`       | `_fields/textarea.twig`            | `value_text`                     | Multi-line                                                 |
| `Html`           | **— no partial — see remedy**      | `value_text`                     | Rich-text; calls `view('_fields.html', $params)` (missing) |
| `Number`         | `_fields/number.twig`              | `value_integer` or `value_float` | Branches on `decimals` setting                             |
| `Date`           | `_fields/date.twig`                | `value_date`                     | Cast as `datetime`; reads return `Carbon`                  |
| `Time`           | `_fields/time.twig`                | `value_text`                     | Time-of-day (`HH:MM` or `HH:MM:SS`); `value()` returns `App\Support\Iso\TimeValue` |
| `EmailAddress`   | `_fields/email.twig`               | `value_text`                     |                                                            |
| `Url`            | `_fields/url.twig`                 | `value_text`                     |                                                            |
| `Telephone`      | `_fields/telephone.twig`           | `value_text`                     |                                                            |
| `ColorPicker`    | `_fields/color_picker.twig`        | `value_text`                     | Hex value                                                  |
| `Boolean`        | `_fields/boolean.twig`             | `value_boolean`                  | Casts reads to `bool`                                      |
| `Relationship`   | `_fields/relationship.twig`        | *(none — relational)*            | `isRelational() === true`; stores in `entry_relationships` |
| `FileUpload`     | `_fields/file_upload.twig`         | `value_json`                     | IDs synced to `mediables` pivot by `FieldValueObserver`    |
| `Media`          | `_fields/media.twig`               | `value_json`                     | Media picker variant                                       |
| `Select`         | `_fields/select.twig`              | `value_text`                     |                                                            |
| `MultiSelect`    | `_fields/multi_select.twig`       | `value_json`                     |                                                            |
| `RadioGroup`     | `_fields/radio_group.twig`         | `value_text`                     |                                                            |
| `Slider`         | `_fields/slider.twig`              | `value_integer` or `value_float` |                                                            |
| `Users`          | `_fields/users.twig`               | `value_json`                     | Picker for user IDs                                        |
| `StructuredRows` | `_fields/structured_rows.twig`     | `value_json`                     | Repeatable rows; columns declared in field settings        |
| `Money`          | `_fields/money.twig`               | `value_integer`                  | Stored as integer minor units; `value()` returns `Money\Money` object; currency from field settings |
| `Country`        | `_fields/country.twig`             | `value_text`                     | ISO 3166-1 alpha-2 country code                            |
| `StateProvince`  | `_fields/state_province.twig`      | `value_text`                     | ISO 3166-2 subdivision code                                |

> **[Accuracy Note: field type catalogue].** Prior copy listed only the
> original 10 types. Verified against `app/Field/Types/*.php` (23 PHP
> classes), `database/seeders/FieldTypeSeeder.php` (23 seeded rows), and
> `resources/views/_fields/*.twig` (22 partials — `html.twig` is missing;
> see [Recommendations & Remedies](#recommendations--remedies) item R-1).

#### Write pipeline at a glance

Understanding which method runs when matters for any field-level
hardening:

```
HTTP POST/PUT
  └── FormRequest
        └── rules() returns merge of static + schemaFieldRules()
              └── For each layout element:
                    'fields.<handle>' => $field->typeInstance()->getRules()
                                       merged with [required] / [nullable]
        └── Laravel validation fires using the merged rules
                                  ↓
                            (on success)
                                  ↓
  └── Controller → Action → Service
        └── EntryRepository / AbstractFieldableRepository
              └── applyFieldValues($model, $fields)
                    For each handle in the submitted payload:
                      ├── $field->typeInstance()->storageColumn()
                      ├── $field->typeInstance()->prepareForStorage($value)
                      └── FieldValue::updateOrCreate(...) — race-safe SQLSTATE-23000 retry
```

On the read side, `$entry->field('handle')` resolves through
`FieldValue::resolvedValue()`, which calls
`$instance->value($this->{$column})` — the field type's `value()` is the
post-read transform.

> **[Accuracy Note: `AbstractField::validate()` is unwired].** Nine
> concrete types (`FileUpload`, `Media`, `MultiSelect`, `RadioGroup`,
> `Relationship`, `Select`, `Slider`, `StructuredRows`, `Users`)
> override `validate(mixed $value): bool|string`, but **no caller
> invokes it anywhere in the codebase** (verified by grep of
> `app/Repositories`, `app/Services`, `app/Http`, `app/EntryTypes`,
> `app/Models`). The author comments on `FileUpload` and `Relationship`
> say `@todo convert into Laravel validation rules` — the in-place
> validation never ran. See [Recommendations & Remedies](#recommendations--remedies)
> item R-31.

#### Per-type reference

Each entry below documents the storage contract, settings catalogue,
validation surface, and read-side output. **The "Validation today"
line documents what Laravel actually enforces** through `getRules()`;
in-class `validate()` methods are dead per the accuracy note above
unless otherwise stated.

##### `Text` — single-line input

| Aspect | Value |
|---|---|
| Storage column | `value_text` |
| `getRules()` | `['string']` (plus required/nullable from layout) |
| Settings | `placeholder`, `max_length`, `min_length` |
| Read API | Returns the raw string |

**Potential issue.** `min_length` and `max_length` settings are
declared but never reach `getRules()`. The admin UI promises a
constraint the validation pipeline doesn't enforce.

##### `Textarea` — multi-line text

| Aspect | Value |
|---|---|
| Storage column | `value_text` |
| `getRules()` | `[]` (no defaults — accepts anything that survives the layout `required`/`nullable`) |
| Settings | `placeholder`, `max_length`, `rows` (default 4) |
| Read API | Returns the raw string |

**Potential issue.** Same as `Text` — `max_length` is decorative.

##### `Html` — rich text

| Aspect | Value |
|---|---|
| Storage column | `value_text` |
| `getRules()` | `['nullable']` |
| Settings | `toolbar` (`basic` / `full` / `minimal`), `allowed_tags` |
| Write transform | `prepareForStorage()` runs `Purifier::clean()` against `config('purifier.adastra')`, with the field's `allowed_tags` overriding `HTML.Allowed` when set. |
| Read API | Returns the sanitised HTML string. |

**Potential issue.** The Twig partial `_fields/html.twig` does not
exist (R-1). Rendering any field layout that contains an `Html` field
will throw `View [_fields.html] not found`.

##### `Number` — integer or float

| Aspect | Value |
|---|---|
| Storage column | `value_integer` when `decimals === 0`, else `value_float` (via `HasDecimalStorage` trait) |
| `getRules()` | `['numeric']` |
| Settings | `min`, `max`, `step`, `decimals` (0–10), `default` |
| Read API | Cast by `FieldValue::$casts` (`integer` or `float` depending on storage column) |

**Potential issue.** `min`, `max`, and `step` settings are not pushed
into `getRules()`. The admin form claims constraints that the
validator doesn't honour.

##### `Boolean` — toggle

| Aspect | Value |
|---|---|
| Storage column | `value_boolean` |
| `getRules()` | `['boolean']` |
| Settings | `default` (toggle), `label_on`, `label_off` |
| Read API | `cast()` returns `(bool)`. `FieldValue::$casts` also casts the column. |

No known issues.

##### `Date` — calendar date

| Aspect | Value |
|---|---|
| Storage column | `value_date` |
| `getRules()` | `['date']` |
| Settings | `min_date`, `max_date`, `default` (date string or `"today"`), `format` |
| Read API | `FieldValue::$casts` returns `Carbon` (`'value_date' => 'datetime'`). |

**Potential issue.** `min_date` and `max_date` settings are not
enforced by `getRules()`.

##### `Time` — time of day

| Aspect | Value |
|---|---|
| Storage column | `value_text` (canonical `HH:MM` or `HH:MM:SS`) |
| `getRules()` | `['nullable', 'string', new TimeFormatRule(...)]` |
| Settings | `include_seconds`, `min_time`, `max_time`, `step_minutes`, `default` (literal value or `"now"`) |
| Write transform | `prepareForStorage()` validates the pattern, zero-pads the hour, and aligns the seconds component with `include_seconds`. Throws `InvalidArgumentException` on malformed input. |
| Read API | `value()` returns `App\Support\Iso\TimeValue`. |

Custom validator (`TimeFormatRule`) honours `min_time` / `max_time`.

##### `EmailAddress` — email

| Aspect | Value |
|---|---|
| Storage column | `value_text` |
| `getRules()` | `[]` |
| Settings | None |
| Read API | Returns the raw string |

**Potential issue (High).** `protected $rules = []` and no
`getRules()` override — **no `email` rule is applied**. Submitting
arbitrary strings ("not-an-email") will store successfully. See R-33.

##### `Url` — URL

| Aspect | Value |
|---|---|
| Storage column | `value_text` |
| `getRules()` | `['string', 'url']` |
| Settings | None |
| Read API | Returns the raw string |

No known issues. The Laravel `url` rule accepts any scheme by default;
tighten with `'url:http,https'` if needed at the field level.

##### `Telephone` — phone number

| Aspect | Value |
|---|---|
| Storage column | `value_text` |
| `getRules()` | `['string', 'telephone']` |
| Settings | None |
| Read API | Returns the raw string |

**Potential issue (Critical).** `telephone` is **not a registered
Laravel validator** — no `Validator::extend('telephone', …)` call
anywhere in `app/`, `bootstrap/`, or `config/`. Submitting a form
that includes a `Telephone` field will throw
`BadMethodCallException: Method Illuminate\Validation\Validator::validateTelephone does not exist`.
Until either a rule is registered or the rule string is changed (to
something built-in such as `regex:/.../` or `string`), this field type
is effectively broken. See R-32.

##### `ColorPicker` — color value

| Aspect | Value |
|---|---|
| Storage column | `value_text` |
| `getRules()` | `[]` |
| Settings | `format` (`hex` / `rgb` / `hsl`), `alpha` (toggle), `presets` (key/value swatches) |
| Read API | Returns the raw string |

**Potential issue.** No format validation — the picker chooses a
format but the backend accepts any string. `presets` are read by the
Twig partial only.

##### `Relationship` — entries M2M

| Aspect | Value |
|---|---|
| Storage | `isRelational() === true` — writes to `entry_relationships(entry_id, related_entry_id, field_id, sort_order)`, not `field_values` |
| `getRules()` | `['array']` |
| Settings | `entry_groups[]` (handles), `entry_types[]` (handles), `limit` |
| Read API | `Entry::field()` resolves to `Collection<Entry>` ordered by `sort_order`. |

**Potential issue.** `entry_types` setting is declared and shown in
the admin form but **not read by `fetchAvailableEntries()`** — only
`entry_groups` filters the picker list. The setting has no effect.

**Potential issue.** `validate()` (dead code) is the only place that
enforces `limit`. Submitting beyond `limit` via the API silently
ignores the limit.

##### `FileUpload` — multi-media via upload + picker

| Aspect | Value |
|---|---|
| Storage column | `value_json` (int[] of Media IDs) |
| Marker | Implements `App\Contracts\SyncsToMediables` — `FieldValueObserver` mirrors the array into the `mediables` pivot table on save |
| `getRules()` | `[]` (relies on dead `validate()` plus library-level upload rules at upload time) |
| Settings | `library` (select_multiple of Library IDs), `allowed_types` (per-field MIME override), `min`, `max` |
| Read API | `value()` returns `Collection<Media>` sorted by stored array index |

**Potential issue.** `min`/`max`/library scoping is enforced only by
the dead `validate()`. The Laravel pipeline doesn't reject violations.

##### `Media` — same storage as FileUpload, different UX

| Aspect | Value |
|---|---|
| Storage column | `value_json` (int[] of Media IDs) |
| Marker | Also implements `SyncsToMediables` |
| `getRules()` | `[]` |
| Settings | `libraries` (required, multi-select), `min`, `max` |
| Read API | `value()` returns `Collection<Media>` sorted by stored array index |
| Render | Passes the matching `media.picker.index` URL into the partial; the picker chip strip lazy-loads via JSON |

**Potential issue.** Same dead-`validate()` issue — the configured
`min`/`max` and library scoping are not enforced by the request layer.

> **[Accuracy Note: FileUpload vs Media].** Storage and observer
> integration are identical. The split exists so a `FileUpload` field
> can also accept inline uploads from the form, while `Media` is a
> pure picker chip strip backed by an existing library. They are
> separate seeded `field_types` rows.

##### `Users` — user picker

| Aspect | Value |
|---|---|
| Storage column | `value_json` (int[] of User IDs) |
| `getRules()` | `['nullable', 'array']` |
| Settings | `roles[]` (restrict to users with these role IDs), `limit`, `display` (`dropdown` / `checkboxes` / `tokens`) |
| Read API | `value()` returns `Collection<User>` with `[id, name, email]` only — **never exposes password, tokens, remember_token** |

**Potential issue.** Limit and role-membership checks live only in
dead `validate()`. Layer adopts `User::select(['id','name','email'])`
defensively on the read path, which is the right call.

##### `Select` — single-choice dropdown

| Aspect | Value |
|---|---|
| Storage column | `value_text` |
| `getRules()` | `['nullable', 'string']` |
| Settings | `options` (key/value, required), `placeholder`, `default`, `strict_options` (toggle) |
| Read API | Returns the raw string key |
| Trait | `ValidatesAgainstOptions` |

**Potential issue.** `validateAgainstOptions()` is the only thing
that respects `strict_options`, and it's invoked only from the dead
`validate()`. Today an editor can submit any string and it stores.

##### `MultiSelect` — multi-choice

| Aspect | Value |
|---|---|
| Storage column | `value_json` (string[] of keys) |
| `getRules()` | `['nullable', 'array']` |
| Settings | `options` (required), `min`, `max`, `display` (`checkboxes` / `multiselect`), `strict_options` |
| Read API | `cast()` returns `string[]` |
| Trait | `ValidatesAgainstOptions` |

Same dead-`validate()` caveat as `Select`. `min`/`max` are
unenforced via Laravel rules.

##### `RadioGroup` — single-choice radio

| Aspect | Value |
|---|---|
| Storage column | `value_text` |
| `getRules()` | `['nullable', 'string']` |
| Settings | `options` (required), `default`, `layout` (`stacked` / `inline`), `strict_options` |
| Read API | Returns the raw string key |
| Trait | `ValidatesAgainstOptions` |

Same dead-`validate()` caveat as `Select`.

##### `Slider` — bounded numeric range

| Aspect | Value |
|---|---|
| Storage column | `value_integer` when `decimals === 0`, else `value_float` (via `HasDecimalStorage`) |
| `getRules()` | `[]` (no default rules) |
| Settings | `min` (required, default 0), `max` (required, default 100), `step` (default 1), `suffix`, `decimals`, `default` |
| Read API | Cast by `FieldValue::$casts` |

**Potential issue.** `min`/`max` enforcement is in dead
`validate()`. Submitting out-of-range values via the API saves
without complaint.

##### `Country` — ISO 3166-1 country code

| Aspect | Value |
|---|---|
| Storage column | `value_text` (uppercase ISO 3166-1 alpha-2) |
| `getRules()` | `['nullable', 'string', new CountryCodeRule($allowed)]` |
| Settings | `allowed_countries[]`, `default`, `placeholder` |
| Write transform | `prepareForStorage()` uppercases the value |
| Read API | `value()` returns `['code' => 'US', 'name' => 'United States']` (or `null`) |

`CountryCodeRule` enforces both validity (every ISO 3166-1 country)
and the optional `allowed_countries` allowlist. Active validation.

##### `StateProvince` — ISO 3166-2 subdivision

| Aspect | Value |
|---|---|
| Storage column | `value_text` (typically `US-CA`-style code) |
| `getRules()` | `['nullable', 'string', new SubdivisionCodeRule($country, $allowFreetextFallback)]` |
| Settings | `country` (required, default `'US'`), `default`, `placeholder`, `allow_freetext_fallback` (toggle, default `true`) |
| Read API | `value()` returns `['code', 'name', 'country']`. Falls back to `code` when no subdivision data exists for the country. |

**Note.** The field is single-country per instance. To support
country-conditional dropdowns the entry must declare a `Country`
field separately and the JS resolves the pair at render time. Not
shipped today; document if the requirement comes up.

##### `Money` — currency-typed monetary value

See [Money field — design notes](#money-field--design-notes) below.

##### `StructuredRows` — repeatable rows of typed columns

| Aspect | Value |
|---|---|
| Storage column | `value_json` (array of row objects keyed by column handle) |
| `getRules()` | `['nullable', 'array']` |
| Settings | `columns[]` (handle/label/type triples — declared via the `structured_rows_columns` settings widget), `min_rows`, `max_rows`, `add_label` |
| Read API | `cast()` returns the raw array; `render()` fills missing column keys with `null` so the template never hits undefined indices |

**Potential issue.** Same dead-`validate()` story — `min_rows`,
`max_rows`, and per-row column-presence checks live only in
unreachable code. An API caller can submit rows with missing or extra
keys and they will store.

#### Money field — design notes

The `Money` field deserves a focused note because the storage convention
is invisible at the column level and the design is deliberately
single-currency-per-field-instance.

**Storage contract.**

- Column: `value_integer`. The stored value is the amount in the
  currency's **minor unit** (cents for USD, pence for GBP, yen for JPY,
  etc.). `prepareForStorage()` parses the submitted decimal string with
  `moneyphp/moneyphp`'s `DecimalMoneyParser` against the field's
  configured currency, then writes `$money->getAmount()` (an integer).
- The minor-unit decimal precision comes from the ISO 4217 currency
  metadata via `App\Support\Iso\Currencies::decimals($currency)`, so JPY
  fields are stored as whole integers and BHD fields as thousandths
  without any per-field configuration.

**Write-path guard.**

`prepareForStorage()` rejects values with more fractional digits than
the configured currency allows. `19.999` against a `USD` field throws
`InvalidArgumentException` rather than silently rounding. This is the
behaviour the field-type contract promises — "no implicit rounding."

**Read API.**

```php
$entry->field('price');  // Money\Money instance (NOT the raw integer)
$entry->field('price')->getAmount();    // '1999' (string, minor units)
$entry->field('price')->getCurrency();  // Money\Currency('USD')
```

`Money\Money` provides precision-safe arithmetic
(`add()`/`subtract()`/`multiply()`) and formatting helpers via
`moneyphp/moneyphp`'s built-in formatters. Never do raw integer math on
the underlying column outside this API — the minor-unit scale is
currency-dependent.

**Settings.**

| Setting    | Purpose                                                          |
|------------|------------------------------------------------------------------|
| `currency` | Required. ISO 4217 code. Sets minor-unit precision + parser.     |
| `min`      | Optional. Major-unit decimal string. Enforced by `MoneyRangeRule`. |
| `max`      | Optional. Major-unit decimal string. Same rule.                  |
| `default`  | Optional. Pre-filled value, major-unit decimal string.           |

**Design choice — single-currency per field instance.**

The currency lives in field settings, not next to the value. A single
`Field` row is therefore single-currency: `price` cannot be USD on one
entry and EUR on another. The two intended patterns when multi-currency
behaviour is actually needed:

1. **Per-currency field handles** — declare `price_usd`, `price_eur`,
   `price_gbp` as separate `Money` fields with different currency
   settings.
2. **A future "Currency" field type** — declare a currency-code field
   alongside the money field and resolve the pair at render time. Not
   shipped today; flag if the requirement comes up.

**Raw-column awareness.**

Any custom report, admin SQL query, or data export that reads
`field_values.value_integer` directly must look up the field's
`currency` setting to interpret the scale. The model-level API
(`$entry->field('price')`) hides this; the raw column does not.

```php
// app/Field/Types/Toggle.php
namespace App\Field\Types;

use App\Field\AbstractField;

class Toggle extends AbstractField
{
    protected string $handle = 'toggle';
    protected string $name = 'Toggle';
    protected array $rules = ['boolean'];

    public function storageColumn(): string { return 'value_boolean'; }
    public function cast(mixed $value): bool { return (bool) $value; }
}
```

Register it in a seeder:

```php
use App\Models\Field\Type as FieldType;

FieldType::firstOrCreate(
    ['object' => \App\Field\Types\Toggle::class],
    ['name' => 'Toggle']
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
    ['name' => 'Product Details', 'description' => 'Core product information.']
);

foreach ([
    ['handle' => 'price', 'name' => 'Price', 'label' => 'Price'],
    ['handle' => 'sku', 'name' => 'SKU', 'label' => 'SKU Number'],
] as $def) {
    $field = Field::firstOrCreate(
        ['handle' => $def['handle']],
        array_merge($def, ['field_type_id' => $textType->id])
    );
    $group->fields()->syncWithoutDetaching([$field->id]);
}
```

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

$layout = FieldLayout::create(['name' => 'Article Layout']);
$contentTab = Tab::create(['field_layout_id' => $layout->id, 'name' => 'Content', 'sort_order' => 1]);

foreach (['body', 'excerpt'] as $order => $handle) {
    TabElement::create([
        'field_layout_tab_id' => $contentTab->id,
        'field_id' => Field::where('handle', $handle)->value('id'),
        'required' => $handle === 'body',
        'sort_order' => $order + 1,
    ]);
}

$seoTab = Tab::create(['field_layout_id' => $layout->id, 'name' => 'SEO', 'sort_order' => 2]);

foreach (['meta_title', 'meta_description'] as $order => $handle) {
    TabElement::create([
        'field_layout_tab_id' => $seoTab->id,
        'field_id' => Field::where('handle', $handle)->value('id'),
        'required' => false,
        'sort_order' => $order + 1,
    ]);
}
```

### Getting All Fields from a Layout

```php
$layout->fields(); // Collection<Field>, flattened from all tabs in sort order
```

`FieldLayout::fields()` calls `loadMissing('tabs.elements.field')` — N+1-safe.

### Field Uniqueness Constraint

A field may only be assigned **once per layout** — not once per tab. The
`field_layout_tab_elements` table enforces uniqueness at the tab level
(`field_layout_tab_id + field_id`), but the admin UI enforces it at the layout
level: the Available Fields panel for a tab excludes any field already assigned
to any other tab within the same layout.

**Known gap — moving a field between tabs:** There is no single-step "move"
operation. To reassign a field from Tab A to Tab B, remove it from Tab A (save),
then add it to Tab B (save). A dedicated move UI has not been implemented.

---

## Status Groups and Statuses

### Creating a Status Group

```php
use App\Models\Status;
use App\Models\StatusGroup;

$group = StatusGroup::create([
    'name' => 'Review Workflow',
    'handle' => 'review',
    'sort_order' => 2,
]);

$statuses = [
    [
        'name' => 'Pending Review',
        'handle' => 'pending',
        'color' => '#F59E0B',
        'is_default' => true,
        'is_public' => false,
        'sort_order' => 1,
    ],
    [
        'name' => 'Approved',
        'handle' => 'approved',
        'color' => '#10B981',
        'is_default' => false,
        'is_public' => true,
        'sort_order' => 2,
    ],
    [
        'name' => 'Rejected',
        'handle' => 'rejected',
        'color' => '#EF4444',
        'is_default' => false,
        'is_public' => false,
        'sort_order' => 3,
    ],
];

foreach ($statuses as $s) {
    Status::create(array_merge($s, ['status_group_id' => $group->id]));
}
```

### How an Entry Stores its Status

The status is denormalised across three columns maintained together by
`EntryRepository::applyStatus()`:

| Column             | Notes                                        |
|--------------------|----------------------------------------------|
| `status_id`        | nullable FK to `statuses.id`, `nullOnDelete` |
| `status_handle`    | indexed string for fast lookups              |
| `status_is_public` | indexed boolean                              |

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
    ['name' => 'Regions', 'sort_order' => 1]
);

$europe = Category::create([
    'group_id' => $group->id,
    'name' => 'Europe',
    'handle' => 'europe',
    'sort_order' => 1,
]);

Category::create([
    'group_id' => $group->id,
    'parent_id' => $europe->id,
    'name' => 'France',
    'handle' => 'france',
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
$tab = Tab::create(['field_layout_id' => $layout->id, 'name' => 'Details', 'sort_order' => 1]);
foreach ([$description, $imageUrl] as $i => $field) {
    TabElement::create([
        'field_layout_tab_id' => $tab->id,
        'field_id' => $field->id,
        'sort_order' => $i + 1,
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
    'name' => 'PHP',
    'handle' => 'php',
    'fields' => [
        'cat_description' => 'Articles about the PHP language.',
        'cat_image_url' => 'https://example.com/php.png',
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

## Entry Groups and Entry Types

An **EntryGroup** is the section/channel (e.g. "Blog", "Products") tying
together a FieldLayout, a StatusGroup, polymorphic CategoryGroups, and
polymorphic FieldGroups.

An **EntryType** row maps a group-scoped handle to PHP behaviour through the
`EntryBehavior` registry:

| Column                                                | Description                              |
|-------------------------------------------------------|------------------------------------------|
| `entry_group_id`                                      | FK to `entry_groups`, nullable, nullOnDelete |
| `entry_behavior_id`                                   | FK to `entry_behaviors`, nullable, nullOnDelete |
| `field_layout_id`                                     | Optional override layout for this type   |
| `name`, `handle`                                      | `(entry_group_id, handle)` unique        |
| `default_template`                                    | Optional default template for SiteRouter |
| `default_schema_type`                                 | Default schema.org type (SEO; reserved)  |
| `has_entry_tree`, `max_depth`, `allowed_parent_types` | Tree config                              |
| `sort_order`                                          | Display order within the group           |

> **[Accuracy Note: EntryType has no `class` column].** Earlier copy of this
> document described an `entry_types.class` column storing an
> `AbstractEntryType` FQCN. **That column does not exist.** The behaviour
> binding is two-step: `EntryType.entry_behavior_id` →
> `EntryBehavior.class` (a morph alias such as `behavior.blog-post`) →
> `Relation::getMorphedModel(...)` → the concrete PHP class. Verified
> against `database/migrations/2026_04_18_000008_create_entry_types_table.php`,
> `app/Models/EntryType.php`, `app/Models/EntryBehavior.php`, and
> `app/EntryTypes/EntryTypeRegistry.php`.

#### Runtime resolution (corrected)

```
EntryService::create($handle, ...)
  └── EntryTypeRegistry::resolveByHandle($handle)
        ├── Fetch EntryType row (with('entryGroup', 'entryBehavior', 'fieldLayout…'))
        └── Instantiate:
              ├── If entryBehavior IS NULL → GeneralEntryType (fallback)
              └── Else → EntryBehavior::instance($record):
                    ├── $fqcn = Relation::getMorphedModel($behavior->class)
                    ├── Throws RuntimeException if morph key not registered
                    ├── Throws RuntimeException if class does not exist
                    ├── Throws RuntimeException if class does not extend AbstractEntryType
                    └── return new $fqcn($record)
```

`EntryTypeRegistry::resolveByHandle()` resolves by `handle` only — it does
**not** filter by group. Keep entry type handles globally unique when using
`Content::create('type_handle', ...)` unless the creation API is extended to
accept group context (see Ambivalence A-1).

#### Adding a new EntryType

1. Write a PHP class extending `AbstractEntryType` in `app/EntryTypes/`.
2. Register a morph alias in `AppServiceProvider::boot()`'s morph map under
   the `behavior.*` prefix.
3. Insert a row into `entry_behaviors` (the `EntryBehaviorSeeder` is the
   canonical example) — `class` is the morph alias, not the FQCN.
4. Insert (or update) the `entry_types` row with `entry_behavior_id`
   pointing at the new behaviour.
5. Run `php artisan app:validate-class-references` to confirm the morph
   alias resolves to a real class extending `AbstractEntryType`.

### Seeded Entry Groups and Types

The seeders create one entry type per seeded entry group. The handles are
currently globally unique, which is important because `Content::create()` and
`EntryTypeRegistry::resolveByHandle()` resolve by type handle alone.

| EntryGroup handle | EntryType handle   | Name             | Behaviour handle    | Resolves to                      | Status group      | Tree routing |
|-------------------|--------------------|------------------|---------------------|----------------------------------|-------------------|--------------|
| `blog`            | `blog_post`        | Blog Post        | `blog-post`         | `BlogPostEntryType`              | `publication`     | No           |
| `products`        | `product`          | Product          | `product`           | `ProductEntryType`               | `product-status`  | No           |
| `events`          | `event`            | Event            | `event`             | `EventEntryType`                 | `publication`     | No           |
| `news`            | `news_article`     | News Article     | `news-article`      | `NewsArticleEntryType`           | `publication`     | No           |
| `pages`           | `page`             | Page             | `page`              | `PageEntryType`                  | `publication`     | Yes          |
| `jobs`            | `job_listing`      | Job Listing      | `job-listing`       | `JobListingEntryType`            | `job-status`      | No           |
| `podcast`         | `podcast_episode`  | Podcast Episode  | `podcast-episode`   | `PodcastEpisodeEntryType`        | `publication`     | No           |
| `portfolio`       | `portfolio_item`   | Portfolio Item   | `portfolio-item`    | `PortfolioItemEntryType`         | `publication`     | Yes          |
| `videos`          | `video`            | Video            | `video`             | `VideoEntryType`                 | `publication`     | Yes          |
| `recipes`         | `recipe`           | Recipe           | `recipe`            | `RecipeEntryType`                | `publication`     | Yes          |
| `general`         | `general`          | General          | `general`           | `GeneralEntryType`               | `publication`     | No           |

Mind the dialect: EntryType handles tend to use **underscores**
(`blog_post`), behaviour handles use **kebab-case** (`blog-post`). The
`behavior.*` morph alias keys (in `entry_behaviors.class`) also use kebab.

`pages`, `portfolio`, `videos`, and `recipes` also seed
`default_template = 'entries.page'`. If an Entry Tree node has its own
`template`, that node template wins over the entry type default.

### Available Entry Type Classes

Entry type classes live in `app/EntryTypes/` and extend `AbstractEntryType`.
They are behavior objects for lifecycle hooks and validation; field schema still
comes from the entry group's layout and optional type-level layout.

| Class                       | Current behavior |
|-----------------------------|------------------|
| `GeneralEntryType`          | Default/fallback type; stamps `published_at` on create, and when transitioning to `published` on update if no publish date exists |
| `BlogPostEntryType`         | Stamps `published_at` when created as `published`; computes `reading_time` from `fields.body` at roughly 200 words/minute |
| `EventEntryType`            | Stamps `published_at`; validates `end_date >= start_date` when both can be resolved |
| `JobListingEntryType`       | Stamps `published_at`; clears publication for `expired`/`closed`; auto-expires after `closing_date`; requires application URL or email before publishing |
| `NewsArticleEntryType`      | Stamps `published_at` on publish; requires `source` when `source_url` is present |
| `PageEntryType`             | Stamps `published_at` on create |
| `PodcastEpisodeEntryType`   | Auto-assigns `episode_number` under a group-row lock; stamps `published_at`; validates positive integer `episode_duration` on update |
| `PortfolioItemEntryType`    | Stamps `published_at` on create |
| `ProductEntryType`          | Validates price/sale-price rules; requires SKU before publishing; sets status to `out-of-stock` when stock reaches zero |
| `RecipeEntryType`           | Stamps `published_at`; computes `total_time` from `prep_time + cook_time` |
| `VideoEntryType`            | Stamps `published_at`; requires `platform_id` or `video_url` before publishing |

`AbstractEntryType` provides:

```php
public function getRecord(): EntryTypeRecord;
public function getName(): string;
public function getHandle(): string;
public function getEntryGroup(): EntryGroup;
public function validate(array $data, ?Entry $entry = null): array;
```

It also provides `existingFieldValue(?Entry $entry, string $handle): mixed` for
safe validation/update logic that needs to inspect an existing field value.

### Registry Resolution and Admin Constraints

`EntryTypeRegistry` has two resolution paths:

| Method                  | Used by                         | Notes |
|-------------------------|---------------------------------|-------|
| `resolveByHandle()`     | `EntryService::create()`        | Looks up the first `entry_types.handle` match globally and caches by handle |
| `resolveByRecord()`     | `EntryService::update()`        | Instantiates from the entry's existing `EntryType` row and caches by ID |

`entry_types.entry_behavior_id` is nullable. When a row's behaviour FK is
null (or the linked behaviour's morph key has gone missing from
`AppServiceProvider::boot()`), `EntryTypeRegistry::instantiate()` returns
`GeneralEntryType` as a fallback. When the linked behaviour resolves but
the resulting PHP class either doesn't exist or doesn't extend
`AbstractEntryType`, `EntryBehavior::instance()` throws `RuntimeException`
— that's a deploy-time failure, not a silent fallback.

`StoreEntryTypeRequest` and `EditEntryTypeRequest` validate
`entry_behavior_id` as `['nullable', 'integer', 'exists:entry_behaviors,id']`.
The standalone `ExtendsClass` validation rule still lives in
`app/Rules/ExtendsClass.php` but is **not** wired into the EntryType
requests — the morph-alias indirection means the class linkage is now a
deploy-time invariant (`app:validate-class-references`), not a form-time one.

In practice:

- Seeder-created types reference a concrete `entry_behaviors` row.
- Programmatic types can leave `entry_behavior_id` null and get
  `GeneralEntryType` behaviour.
- Admin-created types can also leave the behaviour empty (the form field
  is nullable). Pick a behaviour when the type needs hooks or validation.
- Existing rows whose behaviour resolves to a missing or wrong-shape PHP
  class throw `RuntimeException` at lookup time rather than falling back.

### Field Layering: Group Fields + Type Fields

```php
// From EntryRepository::resolveLayoutFields()
$groupFields = $entry->entryGroup->fieldLayout?->fields() ?? collect();
$typeFields = $entry->entryType->fieldLayout?->fields() ?? collect();

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
    'name' => 'News Articles',
    'handle' => 'news',
    'description' => 'News and press releases.',
    'field_layout_id' => $layout->id,
    'status_group_id' => $statusGroup->id,
    'sort_order' => 3,
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
        $data['status'] = $data['status'] ?? 'draft';
        return $data;
    }

    public function afterCreate(Entry $entry, array $data): void
    {
        // SendReviewNotification::dispatch($entry);
    }

    public function beforeUpdate(Entry $entry, array $data): array
    {
        if ($entry->status_handle === 'published' && ($data['status'] ?? null) === 'draft') {
            unset($data['status']);
        }
        return $data;
    }
}
```

### Registering the Entry Type in the Database

```php
use App\Models\EntryBehavior;
use App\Models\EntryGroup;
use App\Models\EntryType;

$group = EntryGroup::where('handle', 'news')->firstOrFail();
$behavior = EntryBehavior::where('handle', 'news-article')->firstOrFail();
// EntryBehavior row was created by EntryBehaviorSeeder with
// class = 'behavior.news-article' (a morph alias registered in
// AppServiceProvider::boot()).

EntryType::firstOrCreate(
    ['entry_group_id' => $group->id, 'handle' => 'news_article'],
    [
        'name' => 'News Article',
        'entry_behavior_id' => $behavior->id,
        'sort_order' => 1,
    ]
);
```

> **[Accuracy Note: registration column].** The earlier sample showed
> `'class' => \App\EntryTypes\NewsArticleEntryType::class` — that key is
> ignored because no `class` column exists on `entry_types`. The behaviour
> linkage is `entry_behavior_id`. Verified against
> `database/seeders/EntryGroupSeeder.php` and
> `database/seeders/ExtendedEntryGroupSeeder.php`.

---

## Adding a New Entry Type End-to-End

The following walkthrough adds a "Recipes" section with two types: Standard
Recipe and Video Recipe.

### 1. Create fields and a FieldGroup

```php
use App\Models\Field;
use App\Models\Field\Group as FieldGroup;
use App\Models\Field\Type as FieldType;

$text = FieldType::where('object', \App\Field\Types\Text::class)->firstOrFail();
$textarea = FieldType::where('object', \App\Field\Types\Textarea::class)->firstOrFail();
$number = FieldType::where('object', \App\Field\Types\Number::class)->firstOrFail();

$fieldDefs = [
    [
        'handle' => 'ingredients',
        'name' => 'Ingredients',
        'label' => 'Ingredients',
        'type' => $textarea,
    ],
    [
        'handle' => 'instructions',
        'name' => 'Instructions',
        'label' => 'Instructions',
        'type' => $textarea,
    ],
    [
        'handle' => 'prep_time_mins',
        'name' => 'Prep Time',
        'label' => 'Prep Time (min)',
        'type' => $number,
    ],
    [
        'handle' => 'servings',
        'name' => 'Servings',
        'label' => 'Servings',
        'type' => $number,
    ],
    [
        'handle' => 'video_url',
        'name' => 'Video URL',
        'label' => 'Video URL',
        'type' => $text,
    ],
];

foreach ($fieldDefs as $def) {
    Field::firstOrCreate(
        ['handle' => $def['handle']],
        [
            'name' => $def['name'],
            'label' => $def['label'],
            'field_type_id' => $def['type']->id,
        ]
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
$recipeTab = Tab::create(['field_layout_id' => $groupLayout->id, 'name' => 'Recipe', 'sort_order' => 1]);

foreach (Field::whereIn('handle', ['ingredients', 'instructions', 'prep_time_mins', 'servings'])->get() as $i => $field) {
    TabElement::create([
        'field_layout_tab_id' => $recipeTab->id,
        'field_id' => $field->id,
        'required' => in_array($field->handle, ['ingredients', 'instructions']),
        'sort_order' => $i + 1,
    ]);
}

// Video-specific type layout
$videoLayout = FieldLayout::create(['name' => 'Video Recipe Layout']);
$videoTab = Tab::create(['field_layout_id' => $videoLayout->id, 'name' => 'Video', 'sort_order' => 1]);
TabElement::create([
    'field_layout_tab_id' => $videoTab->id,
    'field_id' => Field::where('handle', 'video_url')->value('id'),
    'required' => true,
    'sort_order' => 1,
]);
```

### 3. Create the EntryGroup

```php
use App\Models\EntryGroup;
use App\Models\StatusGroup;

$statusGroup = StatusGroup::where('handle', 'publication')->firstOrFail();

$entryGroup = EntryGroup::create([
    'name' => 'Recipes',
    'handle' => 'recipes',
    'description' => 'Step-by-step cooking guides.',
    'field_layout_id' => $groupLayout->id,
    'status_group_id' => $statusGroup->id,
    'sort_order' => 10,
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
use App\Models\EntryBehavior;
use App\Models\EntryType;
use Illuminate\Database\Eloquent\Relations\Relation;

// 5a. Register the morph alias once (in AppServiceProvider::boot())
Relation::morphMap([
    // existing entries…
    'behavior.recipe' => \App\EntryTypes\RecipeEntryType::class,
    'behavior.video-recipe' => \App\EntryTypes\VideoRecipeEntryType::class,
]);

// 5b. Insert the EntryBehavior rows
$standard = EntryBehavior::firstOrCreate(
    ['handle' => 'recipe'],
    ['name' => 'Recipe', 'class' => 'behavior.recipe']
);
$video = EntryBehavior::firstOrCreate(
    ['handle' => 'video-recipe'],
    ['name' => 'Video Recipe', 'class' => 'behavior.video-recipe']
);

// 5c. Bind EntryType rows to those behaviours
EntryType::firstOrCreate(
    ['entry_group_id' => $entryGroup->id, 'handle' => 'recipe'],
    ['name' => 'Standard Recipe', 'entry_behavior_id' => $standard->id, 'sort_order' => 1]
);

EntryType::firstOrCreate(
    ['entry_group_id' => $entryGroup->id, 'handle' => 'video_recipe'],
    [
        'name' => 'Video Recipe',
        'entry_behavior_id' => $video->id,
        'field_layout_id' => $videoLayout->id,
        'sort_order' => 2,
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
    'title' => 'Classic Carbonara',
    'status' => 'published',
    'fields' => [
        'ingredients' => "200g spaghetti\n2 eggs\n100g pancetta",
        'instructions' => "1. Boil pasta...\n2. Fry pancetta...",
        'prep_time_mins' => 15,
        'servings' => 2,
    ],
]);

$videoRecipe = Content::create('video_recipe', [
    'title' => 'Carbonara in 60 Seconds',
    'status' => 'published',
    'fields' => [
        'ingredients' => "200g spaghetti\n2 eggs",
        'instructions' => 'Watch the video.',
        'prep_time_mins' => 5,
        'servings' => 2,
        'video_url' => 'https://youtube.com/watch?v=example',
    ],
]);

// Query
$allRecipes = Content::query()->inGroup('recipes')->published()->get();
$videos = Content::query()->ofType('video_recipe')->published()->latest()->paginate(12);

echo $recipe->field('ingredients');
echo $videoRecipe->field('video_url');
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

$author = User::find(1);
$category = Category::where('handle', 'france')->firstOrFail();

$entry = Content::create('news_article', [
    'title' => 'Election Results 2026',
    'published_at' => now(),
    'status' => 'published',
    'authors' => [$author->id], // ordered M2M — sort_order = array key
    'categories' => [$category->id],
    'fields' => [
        'body' => 'Full article text...',
        'excerpt' => 'Short summary.',
        'meta_title' => 'Election Results 2026 | News',
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
    'title' => 'Updated Title',
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
$relatedA = Content::query()->inGroup('products')->where('handle', 'widget-a')->firstOrFail()->id;
$relatedB = Content::query()->inGroup('products')->where('handle', 'widget-b')->firstOrFail()->id;

// On create
$post = Content::create('blog_post', [
    'title' => 'My Post',
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
$techPosts = Content::query()
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

| Method                                                  | Notes                                               |
|---------------------------------------------------------|-----------------------------------------------------|
| `inGroup($group)`                                       | `string\|int\|EntryGroup`                           |
| `ofType($type)`                                         | `string\|int\|EntryType`                            |
| `published()`                                           | `status_is_public = true AND published_at <= now()` |
| `withStatus($handle)`                                   | matches `status_handle`                             |
| `withAuthor(int $userId)`                               | `whereHas('authors', users.id)`                     |
| `withCategory(int $categoryId)`                         | `whereHas('categories', categories.id)`             |
| `where($column, $op, $value = null)`                    | passthrough to Eloquent Builder                     |
| `orderBy($column, $direction = 'asc')`                  | passthrough                                         |
| `latest()`                                              | `orderBy('created_at', 'desc')`                     |
| `get()` / `paginate(int)` / `first()` / `firstOrFail()` | terminal; always eager-load                         |
| `count()`                                               | does **not** apply eager loads                      |

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

| Relationship | Type                        | Description                                                             |
|--------------|-----------------------------|-------------------------------------------------------------------------|
| `creator`    | `BelongsTo User`            | User who created the record (`created_by_user_id`)                      |
| `authors`    | `BelongsToMany EntryAuthor` | Eligible byline authors, ordered by pivot `sort_order` from `entry_author_entry` |

`Entry::authors()` returns **`EntryAuthor` models**, not `User` models directly. Each `EntryAuthor` has a `user_id` FK, a nullable `display_name`, and carries `status`. Access the underlying user through the `user` relation:

```php
$post = Content::query()->inGroup('blog')->where('handle', 'my-post')->firstOrFail();

// Creator (the user who hit "Save" — always a User)
echo $post->creator->name;

// Authors (EntryAuthor eligibility records, eager-loaded automatically)
foreach ($post->authors as $author) {
    echo $author->display_name;      // falls back to user->name if null
    echo $author->user_id;           // FK to users table
    echo $author->user->email;       // access the related User
    echo $author->pivot->sort_order; // ordering from entry_author_entry pivot
}

// Primary author (lowest sort_order)
$primary = $post->authors->first();
echo $primary?->display_name;
```

`authors` is always eager-loaded by `EntryQueryBuilder`'s terminal methods (`get()`, `first()`, `firstOrFail()`, `paginate()`). For entries fetched by other means, eager-load explicitly:

```php
$entry->load('authors.user');
```

**Filtering by author** uses `withAuthor(int $userId)` on the query builder. It accepts the plain `user_id` (not the `entry_author_id`):

```php
$posts = Content::query()
    ->inGroup('blog')
    ->withAuthor($user->id)
    ->published()
    ->get();
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

## Entry Metrics

Entries can record named daily metrics through `EntryMetric` rows. Each row is
unique by entry, metric name, and recorded date.

```php
use App\Facades\Entries;

Entries::recordMetric($entry, 'view');
Entries::recordMetric($entry, 'download', value: 3, date: now()->subDay());

$views = $entry->metricTotal('view');
$recentViews = $entry->metricTotal('view', from: now()->subDays(30));
```

`EntryService::recordMetric()` increments an existing row when present, or
creates a new row for the day. If two requests race to create the same metric
row, the losing insert retries as an increment after the unique-constraint
failure.

`Entry::metricTotal()` aggregates in the database and can optionally filter
from a given date forward.

---

## Deleting Entries

```php
// FK cascades remove field_values, entry_relationships, entry_author_entry
// (pivot rows), categorizables, and the entry_tree node automatically.
// entry_authors eligibility records are NOT removed — they belong to the user,
// not the entry.
$entry->delete();

// Via the facade — preferred for consistency
\App\Facades\Entries::delete($entry);
```

---

## Media Library

The native Media layer is complete and in testing. Media is handled by first-party
Laravel models rather than Spatie MediaLibrary. See
`docs/MEDIA_LAYER_OVERVIEW.md` for the detailed operating outline.

### Libraries

`media_libraries` stores admin-defined upload containers:

| Column             | Purpose                                      |
|--------------------|----------------------------------------------|
| `name` / `handle`  | Human name and unique library handle         |
| `field_layout_id`  | Optional layout for custom media fields      |
| `adapter`          | Storage disk/adapter name used during upload |
| `adapter_settings` | JSON settings for adapter-specific behavior  |
| `allowed_types`    | JSON list of allowed MIME types              |
| `max_size`         | Integer size limit used by validation        |
| `sort_order`       | Admin ordering                               |

`Media\Library` uses `HasMediaItems` for uploads and can own category groups,
field groups, and an optional field layout. The seeded `avatars` library is used
by `User::avatar()` and related avatar helpers.

### Uploads

The admin upload path is:

```
POST /admin/media/libraries/{library_id}/upload
  -> Admin\Media\Library::upload()
  -> App\Actions\Media\Library\UploadMedia::upload()
  -> App\Services\MediaStorageService::upload()
  -> Media\Library::addMediaFromUpload()
```

`addMediaFromUpload()` validates against the library constraints, stores the
physical file on the configured disk, then creates the `media` row in a
transaction. If persistence fails after storage succeeds, the stored file is
deleted as compensation.

### Attachments and Field Usage

`Entry` and `User` use `HasMedia` for direct attachments through `mediables`.
Direct attachments use `field_id = 0`; media referenced by a `FileUpload` field
uses the real `fields.id`.

`FileUpload` stores ordered media IDs in `field_values.value_json`.
`FieldValueObserver` keeps the `mediables` pivot synchronized so field-driven
media usage remains queryable.

### Media Picker Endpoint

The admin field UIs for `FileUpload`, `Media`, and any other field that lets
an editor pick existing media call a dedicated JSON endpoint to populate the
picker chip strip rather than embedding the full library into the entry form.

| Property        | Value                                                  |
|-----------------|--------------------------------------------------------|
| HTTP method     | `GET`                                                  |
| URI             | `/admin/media/picker`                                  |
| Route name      | `media.picker.index`                                   |
| Controller      | `App\Http\Controllers\Admin\MediaPicker::index`        |
| Auth guard      | `auth` middleware on the admin route group; `access admin` from `Admin\Controller` constructor |
| Response format | JSON                                                   |

**Query parameters:**

| Param          | Rules                                | Notes                                          |
|----------------|--------------------------------------|------------------------------------------------|
| `library_id[]` | `required, array, min:1, integer.*`  | Allowed libraries; unknown IDs are dropped server-side |
| `q`            | `nullable, string, max:200`          | Name search; SQL `LIKE` wildcards in the input are escaped with `\` |
| `page`         | `nullable, integer, min:1`           | Paginator page (default 1)                     |
| `per_page`     | `nullable, integer, min:1, max:100`  | Page size (default 24)                         |

**Response shape:**

```json
{
  "data": [
    {
      "id": 42,
      "name": "Cover Image",
      "original_name": "cover.jpg",
      "mime_type": "image/jpeg",
      "size": 184320,
      "library_id": 3,
      "library_name": "Editorial",
      "url": "https://...",
      "thumbnail_url": "https://...",
      "is_image": true
    }
  ],
  "meta": {
    "total": 128,
    "current_page": 1,
    "last_page": 6,
    "per_page": 24
  }
}
```

**Thumbnails.** For image media, the endpoint kicks a `picker` transformation
(240×240, `cover` mode) idempotently. Subsequent picker calls re-use the
existing transformation record rather than regenerating. Non-image media gets
`thumbnail_url = null`; the field UIs fall back to a file-type icon.

**Why a separate endpoint.** Embedding a media list inline in every entry
form would scale badly once a library has thousands of items. The picker
endpoint stays JSON-only so the field's JS can lazy-load and paginate without
re-rendering the surrounding form.

### Categories, Fields, Transformations, and Cleanup

Media items can be categorized through `categorizables` and can store custom
fields because `Media` uses `Fieldable`.

Transformations live in `media_transformations` and are dispatched through
`TransformationDriverInterface` with Imagick, GD, or null-driver implementations.

Media records are soft-deleted first. `PurgeDeletedMedia` removes physical files
and transformation files after the configured grace period.

---

## Site Routing (Public-Facing URLs)

The public-facing site is served by a catch-all frontend route and a two-driver
resolution pipeline. This is server-rendered Laravel view routing, not a client
SPA router.

### Frontend Catch-All Route

`routes/web.php` registers social login routes first, then sends every remaining
frontend URL to `SiteController@show`:

```php
Route::get('/{uri?}', [SiteController::class, 'show'])
    ->where('uri', '.*')
    ->name('site.show');
```

`SiteController::show()` receives the optional URI and delegates to
`App\Services\SiteRouting\SiteRouter::render()`. `SiteRouter` calls
`resolve()`, then renders the selected Laravel view:

```php
public function render(?string $uri): View
{
    $result = $this->resolve($uri);

    return view($result->template, $result->data);
}
```

Drivers are tried in `config('site.routing.priority')` order. The default is:

```php
['entry_tree', 'template']
```

The first driver returning a non-null `RouteResult` wins. If no driver resolves
the URI, `SiteRouter::resolve()` throws `NotFoundHttpException`.

### RouteResult

Route drivers return `App\Services\SiteRouting\RouteResult`:

```php
new RouteResult(
    type: 'entry_tree',
    template: 'entries.show',
    data: ['entry' => $entry],
    resource: $node,
);
```

| Property   | Purpose                                                   |
|------------|-----------------------------------------------------------|
| `type`     | Driver identifier, currently `entry_tree` or `template`   |
| `template` | Laravel view name passed to `view()`                      |
| `data`     | View data array                                           |
| `resource` | Matched resource, such as an `EntryTree` node or view name |

### Entry Tree Layer

The Entry Tree layer maps entries to explicit public URLs. It is stored in
`entry_trees` and managed through `EntryService` tree methods.

`entry_trees` schema:

| Column            | Notes                                                            |
|-------------------|------------------------------------------------------------------|
| `entry_id`        | Unique FK to `entries`; each entry can have at most one tree node |
| `parent_id`       | Nullable self-FK; deleting a parent sets direct children to root  |
| `handle`          | URL-safe slug segment generated by `EntryTree::validatedHandle()` |
| `uri`             | Full normalized URI, unique across the tree; home node uses `/`   |
| `depth`           | Root depth is `0`; rebuilt when nodes move                        |
| `sort_order`      | Sibling order                                                     |
| `template`        | Optional per-node template override                               |
| `redirect_url`    | Optional URL to 30x to; takes precedence over `template`          |
| `redirect_status` | HTTP status used with `redirect_url` (default `302`); column is `unsignedSmallInteger` |
| `is_home`         | Marks the single home node                                        |

> **[Accuracy Note: redirect columns].** Earlier copy omitted `redirect_url`
> and `redirect_status`. Both exist on `entry_trees` and are honoured by
> `EntryTreeRouteDriver`. Verified against
> `database/migrations/2026_04_23_200641_create_entry_tree_table.php`
> and `app/Services/SiteRouting/RouteDrivers/EntryTreeRouteDriver.php`.

Important model helpers:

| Helper                         | Behavior                                   |
|--------------------------------|--------------------------------------------|
| `EntryTree::normalizeHandle()` | `Str::slug()` for one URL segment          |
| `EntryTree::validatedHandle()` | Slugifies and rejects empty handles        |
| `EntryTree::normalizeUri()`    | Trims slashes; empty URI becomes `/`       |
| `$node->url`                   | Returns `/` for home or `/{uri}` otherwise |
| `root()` scope                 | Filters nodes with no parent               |
| `byUri($uri)` scope            | Filters by normalized URI                  |

Tree support is enabled per entry type with `entry_types.has_entry_tree`.
`max_depth` and `allowed_parent_types` are stored and cast on `EntryType`, but
the current `EntryService` tree methods only enforce `has_entry_tree`, unique
sibling handles, home-node rules, and circular-move prevention.

#### Creating Nodes

```php
use App\Facades\Entries;

$node = Entries::createTreeNode(
    entry: $entry,
    handle: 'about',
    parent: null,
    template: 'templates::pages.entry',
    isHome: false,
);
```

Creation rules:

- The entry's type must have `has_entry_tree = true`.
- Handles are slugified; non-home handles must contain at least one URL-safe character.
- Sibling handles must be unique at the target parent level.
- A home node must be root-level and only one home node may exist.
- The URI is built from ancestor handles. Home nodes resolve to `/`.
- New nodes are appended after existing siblings using the next `sort_order`.

#### Moving Nodes

```php
$moved = Entries::moveTreeNode($node, $newParent, sortOrder: 2);
```

Move rules:

- A node cannot become its own parent.
- A node cannot move beneath one of its descendants.
- The home node must remain at root.
- The handle must remain unique within the new parent level.
- Sibling `sort_order` values are rebalanced.
- `rebuildTreeUri()` updates the moved node and all descendants.

#### Deleting Nodes

```php
app(\App\Services\EntryService::class)->deleteTreeNode($node);
```

Deleting an `EntryTree` node runs inside a transaction. The database promotes
direct children to root with `nullOnDelete`; `EntryTreeObserver` snapshots those
children before delete and rebuilds each promoted subtree after delete so `uri`
and `depth` stay consistent. Deleting the entry itself cascades its tree node.

### EntryTree Driver

`EntryTreeRouteDriver` resolves a URI against `entry_trees`. Only entries
passing `published()` are served.

The driver eager-loads:

```php
[
    'entry.entryType',
    'parent.entry',
    'children.entry.entryType',
]
```

**Redirect short-circuit.** If the matched node has a `redirect_url` and it
passes `isSafeRedirect()` (relative path or `http`/`https` scheme), the
driver returns a `RouteResult` with `type: 'entry_tree_redirect'` and
`data = ['url' => $url, 'status' => $node->redirect_status ?: 302]`.

**Template precedence (no redirect).** `EntryTree.template` →
`EntryType.default_template` → `'entries.show'`, with the
`templates::` namespace prefixed.

```php
use App\Services\SiteRouting\SiteRouter;

$view = app(SiteRouter::class)->render('/blog/my-post');
// Template receives: $entry, $entryType, $node
```

The selected template receives:

| Variable     | Value                       |
|--------------|-----------------------------|
| `$entry`     | Matched `Entry` model       |
| `$entryType` | Matched entry's `EntryType` |
| `$node`      | Matched `EntryTree` node    |

Entry Tree routes win over template routes when `entry_tree` appears before
`template` in `site.routing.priority`, which is the default.

> **[Accuracy Note: RouteResult shape].** `RouteResult` carries
> `type, template, data, resource` only — there is no top-level
> `redirect_url` property. Redirects piggy-back on the `data` array. Verified
> against `app/Services/SiteRouting/RouteResult.php`.

### Template Driver

`TemplateRouteDriver` maps URL segments to views under `resources/templates/`.
Reserved first segments (`api`, `admin`, `login`, `logout`, `register`,
`password`, `sanctum`, `storage`, `assets`, `vendor`) are blocked.

| URL             | Resolved view                                        |
|-----------------|------------------------------------------------------|
| `/`             | `templates::site.index`                              |
| `/blog`         | `templates::blog.index`                              |
| `/blog/my-post` | `templates::blog.entry` (with `$handle = 'my-post'`) |
| `/blog/archive` | `templates::blog.archive` (if the file exists)       |

Key/value pairs after the second segment are parsed into `$params`:

```
/blog/my-post/page/2  →  $params = ['page' => '2']
```

The template driver passes these common variables:

| Variable     | Value                                                   |
|--------------|---------------------------------------------------------|
| `$segments`  | All URL segments as an array                            |
| `$params`    | Key/value pairs from segments after the second segment  |
| `$get`       | Query string array from the current request             |
| `$segment_1` | First segment, when present                             |
| `$segment_2` | Second segment, when present                            |
| `$handle`    | Second segment for `templates::{group}.entry`, else null |
| `$tail`      | Segments after the second segment for two-segment routes |

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
    'routing' => [
        'priority' => [
            'entry_tree',
            'template',
        ],
    ],
    'templates' => [
        'base_path' => 'site',
        'default_template' => 'templates::site.index',
        'not_found_template' => 'errors.404',
    ],
];
```

`SiteRouter` currently reads `routing.priority`. `TemplateRouteDriver` reads
`templates.default_template` when resolving `/`. The `base_path` and
`not_found_template` keys are present in config for future use but are not read
by the current route drivers.

---

## Template and View Stack

Admin views are Twig templates under `resources/views/admin/**/*.twig`, powered
by TwigBridge. Public templates use the `templates::` namespace and are resolved
by the site routing layer under `resources/templates`.

The frontend asset pipeline uses Vite 7 and Tailwind CSS 4:

```bash
npm run dev
npm run build
```

This is not a SPA routing setup. Public URL resolution is server-side through
`SiteRouter`, while admin screens are standard Laravel controller/view flows.

---

## API Layer

The API is versioned under `/api/v1` and requires Sanctum authentication. API
tokens are issued through the admin/account token flows and are checked by
Laravel's `auth:sanctum` middleware.

### API Routes

`routes/api.php` currently registers:

| Route                 | Controller               | Notes                                      |
|-----------------------|--------------------------|--------------------------------------------|
| `/api/v1/users`       | `Api\v1\User`            | Resource routes, logged by middleware      |
| `/api/v1/entries`     | `Api\v1\Entries`         | Resource routes, logged by middleware      |
| `/api/v1/account`     | `Api\v1\Account@show`    | Authenticated account endpoint             |

Each route is wrapped in `LogRequestResponse`, so successful and failed API
responses produce `api_logs` rows.

### API Resources and Current Limitations

API response classes live under `app/Http/Resources/Api`.

`UserResource` returns `id`, `name`, `email`, `created_at`, and `updated_at`.

`EntryResource` now returns the proper entry shape: `id`, `entry_group_id`,
`entry_type_id`, `title`, `handle`, `status_handle`, `status_is_public`,
`published_at`, `fields` (via `fieldArray()`), `authors`
(`{ id: user_id, display_name }`), `categories` (`{ id, title }`),
`created_at`, `updated_at`. OpenAPI attributes on the class match the
runtime shape.

> **[Accuracy Note: EntryResource].** Earlier copy of this document
> recorded `EntryResource` as still returning user-shaped fields
> (`name`/`email`). That has been fixed in the codebase — see
> `app/Http/Resources/Api/EntryResource.php`.

`Api\v1\Account` is still mostly placeholder stubs — only `show` is routed
and it returns `response()->json(['message' => 'Profile updated successfully'])`
rather than the authenticated user's resource. See
[Recommendations & Remedies](#recommendations--remedies) item R-6.

#### API permission strings — current state

| Endpoint                       | Permission required           | Status |
|--------------------------------|-------------------------------|--------|
| `GET /api/v1/users` (`index`)  | **`read user`** (singular)    | **Bug** — see R-3 below |
| `GET /api/v1/users/{id}` (`show`) | `read users` (plural)      | Matches seeded permission |
| `DELETE /api/v1/users/{id}`    | `delete user`                 | Matches seeded permission |
| `GET /api/v1/entries`          | `read entries`                | Matches seeded permission |
| `DELETE /api/v1/entries/{id}`  | `delete entry`                | Matches seeded permission |
| `GET /api/v1/entry-groups`     | `read entry groups`           | Matches seeded permission |
| `GET /api/v1/category-groups`  | `read category groups`        | Matches seeded permission |
| `GET /api/v1/status-groups`    | `read status groups`          | Matches seeded permission |
| `GET /api/v1/statuses`         | `read statuses`               | Matches seeded permission |

> **[Accuracy Note: API/seeder drift].** Earlier copy noted that the User
> API checked `read users` against a seeded `view user`. The seeder
> currently defines **both** `view user` (admin UI) and `read users`
> (API). The remaining drift is *within* `Api\v1\User`: `index()` checks
> the singular `read user` (no such permission exists), while `show()`
> and `destroy()` check the plural/correct strings. Verified against
> `app/Http/Controllers/Api/v1/User.php`.

### API Request/Response Logging

`LogRequestResponse` writes these fields to `api_logs`:

| Column                 | Source                                      |
|------------------------|---------------------------------------------|
| `request_route`        | Request path                                |
| `method`               | HTTP method                                 |
| `user_id`              | Current authenticated user ID, when present |
| `request_payload`      | Sanitized JSON request input                |
| `request_headers`      | Sanitized JSON request headers              |
| `response_payload`     | JSON body or error/body summary             |
| `response_headers`     | Sanitized JSON response headers             |
| `response_status_code` | HTTP response status                        |

Sensitive keys and headers are redacted, including passwords, tokens,
authorization headers, cookies, CSRF headers, secrets, and client secrets.
Logged JSON is truncated to 4000 characters.

`ApiLog` uses Laravel's `Prunable` trait and prunes rows older than 90 days.
`routes/console.php` schedules `model:prune --model App\Models\ApiLog` daily at
02:00. `App\Jobs\PruneApiLogs` provides an alternate self-rescheduling queue
job, but the active scheduler entry is the console schedule.

---

## Admin Route Map

Admin UI routes live under `/admin` and require `auth`. Controllers and
FormRequests enforce finer-grained authorization for specific actions.

| Area                 | Route Pattern                                      | Main Controller(s)                                  |
|----------------------|----------------------------------------------------|-----------------------------------------------------|
| Dashboard            | `/admin/dashboard`                                 | `Admin\Dashboard`                                   |
| Account              | `/admin/account`, `/admin/account/settings`        | `Admin\Account`, `Admin\Account\Token`             |
| Users                | `/admin/users/*`                                   | `Admin\User`, `Admin\User\Token`, `Admin\User\Layout` |
| Roles                | `/admin/roles/*`                                   | `Admin\Role`                                        |
| Category groups      | `/admin/categories/groups/*`                       | `Admin\Category\Group`                              |
| Categories           | `/admin/categories/*`                              | `Admin\Category`                                    |
| Media libraries      | `/admin/media/libraries/*`                         | `Admin\Media\Library`                               |
| Media items          | `/admin/media/*`                                   | `Admin\Media`                                       |
| Media picker (JSON)  | `/admin/media/picker`                              | `Admin\MediaPicker` (see [Media Picker Endpoint](#media-picker-endpoint)) |
| Field groups         | `/admin/fields/groups/*`                           | `Admin\Field\Group`                                 |
| Fields               | `/admin/fields/*`                                  | `Admin\Field`                                       |
| Status groups        | `/admin/statuses/groups/*`                         | `Admin\Status\Group`                                |
| Statuses             | `/admin/statuses/*`                                | `Admin\Status`                                      |
| Entry groups/types   | `/admin/entries/groups/*`, nested `/types/*`       | `Admin\Entry\Group`, `Admin\Entry\Type`            |
| Entries              | `/admin/entries/groups/{group_id}/create`, entries | `Admin\Entry`                                       |
| Field layouts        | `/admin/field-layouts/*`                           | `Admin\FieldLayout`, tab and element controllers    |
| Settings             | `/admin/settings`, `/admin/settings/{handle}`      | `Admin\Settings\Domain`, `Admin\Settings\UserSettings` |

Destructive flows generally include a `confirm` route before the `DELETE`.

---

## Validation Strategy

The admin layer uses dedicated FormRequest classes in `app/Http/Requests`.
Those requests own two concerns: authorization (`authorize()`) and shape/rule
validation (`rules()`).

Common patterns:

- CRUD requests map directly to domain resources, such as users, roles,
  entries, fields, statuses, categories, and media libraries.
- Nested objects have nested request namespaces, such as `Entry\Type`,
  `FieldLayout\Tab`, and `FieldLayout\Tab\Element`.
- Settings validation is generated from settings configuration and current
  domain definitions through `SettingFormRequest`,
  `UpdateDomainSettingsRequest`, and `UpdateUserSettingsRequest`.
- Entry persistence performs an additional type-level validation step through
  the resolved `AbstractEntryType::validate()` method before repository writes.
- Field value writes are routed through service/repository code so scalar and
  relationship fields use the correct backing tables.

This means controller methods should stay thin: validate through FormRequests,
then delegate mutations to action, service, or repository classes.

---

## Bot Blocking, Webhooks, and External Integrations

The codebase includes a bot-blocking layer:

- `BotBlockRequest` middleware.
- `BotBlockServiceProvider`.
- `BbValue` model and bot-block database table.

The project also includes `spatie/laravel-webhook-client` and
`config/webhook-client.php`. Treat webhook behavior as integration-ready
infrastructure unless a concrete webhook profile has been configured and
documented for the installation.

External integration packages currently present include Sanctum, Fortify,
Socialite, Spatie Permission, and Spatie Webhook Client. Media handling is now
native to the application.

---

## Known Gaps and Implementation Status

Refreshed against the live source on 2026-05-26. Items below are real today.

### Resolved since the original 2026-05-07 list

- ✅ `EntryResource` now returns the proper entry shape (`title`, `handle`,
  `status_handle`, `status_is_public`, `published_at`, `fields`, `authors`,
  `categories`).
- ✅ The Entries API has full CRUD landed via `Api\v1\Entries` and
  `Api\v1\EntryGroups` (`index`, `store`, `show`, `update`, `destroy`).
- ✅ The seeded permission set defines both `view user` (admin UI) and
  `read users` (API). Authentication strings align *except* for the
  `Api\v1\User::index` typo described below.
- ✅ Native Media layer is in place (Spatie has been removed). Media is
  `Fieldable`, supports transformations, and now has optional status
  governance — see [Media Status Governance](#media-status-governance).
- ✅ `default_template` is now read by `TemplateRouteDriver` when resolving
  the home page (`/`).

### Still real

- **R-1**: `Html` field type calls `view('_fields.html', $params)` but no
  `resources/views/_fields/html.twig` partial exists — entry forms containing
  an Html field will throw `InvalidArgumentException: View [_fields.html]
  not found`.
- **R-2** *(Resolved 2026-05-26)*: `Admin\Account\Settings::update()`
  now redirects to `route('account.settings')`. Residual cleanup —
  delete the orphan `resources/views/admin/settings/user.twig` view —
  is documented in the R-2 Recommendation entry.
- **R-3**: `Api\v1\User::index()` checks `$this->can('read user')` (singular),
  while the seeded permission is `read users` (plural). All non-super-admin
  callers receive 404 from the API users list endpoint.
- **R-4** *(Resolved 2026-05-26)*: `Admin\Settings\UserSettings`
  deleted. The duplicate-controller decision (Ambivalence A-2) is
  closed in favour of `Admin\Account\Settings`. Residual cleanup —
  delete the orphan `resources/views/admin/settings/user.twig` —
  rolls in with the R-2 fix.
- **R-5**: `Admin\Settings\Domain::index()` is implemented and renders
  `settings.index`, but no route binds it. `GET /admin/settings` is 404.
- **R-6**: `Api\v1\Account@show` returns a placeholder success message
  instead of the authenticated user resource described by its OpenAPI
  annotation. `update`, `updatePassword`, `updateAvatar`, `updateEmail`
  exist as stubs and aren't routed.
- **R-7**: `EntryType.max_depth` and `EntryType.allowed_parent_types` are
  stored, fillable, and cast, but `EntryService` tree methods do not
  enforce them at insertion or move time.
- **R-8**: `app:refresh-tokens` is a scaffold. `TokenRefreshService` is
  implemented; the command just needs wiring plus a schedule entry.
- **R-9**: `config('site.templates.base_path')` and
  `config('site.templates.not_found_template')` are present but not read by
  any route driver. `default_template` IS read.
- **R-10**: `Admin\User\Layout` has six empty `//` methods (`index`,
  `create`, `store`, `edit`, `update`, `destroy`); only `show()` is
  implemented and routed (`users.layouts.show`).
- **R-11**: `Admin\Index` contains unreachable code after an unconditional
  `return redirect('/login');` on line 11. Not registered in any route.
- **R-12**: `Admin\Field::index()` unconditionally `abort(404)`s. Either
  add `->except(['index'])` to the resource registration or delete the
  method.
- **R-13**: `Admin\Role::show()` is empty (`//`).
- **R-14**: `Admin\Dashboard::index` does its own SQL aggregation
  (including `selectRaw`) rather than delegating to a service.
- **R-15**: `EntryService::createTreeNode()` runs **after** the
  `EntryRepository::create` transaction commits. A tree-create failure
  leaves an entry without a tree row.
- **R-16**: Two flash keys, `success` and `status`, are used
  inconsistently across admin controllers. Pick one.

#### Security & hardening (absorbed from `ALPHA_READINESS_REPORT.md`)

The items below were brought forward after a 2026-05-26 verification
pass against `docs/ALPHA_READINESS_REPORT.md`. Items already fixed in
code are not re-listed — see the
[Accuracy Notes Log](#accuracy-notes-log) for the resolved set.

- **R-17 (Critical)**: `UpdateUserPassword::update()` no longer
  validates `current_password` — the Fortify password-change endpoint
  is a one-step takeover from any hijacked session.
- **R-18 (Critical)**: `UserService::syncRoles()` accepts any role
  name. The request-layer guard against assigning `super admin` is in
  place; the service has no defence-in-depth.
- **R-19 (High)**: `LogRequestResponse::handle()` never passes
  `response_payload` into `ApiLog::create()`. Column is always `NULL`
  in production despite `summarizeResponse()` existing.
- **R-20 (High)**: Personal access token is string-concatenated into a
  generic `success` flash, which then survives into the next page's
  flash bag (and any tooling reading it).
- **R-21 (High)**: `EntryTypeRegistry::resolveByHandle()` is
  group-blind. Already mitigated at request + action layers; the
  registry remains a defence-in-depth gap (see Ambivalence A-1).
- **R-22 (High)**: `User::$fillable` includes `status`, `suspended_until`,
  `banned_at`, `locked_until`. Mass-assignment can silently bypass the
  status-change audit log.
- **R-23 (High)**: `config/cors.php` ships with `*` origins, `*`
  methods, `*` headers. Adopt the commented-out env-driven allowlist.
- **R-24 (Medium)**: `Api\v1\User::update()`, `Api\v1\Entries::store()`,
  and `Api\v1\Entries::update()` rely on the FormRequest's
  `authorize()` alone — no controller-level `$this->can(…)` check
  matching the rest of the API surface.
- **R-25 (Medium)**: `UserService::updateToken()` does
  `$token->update($data)` with no key filtering. The current
  `EditUserTokenRequest` only validates `name`, but a non-request
  caller could rewrite `tokenable_id` / `abilities`.
- **R-26 (Medium)**: `users.default_status` and
  `users.social_default_status` settings accept any
  `UserStatus::ALL` value, including `suspended` and `banned` — both
  nonsensical at creation time. Restrict to
  `UserStatus::CREATION_ALLOWED`.
- **R-27 (Low)**: Media library `handle` is not slug-restricted; an
  admin can save `../../etc` and the upload path uses it as a
  directory segment.
- **R-28 (Low)**: Library can be configured to accept `image/svg+xml`
  or `text/html`; on a public disk these become stored XSS. Requires
  admin misconfiguration plus public-disk usage.
- **R-29 (Low)**: `BotBlockRequest` only checks `POST` — future
  unauthenticated `PUT`/`PATCH`/`DELETE` endpoints bypass the block.
- **R-30 (Low)**: `Entry::$fillable` includes `created_by_user_id`.
  Same defence-in-depth argument as R-22.

#### Field Types layer (2026-05-26 audit)

- **R-31 (Medium)**: `AbstractField::validate(mixed $value): bool|string`
  is implemented by nine concrete types but **never invoked** by the
  repository or the FormRequest pipeline. Settings such as
  `strict_options`, `min`/`max` selection counts, `Relationship`
  `limit` / `entry_types`, and library scoping on `FileUpload` /
  `Media` are therefore unenforced.
- **R-32 (Critical)**: `Telephone::$rules` references the validator
  `'telephone'`, which is **not registered**. Any form whose layout
  includes a Telephone field throws `BadMethodCallException` at
  submission time.
- **R-33 (Medium)**: `EmailAddress::$rules` is empty. The field
  type stores arbitrary strings — no `email:` rule is applied.
- **R-34 (Low)**: Several field types declare settings that don't
  reach `getRules()`: `Text::min_length`/`max_length`,
  `Textarea::max_length`, `Number::min`/`max`/`step`,
  `Date::min_date`/`max_date`, `ColorPicker::format`/`alpha`,
  `Relationship::entry_types`. The admin form advertises constraints
  the validator doesn't honour.
- **R-35 (Low)**: `ValidatesAgainstOptions::renderOrphanedValue()` is
  a helper for visibly flagging stale select values; no partial calls
  it. Either wire it into `select.twig` / `multi_select.twig` /
  `radio_group.twig` or delete the method.

See [Recommendations & Remedies](#recommendations--remedies) for proposed
fixes and consequences.

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

| Alias            | Model                       | Fieldable                                    |
|------------------|-----------------------------|----------------------------------------------|
| `entry`          | `App\Models\Entry`          | Scalar + Relational                          |
| `user`           | `App\Models\User`           | Scalar only                                  |
| `category`       | `App\Models\Category`       | Scalar only                                  |
| `media`          | `App\Models\Media`          | Scalar only                                  |
| `entry_group`    | `App\Models\EntryGroup`     | No                                           |
| `entry_type`     | `App\Models\EntryType`      | No                                           |
| `category_group` | `App\Models\Category\Group` | No                                           |
| `field_group`    | `App\Models\Field\Group`    | No                                           |
| `media_library`  | `App\Models\Media\Library`  | No                                           |


## Media Status Governance

Media items can carry the same denormalised status triple as entries. The
feature is **optional** at the library level: a library with a null
`status_group_id` is "ungoverned" and its media rows leave the triple null.

### Schema

- `media_libraries.status_group_id` (nullable FK to `status_groups`,
  `nullOnDelete`) declares the palette available to media owned by the
  library. FK is wired in `2026_05_07_000003_add_media_foreign_keys.php`
  (deferred — `status_groups` does not exist at the original media
  migration timestamp).
- `media.status_id` (nullable FK → `statuses`, `nullOnDelete`),
  `media.status_handle` (string, indexed), `media.status_is_public`
  (boolean, indexed, default false). The triple is added via the
  `Blueprint::statusColumns()` macro registered in
  `AppServiceProvider::register()`.

### Model surface

`Media` uses `App\Traits\HasStatus`, gaining `status(): BelongsTo`,
`scopeWithStatus($handle)`, and `scopePublic()`. `Media::scopePublished()` is
a backward-compatible alias delegating to `scopePublic()` — media has no
`published_at`. `Media\Library` uses `App\Traits\HasStatusGroup`, exposing
`statusGroup`, `statuses`, and `defaultStatus`.

### Upload auto-assignment

`HasMediaItems::addMediaFromUpload(UploadedFile, array $attributes)` resolves
`$library->defaultStatus()` outside the transaction, then merges the status
triple into the create payload as `array_merge([defaults], $statusAttributes, $attributes)`
— caller-supplied attributes win. Ungoverned libraries leave the triple
null. A governed library with no `is_default` status also produces a
triple-null row (silent fallback).

### Validation

`EditMediaRequest::rules()` exposes:

```php
'status' => [
    'nullable', 'string', 'max:100',
    Rule::exists('statuses', 'handle')
        ->where(fn ($q) => $q->where('status_group_id', $library?->status_group_id)),
],
```

A non-null `status` submitted against an ungoverned library returns 422.

### Admin UI

- Library create/edit forms include a "Status Group" dropdown with a
  `— None (ungoverned) —` option.
- The libraries index shows the attached `StatusGroup.name` column
  (or "Ungoverned").
- Media edit ships a "Publishing" card on the right rail with a Status
  dropdown, rendered only when the owning library has a status group.
- The media show page surfaces the current status as a coloured badge.
- The library grid view overlays a small status pill on each tile.

### Known limits

- Reassigning a library's `status_group_id` does **not** auto-migrate
  existing media rows; the triple may go stale if the new group lacks the
  old handle. (Pinned by `tests/Feature/Admin/MediaStatusTest.php`.)
- `FileUpload::validate()` does not filter submitted media IDs by status.
- No `MediaResource` exists yet for API consumption.
- No bulk-status admin UI on the media index.

---

## HasStatus and the Status Sync Registry

`StatusObserver` listens for `Status::updating` and cascades changes to
denormalised consumer columns. The roster of consumers is supplied by
`StatusSyncRegistry`, which each consumer registers itself with via the
`HasStatus` trait's `bootHasStatus()` hook.

### Contract

A model adopting `HasStatus` must:

1. `use HasStatus;` on the model.
2. Declare `status_id`, `status_handle`, `status_is_public` in `$fillable`,
   and cast `status_is_public` to `boolean`.
3. Add a `$table->statusColumns()` macro to its create migration (and a
   deferred FK migration to `statuses` later, à la `media`).
4. Be added to the **force-boot list** in `AppServiceProvider::boot()`
   alongside `Entry` and `Media`. Without this, a queue worker that only
   touches `Status` (no consumer) would find an empty registry.

`bootHasStatus()` in `local`/`testing` runs a contract check and throws
`LogicException` if `$fillable` or the boolean cast is missing. Production
skips the check.

### What the observer does

When a `Status` row's `is_public` or `handle` is dirty on save, the
observer iterates `StatusSyncRegistry::consumers()` and bulk-updates the
denormalised triple on each consumer inside a `DB::transaction`. Consumers
using `SoftDeletes` are queried via `withTrashed()`.

### Current consumers

| Consumer | File | scopePublished |
|---|---|---|
| `Entry`  | `app/Models/Entry.php` | composes `scopePublic()` + `published_at <= now()` |
| `Media`  | `app/Models/Media.php` | aliases `scopePublic()` (no scheduled-publish concept) |

---


## Technical Tutorials

### Adding Custom Fields to Any Model

The custom field layer used by Entries, Categories, and Users is reusable on
any Eloquent model that should store dynamic, admin-defined scalar field values.
The two reusable pieces are:

1. **`Fieldable` trait** (`app/Traits/Field/Fieldable.php`) - adds `fieldValues()`
   (morphMany to `field_values`), `field(string $handle): mixed`, and
   `fieldArray(): array`.
2. **`PersistsFieldValues` trait** (`app/Traits/Field/PersistsFieldValues.php`) -
   adds `setField()` and `setFields()` for writing.

Use this pattern for any model that needs custom fields, such as media,
commerce records, profile-like records, or plugin-owned domain models.
The examples below use `ProductVariant` and `ProductType` as placeholder model
names; replace them with the concrete models in your feature.

### How the Field Layer Works

Every `Fieldable` model stores values in the shared `field_values` table, keyed
on `(field_id, fieldable_id, fieldable_type)`. The `fieldable_type` column holds
the morph alias (`'entry'`, `'user'`, `'media'`, etc.), which is why
`getMorphClass()` must be used for writes.

This layer is for scalar custom fields. Entry relationship fields are a special
entry-only path stored in `entry_relationships`.

### Step 1 - Add a Morph Map Alias

Before writing field values for a new model type, add a stable morph alias in
`AppServiceProvider::boot()`:

```php
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Relations\Relation;

Relation::morphMap([
    // Existing aliases...
    'product_variant' => ProductVariant::class,
]);
```

The alias is what gets stored in `field_values.fieldable_type`. Avoid storing
fully-qualified class names because future namespace changes would break those
rows.

### Step 2 - Add the Fieldable Trait to the Model

Add `Fieldable` to the Eloquent model that should read custom field values:

```php
// app/Models/ProductVariant.php
namespace App\Models;

use App\Traits\Field\Fieldable;
use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    use Fieldable;

    protected $fillable = [
        'name',
        'handle',
    ];
}
```

This adds three methods: `fieldValues(): MorphMany`, `field(string $handle): mixed`,
and `fieldArray(): array`.

### Step 3 - Create Fields and a Field Layout

Run this in a seeder or Artisan command. Use a model-specific prefix, such as
`variant_`, because field handles are globally unique.

```php
use App\Models\Field;
use App\Models\Field\Group as FieldGroup;
use App\Models\Field\Type as FieldType;
use App\Models\FieldLayout;
use App\Models\FieldLayout\Tab;
use App\Models\FieldLayout\TabElement;

$textType = FieldType::where('object', \App\Field\Types\Text::class)->firstOrFail();
$textArea = FieldType::where('object', \App\Field\Types\Textarea::class)->firstOrFail();

$material = Field::firstOrCreate(
    ['handle' => 'variant_material'],
    ['name' => 'Material', 'label' => 'Material', 'field_type_id' => $textType->id]
);
$care = Field::firstOrCreate(
    ['handle' => 'variant_care'],
    ['name' => 'Care Instructions', 'label' => 'Care Instructions', 'field_type_id' => $textArea->id]
);
$color = Field::firstOrCreate(
    ['handle' => 'variant_color'],
    ['name' => 'Color', 'label' => 'Color', 'field_type_id' => $textType->id]
);

$fieldGroup = FieldGroup::firstOrCreate(
    ['handle' => 'variant-details'],
    ['name' => 'Variant Details', 'description' => 'Custom fields for product variants.']
);
$fieldGroup->fields()->syncWithoutDetaching([$material->id, $care->id, $color->id]);

$layout = FieldLayout::create(['name' => 'Variant Field Layout']);
$tab = Tab::create(['field_layout_id' => $layout->id, 'name' => 'Details', 'sort_order' => 1]);

foreach ([$material, $care, $color] as $i => $field) {
    TabElement::create([
        'field_layout_tab_id' => $tab->id,
        'field_id' => $field->id,
        'required' => false,
        'sort_order' => $i + 1,
    ]);
}
```

The layout is useful for admin UI rendering. The storage layer itself only
requires `Field` rows and the `Fieldable` model.

### Step 4 - Write Field Values

`$model->getMorphClass()` returns the registered morph alias. That alias is the
correct value for `fieldable_type`.

```php
use App\Models\Field;
use App\Models\FieldValue;
use App\Models\ProductVariant;
use App\Traits\Field\PersistsFieldValues;

// Option A - via PersistsFieldValues in a service
class ProductVariantFieldService
{
    use PersistsFieldValues;

    public function saveFields(ProductVariant $variant, array $fields): void
    {
        $this->setFields($variant, $fields);
    }
}

$service = new ProductVariantFieldService();
$service->saveFields($variant, [
    'variant_material' => 'Organic cotton',
    'variant_care' => 'Machine wash cold.',
    'variant_color' => 'Blue',
]);

// Option B - write directly (mirrors what PersistsFieldValues does internally)

$field = Field::where('handle', 'variant_material')->firstOrFail();

FieldValue::updateOrCreate(
    [
        'field_id' => $field->id,
        'fieldable_id' => $variant->getKey(),
        'fieldable_type' => $variant->getMorphClass(), // 'product_variant'
    ],
    [$field->fieldType->instance()->storageColumn() => 'Organic cotton']
);
```

In a controller or action, write fields after the model has been saved:

```php
use App\Traits\Field\PersistsFieldValues;

class UpdateProductVariant
{
    use PersistsFieldValues;

    public function update(ProductVariant $variant, array $input): ProductVariant
    {
        $variant->fill($input)->save();

        if (!empty($input['fields'])) {
            $this->setFields($variant, $input['fields']);
        }

        return $variant->refresh();
    }
}
```

### Step 5 - Read Field Values

```php
use App\Models\ProductVariant;

// Single item - eager-load the full field chain
$variant = ProductVariant::with('fieldValues.field.fieldType')->findOrFail($id);

echo $variant->field('variant_material'); // 'Organic cotton'
echo $variant->field('variant_care');     // 'Machine wash cold.'
echo $variant->field('variant_color');    // 'Blue'

// As an associative array
$variant->fieldArray();
// ['variant_material' => '...', 'variant_care' => '...', 'variant_color' => '...']

// Collection - avoid N+1 with loadMissing
$variants = ProductVariant::query()->get();
$variants->loadMissing('fieldValues.field.fieldType');

foreach ($variants as $variant) {
    echo $variant->field('variant_material');
}
```

### Step 6 - Attach Field Groups to the Owning Configuration Model

If the model type has an owning configuration model, attach field groups there.
Existing examples include:

| Fieldable records | Configuration owner | Attachment relation |
|-------------------|---------------------|---------------------|
| `Entry`           | `EntryGroup`        | `fieldGroups()`     |
| `Category`        | `CategoryGroup`     | `fieldGroups()`     |
| `User`            | `UserSchema`        | `fieldGroups()`     |
| `Media`           | `Media\Library`     | `field_groups()`    |

For a new model family, create the equivalent owner relation only if the admin
UI needs to know which fields are available for that model type. The underlying
`field_values` storage does not require an owner relation.

```php
use App\Models\Field\Group as FieldGroup;
use App\Models\ProductType;

$type = ProductType::where('handle', 'shirts')->firstOrFail();
$fieldGroup = FieldGroup::where('handle', 'variant-details')->firstOrFail();

$type->fieldGroups()->syncWithoutDetaching([$fieldGroup->id]);

// Inspect attached field groups (for admin UI rendering)
$type->load('fieldGroups.fields.fieldType');
foreach ($type->fieldGroups as $group) {
    foreach ($group->fields as $field) {
        echo $field->handle; // 'variant_material', 'variant_care', ...
    }
}
```

### Morph Map Note

`entry`, `category`, `user`, and `media` are already registered in
`AppServiceProvider`. New fieldable model types need their own alias before
field values are written. If old code wrote a fully-qualified class name into
`field_values.fieldable_type`, those rows will not resolve through the morph
map until converted to the registered alias.

---


## Recommendations & Remedies

The items below are the still-real Known Gaps with proposed fixes and the
trade-offs of implementing each one. They're paired by R-number with
[Known Gaps and Implementation Status](#known-gaps-and-implementation-status)
so we can pick them up in any order. **No code has been changed yet.**

**R-1 through R-16** came out of the 2026-05-26 documentation refresh.
**R-17 through R-30** were absorbed from `docs/ALPHA_READINESS_REPORT.md`
after a verification pass on the same day — items the alpha report
flagged that the live code has not yet resolved. **R-31 through R-35**
came out of a focused 2026-05-26 audit of `app/Field/Types/*.php` and
the related write pipeline (see
[Per-type reference](#per-type-reference) for the data behind each
item). Items the alpha report flagged that **have** since been fixed
(C-2 upload permission, H-1 OAuth session regeneration + throttling,
H-3 redirect-URL scheme validation, H-7 HTML Purifier on the write
path, M-1 category-group membership rule, L-1 admin-namespace
clobber, L-6 configurable redirect status, plus the four "Info" docs
items) are not listed here. They are recorded in the
[Accuracy Notes Log](#accuracy-notes-log) so the provenance is
visible without bloating the active list.

Severity legend:

- **Critical** — active security defect or data-integrity break. Fix before
  any external eye sees the build.
- **High** — exploitable under realistic conditions, current user paths
  surface 500/4xx, or silent permission failures. Fix in Alpha week.
- **Medium** — broken in normal use but not on a default path; or latent
  bugs visible under planned features.
- **Low** — style, hygiene, dead-code cleanup, or conditional risk (only
  fires under specific opt-in configurations).

### R-1 — Missing `_fields/html.twig` partial (High)

**Symptom.** Rendering any field layout that contains an `Html` field will
throw `InvalidArgumentException: View [_fields.html] not found`. The
`Html` field type IS seeded (see `FieldTypeSeeder`), but the partial it
calls (`view('_fields.html', $params)`) does not exist.

**Fix options.**

A. Create `resources/views/_fields/html.twig` modelled on
   `_fields/textarea.twig`, then layer a rich-text editor on top (TinyMCE,
   CKEditor, or just a `<textarea>` with `class="js-html-editor"`).
B. Temporarily change `Html::render()` to delegate to
   `_fields/textarea.twig` until a proper editor lands.
C. Remove `Html` from `FieldTypeSeeder` until the partial is ready.

**Recommendation.** Option A. The settings form (`toolbar`, `allowed_tags`)
already exists and `Html::prepareForStorage()` already runs Purifier. A
plain `<textarea>` partial unblocks development immediately; the editor
can be swapped in later.

**Consequences.**

- Pro: unblocks every entry form that wants rich text.
- Con (Option A): commit timing — the editor choice changes the partial.
  Pick a "good enough" textarea now; iterate.
- Con (Option B/C): kicks the can.

### R-2 — Broken `route('settings.user')` redirect (Resolved 2026-05-26)

> **Status: Resolved.** `Admin\Account\Settings::update()` now redirects
> to `route('account.settings')`. Verified against
> `app/Http/Controllers/Admin/Account/Settings.php` lines 56–58. The
> user-preferences form completes cleanly on save.
>
> **Residual follow-up (Low).** The orphan view
> `resources/views/admin/settings/user.twig` is still present. No
> route binds it, so it's dead code today, but its body contains two
> `route('settings.user')` and `route('settings.user.update')` calls
> that would 500 if anyone ever did render it. Recommend deleting it
> in the next cleanup pass:
>
> ```bash
> rm resources/views/admin/settings/user.twig
> ```

### R-3 — `Api\v1\User::index` permission typo (High)

**Symptom.** `GET /api/v1/users` returns `404` for every non-super-admin
caller because `index()` checks `$this->can('read user')` (singular). No
such seeded permission exists; `show()` and `destroy()` use the correct
strings.

**Fix.** Change line 60 of `app/Http/Controllers/Api/v1/User.php` from
`'read user'` to `'read users'`.

**Consequences.**

- Pro: the API works as documented.
- Con: anyone currently relying on the silent-404 behaviour as a feature
  flag will see lists they didn't see before. Audit consumers first; in
  practice the gate is currently effectively "super admin only".

### R-4 — Duplicate user-settings controller, one unrouted (Resolved 2026-05-26)

> **Status: Resolved.** `Admin\Settings\UserSettings` has been deleted.
> `Admin\Account\Settings` is now the single routed user-settings stack.
> Verified: no remaining `App\Http\Controllers\Admin\Settings\UserSettings`
> imports anywhere in `app/`, `routes/`, or `resources/`.
>
> **Residual cleanup (Low).** The orphan view
> `resources/views/admin/settings/user.twig` still exists and contains
> two `route('settings.user')` calls. No route binds it, so it's
> harmless today, but it should be deleted to remove the trip hazard.
> R-2 (the redirect-target fix that was originally the natural companion
> to this deletion) has since been completed independently. See
> [Accuracy Notes Log](#accuracy-notes-log) item 18.

### R-5 — `Admin\Settings\Domain::index` unrouted (Low)

**Symptom.** `Admin\Settings\Domain::index()` renders `settings.index`,
but `routes/admin.php` only registers `settings.show` and `settings.update`.
`GET /admin/settings` is 404.

**Fix options.**

A. Add `Route::get('settings', [SettingsDomain::class, 'index'])->name('settings');`
   in `routes/admin.php`.
B. Delete the unused `index()` method and `resources/views/admin/settings/index.twig`.

**Recommendation.** Option A. The view already exists and presents a
sensible domain list. Wire it.

**Consequences.**

- Pro: admins can browse domains without typing a handle into the URL.
- Con: trivial — the navigation link needs to point at the new route.

### R-6 — `Api\v1\Account` placeholder stubs (Medium)

**Symptom.** Only `show` is routed; it returns
`response()->json(['message' => 'Profile updated successfully'])` rather
than the authenticated user resource. `update`, `updatePassword`,
`updateAvatar`, `updateEmail` exist as stubs but aren't routed.

**Fix.** Replace `show()` with `return new UserResource($request->user())`.
Wire the four `update*` routes through dedicated FormRequests (mirroring
the admin Account controller pattern), then back each with a `Users::*`
service call.

**Consequences.**

- Pro: external consumers can self-service.
- Con: every `update*` endpoint needs OpenAPI annotations, FormRequest
  validation, and tests. Probably a dedicated mini-project rather than
  a one-line patch.

### R-7 — `EntryType.max_depth` / `allowed_parent_types` unenforced (Medium)

**Symptom.** Both columns are stored, fillable, and cast on `EntryType`
(`allowed_parent_types` as `array`), but `EntryService::createTreeNode()`
and `moveTreeNode()` never read them. Admins can set them in the form
and the values do nothing.

**Fix.** In `EntryService::createTreeNode()` and `moveTreeNode()`, after
resolving the target parent:

1. Compute depth-after-insert; reject if `> entryType.max_depth`.
2. If `entryType.allowed_parent_types` is non-empty, require the new
   parent's entry's type handle to be in that list.

Both checks belong in the existing `treeAssert*` helper neighbourhood.

**Consequences.**

- Pro: admins get the constraint they configured.
- Con: any existing tree rows that already violate the constraints will
  block subsequent moves until the constraints are relaxed or the
  rows are repaired. Migration plan: scan first, then enforce.

### R-8 — `app:refresh-tokens` is a scaffold (Medium)

**Symptom.** `handle()` is empty except for commented-out example code.
`TokenRefreshService` is implemented but never called by the command.
`routes/console.php` does not schedule it either.

**Fix.** Implement `handle()` to iterate active, non-revoked
`OauthToken` rows whose `expires_at` is within a configurable window
(e.g. 1 hour) and call `app(TokenRefreshService::class)->tryRefresh($t)`
on each. Add a schedule entry in `routes/console.php` (every 15 minutes
is fine for most providers).

**Consequences.**

- Pro: long-lived OAuth integrations don't grind to a halt every hour.
- Con: a refresh storm against an upstream provider could trip rate
  limits. The `tryRefresh` already swallows individual failures; cap the
  batch size and use a jittered window.

### R-9 — `site.templates.base_path` / `not_found_template` unread (Low)

**Symptom.** Both keys exist in `config/site.php` but neither is consulted
by `TemplateRouteDriver` or `EntryTreeRouteDriver`. `default_template`
IS read.

**Fix options.**

A. Wire `base_path` into `TemplateRouteDriver::viewName()`
   (prefix the namespace prefix) and `not_found_template` into the
   `return null` branches of both drivers.
B. Remove both keys from `config/site.php`.

**Recommendation.** Option A. The keys exist because they were always
intended to work; the driver was just never finished. Implementing them
gives admins a 404 surface they can theme.

**Consequences.**

- Pro: brandable 404s, configurable template root.
- Con: introducing a real `not_found_template` means the public site
  starts returning 200 (or a custom code) instead of bubbling 404
  upstream. Match the response code to expectations.

### R-10 — `Admin\User\Layout` is mostly empty (Low)

**Symptom.** Six of seven methods are `//` stubs. Only `show()` is wired.

**Fix.** Use `Route::get('users/layouts', [UserLayout::class, 'show'])`
(already present) and delete the unused methods. The actual layout edit
flow goes through the generic `FieldLayout` admin (`Admin\FieldLayout`)
because the user layout is just a `FieldLayout` instance.

**Consequences.**

- Pro: less misleading IDE navigation.
- Con: minor commit churn.

### R-11 — `Admin\Index` is dead code (Low)

**Symptom.** First line of `index()` is `return redirect('/login');`;
everything after is unreachable (including a `Rest\Client` call,
`print_r`, and commented-out user-token spelunking). No route binding.

**Fix.** Delete `app/Http/Controllers/Admin/Index.php`. If anything
ever imported it, the IDE will flag it.

**Consequences.**

- Pro: cleaner directory.
- Con: none expected.

### R-12 — `Admin\Field::index` returns 404 (Low)

**Symptom.** `GET /admin/fields` is reachable as a 404 because
`Route::resource('fields', Field::class)` registers the route but the
controller `abort(404)`s.

**Fix.** Either:

A. Change the resource registration to
   `Route::resource('fields', Field::class)->except(['index']);`
   and delete the `index()` method.
B. Implement a real fields-by-group landing page (probably overlaps with
   the existing `Admin\Field\Group`).

**Recommendation.** Option A for now — fields are managed inside their
group, not as a flat list.

**Consequences.**

- Pro: less surprise on `route:list`.
- Con: `Route::resource` defaults change subtly; double-check that no
  other action depends on the resource macro creating the `index` route
  name.

### R-13 — `Admin\Role::show()` is empty (Low)

**Symptom.** Method exists, has `//` only.

**Fix.** Either implement a role detail page (showing assigned users
and permissions) or `->except(['show'])` the resource.

**Consequences.** Minor.

### R-14 — `Admin\Dashboard` does raw SQL aggregation (Low)

**Symptom.** Aggregates dashboard counters inline, including a
`selectRaw` for top API routes. The only admin controller doing direct
query work.

**Fix.** Extract to a `DashboardService`. Cache the result for 1 minute
under `dashboard.counters`.

**Consequences.**

- Pro: testable, mockable, cacheable.
- Con: minor reorg.

### R-15 — Tree-create runs after the entry transaction commits (Medium)

**Symptom.** `EntryService::create()` calls
`EntryRepository::create($entryType, $data)` (which opens its own
transaction and commits before returning), then calls
`$this->createTreeNode(...)` *afterwards*. If `createTreeNode` throws,
the entry exists without a tree row — meaning no public URL.

**Fix options.**

A. Move the `createTreeNode` call inside `EntryRepository::create`'s
   transaction. Requires injecting `EntryService` into the repository
   or duplicating the tree validation logic — non-trivial.
B. Wrap `EntryService::create` itself in a `DB::transaction` that
   contains both the repository call and the tree call. Easier; the
   inner repository transaction nests harmlessly.
C. Dispatch the tree creation as a retryable queued job. Reasonable if
   tree creation is allowed to be eventually-consistent.

**Recommendation.** Option B. Atomic, smallest change.

**Consequences.**

- Pro: no half-saved entries.
- Con: tree validation errors now roll back the entry save (which may
  feel surprising to admins who expect "entry saved, fix tree later").
  Provide a clear error message and a path to retry.

### R-16 — Two flash keys (`success` vs `status`) (Low)

**Symptom.** Some admin controllers use `->with('success', '...')`;
others use `->with('status', '...')`. The Twig `_message.twig` partial
reads both, so it works either way, but the inconsistency is
gratuitous.

**Fix.** Standardise on `success` (it's the dominant pattern). Update
the message partial to read only that key, then sweep controllers.

**Consequences.** Minor.

---

### Security & Hardening (Alpha review pass)

The items below — **R-17 through R-30** — were brought forward from
`docs/ALPHA_READINESS_REPORT.md` after a verification pass against the
live code on 2026-05-26. Items resolved since the alpha report was
filed (C-2 upload permission, H-1 OAuth session regeneration + throttle,
H-3 redirect-URL scheme validation, H-7 HTML Purifier on the write
path, M-1 category-group membership, L-1 admin-namespace clobber, L-6
configurable redirect status, and four "Info" docs items) are **not**
re-listed here — they are tracked in the
[Accuracy Notes Log](#accuracy-notes-log) instead.

Severity here uses the same legend as above, with **Critical** added
for active security defects that should block any external
sign-up link being shared.

---

### R-17 — `UpdateUserPassword` skips the current-password check (Critical)

**Symptom.** `app/Actions/User/UpdateUserPassword.php` implements
Fortify's `UpdatesUserPasswords` contract, but the validation block that
should require the current password is gone — the action just calls
`UserService::setPassword($user, $input['password'])`. Fortify's
`/user/password` endpoint is therefore a one-step account-takeover from
any hijacked session: no current-password challenge, no minimum-length
guard, no complexity rules.

`FortifyServiceProvider` binds this action via
`Fortify::updateUserPasswordsUsing(UpdateUserPassword::class)`, so this
covers every front-channel password change.

**Fix.** Re-introduce the `Validator::make([...])` block that the
class header still hints at:

```php
public function update(User $user, array $input): void
{
    Validator::make($input, [
        'current_password' => ['required', 'string', 'current_password:web'],
        'password' => $this->passwordRules(),
    ], [
        'current_password.current_password'
            => __('The provided password does not match your current password.'),
    ])->validateWithBag('updatePassword');

    app(UserService::class)->setPassword($user, $input['password']);
}
```

If the admin-driven "reset another user's password" flow needs to
skip the current-password challenge, route that flow through the
existing `ResetUserPassword` action (which is already current-check-free)
and gate `PasswordUserRequest::authorize()` on
`manage user status` (or a new `reset user password` permission) rather
than `edit user`.

**Consequences.**

- Pro: closes the open back door on every password-update path.
- Con: any test or seeder that calls `Users::setPassword(...)` directly
  is unaffected (correctly — that's the admin path), but any test that
  POSTs to `/user/password` without `current_password` will now fail.
  Sweep tests and fix any that were exercising the broken path.

---

### R-18 — Role assignment hardening: service-layer defence-in-depth (Critical)

**Symptom.** The request-layer fix is in: `StoreUserRequest::rules()`
and `EditUserRequest::rules()` both use
`Rule::in($this->assignableRoleNames())`, where the helper excludes
`super admin` for non-super-admin actors. Good. But
`UserService::syncRoles()` accepts any role names that survive
validation, so any bypass of the FormRequest (a future endpoint that
takes raw `Request $request`, a queue job, a seeder, etc.) would still
allow privilege escalation.

**Fix.** Belt-and-braces guard inside the service:

```php
// app/Services/UserService.php
public function syncRoles(User $user, array $roles): User
{
    $actor = auth()->user();

    if (in_array('super admin', $roles, true) && ! $actor?->hasRole('super admin')) {
        throw new \Illuminate\Auth\Access\AuthorizationException(
            'Only a super admin may assign the super admin role.'
        );
    }

    $user->syncRoles($roles);

    return $user;
}
```

The same guard belongs around `assignRoles()` for symmetry.

**Consequences.**

- Pro: privilege escalation requires both layers to be broken; the
  service throws cleanly with a translatable message.
- Con: console/seeder code that legitimately assigns `super admin`
  outside a request context (e.g. `UsersSeeder`) will need to either
  authenticate a super-admin actor first, or call `$user->syncRoles(...)`
  directly. Document the seeded escape hatch where it lands.

---

### R-19 — `LogRequestResponse` never writes `response_payload` (High)

**Symptom.** `api_logs.response_payload` is declared in the schema and
`LogRequestResponse::summarizeResponse()` is implemented, but the
middleware never passes the value into `ApiLog::create([...])`. Every
row goes in with `response_payload = NULL`. The doc claims this column
carries the rendered body or error summary.

**Fix.** Add the field to the `ApiLog::create` payload:

```php
ApiLog::create([
    'request_route' => $request->getPathInfo(),
    'method' => $request->method(),
    'user_id' => Auth::id(),
    'request_payload' => $this->encodeForLog($this->sanitizeValue($request->all())),
    'request_headers' => $this->encodeForLog($this->sanitizeHeaders($request->headers->all())),
    'response_payload' => $this->summarizeResponse($response),
    'response_headers' => $this->encodeForLog($this->sanitizeHeaders($response->headers->all())),
    'response_status_code' => $response->status(),
]);
```

The dead `summarizeResponse()` method already redacts sensitive keys via
`sanitizeValue()` and truncates with `truncate()`, so no new sanitiser
is needed.

**Consequences.**

- Pro: postmortems on production 500s can read the rendered body. Bug
  reports gain a useful payload.
- Con: storage cost goes up. Today the table prunes at 90 days via
  `model:prune`; verify the schedule entry is still active before
  enabling this so logs don't grow without bound.

---

### R-20 — Personal access token flashed in URL/session (High)

**Symptom.** `Admin\User\Token::store()` and the analogous
`Admin\Account\Token` flow build a success-flash by string-concatenating
the plain-text token onto a generic message:

```php
return redirect()->route('users.edit', $user)
    ->with('success', __('user.token_created') . ' - ' . $token);
```

The plain-text token ends up in the next page's flash banner. From
there it leaks into anything that captures session flash data
(Telescope/Debugbar, Sentry breadcrumbs, screenshots, in-page logs).
`LogRequestResponse` already lists `plain_text_token` and
`one_time_token` in its sensitive-keys allowlist, but the current
controller doesn't use either key — it concatenates the secret into a
generic `success` string that the middleware can't detect.

**Fix.** Show the plain-text token exactly once on a dedicated view,
behind a one-shot session pull:

```php
// store()
return redirect()
    ->route('users.token.show_once', [
        'id' => $user->id,
        'token_id' => $newAccessToken->accessToken->id,
    ])
    ->with('one_time_token', $newAccessToken->plainTextToken);

// new showOnce() action
public function showOnce(string $id, string $token_id)
{
    $token = session()->pull('one_time_token'); // pull = read + forget

    if (! $token) {
        return redirect()->route('users.edit', $id)
            ->with('failure', trans('user.token_already_revealed'));
    }

    return $this->view('users.tokens.show_once', [
        'plain_text_token' => $token,
        'user' => Users::find((int) $id),
    ]);
}
```

The sensitive-keys allowlist (`plain_text_token`, `one_time_token`)
already redacts these out of `api_logs` if they ever leak into a
request body, so the middleware-side defence is already in place.

**Consequences.**

- Pro: the secret appears once, on a page the user must keep open, and
  is never present in the flash bag on subsequent pages.
- Con: the create flow gets one extra redirect and a new view. If the
  user closes the show-once page before copying the token, they have
  to regenerate. That's the same property `composer create-project`
  has when it emits an APP_KEY — acceptable tradeoff for the security
  win.

---

### R-21 — `EntryTypeRegistry::resolveByHandle` is group-blind (High, defence-in-depth)

**Symptom.** The user-facing surfaces are now safe — `StoreEntryRequest`
constrains `type_handle` to the route's `group_id` via a closure rule,
and `CreateNewEntry::create()` resolves the `EntryType` row by
`(handle, entry_group_id)` before passing the handle on to
`Content::create()`. Both layers will reject a cross-group submission.

However, `EntryTypeRegistry::resolveByHandle()` still does a global
`EntryType::where('handle', $handle)->firstOrFail()`. Any future caller
that bypasses the action and goes straight to `Content::create($handle, …)`
— a queue job, a console command, a different controller — would resolve
to whichever row happens to match the handle globally. With today's
seeded data the handles are globally unique so the issue is invisible.

This overlaps with **Ambivalence A-1**.

**Fix.** Pick one of three:

A. **(Smallest)** Add an optional `?int $entryGroupId = null` parameter
   to `EntryTypeRegistry::resolveByHandle()` and apply it as a
   `where('entry_group_id', $entryGroupId)` filter when present. The
   action passes the group ID through; legacy callers continue to work.

B. **(Cleanest)** Add `Content::create(EntryType $record, array $data)`
   and `EntryTypeRegistry::resolveByRecord(EntryType $record)` as the
   preferred public API. Deprecate the handle-only form.

C. **(Heaviest)** Make `entry_types.handle` globally unique with a
   schema migration. Closes the loophole but loses the
   `(entry_group_id, handle)` flexibility.

**Recommendation.** Option A for Alpha; Option B at the next refactor;
Option C only if the team genuinely wants global handle uniqueness.

**Consequences.**

- Pro: closes the indirection so future callers can't accidentally
  resolve to the wrong type.
- Con: caches keyed by handle in the registry have to either become
  `(handle, group_id)` keyed, or accept that they no longer fully
  short-circuit lookups for cross-group requests. Negligible perf cost.

---

### R-22 — `User::$fillable` exposes status columns to mass-assignment (High)

**Symptom.** `User::$fillable` includes `status`, `suspended_until`,
`banned_at`, and `locked_until`. The `UserService` methods that need
to write these columns use `forceFill()` (correct), but the fillable
also means any unguarded `User::create($request->validated())` call
or `$user->update(...)` from a new endpoint can mass-assign them. The
careful audit-log + event-firing infrastructure (`UserStatusChanged`,
`WriteUserStatusLog`) is then silently bypassed.

**Fix.** Narrow `$fillable` to the safely-assignable columns:

```php
protected $fillable = [
    'name',
    'email',
    'password',
];
```

Then update `UserService::create()` to `forceFill` the status:

```php
private function buildUserAttributes(array $data): array
{
    $attributes = Arr::only($data, ['name', 'email', 'password', 'status']);

    if (! empty($attributes['password'])) {
        $attributes['password'] = Hash::make($attributes['password']);
    }

    if (empty($attributes['status'])) {
        $attributes['status'] = app(Settings::class)->get('users', 'default_status')
            ?? UserStatus::ACTIVE;
    }

    return $attributes;
}

public function create(array $data): User
{
    $attributes = $this->buildUserAttributes($data);

    $user = new User();
    $user->forceFill($attributes)->save();
    // ... roles / fields / is_author handling unchanged
}
```

Update `firstOrCreateFromSocial()` similarly.

**Consequences.**

- Pro: status transitions are forced through `setStatus()`,
  `suspend()`, `lockUser()` — the only paths that fire events and
  write `user_status_logs`.
- Con: any existing seeder or factory that relies on mass-assigning
  `status` will need either `->forceFill(['status' => ...])` or a
  call into `UserService::setStatus()`. Sweep factories + seeders.

---

### R-23 — CORS is wide-open (`*` origins, `*` methods, `*` headers) (High)

**Symptom.** `config/cors.php` ships with:

```php
'paths' => ['api/*', 'sanctum/csrf-cookie'],
'allowed_methods' => ['*'],
'allowed_origins' => ['*'],
'allowed_headers' => ['*'],
```

`supports_credentials` is `false`, so cookie-based exfiltration is
blocked. But any third-party origin can issue Bearer-token requests
against the API. A leaked customer token is a free key to any rogue
site that wants to use it.

The file already carries a commented-out env-driven allowlist block
at the bottom — it just hasn't been adopted.

**Fix.** Adopt the env-driven config:

```php
'paths' => ['api/*', 'sanctum/csrf-cookie'],
'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
'allowed_origins' => array_filter(explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))),
'allowed_origins_patterns' => array_filter(explode(',', (string) env('CORS_ALLOWED_ORIGINS_PATTERNS', ''))),
'allowed_headers' => ['Accept', 'Authorization', 'Content-Type', 'X-Requested-With', 'X-XSRF-TOKEN'],
'exposed_headers' => [],
'max_age' => 600,
'supports_credentials' => filter_var(env('CORS_SUPPORTS_CREDENTIALS', false), FILTER_VALIDATE_BOOLEAN),
```

```dotenv
CORS_ALLOWED_ORIGINS=https://app.example.com,https://docs.example.com
```

If a future "public read-only" API surface is wanted, split it into
its own `paths` entry with `*` origins.

**Consequences.**

- Pro: the API is no longer a cross-origin free-for-all.
- Con: any current consumer (the admin SPA, customer integrations) is
  now origin-gated. Inventory and add every legitimate origin to
  `CORS_ALLOWED_ORIGINS` before flipping the config. Local
  development needs `http://127.0.0.1:8000` etc. — coordinate with
  the Vite config.

---

### R-24 — API mutator endpoints rely solely on FormRequest authorize (Medium)

**Symptom.**

- `Api\v1\User::update()` — no `$this->can(...)` check; gate is only in
  `EditUserRequest::authorize()`.
- `Api\v1\Entries::store()` — no `$this->can(...)` check; gate only in
  `StoreEntryRequest`.
- `Api\v1\Entries::update()` — same pattern.

The peer methods (`Api\v1\User::store()`, `::show()`, `::destroy()`,
and `Api\v1\Entries::show()`/`destroy()`) all gate explicitly with
`$this->can(...)`. The asymmetry is brittle: a future refactor that
swaps the FormRequest for `Request $request` (e.g. to relax
validation) silently turns these into public endpoints.

**Fix.** Add a matching controller-level guard to each:

```php
public function update(EditUserRequest $request, int $user): UserResource
{
    if (! $this->can('edit user')) {
        abort(403);
    }
    // ... rest unchanged
}

public function store(StoreEntryRequest $request): JsonResponse
{
    if (! $this->can('create entry')) {
        abort(403);
    }
    // ...
}

public function update(EditEntryRequest $request, int $group_id, int $entry): EntryResource
{
    if (! $this->can('edit entry')) {
        abort(403);
    }
    // ...
}
```

**Consequences.**

- Pro: defence-in-depth matches the rest of the API surface.
- Con: the gate now fires twice per request (FormRequest + controller).
  Negligible perf cost; if it becomes noisy, extract a `requireCan()`
  base helper.

---

### R-25 — `UserService::updateToken()` accepts arbitrary fields (Medium)

**Symptom.** `UserService::updateToken(User $user, $tokenId, array $data)`
calls `$token->update($data)` with no key filtering.
`EditUserTokenRequest::rules()` currently validates only `name`, so in
practice `$request->validated()` only carries `name` — the bug is
latent today. But a future caller (queue job, seeder, console) that
passes raw user input could rewrite `tokenable_id` (move the token to
another user) or `abilities` (escalate to `['*']`).

**Fix.** Filter at the service layer:

```php
public function updateToken(User $user, int|string $tokenId, array $data): ?PersonalAccessToken
{
    $token = $this->getToken($user, $tokenId);

    if (! $token instanceof PersonalAccessToken) {
        return null;
    }

    $allowed = Arr::only($data, ['name', 'abilities', 'expires_at']);

    if (isset($allowed['abilities']) && ! is_array($allowed['abilities'])) {
        $allowed['abilities'] = [];
    }

    $token->update($allowed);

    return $token->refresh();
}
```

If product genuinely wants admin-editable abilities/expiry, extend
`EditUserTokenRequest::rules()` with explicit typed rules
(`abilities` array of strings, `expires_at` date) and keep the
service-layer filter as defence-in-depth.

**Consequences.**

- Pro: token mutation can't accidentally rewrite ownership or
  abilities through future code paths.
- Con: any caller currently passing extra keys (none today) will
  see them silently dropped. Document the contract.

---

### R-26 — `users.default_status` / `social_default_status` accept post-creation statuses (Medium)

**Symptom.** Both settings validate values against
`Rule::in(UserStatus::ALL)`, which includes `suspended` and `banned`.
Those statuses are nonsensical as defaults for newly-created accounts
and the `UserStatus::CREATION_ALLOWED` constant
(`active, inactive, pending`) exists specifically to describe the
valid creation-time set. An admin can save `suspended` as the default
today and every subsequent registration creates a user who can't log
in.

**Fix.** Tighten the `rules` array in `config/settings.php`:

```php
'default_status' => [
    'handle' => 'default_status',
    // ...
    'rules' => ['required', Rule::in(UserStatus::CREATION_ALLOWED)],
    // options_callback unchanged
],
'social_default_status' => [
    'handle' => 'social_default_status',
    // ...
    'rules' => ['required', Rule::in(UserStatus::CREATION_ALLOWED)],
],
```

The `options_callback` for both fields currently emits every status —
restrict the callback to `CREATION_ALLOWED` so the admin select
dropdown can't even present `suspended` / `banned` as choices.

**Consequences.**

- Pro: "auto-approve all OAuth signups" stops being a typo away from
  "auto-suspend everything."
- Con: nothing — `suspended` / `banned` were never useful at creation
  time; the only behaviour change is the admin form rejects the
  invalid choice with a clear error.

---

### R-27 — Media library handle is not slug-restricted (Low)

**Symptom.** `StoreMediaLibraryFormRequest::rules()` validates `handle`
as `['required', 'string', 'max:255', Rule::unique(...)]` with no
character constraint. `HasMediaItems::addMediaFromUpload()` then uses
the handle directly as the storage folder name via
`storeAs($this->handle, $fileName, $disk)`. An admin can save a
library with handle `../../etc` and place files outside the disk root.

Impact is bounded by the disk visibility today (`local` is private,
not web-served), but on the `public` disk this becomes a path-traversal
that serves arbitrary disk contents at `storage/app/public/../...`.

**Fix.** Two layers — request validation plus a runtime guard:

```php
// StoreMediaLibraryFormRequest::rules()
'handle' => [
    'required',
    'string',
    'max:255',
    'regex:/^[a-z0-9][a-z0-9_-]*$/',
    Rule::unique('media_libraries', 'handle')->ignore($library),
],
```

```php
// HasMediaItems::addMediaFromUpload(), before storeAs
$folder = preg_replace('/[^a-z0-9_-]/i', '', (string) $this->handle);

if ($folder === '' || $folder !== $this->handle) {
    throw new \InvalidArgumentException(
        "Library handle [{$this->handle}] is not a valid storage folder name."
    );
}
```

`EditMediaLibraryRequest` inherits from the store request, so the
regex applies to both create and edit.

**Consequences.**

- Pro: closes the path-traversal vector regardless of disk visibility.
- Con: any existing library row with a "weird" handle (none in the
  seed data, but possible in dev installs) becomes uneditable until
  the handle is normalised. A short data migration covers it.

---

### R-28 — SVG / HTML uploads on a public disk are stored-XSS vectors (Low, conditional)

**Symptom.** `UploadMediaRequest::rules()` builds its `mimetypes:`
allowlist from the owning library's `allowed_types`. If an admin
explicitly allows `image/svg+xml` or `text/html` on a library backed
by the **public** disk, an uploaded `evil.svg` containing inline
`<script>` is served with the matching `Content-Type` and runs as
top-level navigation in the visitor's browser — classic stored XSS on
the application origin.

Default seeded libraries do not allow these MIME types, so the bug is
opt-in. Listed Low because it requires both an admin misconfiguration
**and** a public disk.

**Fix.** Two options:

A. **(Conservative)** Reject SVG and HTML uploads outright in
   `UploadMediaRequest::rules()` regardless of library config:

```php
'file' => [
    'required',
    'file',
    'not_in_mimetypes:image/svg+xml,text/html,application/xhtml+xml',
    // ... library-driven rules still apply
],
```

   …registered via a custom `Validator::extend('not_in_mimetypes', …)`
   in `AppServiceProvider::boot()`.

B. **(Permissive)** Allow SVG via `enshrined/svg-sanitize` on the
   upload path:

```bash
composer require enshrined/svg-sanitize
```

```php
// in HasMediaItems::addMediaFromUpload(), before storeAs
if ($file->getMimeType() === 'image/svg+xml') {
    $sanitizer = new \enshrined\svgSanitize\Sanitizer();
    file_put_contents(
        $file->getRealPath(),
        $sanitizer->sanitize(file_get_contents($file->getRealPath()))
    );
}
```

   …and pair it with `Content-Disposition: attachment` for
   HTML/`text/*` mimetypes (or just deny those outright).

**Recommendation.** Option B for SVG (admins ask for it), Option A
for HTML/XHTML.

**Consequences.**

- Pro: removes the XSS vector even from misconfigured public-disk
  libraries.
- Con: legitimate SVG uploads now go through a sanitiser pass, which
  occasionally rejects valid-but-exotic markup. The package's
  defaults are sensible; we'd ship a config override only if a
  customer hits a false positive.

---

### R-29 — `BotBlockRequest` only checks `POST` (Low)

**Symptom.** `BotBlockRequest::handle()` only inspects the bot-block
token when the request method is `POST` and the caller is
unauthenticated. Today this is fine — every unauthenticated form
(login, registration, password reset) is POST. But a future
unauthenticated `PUT`/`PATCH` endpoint (e.g. a magic-link "set
password" form) bypasses the block entirely.

**Fix.** Broaden the method allowlist to every modifying verb:

```php
public function handle(Request $request, Closure $next): mixed
{
    $modifying = in_array(strtolower($request->method()), ['post', 'put', 'patch', 'delete'], true);

    if ($modifying && ! Auth::user()) {
        $bb = BbValue::where('field_value', $request->post('__bb'))->first();

        if (! $bb instanceof BbValue) {
            abort(403);
        }

        $bb->delete();
    }

    return $next($request);
}
```

**Consequences.**

- Pro: future unauthenticated mutation endpoints inherit the same
  bot-block protection without each engineer having to remember.
- Con: any internal automation that issues unauthenticated
  `PUT`/`PATCH`/`DELETE` against the application will need the
  bot-block token. None today; future infrastructure work to be
  aware of.

---

### R-30 — `Entry::$fillable` exposes `created_by_user_id` (Low)

**Symptom.** `Entry::$fillable` includes `created_by_user_id`. The
canonical write path (`EntryRepository::create()`) assigns this from
`Auth::id()` correctly. But a future caller doing
`Entry::create($request->validated())` (skipping the repository)
would let the requester pick the creator. Same defence-in-depth
argument as R-22.

**Fix.** Drop `created_by_user_id` from `$fillable`:

```php
protected $fillable = [
    'entry_group_id',
    'entry_type_id',
    'title',
    'handle',
    'published_at',
    'status_id',
    'status_handle',
    'status_is_public',
];
```

The repository already assigns `created_by_user_id` directly with
`$entry->created_by_user_id = Auth::id()`, so no other change is
needed.

**Consequences.**

- Pro: the audit trail (`creator` relation) cannot be spoofed by a
  future caller who bypasses the repository.
- Con: any seeder or factory currently relying on mass-assigning
  `created_by_user_id` (the EntrySeeder may; verify) needs to switch
  to `forceFill` or set the column directly after `new Entry()`.

---

### Field Types layer findings (2026-05-26 audit)

The items below — **R-31 through R-35** — were raised by a focused
audit of `app/Field/Types/*.php` and related infrastructure. See the
[Per-type reference](#per-type-reference) for the data underlying each
item.

---

### R-31 — `AbstractField::validate()` is unreachable infrastructure (Medium)

**Symptom.** Nine concrete field types override
`AbstractField::validate(mixed $value): bool|string` with substantive
checks (`FileUpload`, `Media`, `MultiSelect`, `RadioGroup`,
`Relationship`, `Select`, `Slider`, `StructuredRows`, `Users`). A
grep across `app/Repositories`, `app/Services`, `app/Http`,
`app/EntryTypes`, and `app/Models` finds **zero call sites**. The
write pipeline runs only `getRules()` (for the Laravel validator)
and `prepareForStorage()` (for the value cast). The `@todo convert
into Laravel validation rules` comment on `FileUpload` and
`Relationship` confirms this is known but unfixed.

Practical consequence:

- `Select` / `MultiSelect` / `RadioGroup` `strict_options` toggles do
  nothing.
- `MultiSelect` and `StructuredRows` `min` / `max` thresholds do
  nothing.
- `Relationship` `limit` and entry-group/type restriction do nothing
  at the request layer.
- `Slider` `min` / `max` do nothing.
- `FileUpload` and `Media` `min`/`max`/library scoping do nothing.
- `Users` `limit` and role-restriction do nothing.

**Fix options.**

A. **(Direct path)** Invoke `validate()` from
   `AbstractFieldableRepository::applyFieldValues()` and throw a
   `ValidationException` on string return — wires it in everywhere
   without touching FormRequests.

```php
$result = $instance->validate($value);
if (is_string($result)) {
    throw \Illuminate\Validation\ValidationException::withMessages([
        "fields.{$field->handle}" => $result,
    ]);
}
```

B. **(Per the @todo comments)** Convert each `validate()` body into
   real Laravel validation rules and surface them via `getRules()`.
   Some checks (e.g. "every selected media's library_id is in the
   field's allowed libraries") would need custom `ValidationRule`
   classes — pattern already established for `CountryCodeRule`,
   `TimeFormatRule`, etc.

C. **(Tactical)** Delete the dead methods and ship the field types
   with their stated behaviour limited to what `getRules()` enforces.

**Recommendation.** Option A as a one-commit fix that closes every
visible gap, then migrate one type at a time to Option B at leisure.
Option C is the wrong tradeoff because the dead methods document
intended behaviour that admins can see in the field-settings UI.

**Consequences.**

- Pro: makes nine field types behave the way the settings forms
  advertise.
- Con: any test that has been passing because validation didn't run
  will start to fail. Sweep tests before merging.

---

### R-32 — `Telephone::$rules` references unregistered `'telephone'` validator (Critical)

**Symptom.** `app/Field/Types/Telephone.php` declares
`protected array $rules = ['string', 'telephone'];`. The string
`telephone` is not a built-in Laravel validation rule and no
`Validator::extend('telephone', …)` call exists anywhere in `app/`,
`bootstrap/`, or `config/`. Any field layout containing a `Telephone`
field that survives request validation will trigger Laravel to
resolve a `validateTelephone` method on the validator — none exists,
so the framework throws `BadMethodCallException: Method
Illuminate\Validation\Validator::validateTelephone does not exist`.

This is a runtime exception thrown at form-submission time, not a
silent failure. Every entry form, user-profile form, or category
form whose layout references a Telephone field is **currently
unsubmittable**.

**Fix options.**

A. **(Smallest)** Drop the bogus rule:

```php
// app/Field/Types/Telephone.php
protected array $rules = ['string'];
```

   Phones can take so many regional formats that "string + library-side
   cleanup" is often the right tradeoff for a CMS field.

B. **(Hardened)** Register a custom validator and a permissive regex:

```php
// app/Providers/AppServiceProvider.php — boot()
Validator::extend('telephone', function ($attribute, $value) {
    return is_string($value) && preg_match('/^[0-9+\-\s().]{4,30}$/', $value) === 1;
}, 'The :attribute must be a valid phone number.');
```

C. **(Best for a strict product)** Adopt `propaganistas/laravel-phone`
   and bind it as the rule. Heavier dependency but produces
   E.164-normalised storage.

**Recommendation.** Option A for the Alpha fix; revisit at product
maturity. The `Telephone` field type rarely justifies the dependency
weight of Option C.

**Consequences.**

- Pro: forms that include a Telephone field stop throwing.
- Con: validation is loose — but it always was, since the broken
  rule didn't actually run. Net change is zero from the user's POV;
  the gap is purely operational.

---

### R-33 — `EmailAddress::$rules` is empty — no format validation (Medium)

**Symptom.** `EmailAddress::$rules` is `[]` (no default declaration).
The Laravel validator therefore enforces only the layout-level
`required`/`nullable`. Submitting `'not-an-email'`,
`'<script>alert(1)</script>'`, or an empty string stores successfully.

**Fix.**

```php
// app/Field/Types/EmailAddress.php
protected array $rules = ['email:rfc'];
```

Or, if a more permissive shape is desired (accept gmail-style
`+tag` addresses but still reject obvious junk),
`['email:rfc,dns']` adds an MX-record check.

**Consequences.**

- Pro: an `EmailAddress` field actually validates email shape.
- Con: existing data may include junk. Run a one-off audit before
  shipping if customers have populated this field in the wild.

---

### R-34 — Field-type setting forms advertise unenforced constraints (Low)

**Symptom.** Several field types declare admin-facing settings that
the validation pipeline does not honour because `getRules()` doesn't
push them through. Per the [Per-type reference](#per-type-reference):

| Field          | Declared settings that don't reach the validator        |
|----------------|---------------------------------------------------------|
| `Text`         | `min_length`, `max_length`                              |
| `Textarea`     | `max_length`                                            |
| `Number`       | `min`, `max`, `step`                                    |
| `Date`         | `min_date`, `max_date`                                  |
| `ColorPicker`  | `format` (any string accepted), `alpha`                 |
| `Relationship` | `entry_types` (not even read at render time)            |

`MultiSelect` / `Slider` / `Users` / `FileUpload` / `Media` /
`Relationship` `min`/`max`/`limit` are covered separately by R-31 —
they live in the dead `validate()` method.

**Fix.** Have each affected type override `getRules()` and push the
relevant settings into Laravel rule strings:

```php
// Example for Text
public function getRules(): array
{
    $rules = ['string'];

    if ($min = $this->getSetting('min_length')) {
        $rules[] = "min:{$min}";
    }
    if ($max = $this->getSetting('max_length')) {
        $rules[] = "max:{$max}";
    }

    return $rules;
}
```

Apply analogously for `Number` (`min:`/`max:`), `Date`
(`after_or_equal:`/`before_or_equal:`), `ColorPicker` (a `regex:`
per format), and friends. For `Relationship::entry_types`, either
filter `fetchAvailableEntries()` by the configured handles or
remove the setting from `$settings_form`.

**Consequences.**

- Pro: the admin form stops lying to its users.
- Con: existing data may violate the new constraints. Migration
  plan: log violations first, fix the data, then enforce.

---

### R-35 — `ValidatesAgainstOptions::renderOrphanedValue()` is dead helper (Low)

**Symptom.** The trait `App\Traits\Field\ValidatesAgainstOptions`
defines a `renderOrphanedValue()` helper that returns an HTML
`<option disabled selected>` for orphaned select values. A grep of
`app/`, `resources/views/`, and `resources/templates/` finds **no
caller**. The intent (visibly flag stale stored values in
`Select` / `MultiSelect` / `RadioGroup` admin partials) never
shipped.

**Fix options.**

A. Wire the helper into each of the three partials so editors can
   see when an entry's stored value is no longer in the option list.
   Pair with `strict_options` (which will start working once R-31
   lands).
B. Delete the helper.

**Recommendation.** Option A — orphan visibility is genuinely useful
in an admin UI where editors maintain Select options over years.
But it's Low priority until R-31 lands; without
`strict_options` actually firing, an orphaned value is just a stale
selection rather than an error.

**Consequences.**

- Pro: editors can see "this entry references an option that no
  longer exists" without reading the database.
- Con: changing the partial output may shift the visual layout of
  the select widgets. Coordinate with whatever CSS expects the
  current shape.

---

## Ambivalences for Review

Items where the code is ambiguous, drifting, or where the team should
make a deliberate choice before doc updates lock in an answer.

### A-1 — EntryTypeRegistry resolves by handle, not by group

`EntryTypeRegistry::resolveByHandle($handle)` does a `EntryType::where('handle', $handle)->firstOrFail()`
without filtering by `entry_group_id`. The unique constraint is
`(entry_group_id, handle)`, so two groups can technically each register
an entry type with handle `featured`. The registry then returns
whichever the database happens to surface first, and caches it.

The seeded data avoids this by using globally unique handles (`blog_post`,
`product`, `news_article`, etc.). But there is no in-code enforcement.

**Open question.** Do we want:

A. To document the constraint informally (handles must be globally
   unique) and add a deploy-time check.
B. To extend `Content::create()` to accept group context
   (`Content::create($groupHandle, $typeHandle, $data)`).
C. To add a unique index on `entry_types.handle` (globally) and accept
   the constraint at the schema level.

We should not assert a "correct" answer in OVERVIEW.md until the team
picks one.

### A-2 — Two near-identical user-settings stacks (Resolved 2026-05-26)

> **Resolved.** Decision: `Admin\Account\Settings` (under `/account/*`)
> is the canonical stack. `Admin\Settings\UserSettings` has been
> deleted. Subsequent tutorials and docs should reference the
> `account.settings` route name and `account.settings.twig` view only.
>
> Open follow-up (cleanup only): deletion of the orphan view
> `resources/views/admin/settings/user.twig`. R-2 (the redirect-target
> fix on `Admin\Account\Settings::update()`) was completed
> independently on the same day.

### A-3 — `Admin\Field::index` is intentional 404 vs scaffolding mistake

The `index()` action exists and `abort(404)`s. We don't know whether
this was a deliberate signal ("fields have no flat list") or an
incomplete scaffold. R-12 picks A on the assumption it's intentional.
Confirm before the cleanup.

### A-4 — `MediaPicker` route and controller — undocumented (Resolved 2026-05-26)

> **Resolved.** Documented under
> [Media Library → Media Picker Endpoint](#media-picker-endpoint) and
> added as a row in the [Admin Route Map](#admin-route-map). The
> picker is a JSON endpoint (`GET /admin/media/picker`,
> `media.picker.index`) that backs the `FileUpload` / `Media` field
> picker chip UIs — it returns paginated media filtered by
> `library_id[]`, with `picker`-keyed thumbnails (240×240 `cover`) for
> images. Reference the section by anchor; do not re-explain inline.

### A-5 — `Money` field: storage convention (Resolved 2026-05-26)

> **Resolved.** A focused "Money field — design notes" subsection is
> now under [Field Types → Built-in Types](#built-in-types). The
> subsection records: (a) the integer minor-unit storage contract and
> the `moneyphp/moneyphp` parser/formatter chain; (b) the no-implicit-
> rounding write guard; (c) the read API (`Money\Money` value object);
> (d) the deliberate **single-currency-per-field-instance** design
> choice with two intended escape hatches (per-currency field handles
> today, a future "Currency" field type tomorrow); (e) the awareness
> note that raw-column readers must consult the field's `currency`
> setting because the schema doesn't carry it.
>
> No code action required. If a multi-currency-per-instance
> requirement ever lands, re-open as a fresh recommendation (R-N+1)
> at that time.

### A-6 — `Entry::redirect_url` validation vs `EntryTree::redirect_url` storage

`StoreEntryRequest` accepts `redirect_url` and `redirect_status` at the
top level of the entry payload. The actual storage is on `entry_trees`,
not `entries`. The form bundles entry + tree-node creation, and the
service splits them at write time. This is fine, but worth either:
documenting the tree-node payload keys separately from the entry's
core fields, or considering a nested `tree` array in the validated
payload for clarity.

### A-7 — `MediaResource` not yet built

The native Media layer is otherwise complete, but there is no
`Http\Resources\Api\MediaResource`. Any API consumer of media will need
this. Decide the shape (URL? transformed URLs? library handle? status?)
before building, so the OpenAPI surface stays stable.

### A-8 — `Entry` lacks `SoftDeletes` while `Media` has it

`Media` uses `SoftDeletes` (two-stage delete + `PurgeDeletedMedia`
purge). `Entry` does not. `TODOS.md` and the Shop plan both want soft
deletes on entries. Adopting `SoftDeletes` on `Entry` has knock-on
effects on every `whereHas`/`belongsToMany` relation that involves
entries — eligibility scopes for authors, etc. Decision-and-design
needed before the migration.

### A-9 — `default_schema_type` on `entry_types` and `schema_type` on `entries`

Both columns exist but are not yet referenced by any service or
template (per `Grep` against the codebase as of this writing). They
correspond to `SEO_SCHEMA_PLAN.md`. Document as **reserved for the SEO
schema work**, not as live fields.

### A-10 — Categorical handle uniqueness across groups

`categories.handle` is unique per `(group_id, handle)`, but the
admin-side validation rule in `StoreEntryRequest` validates category IDs
without checking which group they belong to (relative to the entry's
configured `categoryGroups`). The current rule does enforce
membership in the entry group's category groups via a `whereIn`
sub-query, but this only catches submission-time mismatches, not the
case where an admin reassigns a category to a different group after
content references it. Worth a short note about the lifecycle.

---

## Accuracy Notes Log

Inline "[Accuracy Note]" boxes throughout this document call out specific
drift items between prior doc copy and the live code. The full list of
corrections made in the 2026-05-26 refresh pass:

1. **EntryType column**: doc said `class`; code uses `entry_behavior_id`
   plus the `entry_behaviors.class` morph-alias indirection.
2. **EntryTypeRegistry**: doc described one-step instantiation; the
   actual flow is `EntryType → EntryBehavior → morph map → PHP class`.
3. **Field types**: doc listed 10 built-in types; the codebase has 23
   seeded.
4. **Field partials**: `_fields/html.twig` is missing despite `Html`
   being seeded (R-1).
5. **Seeder list**: doc had 10 base + 1 dev; the actual count is 12
   base + 3 dev seeders (added `EntryBehaviorSeeder`,
   `MediaLibrarySeeder`, `SandboxedEntryTreeSeeder`,
   `SampleApiTokenSeeder`).
6. **Status groups**: doc described `publication` only; three groups
   are seeded (`publication`, `job-status`, `product-status`).
7. **Morph map**: `behavior.*` aliases were omitted entirely.
8. **`app:validate-class-references`**: command checks
   `entry_behaviors.class` (morph keys) plus `field_types.object`
   (FQCNs), not `entry_types.class` (which doesn't exist).
9. **`entry_trees` columns**: `redirect_url` and `redirect_status` were
   omitted from the schema table.
10. **`RouteResult`**: doc implied a `redirect_url` property; the
    object only has `type, template, data, resource`.
11. **EntryResource shape**: doc said it returned user-shaped fields;
    it now returns the proper entry shape.
12. **Entries API completeness**: doc treated the resource controllers
    as stubs; full CRUD has landed in `Api\v1\Entries` and
    `Api\v1\EntryGroups`.
13. **Media status governance**: feature was undocumented; full
    section now added.
14. **`HasStatus` / `StatusSyncRegistry`**: trait + registry abstraction
    was undocumented; full section now added.
15. **Permission strings**: `view user` and `read users` are both
    seeded; the remaining drift is the typo in `Api\v1\User::index`
    (R-3).
16. **`Money` field design**: documented in
    [Built-in Types → Money field — design notes](#built-in-types) as
    of 2026-05-26 (closes Ambivalence A-5). Storage contract, write
    guard, read API, and single-currency-per-field design choice are
    all asserted there.
17. **`entries.schema_type` / `entry_types.default_schema_type`**:
    columns exist but are reserved for SEO plan work; see Ambivalence
    A-9.
18. **`Admin\Settings\UserSettings` deleted (2026-05-26)**: the
    duplicate user-settings controller has been removed. Decision
    recorded in Ambivalence A-2 (now resolved); R-4 marked Resolved
    above. `Admin\Account\Settings` is now the single canonical
    user-settings controller.
19. **R-2 redirect target fixed (2026-05-26)**:
    `Admin\Account\Settings::update()` now redirects to
    `route('account.settings')`. The `RouteNotFoundException` on
    `PUT /admin/account/settings` no longer fires. The orphan
    `resources/views/admin/settings/user.twig` view remains as dead
    code containing broken `route('settings.user')` calls; flagged as
    a Low-severity residual cleanup under the R-2 entry.
20. **`MediaPicker` endpoint documented (2026-05-26)**: closes
    Ambivalence A-4. New
    [Media Picker Endpoint](#media-picker-endpoint) section under
    Media Library, plus a row in the Admin Route Map. Documents the
    JSON shape, query parameters, validation rules, and thumbnail
    behaviour. No code change.
21. **Field Types layer audited (2026-05-26)**: 23 concrete field
    types reviewed; full per-type reference added under
    [Built-in Types → Per-type reference](#per-type-reference) with
    storage column, settings catalogue, validation surface, read
    API, and per-type "potential issues" callouts. Cross-cutting
    findings raised as R-31 through R-35 in
    [Recommendations & Remedies](#recommendations--remedies). The
    biggest finding: **`AbstractField::validate()` is never invoked**
    by the repository or FormRequest pipeline (R-31), so nine field
    types' settings (`strict_options`, selection min/max, relationship
    limits, etc.) silently fail to enforce. The most embarrassing
    finding: **`Telephone::$rules` references an unregistered
    `'telephone'` validator** (R-32), which throws
    `BadMethodCallException` on any form whose layout includes a
    Telephone field.

### Items absorbed from ALPHA_READINESS_REPORT.md (2026-05-26)

A separate review (`docs/ALPHA_READINESS_REPORT.md`, 2026-05-25) raised
29 numbered findings ahead of an external Alpha announcement. A
verification pass against the live code on 2026-05-26 reclassified
each one:

**Already resolved in code — no action remaining:**

- **C-2** Upload endpoint now requires the `upload media` permission
  (verified in `UploadMediaRequest::authorize()`).
- **H-1** OAuth callback now calls `$request->session()->regenerate()`
  and `regenerateToken()`; `routes/web.php` wraps social-login routes
  in `throttle:10,1`.
- **H-3** `redirect_url` is now validated as `url:http,https` in both
  `StoreEntryRequest` and `EditEntryRequest`; `EntryTreeRouteDriver`
  enforces a scheme allowlist at render time via `isSafeRedirect()`.
- **H-7** `Html` field type calls `Purifier::clean()` in
  `prepareForStorage()` and reads the `allowed_tags` setting (so I-3 is
  also resolved — the setting is no longer dead).
- **M-1** `categories.*` now scopes `Rule::exists` to the categories
  whose group is attached to the entry's group via the
  `category_groupables` pivot.
- **L-1** `TemplateRouteDriver::__construct()` no longer mutates the
  `admin` view namespace; the render-time guard
  (`if (str_starts_with($view, 'admin::')) throw …`) is in place.
- **L-6** `entry_trees.redirect_status` column exists and is honoured
  by `EntryTreeRouteDriver`.
- **I-1** `EntryResource` correctly returns entry-shaped fields.
- **I-3** see H-7 above.

**Still real and absorbed into the active list as R-17 through R-30:**

- C-1 → R-17 (`UpdateUserPassword` skips current-password check).
- C-3 → R-18 (role-assignment privilege escalation, service-layer
  defence-in-depth).
- H-2 → R-19 (`LogRequestResponse` never writes `response_payload`).
- H-4 → R-20 (personal access token flashed in URL/session).
- H-5 → R-21 (`EntryTypeRegistry::resolveByHandle` is group-blind;
  partial mitigation already in place at request + action layers).
- H-6 → R-22 (`User::$fillable` exposes status columns).
- H-8 → R-23 (CORS wide open).
- M-4 + M-5 → R-24 (API mutator endpoints lack controller-level
  permission checks).
- M-6 → R-25 (`UserService::updateToken` accepts arbitrary fields).
- M-7 → R-26 (`users.default_status` / `social_default_status`
  accept post-creation statuses).
- L-2 → R-27 (media library handle not slug-restricted).
- L-3 → R-28 (SVG/HTML uploads on public disk are XSS vectors).
- L-4 → R-29 (`BotBlockRequest` only checks `POST`).
- L-5 → R-30 (`Entry::$fillable` exposes `created_by_user_id`).
- M-2 → already R-3 (`Api\v1\User::index` permission typo).
- M-3, I-2 → already R-6 (`Api\v1\Account@show` placeholder).
- I-4 → already R-8 (`app:refresh-tokens` scaffold).
- I-5 → already R-9 (`site.templates.base_path` / `not_found_template`).

If a future change touches an item in this log, update both the
relevant section and this log so the corrected state is traceable.

---
