# Laravel CMS — Project Overview

> **Documentation status (2026-05-07).** This file is synchronised against
> the live source in `app/`, `database/`, `routes/`, and `config/`.
> Project-specific code snippets are copy-paste accurate against the current
> codebase; generic tutorial snippets use placeholder model names where noted.
> The codebase consistently uses **`handle`** (not `slug`) on every model
> that carries a developer-facing identifier — `Field`, `FieldGroup`,
> `EntryGroup`, `EntryType`, `StatusGroup`, `Status`, `CategoryGroup`,
> `Category`, and `Entry`.

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
    - [Categories and Field Groups](#categories-and-field-groups)
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

---

## Architecture at a Glance

This is an **ExpressionEngine-inspired headless CMS** built on Laravel 12. The
core philosophy: all content structure is admin-defined at runtime. Entry types
can be backed by concrete PHP classes; when no class is configured the registry
falls back to `GeneralEntryType`. Everything else (fields, layouts, statuses,
categories) is database-driven.

```
FieldType          — system-level type registry (Text, Textarea, Number, Date,
                     EmailAddress, Url, Telephone, ColorPicker, Relationship,
                     Boolean)
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
  └── EntryType   — optional PHP class extending AbstractEntryType.
                    Schema: name, handle, class, default_template,
                    has_entry_tree, max_depth, allowed_parent_types,
                    field_layout_id (optional override), sort_order
        └── Entry — title, handle, status_id + status_handle + status_is_public,
                    published_at, created_by_user_id
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

The `DatabaseSeeder` runs in this order (ten always run; the eleventh runs
only in `local`/`testing`):

1. `RolesPermissionsSeeder` — permissions + 3 roles
2. `UsersSeeder` — seeds a single **super-admin** user (Eric Lamb,
   `eric@mithra62.com`, password `password`)
3. `FieldTypeSeeder` — 10 field type rows (Text, Textarea, Number, Date,
   Email Address, URL, Telephone, Color Picker, Relationship, Boolean)
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
10. `SettingsDomainSeeder` — settings domains and system-level default values
11. `EntrySeeder` *(local/testing only)* — sample blog posts and products

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

`app:validate-class-references` checks database-stored class names in
`entry_types.class` and `field_types.object`, verifying that entry type classes
extend `AbstractEntryType` and field type classes extend `AbstractField`.

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
    'name'                => 'Jane Doe',
    'email'               => 'jane@example.com',
    'password'            => 'secret',
    'is_author'           => true,
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
    'name'                => 'Jane Doe',
    'email'               => 'jane@example.com',
    'password'            => 'secret',
    'roles'               => ['admin'],
    'fields'              => ['first_name' => 'Jane', 'last_name' => 'Doe'],
    // Optional author eligibility keys (stripped before User::create()):
    'is_author'           => true,
    'author_display_name' => 'J. Doe',
]);

$user = Users::update($user, [
    'name'   => 'Jane Smith',
    'roles'  => ['user'],
    'fields' => ['last_name' => 'Smith'],
    // Optional — omit to leave eligibility unchanged:
    'is_author'           => false,
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

### Status Management

```php
use App\Enums\UserStatus;

// Set any non-suspension status; manages banned_at automatically
Users::setStatus($user, UserStatus::INACTIVE, 'Account deactivated');
Users::setStatus($user, UserStatus::BANNED,   'Violated terms of service');
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
    'site_name'      => 'Laravel Base',
    'timezone'       => 'America/Phoenix',
    'date_format'    => 'Y-m-d',
    'time_format'    => 'H:i',
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
    'timezone'       => 'America/Phoenix',
    'date_format'    => 'm/d/Y',
    'time_format'    => 'g:i A',
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

| Class          | `storageColumn()`                | Notes                                                      |
|----------------|----------------------------------|------------------------------------------------------------|
| `Text`         | `value_text`                     | Single-line input                                          |
| `Textarea`     | `value_text`                     | Multi-line                                                 |
| `Number`       | `value_integer` or `value_float` | Branches on `decimals` setting                             |
| `Date`         | `value_date`                     | Cast as `datetime`; reads return `Carbon`                  |
| `EmailAddress` | `value_text`                     |                                                            |
| `Url`          | `value_text`                     |                                                            |
| `Telephone`    | `value_text`                     |                                                            |
| `ColorPicker`  | `value_text`                     | Hex value                                                  |
| `Relationship` | *(unused)*                       | `isRelational() === true`; stores in `entry_relationships` |
| `Boolean`      | `value_boolean`                  | Casts reads to `bool`                                      |

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

## Entry Groups and Entry Types

An **EntryGroup** is the section/channel (e.g. "Blog", "Products") tying
together a FieldLayout, a StatusGroup, polymorphic CategoryGroups, and
polymorphic FieldGroups.

An **EntryType** row maps a group-scoped handle to an optional PHP class:

| Column                                                | Description                              |
|-------------------------------------------------------|------------------------------------------|
| `entry_group_id`                                      | FK to `entry_groups`, cascade on delete  |
| `field_layout_id`                                     | Optional override layout for this type   |
| `name`, `handle`                                      | `(entry_group_id, handle)` unique        |
| `class`                                               | Nullable FQCN of an `AbstractEntryType` subclass |
| `default_template`                                    | Optional default template for SiteRouter |
| `has_entry_tree`, `max_depth`, `allowed_parent_types` | Tree config                              |
| `sort_order`                                          | Display order within the group           |

At runtime, `EntryTypeRegistry::resolveByHandle()` resolves by `handle` only,
not by group. Keep entry type handles globally unique when using
`Content::create('type_handle', ...)` unless the creation API is extended to
accept group context. If `class` is null or names a missing class, the registry
logs a warning and falls back to `GeneralEntryType`. A configured class that
exists but does not extend `AbstractEntryType` still throws a `RuntimeException`.

### Seeded Entry Groups and Types

The seeders create one entry type per seeded entry group. The handles are
currently globally unique, which is important because `Content::create()` and
`EntryTypeRegistry::resolveByHandle()` resolve by type handle alone.

| EntryGroup handle | EntryType handle   | Name             | Class                              | Status group      | Tree routing |
|-------------------|--------------------|------------------|------------------------------------|-------------------|--------------|
| `blog`            | `blog_post`        | Blog Post        | `BlogPostEntryType`                | `publication`     | No           |
| `products`        | `product`          | Product          | `ProductEntryType`                 | `product-status`  | No           |
| `events`          | `event`            | Event            | `EventEntryType`                   | `publication`     | No           |
| `news`            | `news_article`     | News Article     | `NewsArticleEntryType`             | `publication`     | No           |
| `pages`           | `page`             | Page             | `PageEntryType`                    | `publication`     | Yes          |
| `jobs`            | `job_listing`      | Job Listing      | `JobListingEntryType`              | `job-status`      | No           |
| `podcast`         | `podcast_episode`  | Podcast Episode  | `PodcastEpisodeEntryType`          | `publication`     | No           |
| `portfolio`       | `portfolio_item`   | Portfolio Item   | `PortfolioItemEntryType`           | `publication`     | Yes          |
| `videos`          | `video`            | Video            | `VideoEntryType`                   | `publication`     | Yes          |
| `recipes`         | `recipe`           | Recipe           | `RecipeEntryType`                  | `publication`     | Yes          |
| `general`         | `general`          | General          | `GeneralEntryType`                 | `publication`     | No           |

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

The database allows `entry_types.class` to be nullable, and the registry falls
back to `GeneralEntryType` when the class is null/empty or the named class is
missing. The current admin create/edit form requests and `EntryTypeService`
still require `class` and validate it with `ExtendsClass(AbstractEntryType::class)`.
In practice:

- Seeder-created types use explicit concrete classes.
- Programmatic/database-created types can leave `class` empty and get
  `GeneralEntryType` behavior.
- Admin-created types currently need a valid concrete class name.
- Existing but invalid classes throw `RuntimeException` instead of falling back.

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
$relatedA = Content::query()->inGroup('products')->where('handle', 'widget-a')->firstOrFail()->id;
$relatedB = Content::query()->inGroup('products')->where('handle', 'widget-b')->firstOrFail()->id;

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

| Column       | Notes                                                            |
|--------------|------------------------------------------------------------------|
| `entry_id`   | Unique FK to `entries`; each entry can have at most one tree node |
| `parent_id`  | Nullable self-FK; deleting a parent sets direct children to root  |
| `handle`     | URL-safe slug segment generated by `EntryTree::validatedHandle()` |
| `uri`        | Full normalized URI, unique across the tree; home node uses `/`   |
| `depth`      | Root depth is `0`; rebuilt when nodes move                       |
| `sort_order` | Sibling order                                                    |
| `template`   | Optional per-node template override                              |
| `is_home`    | Marks the single home node                                       |

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
passing `published()` are served. Template precedence: `EntryTree.template`
→ `EntryType.default_template` → `'entries.show'`.

The driver eager-loads:

```php
[
    'entry.entryType',
    'parent.entry',
    'children.entry.entryType',
]
```

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

API response classes live under `app/Http/Resources/Api`. `UserResource`
returns `id`, `name`, `email`, `created_at`, and `updated_at`.

`EntryResource` currently returns `id`, `name`, `email`, `created_at`, and
`updated_at`. That shape does not match the `Entry` model, which stores
`title`, `handle`, `status_id`, `status_handle`, `status_is_public`,
`published_at`, and entry-group/type relationships. Treat the Entry API schema
as incomplete until the resource is brought in line with the content model.

The `Entries` controller is also partially scaffolded: `show()` reads through
`Content::find($id)`, but `index()`, `store()`, `update()`, `destroy()`, and
`search()` return placeholder JSON messages.

`Api\v1\User` gates reads with `$this->can('read users')`, while the seeded
permission set uses `view user`. Either the API gate or the seeded permission
name should be aligned before relying on this endpoint outside development.

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

The codebase is functional in the core CMS areas, but these implementation
details should be kept visible:

- API `entries` endpoints are partially scaffolded; only `show()` attempts to
  return real content.
- `EntryResource` currently has user-shaped fields (`name`, `email`) instead of
  entry-shaped fields (`title`, `handle`, status, type, group, fields).
  The author sub-object now returns `{ id: user_id, display_name }` correctly,
  but the broader entry shape is still incomplete.
- `Api\v1\User` checks `read users`, while the seeded permission is `view user`.
- `Api\v1\Account@show` returns a placeholder success message instead of the
  authenticated user resource described by its OpenAPI annotation.
- `EntryType.max_depth` and `EntryType.allowed_parent_types` are stored and
  cast, but current Entry Tree service methods do not enforce them.
- `app:refresh-tokens` is a scaffold and does not perform token refresh until
  implementation code is added.
- `site.templates.base_path` and `site.templates.not_found_template` are present
  in config but are not read by the current route drivers.
- `entry_author_entry` FK cascades are defined in the migration but `Entry::delete()`
  only cascades through `entry_authors` if `entry_id` is present in that table —
  with the current schema the `entry_author_entry` pivot rows are removed via the
  cascade on `entry_author_entry.entry_id`; no manual cleanup is required.

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


## Technical Tutorials

### Adding Custom Fields to Any Model

The custom field layer used by Entries, Categories, and Users is reusable on
any Eloquent model that should store dynamic, admin-defined scalar field values.
The two reusable pieces are:

1. **`Fieldable` trait** (`app/Traits/Fieldable.php`) - adds `fieldValues()`
   (morphMany to `field_values`), `field(string $handle): mixed`, and
   `fieldArray(): array`.
2. **`PersistsFieldValues` trait** (`app/Traits/PersistsFieldValues.php`) -
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

use App\Traits\Field\Fieldable;use Illuminate\Database\Eloquent\Model;

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
        'field_id'            => $field->id,
        'required'            => false,
        'sort_order'          => $i + 1,
    ]);
}
```

The layout is useful for admin UI rendering. The storage layer itself only
requires `Field` rows and the `Fieldable` model.

### Step 4 - Write Field Values

`$model->getMorphClass()` returns the registered morph alias. That alias is the
correct value for `fieldable_type`.

```php
use App\Models\Field;use App\Models\FieldValue;use App\Models\ProductVariant;use App\Traits\Field\PersistsFieldValues;

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
    'variant_care'     => 'Machine wash cold.',
    'variant_color'    => 'Blue',
]);

// Option B - write directly (mirrors what PersistsFieldValues does internally)

$field = Field::where('handle', 'variant_material')->firstOrFail();

FieldValue::updateOrCreate(
    [
        'field_id'       => $field->id,
        'fieldable_id'   => $variant->getKey(),
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
