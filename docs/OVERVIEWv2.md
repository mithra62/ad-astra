# Laravel CMS — Project Overview v2

> **Documentation status (2026-05-16).** This file is synchronised against the
> live source in `app/`, `database/`, `routes/`, `config/`, and `resources/`.
> Where the live source disagrees with the parent `OVERVIEW.md`, this document
> records what the code does. Sections marked **Potential Issue** call out
> divergences, dead code, naming drift, and contradictions between the live
> source and the documents in `docs/`.
>
> The codebase consistently uses **`handle`** (not `slug`) on every model that
> carries a developer-facing identifier — `Field`, `FieldGroup`, `EntryGroup`,
> `EntryType`, `StatusGroup`, `Status`, `CategoryGroup`, `Category`, `Entry`,
> and `Media\Library`.

## Table of Contents

- [Architecture at a Glance](#architecture-at-a-glance)
    - [Request Lifecycle](#request-lifecycle)
    - [Cross-cutting infrastructure](#cross-cutting-infrastructure)
- [Setup](#setup)
- [Operational Commands and Deployment Notes](#operational-commands-and-deployment-notes)
- [Testing Strategy](#testing-strategy)
- [Users, Roles, and Permissions](#users-roles-and-permissions)
    - [Built-in Roles](#built-in-roles)
    - [Built-in Permissions](#built-in-permissions)
    - [Permission-string drift](#permission-string-drift-admin-vs-api)
- [Adding New Permissions](#adding-new-permissions)
- [User Account Status](#user-account-status)
- [User Extended Profile (UserSchema)](#user-extended-profile-userschema)
- [Author Eligibility](#author-eligibility)
- [UserService and the Users Facade](#userservice-and-the-users-facade)
- [OAuth and Social Login](#oauth-and-social-login)
- [System and User Settings](#system-and-user-settings)
- [Field Types](#field-types)
    - [Built-in Types](#built-in-types)
    - [Field Render Chain](#field-render-chain)
- [Field Groups and Fields](#field-groups-and-fields)
- [Field Layouts](#field-layouts)
- [Status Groups and Statuses](#status-groups-and-statuses)
- [Category Groups and Categories](#category-groups-and-categories)
- [Entry Groups and Entry Types](#entry-groups-and-entry-types)
- [Creating and Updating Entries](#creating-and-updating-entries)
- [Querying Entries](#querying-entries)
- [Entry Metrics](#entry-metrics)
- [Deleting Entries](#deleting-entries)
- [Media Library](#media-library)
- [Site Routing (Public-Facing URLs)](#site-routing-public-facing-urls)
- [Template and View Stack](#template-and-view-stack)
    - [TwigBridge Configuration](#twigbridge-configuration)
    - [View Namespaces](#view-namespaces)
    - [Admin Layout](#admin-layout)
    - [Admin Includes](#admin-includes)
    - [Site Templates](#site-templates)
    - [Vite Asset Pipeline](#vite-asset-pipeline)
- [Controller Layer](#controller-layer)
    - [Base Classes and Helpers](#base-classes-and-helpers)
    - [Standard CRUD Shape](#standard-crud-shape)
    - [Site Controllers](#site-controllers)
    - [Admin Controllers — Catalog](#admin-controllers--catalog)
    - [API Controllers — Catalog](#api-controllers--catalog)
- [Model Creation and Modification](#model-creation-and-modification)
    - [Services Layer](#services-layer)
    - [Repositories Layer](#repositories-layer)
    - [Actions Layer](#actions-layer)
    - [FormRequests Layer](#formrequests-layer)
    - [Observers, Events, and Listeners](#observers-events-and-listeners)
    - [Transactional Behaviour](#transactional-behaviour)
- [End-to-End Chain Traces](#end-to-end-chain-traces)
    - [Entry create](#entry-create-chain)
    - [Entry update](#entry-update-chain)
    - [Category create](#category-create-chain)
    - [Media upload](#media-upload-chain)
    - [User create](#user-create-chain)
- [API Layer](#api-layer)
- [Admin Route Map](#admin-route-map)
- [Validation Strategy](#validation-strategy)
- [Bot Blocking, Webhooks, and External Integrations](#bot-blocking-webhooks-and-external-integrations)
- [Known Gaps and Implementation Status](#known-gaps-and-implementation-status)
- [Potential Issues — Aggregate Register](#potential-issues--aggregate-register)
- [Future Plans and Agenda](#future-plans-and-agenda)
    - [Plan documents in `docs/`](#plan-documents-in-docs)
    - [Cross-plan contradictions](#cross-plan-contradictions)
- [Key Data Flow Summary](#key-data-flow-summary)
- [Morph Map Aliases](#morph-map-aliases)

---

## Architecture at a Glance

The application is a Laravel 12 CMS built around a polymorphic **Field** system that lets any model with the `Fieldable` trait carry an arbitrary set of editor-defined fields. Content is organised as `EntryGroup → EntryType → Entry`, with parallel hierarchies for `CategoryGroup → Category` and `MediaLibrary → Media`. Twig (via `rcrowe/twigbridge`) is the only view engine. Authentication uses Laravel **Fortify**; the API uses **Sanctum** tokens. Role-based access control uses **Spatie Permission**.

Tenancy, multilingual support, full-text search, schema.org JSON-LD generation, a discussion/review layer, and an e-commerce module are all planned but not yet implemented — see [Future Plans and Agenda](#future-plans-and-agenda).

### Request Lifecycle

A request enters the application via one of four route files:

| File | Prefix | Middleware | Purpose |
|---|---|---|---|
| `routes/admin.php` | `/admin` | `auth` | Twig-rendered admin UI |
| `routes/api.php` | `/api/v1` | `auth:sanctum`, `LogRequestResponse` | REST API |
| `routes/web.php` | none | `web` | Public site (catch-all → `Site::show`) and OAuth |
| `routes/console.php` | n/a | n/a | Scheduled jobs |

Public site requests fall through to `App\Http\Controllers\Site::show()`, which delegates to `App\Services\SiteRouting\SiteRouter::render($uri)`. The router iterates the driver chain configured in `config('site.routing.priority')` (default `['entry_tree', 'template']`) until one returns a `RouteResult`.

Admin requests pass `Admin\Controller`'s constructor gate (`abort(403)` unless `Gate::allows('access admin')`), then a per-action `FormRequest::authorize()` that checks a Spatie permission. API requests pass Sanctum auth, are logged by `LogRequestResponse`, then **also** check a per-controller `$this->can('<permission>')` call before doing work.

### Cross-cutting infrastructure

- **Settings** (`app/Settings.php`, `App\Settings` facade alias `settings`) — schema-driven settings system. Domains and fields are declared in `config/settings.php`; values land in the `setting_values` table; resolution is **user → system → config default** with one-hour caches per domain.
- **Morph map** — declared in `AppServiceProvider::boot()` (lines 138–139 register view namespaces; the morph map is declared on `Relation::morphMap(...)` earlier in the same `boot()`). Aliases decouple polymorphic type strings from class names.
- **Bot block** — `App\Models\BotBlock` and `App\Http\Middleware\BotBlock` (table `bot_block`); IP-keyed reputation cache used by public routes.
- **API logging** — `App\Http\Middleware\LogRequestResponse` writes to `api_logs`; rows are pruned at 90 days via the `model:prune` schedule entry.
- **Two-factor authentication** — Fortify's `TwoFactorAuthenticatable` trait on `User`.

---

## Setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
npm run dev   # or composer run dev for combined server+queue+vite
```

`composer run dev` (defined in `composer.json`) runs `php artisan serve`, `php artisan queue:listen`, and `npm run dev` in parallel via the `concurrently` npm package.

Testing uses a separate SQLite database (`database/testing.sqlite`) and `APP_ENV=testing` via `phpunit.xml`. Run `php artisan migrate --env=testing` if the test database is missing or stale.

---

## Operational Commands and Deployment Notes

```bash
# Tests
composer test
php artisan test --filter=TestName

# Code style (always run before committing)
vendor/bin/pint --preset psr12
vendor/bin/pint --preset psr12 --dirty

# Reset caches
php artisan optimize:clear

# OpenAPI generation
php artisan l5-swagger:generate

# Validate that DB-stored entry type and field type class names still exist
php artisan app:validate-class-references

# Common one-offs
php artisan route:list --except-vendor
php artisan schedule:run
php artisan queue:work
```

**Windows note:** PHP is at `C:\php\php.exe`. If `php` is not in `PATH`, prefix `artisan` and `pint` calls accordingly.

Scheduled work (`routes/console.php`):

- `model:prune --model App\Models\ApiLog` daily at 02:00.
- `App\Jobs\PruneApiLogs` exists as an alternative self-rescheduling job (not currently the active schedule entry).

> **Potential Issue.** `app:refresh-tokens` is a scaffold — `RefreshTokens::handle()` is empty except for commented-out example code at [app/Console/Commands/RefreshTokens.php:16-35](app/Console/Commands/RefreshTokens.php). `App\Services\OAuth\TokenRefreshService` already exists and contains the refresh logic; the command just needs wiring plus a schedule entry.

---

## Testing Strategy

PHPUnit only (no Pest). Tests live under `tests/Feature/` (admin and API workflows) and `tests/Unit/` (models, services, repositories, observers, field types). Notable suites:

- `tests/Feature/Admin/` — admin controllers (MediaController, MediaLibraryController, EntryTest, etc.).
- `tests/Unit/Observers/` — `EntryTreeObserverTest`, `FieldValueObserverTest`.
- `tests/Unit/Models/Media/`, `tests/Unit/Actions/Media/Library/` — the native Media layer has comprehensive coverage; see `MEDIA_LAYER_OVERVIEW.md`.
- `tests/Unit/Field/Types/StructuredRowsTest.php` and siblings — field-type unit tests.
- `tests/Unit/Repositories/MediaRepositoryTest.php`, `tests/Unit/Services/MediaStorageServiceTest.php`.

The Media layer status is "complete and in testing" per `MEDIA_LAYER_OVERVIEW.md` and the 2026-05-12 status note in `ACTION_PLAN.md`.

---

## Users, Roles, and Permissions

Spatie Permission backs the role/permission system. Users are seeded with concrete roles by `database/seeders/RolesPermissionsSeeder.php`.

### Built-in Roles

| Role | Description |
|---|---|
| `super_admin` | Full access; gate short-circuits in `Gate::before()` |
| `admin` | Administrative access to all CMS resources |
| `editor` | Content editor; can create and edit entries |
| `author` | Limited content authoring |
| `user` | Public/site-facing user; no admin access by default |

### Built-in Permissions

Grouped by domain in `RolesPermissionsSeeder.php`. Notable groups:

- **users** — `view user`, `read users`, `create user`, `edit user`, `delete user`, `manage user status`, `view user token`, `create user token`, `edit user token`, `delete user token`, `create role`, `edit role`, `delete role`
- **system** — `access admin`, `api`, `edit setting`
- **entries** — `read entry groups`, `create entry group`, `edit entry group`, `delete entry group`, `create entry type`, `edit entry type`, `delete entry type`, `read entries`, `create entry`, `edit entry`, `delete entry`, `read status groups`, `read statuses`, `create status`, `edit status`, `delete status`
- **categories** — `read category groups`, `create category group`, …, `read categories`, `create category`, …, `reorder category`
- **fields** — `create field group`, `edit field group`, `delete field group`, `create field`, …
- **media** — `create media library`, …, `upload media`, `delete media`

The `access admin` permission is gated by every admin controller's parent constructor and by every admin FormRequest's `authorize()`. The `api` permission is required to mint Sanctum tokens. The Gate is configured in `AppServiceProvider::boot()` so that `super_admin` short-circuits all checks.

### Permission-string drift (Admin vs API)

> **Potential Issue.** API controllers do explicit `$this->can('<permission>')` checks alongside the FormRequest's `authorize()`. The API string set is `read users`, `read entries`, `read entry groups`, `read category groups`, `read categories`, `read status groups`, `read statuses`, `delete user`, `delete entry`, `delete entry group`, `delete category group`, `delete category`. Admin FormRequests sometimes use different strings (`view user` for example). The previous `OVERVIEW.md` flagged this as a gap; the seeder now defines **both** `view user` (web UI) and `read users` (API), so the mismatch is resolved for users but the broader policy of dual-permissions-per-action is implicit. Make sure new API verbs add both the seeded permission and the controller `can()` string in lock-step.

### Creating Users Programmatically

```php
use App\Facades\Users;

$user = Users::create([
    'name' => 'Jane',
    'email' => 'jane@example.com',
    'password' => 'secret-pass',
    'roles' => ['editor'],
    'fields' => ['bio' => 'Likes coffee.'],
    'is_author' => true,
]);
```

`Users::create()` dispatches to `UserService::create()` which: hashes the password, applies the system default status, calls `User::create()`, syncs roles via `syncRoles()`, writes field values via the `PersistsFieldValues` trait, and synchronises author eligibility if `is_author` is provided.

### Checking Permissions

```php
$user->can('edit entry');     // Spatie permission check
Gate::allows('access admin'); // Same backend
$this->can('read users');     // Controller helper; delegates to Gate::allows()
```

### Creating a New Permission and Role

Edit `RolesPermissionsSeeder.php`, then `php artisan db:seed --class=Database\\Seeders\\RolesPermissionsSeeder`. The seeder calls `$registrar->forgetCachedPermissions()` so changes apply without a manual cache clear.

---

## Adding New Permissions

1. Add the permission string to the appropriate group in `RolesPermissionsSeeder.php`.
2. Re-run the seeder.
3. Reference the permission in a FormRequest's `authorize()` and — for API actions — the controller's `$this->can(...)` call.
4. If admin-only, ensure the relevant controller extends `Admin\Controller` so the parent gate enforces `access admin`.

---

## User Account Status

Users carry a status column (`users.status`, enum-like string) plus three nullable timestamp columns: `suspended_until`, `banned_at`, `locked_until`.

### Status Values

`UserStatus` enum (`app/Enums/UserStatus.php`): `ACTIVE`, `INACTIVE`, `PENDING`, `SUSPENDED`, `BANNED`. `email_verified_at` is independent of these.

### Parallel Lock Column

`locked_until` is independent from `status` — locking is a temporary access denial that does not change the user's underlying status string. `UserService::lockUser()` and `unlockUser()` adjust the column.

### Model Helpers

```php
$user->canAccessSystem();   // active and not locked and (verified or pending allowed)
$user->isLocked();
$user->isActive();
$user->isSuspended();
$user->statusLabel();       // human-readable label
$user->statusColour();       // Tailwind colour key for badges
```

### Authentication Gate

Configured in `AppServiceProvider::boot()` — `Gate::before(fn(User $user) => $user->hasRole('super_admin') ? true : null)`. Per-permission checks then delegate to Spatie.

### Access-Enforcement Middleware

`App\Http\Middleware\EnforceUserStatus` runs on admin routes; rejects sessions for users who fail `canAccessSystem()` even if they have a valid session cookie.

### Status Change Events and Audit Log

Every status transition through `UserService` dispatches `UserStatusChanged` or `UserLockChanged`. The `WriteUserStatusLog` listener is wired manually in `AppServiceProvider::boot()` (no `EventServiceProvider::$listen` array) and writes to `user_status_logs` with the actor's user ID from `Auth::id()`.

### Admin UI

`/admin/users/{user}/status` (`Admin\User\Status::update`) — POSTs the new status via `UserStatusRequest`. Calls `Users::suspend()` if `SUSPENDED` is supplied, otherwise `Users::setStatus()`. The unlock endpoint `/admin/users/{user}/lock` (DELETE) uses an **inline** permission check (`$request->user()->can('manage user status')`) rather than a FormRequest authorize — that's the one place this convention is broken.

---

## User Extended Profile (UserSchema)

The `User` model uses `Fieldable`, so an admin-defined `FieldLayout` can attach arbitrary fields (bio, avatar URL, social handles, etc.) to every user.

### Setting Up the User Schema

`App\Support\UserFieldLayout` is a static resolver that returns the system-wide `FieldLayout` row used for `User`. The layout ID is stored in settings; the resolver caches the lookup. The admin route `/admin/users/layouts` (controller `Admin\User\Layout::show`) renders the layout editor.

### Writing Field Values to a User

`UserService::create()` and `UserService::update()` call `setFields($user, $input['fields'])` from the `PersistsFieldValues` trait, which iterates the layout, casts values per field type, and upserts `FieldValue` rows.

### Reading Field Values from a User

```php
$user->field('bio');        // single value, cast by field type
$user->fieldArray();        // ['bio' => 'Likes coffee.', 'social_x' => '@jane']
```

### Typical Controller Pattern

`Admin\User::edit()` calls `Users::find((int) $id)` and eager-loads `roles`, `tokens`, `fieldValues.field.fieldType`, and the last 10 `statusLogs` with the acting user. The view renders the field layout via `_schema-tab-elements`.

### Comparison: Users vs Entries

Users have no `EntryType`, no lifecycle hooks, and no `entry_relationships`. They have no group; the field layout is system-wide. Apart from these, the storage path is identical: `User::field('handle')` resolves to a `FieldValue` row in the same morphic table.

> **Potential Issue.** `Admin\User\Layout` is mostly a scaffold: six of seven methods are empty `//` stubs ([app/Http/Controllers/Admin/User/Layout.php](app/Http/Controllers/Admin/User/Layout.php)). Only `show()` is implemented and routed. The class file is misleading by suggesting CRUD that is not wired.

---

## Author Eligibility

Authors are not a separate user type — they are users with an `EntryAuthor` row. An entry's `authors()` relation is `BelongsToMany` against this `entry_authors` table, with a per-entry pivot ordering via the `entry_author_entry` join.

### Schema

```
entry_authors           (id, user_id, display_name, is_active)
entry_author_entry      (entry_id, entry_author_id, sort_order)
```

`User::is_author` is the eligibility flag exposed in admin forms; flipping it on calls `EntryAuthorService::promote()`, which inserts an `EntryAuthor` row.

### EntryAuthorService and EntryAuthors Facade

Methods: `getEligible()`, `findByUser(User)`, `promote(User, ?display_name)`, `demote(User)`, `sync(User, bool $isAuthor, ?display_name)`. All accessible via the `EntryAuthors` facade (`app/Facades/EntryAuthors.php`).

### Promoting and Demoting Authors

`promote()` upserts the `entry_authors` row with `is_active = true`. `demote()` flips `is_active = false`; it does **not** delete the row, preserving historical assignments on existing entries.

### Author Eligibility via UserService

`UserService::create()` and `update()` accept `is_author` and `author_display_name`. When present, the service delegates to `EntryAuthorService::sync($user, $isAuthor, $displayName)`.

### Querying Eligible Authors

```php
EntryAuthors::getEligible();  // Collection of active EntryAuthor rows with user loaded
```

The `Admin\Entry::create` and `::edit` controllers call this to populate the authors picker.

---

## UserService and the Users Facade

`App\Services\UserService` is a singleton bound in the container and exposed via the `Users` facade. The service holds 30+ methods grouped into CRUD, roles, custom fields, passwords, status, two-factor, OAuth, and action-class plumbing.

### CRUD

```php
Users::create(array $data): User
Users::update(User $user, array $data): User
Users::delete(User $user): bool
Users::find(int $id): ?User
Users::findOrFail(int $id): User
Users::paginate(int $perPage = 20): LengthAwarePaginator
Users::getTotalCount(): int
```

### Roles

```php
Users::syncRoles(User $user, array $roleNames): void
Users::assignRole(User $user, string $roleName): void
Users::removeRole(User $user, string $roleName): void
```

### Custom Fields

Pulled in via the `PersistsFieldValues` trait — `setFields(User, array $values)`.

### Passwords

```php
Users::updatePassword(User $user, string $newPassword): void
Users::resetPassword(User $user, string $newPassword): void
```

### Status Management

```php
Users::setStatus(User $user, UserStatus $status, ?string $reason): User
Users::suspend(User $user, DateTimeInterface $until, ?string $reason): User
Users::lockUser(User $user, DateTimeInterface $until, string $reason): User
Users::unlockUser(User $user, string $reason): User
```

Each dispatches `UserStatusChanged` or `UserLockChanged`.

### Two-Factor Authentication

Delegated to Fortify's `TwoFactorAuthenticatable` trait on `User`. `Users::enableTwoFactor($user)`, `Users::confirmTwoFactor(...)`, `Users::disableTwoFactor($user)`.

### OAuth Token Management

```php
Users::upsertOauthToken(User, string $provider, array $tokens): void   // wrapped in DB::transaction
Users::oauthTokenFor(User, string $provider): ?OauthToken
Users::firstOrCreateFromSocial(string $email, string $name, string $provider, string $ip): User
```

`firstOrCreateFromSocial` dispatches `NewSocialUserRegistered` when a new row is inserted. There is no first-party listener for that event.

### Action Classes Inventory

```
app/Actions/User/
├── CreateNewUser.php                  (Fortify CreatesNewUsers contract)
├── UpdateUserPassword.php
├── UpdateUserProfileInformation.php
├── ResetUserPassword.php
└── Token/CreateNewUserToken.php
```

Each is a thin wrapper that calls the corresponding `Users::*` method.

---

## OAuth and Social Login

Public routes in `routes/web.php`:

- `GET /login/{provider}` — `Login::redirectToProvider` (`social.login.provider`)
- `GET /login/{provider}/callback` — `Login::handleProviderCallback` (`social.login.callback`)

`handleProviderCallback` calls `Socialite::driver($provider)->user()`, then `Users::firstOrCreateFromSocial(...)`, then `canAccessSystem()`, then `Auth::login($localUser, true)`. Errors surface as `withErrors()` on the redirect back to `/login`.

OAuth tokens are stored in `user_oauth_tokens` and refreshed via `App\Services\OAuth\TokenRefreshService`. `App\Console\Commands\RefreshTokens` is the scheduled command that would batch-refresh those tokens — currently a scaffold.

---

## System and User Settings

`config/settings.php` declares **domains** and **fields**. Values live in the `setting_values` table.

### Settings Domains

Each domain has `name`, `description`, `icon`, `sort_order`, `fields`. Fields have `handle`, `label`, `type`, `default`, `rules`, `instructions`, `group`, `hidden`, `user_overridable`, and (optionally) `options_callback`.

### Value Storage and Resolution

Resolution order: **user override → system value → config default**. System values cache under `settings.system.{domain}` for one hour; user values under `settings.user.{userId}.{domain}` for one hour. Cache is busted by write methods.

### Reading Settings

```php
use App\Facades\Settings;            // no — the facade is bound as 'settings'
app('settings')->get('general', 'items_per_page');
app(\App\Settings::class)->get('general', 'items_per_page', 10, $user);
```

The `App\Settings` class implements both `get($domain, $handle, $default, $user)` and `all($domain, $user)`. `system($domain)` returns only the system-tier values.

### Writing System Settings

`UpdateDomainSettings` action (`app/Actions/Settings/UpdateDomainSettings.php`): iterates the validated payload, calls `Settings::set($domain, $handle, $value)`, and busts the domain cache.

### Writing User Preferences

`UpdateUserSettings` action: same shape, but writes user-tier rows and busts `settings.user.{userId}.{domain}`.

### Adding a Setting

Append a new field block to the domain's `fields` array in `config/settings.php`. No migration required — values are stored in `setting_values`. Set `user_overridable: true` to expose the field on the user-preferences screen.

---

## Field Types

### Built-in Types

`app/Field/Types/`:

| Class | Handle | Storage column | Notes |
|---|---|---|---|
| `Text` | `text` | `value_text` | Single-line input |
| `Textarea` | `textarea` | `value_text` | Multi-line |
| `Html` | `html` | `value_text` | Rich text editor |
| `EmailAddress` | `email_address` | `value_text` | |
| `Url` | `url` | `value_text` | |
| `Telephone` | `telephone` | `value_text` | |
| `ColorPicker` | `color_picker` | `value_text` | |
| `Number` | `number` | `value_integer` / `value_float` (via `HasDecimalStorage` trait) | |
| `Boolean` | `boolean` | `value_boolean` | |
| `Date` | `date` | `value_date` | |
| `Slider` | `slider` | `value_integer` / `value_float` | |
| `Select` | `select` | `value_text` | |
| `MultiSelect` | `multi_select` | `value_json` | |
| `RadioGroup` | `radio_group` | `value_text` | |
| `FileUpload` | `file_upload` | `value_json` | IDs synced to `mediables` pivot by `FieldValueObserver` |
| `Users` | `users` | `value_json` | |
| `Relationship` | `relationship` | n/a — `isRelational() = true` | Writes to `entry_relationships` |
| `StructuredRows` | `structured_rows` | `value_json` | Repeatable rows; columns declared in field settings |

> **Potential Issue.** `BASICS.md` lists the built-in field types as "Text, Textarea, Number, Date, EmailAddress, Url, Telephone, ColorPicker, Boolean, Relationship" — missing `Html`, `FileUpload`, `Slider`, `Select`, `MultiSelect`, `RadioGroup`, `Users`, and `StructuredRows`. Update `BASICS.md` next time it's touched.

> **Potential Issue.** No `_fields/html.twig` partial exists in `resources/views/_fields/` despite the `Html` field type being implemented at `app/Field/Types/Html.php`. The `render()` call will fail until the partial is added.

### Field Render Chain

1. The admin form's tab macro emits `{{ field.render({value: ..., id: ...})|raw }}` from `resources/views/admin/_inc/_schema-tab-elements.twig:54`.
2. `App\Models\Field::render()` ([app/Models/Field.php:57](app/Models/Field.php)) injects `$params['field'] = $this` and calls `$this->typeInstance()->render($params)`.
3. `typeInstance()` resolves the concrete `App\Field\Types\*` subclass via `FieldType::instance($this->settings)`.
4. The subclass returns `view('_fields.<handle>', $params)->render()`.
5. The partial outputs an `<input>`/`<select>`/etc., using the convention `name="fields[{{ field.handle }}]"` and `value="{{ old('fields.' ~ field.handle, value) }}"`.

`AbstractField` (`app/Field/AbstractField.php`) declares the contract:

```php
abstract public function storageColumn(): string;          // value_text | value_integer | value_float | value_date | value_boolean | value_json
public function isRelational(): bool;                       // default false
public function validate(mixed $value): bool|string;
public function getRules(): array;
public function cast(mixed $value): mixed;
public function value(mixed $stored): mixed;                // post-read transform
public function render(array $params): string;              // default ''
public function settingsForm(): array;
public function settingsDefaults(): array;
public function settingsRules(): array;
public function settingsFormOptions(): array;
```

### Creating a Custom Field Type

1. Subclass `App\Field\AbstractField`, implement `storageColumn()` and `render()`.
2. Add a row to the `field_types` table (handle, name, class string).
3. Add a `_fields/<handle>.twig` partial.
4. Optionally register settings via `settingsForm()` so the admin field-create screen exposes them. The `Admin\Field::typeSettings` AJAX endpoint re-renders the settings panel when the user changes the type dropdown.

Custom types are validated against the DB on every boot by `app:validate-class-references`.

---

## Field Groups and Fields

A `FieldGroup` is a named bag of `Field` rows. Groups attach to `EntryGroup`, `CategoryGroup`, and `Media\Library` via the `field_groupables` polymorphic pivot. The `HasFieldGroups` trait on each container exposes the relation.

### Creating a Field Group with Fields

```php
$group = FieldGroup::create(['name' => 'SEO', 'handle' => 'seo']);

Field::create([
    'field_type_id' => FieldType::where('handle', 'text')->value('id'),
    'field_group_id' => $group->id,
    'name' => 'Meta Description',
    'handle' => 'meta_description',
]);
```

---

## Field Layouts

A `FieldLayout` is the visual organisation of fields into tabs. The hierarchy is `FieldLayout → FieldLayoutTab → FieldLayoutTabElement → Field → FieldType`. `FieldLayout` instances attach to containers (EntryGroup, EntryType, CategoryGroup, MediaLibrary, the system User Layout) via the `HasFieldLayout` trait.

### Building a Layout Programmatically

```php
$layout = FieldLayout::create(['name' => 'Article', 'handle' => 'article']);
$tab = $layout->tabs()->create(['name' => 'Content', 'handle' => 'content', 'sort_order' => 1]);
$tab->elements()->create([
    'field_id' => $field->id,
    'required' => true,
    'sort_order' => 1,
]);
```

### Getting All Fields from a Layout

`FieldLayout::fields()` returns a flat `Collection<Field>` from all tabs and elements with type-over-group precedence applied.

> **Potential Issue.** `SEO_SCHEMA_PLAN.md` and `SEARCH_PLAN_V2.md` both introduce a parallel `resolveLayoutElements()` method to return `FieldLayoutTabElement` rows instead of flat `Field` rows. The first plan to land builds this primitive; the second consumes it. The Multilingual plan does not need it. Coordinate to avoid double-implementation.

---

## Status Groups and Statuses

`StatusGroup` belongs-to `EntryGroup`. `Status` belongs to `StatusGroup` and carries `name`, `handle`, `is_public` (boolean), `is_default` (boolean), `sort_order`.

### Creating a Status Group

```php
$group = StatusGroup::create(['name' => 'Article workflow', 'handle' => 'article_workflow']);
$group->statuses()->createMany([
    ['name' => 'Draft',     'handle' => 'draft',     'is_public' => false, 'is_default' => true,  'sort_order' => 1],
    ['name' => 'Published', 'handle' => 'published', 'is_public' => true,  'is_default' => false, 'sort_order' => 2],
]);
```

### How an Entry Stores its Status

Three denormalised columns: `entries.status_id`, `entries.status_handle`, `entries.status_is_public`. The handle and `is_public` flag are duplicated onto the entry for query efficiency (`Entry::published()` reads `status_is_public = true` without joining `statuses`).

### StatusObserver — keeping status_is_public consistent

`App\Observers\StatusObserver` listens for `Status::updating`. When `is_public` flips, the observer bulk-updates every `entries` row pointing at the status (`Entry::where('status_id', $status->id)->update(['status_is_public' => $newValue])`).

> **Potential Issue.** `TODOS.md` item 13 calls for enforcing a "cannot change a status handle while entries are assigned" rule; `ENTRY_LAYER_OVERVIEW.md` issue #6 restates this. Neither is implemented. Likewise item 24 ("ensure deleting default status isn't possible") is not enforced.

---

## Category Groups and Categories

`Category` uses `Fieldable` and supports hierarchical `parent_id` self-references. Unique on `(group_id, handle)`.

### Creating a Category Group and Categories

```php
$group = CategoryGroup::create(['name' => 'Tags', 'handle' => 'tags']);
$root  = $group->categories()->create(['name' => 'News', 'handle' => 'news', 'sort_order' => 1]);
$child = $group->categories()->create(['name' => 'Local', 'handle' => 'local', 'parent_id' => $root->id, 'sort_order' => 1]);
```

### Fetching Categories

```php
Categories::tree($groupId);                    // nested tree
Categories::flat($groupId);                    // flat collection
Categories::resolveLayout(Category $category); // FieldLayout used for fields
$category->fieldArray();                       // ['intro' => '...', ...]
```

---

## Custom Field Groups on Category Groups

CategoryGroup uses `HasFieldGroups` and `HasFieldLayout`. Field group assignments survive across layouts; layout is the visual arrangement.

---

## Entry Groups and Entry Types

```
EntryGroup       ← StatusGroup, FieldLayout, FieldGroups[], CategoryGroups[], EntryTypes[]
EntryType        ← EntryBehavior, FieldLayout (optional), default_template, has_entry_tree, max_depth, allowed_parent_types[]
Entry            ← title, handle, status, published_at, EntryType, EntryGroup, authors[], categories[], fieldValues[], entryRelationships[]
```

`EntryType` references a PHP class extending `App\EntryTypes\AbstractEntryType`. The class drives lifecycle hooks (`beforeCreate`, `afterCreate`, `beforeUpdate`, `afterUpdate`, `validate`). `EntryTypeRegistry::resolveByHandle()` caches the resolved instance per handle.

### Seeded Entry Groups and Types

`database/seeders/EntryGroupSeeder.php` ships default groups (`Pages`, `Blog`, etc.) with appropriate field layouts. Adjust the seeder or create groups via the admin UI at `/admin/entries/groups`.

### Available Entry Type Classes

`app/EntryTypes/`:

- `AbstractEntryType` — base class with the lifecycle hook signatures.
- `GeneralEntryType` — fallback type, used when an `EntryType` row's `object` class string is unresolvable.
- Concrete types as seeded.

### Registry Resolution and Admin Constraints

`EntryTypeRegistry::resolveByHandle($handle)` looks up the `EntryType` row by handle (caching globally), tries to instantiate `$row->object` as an `AbstractEntryType`, and falls back to `GeneralEntryType` with a log warning if the class is missing.

> **Potential Issue.** Per `ENTRY_LAYER_OVERVIEW.md` issue #1, `EntryTypeRegistry` caches by handle alone but `EntryGroup` membership scopes a type's effective domain — two groups can both register a type with the same handle (or the same class can be used twice with different field layouts) and the cache will return whichever the registry happened to resolve first.

### Field Layering: Group Fields + Type Fields

When an `Entry` is created, its effective field set is the union of `EntryGroup.fieldLayout` and `EntryType.fieldLayout`, with **type fields taking precedence** over group fields with the same handle. The merge lives in `EntryRepository::resolveLayoutFields()`.

### Lifecycle Hook Signatures

```php
abstract class AbstractEntryType {
    public function beforeCreate(array $data): array;
    public function afterCreate(Entry $entry, array $data): void;
    public function beforeUpdate(Entry $entry, array $data): array;
    public function afterUpdate(Entry $entry, array $data): void;
    public function validate(array $data, ?Entry $entry = null): array;   // return ['field' => ['message']] to fail
}
```

`beforeCreate` runs **inside** the `DB::transaction`; `afterCreate` runs **outside** the transaction (after commit), so emails/webhooks do not roll back. Same for update.

### Creating an Entry Group

Via the admin UI (`/admin/entries/groups/create`) or programmatically:

```php
$group = EntryGroups::create([
    'name' => 'Blog',
    'handle' => 'blog',
    'status_group_id' => $statusGroup->id,
]);
```

### Creating an Entry Type Class

Subclass `AbstractEntryType` (`app/EntryTypes/PostEntryType.php` etc.), implement whichever hooks you need.

### Registering the Entry Type in the Database

```php
EntryType::create([
    'entry_group_id' => $group->id,
    'entry_behavior_id' => $behavior->id,
    'field_layout_id' => $layout->id,
    'name' => 'Post',
    'handle' => 'post',
    'object' => \App\EntryTypes\PostEntryType::class,
    'default_template' => 'blog.post',
    'has_entry_tree' => true,
    'max_depth' => 0,
    'allowed_parent_types' => [],
]);
```

> **Potential Issue.** `EntryType.max_depth` and `EntryType.allowed_parent_types` are stored, fillable, and cast (`array` cast on `allowed_parent_types`) but **no service or repository reads them** at insertion time. The enforcement belongs in `EntryService::createTreeNode()` / `syncTreeNode()` (and the `treeAssertUnique*` helpers next to them). This was a Known Gap on `OVERVIEW.md` and remains real per the 2026-05-16 verification in `ACTION_PLAN.md`.

---

## Adding a New Entry Type End-to-End

1. **Create fields and a FieldGroup** — define every field once in `fields` and group them into a `FieldGroup`.
2. **Create FieldLayouts** — one for the EntryGroup, one for the EntryType (optional).
3. **Create the EntryGroup** — attach the StatusGroup, the field layout, the field groups, and the category groups.
4. **Write the EntryType PHP class** — extend `AbstractEntryType`, override the hooks you need.
5. **Register the EntryType row** — bind the class string in `entry_types.object`.
6. **Validate and create entries** — submit through the admin or `Content::create('handle', $data)`.

For full code samples see `BASICS.md` and `ENTRY_LAYER_OVERVIEW.md`.

---

## Creating and Updating Entries

### Creating an Entry

```php
use App\Facades\Content;

$entry = Content::create('post', [
    'title' => 'My First Post',
    'handle' => 'my-first-post',
    'status' => 'draft',                  // handle within the group's status group
    'published_at' => null,
    'authors' => [1, 2],                  // user IDs
    'categories' => [4, 7],               // category IDs
    'fields' => [
        'body' => '<p>Hello world.</p>',
        'related_posts' => [11, 12, 13],  // Relationship field — IDs of target entries
    ],
]);
```

`Content::create` → `EntryService::create` → `EntryTypeRegistry::resolveByHandle` → `$entryType->validate($data)` → `EntryRepository::create($entryType, $data)` (transactional) → `EntryService::createTreeNode()` (if `has_entry_tree`) → `$entryType->afterCreate(...)` (outside transaction).

### Updating an Entry

```php
Content::update($entry, [
    'title' => 'Updated Title',
    'status' => 'published',
    'fields' => ['body' => '<p>New body.</p>'],
]);
```

`Content::update` → `EntryService::update` (`DB::transaction` wraps both `repository->applyData()` and `syncTreeNode()`) → `$entryType->beforeUpdate` (outside the inner repository transaction, inside the outer service transaction) → `EntryRepository::applyData` (inside its own nested transaction) → `$entryType->afterUpdate` (outside the inner transaction).

### Using the Relationship Field

The `Relationship` field type has `isRelational() = true`. Submitting an array of entry IDs to its handle writes rows to `entry_relationships(entry_id, related_entry_id, field_id, sort_order)`. Reading via `$entry->field('related_posts')` returns a `Collection<Entry>` sorted by `sort_order`.

> **Potential Issue.** `ENTRY_LAYER_OVERVIEW.md` issue #2 notes that the entry-tree node is created **after** the `EntryRepository::create` transaction commits. If the tree-create then fails, the entry exists without a tree row and the URL routing layer cannot reach it until the next save. The fix is to put `createTreeNode` inside the same transaction as the entry insert, or to dispatch a retryable job.

---

## Querying Entries

The `Content` facade exposes `query()` which returns an `EntryQueryBuilder`:

```php
$results = Content::query()
    ->inGroup('blog')
    ->ofType('post')
    ->published()
    ->withCategory('news')
    ->withAuthor($userId)
    ->latest()
    ->paginate(10);
```

### Full `EntryQueryBuilder` surface

```
->inGroup(string $handle)
->ofType(string $handle)
->published()
->withStatus(string $statusHandle)
->withAuthor(int|User $userOrId)
->withCategory(int|string|Category $idHandleOrModel)
->whereField(string $handle, mixed $valueOrOperator, mixed $value = null)
->orderBy(string $col, string $dir = 'asc')
->latest()
->where(...)         // passthrough
->get(), ->first(), ->firstOrFail(), ->paginate(), ->count()
```

The builder applies the canonical eager-load set automatically:

```
entryGroup, entryType, creator, authors, categories,
fieldValues.field.fieldType,
entryRelationships.field,
entryRelationships.relatedEntry
```

so accessing field values never causes N+1.

### Reading Field Values

```php
$entry->field('body');         // resolved value, cast by field type
$entry->fieldArray();          // ['body' => '<p>...</p>', 'related_posts' => Collection<Entry>]
$entry->fieldValues;            // raw FieldValue collection
```

### Accessing Entry Authors

```php
$entry->authors                 // BelongsToMany<EntryAuthor> with pivot sort_order
$entry->authors->first()->user  // User
$entry->authors->first()->display_name
```

---

## Accessing Entry Categories via the Content Facade

### Reading categories on a result set

```php
$entries = Content::query()->inGroup('blog')->get();
foreach ($entries as $entry) {
    foreach ($entry->categories as $category) {
        echo $category->name;
    }
}
```

### Loading a category's group on already-fetched entries

```php
$entries->loadMissing('categories.group');
```

### Filtering entries by category

```php
Content::query()->withCategory('news')->paginate(10);
Content::query()->withCategory($categoryModel)->get();
```

### Accessing category field values

```php
$category->field('intro');
$category->fieldArray();
```

---

## Entry Metrics

`EntryService::recordMetric(Entry $entry, string $type)` writes a row to `entry_metrics`. The table schema and recorded types are designed for view/click/conversion counters consumed by dashboards. The `Admin\Dashboard::index` controller reads aggregates directly.

---

## Deleting Entries

`Entries::delete($entry)` → `EntryRepository::delete()`. Cascades:

- `field_values` deletes via FK cascade.
- `entry_relationships` deletes via FK cascade (both `entry_id` and `related_entry_id` directions).
- `entry_author_entry` pivot rows delete via FK cascade on `entry_id`.
- `categorizables` pivot rows delete via the morphic cascade.
- `mediables` pivot rows (FileUpload field references) delete via the `FieldValueObserver::deleted` handler on each cascaded `FieldValue`.
- `entry_trees` row deletes via FK cascade on `entry_id`; `EntryTreeObserver::deleted` then re-fetches every direct child (whose `parent_id` has been `nullOnDelete`'d) and calls `EntryService::rebuildTreeUri()` to recompute `uri` and `depth` on the orphaned subtree.

> **Potential Issue.** `TODOS.md` items 11 and 22 call for soft deletes on entries, categories, users, and statuses. None of the four currently use `SoftDeletes`. Only `Media` uses `SoftDeletes` (via the two-stage purge flow documented in `MEDIA_LAYER_OVERVIEW.md`).

---

## Media Library

The native Media layer is the authoritative implementation; `MEDIA_LAYER_OVERVIEW.md` is the canonical reference. Spatie MediaLibrary has been removed from the runtime dependency list.

### Libraries

`Media\Library` (`app/Models/Media/Library.php`) owns disk/adapter settings, `allowed_types`, `max_size`, `sort_order`, an optional `field_layout_id`, attached `category_groups`, and attached `field_groups`. Traits: `HasFactory`, `HasCategoryGroups`, `HasFieldGroups`, `HasFieldLayout`, `HasMediaItems`.

### Uploads

`Library::addMediaFromUpload(UploadedFile, array $attributes)` (from `HasMediaItems`) stores the file on the configured disk, creates the `Media` row inside a transaction, assigns a sequential `sort_order`, and deletes the physical file if DB persistence fails.

Admin uploads come in via `POST /admin/media/libraries/{library_id}/upload` → `Admin\Media\Library::upload(UploadMediaRequest, $id)` → `app(UploadMedia::class)->upload($request, $library)` → `app('media-service')->upload($library, $request->file('file'), $attributes)` → `$library->addMediaFromUpload(...)`.

### Categories and Field Groups

`Media\Library` can have its own `category_groups`, exposing per-library categorisation. The library's `field_layout_id` declares custom fields on every `Media` item it owns (e.g. `alt_text`, `caption`).

### Attachment to Other Models

The `mediables` pivot (`media_id, mediable_type, mediable_id, field_id, sort_order`) records every place a Media item is attached. `field_id = 0` is a sentinel for "direct attachment" (e.g. an avatar, library browser pick); `field_id > 0` indicates the Media is attached through a specific `FileUpload` field. The unique key `(media_id, mediable_type, mediable_id, field_id)` requires `field_id` to be NOT NULL (the sentinel `0` keeps the index correct under MySQL's "many NULLs allowed" rule).

`HasMedia` on attachable models (`Entry`, `User`, etc.) provides `mediaForField(handle)` and direct attachment helpers.

### Transformations

`Media\Transformation` rows track image variants. `TransformationDriverInterface` has three concrete drivers — `ImagickTransformationDriver`, `GDTransformationDriver`, `NullTransformationDriver` — selected at boot time by `AppServiceProvider::register()` based on installed PHP extensions.

### Deletion

Two stages:
1. `Media::delete()` (admin or API) soft-deletes the `Media` row (the model uses `SoftDeletes`).
2. The `PurgeDeletedMedia` job (run on schedule) physically removes the file from the disk and deletes `media_transformations` rows.

`media.library_id` deliberately has no FK constraint so the purge job can find rows whose parent library has been deleted.

> **Potential Issue.** `media-status-implementation-plan.md` proposes adding status governance (`status_group_id` on `Media\Library`, `status_id`/`status_handle`/`status_is_public` on `Media`) modelled on `Entry`. Four known gaps in the plan: `FileUpload::validate()` needs a status check, bulk status update UI, `MediaResource` doesn't exist yet, and admin status filter UI.

---

## Site Routing (Public-Facing URLs)

### Frontend Catch-All Route

`routes/web.php` (single line for the public site):

```php
Route::get('{uri?}', [Site::class, 'show'])->where('uri', '.*')->name('site.show');
```

### RouteResult

`App\Services\SiteRouting\RouteResult` (value object): `type`, `template`, `data` (array shared into the view), `resource` (the view name or other source identifier), and `redirect_url` for redirect responses.

### Entry Tree Layer

`entry_trees(id, entry_id unique, parent_id, handle, uri unique, depth, sort_order, template, redirect_url, is_home)`. URIs are stored absolute (e.g. `/about/contact`); the home node has `uri = '/'`.

`scopeByUri($uri)` is defined on `EntryTree`, but `EntryTreeRouteDriver::resolve()` uses a direct `where('uri', $uri)` rather than the scope.

### EntryTree Driver

`EntryTreeRouteDriver::resolve($uri)` (`app/Services/SiteRouting/RouteDrivers/EntryTreeRouteDriver.php`):

1. Normalises `$uri` via `EntryTree::normalizeUri()`.
2. Finds the `EntryTree` row by `uri` filtered to entries whose `published()` scope is true.
3. Eager-loads `entry.entryType`, `parent.entry`, `children.entry.entryType`.
4. If `$node->redirect_url` is set, returns a redirect `RouteResult`.
5. Resolves `$template = $node->template ?? $entry->entryType?->default_template ?? 'entries.show'` and returns `RouteResult(type: 'entry_tree', template: 'templates::' . $template, data: ['entry', 'entryType', 'node'])`.

### Template Driver

`TemplateRouteDriver::resolve($uri)` matches the URI to a Twig template under `resources/templates/` via the `templates::` namespace:

- Reserved first segments are rejected: `api, admin, login, logout, register, password, sanctum, storage, assets, vendor`.
- `/` → `config('site.templates.default_template', 'templates::site.index')`.
- `/{group}` → `templates::{group}.index`.
- `/{group}/{second}` → first tries `templates::{group}.{second}`; falls back to `templates::{group}.entry` with `handle = $second` and `tail = $rest`.

Constructor sets `View::replaceNamespace('admin', [])` so user URIs cannot escape into admin views.

`templateData($segments)` exposes `segments`, numbered `segment_1..N`, key/value pairs derived from segments 3+ (taken as alternating pairs), and `get` (query string).

> **Potential Issue.** `config('site.templates.base_path')` and `config('site.templates.not_found_template')` are present in `config/site.php` but **not read** by any route driver. `default_template` IS read (line 49 of `TemplateRouteDriver`). Either wire `base_path` into `viewName()` and `not_found_template` into the null-return branches, or remove the unused keys. This is a stable item in `OVERVIEW.md`'s Known Gaps.

### Configuring Driver Priority

`config/site.php`:

```php
'routing' => ['priority' => ['entry_tree', 'template']],
```

`SiteRouter::drivers()` reads this list and constructs a driver chain. Adding a new driver requires a class under `App\Services\SiteRouting\RouteDrivers\` implementing `RouteDriverInterface` and a corresponding entry in the priority list.

---

## Template and View Stack

### TwigBridge Configuration

`config/twigbridge.php`. Highlights:

- Extension `twig`; `debug` mirrors `APP_DEBUG`; `cache = null` (default path); `auto_reload = true`; `autoescape = 'html'`.
- `Illuminate\Contracts\Support\Htmlable` is a safe class (Htmlable returns are not escaped).
- Globals: empty.
- Enabled extensions: `Laravel\Event`, `Loader\Facades`, `Loader\Filters`, `Loader\Functions`, `Loader\Globals`, `Laravel\Auth`, `Laravel\Config`, `Laravel\Dump`, `Laravel\Input`, `Laravel\Session`, `Laravel\Str`, `Laravel\Translator`, `Laravel\Url`, `Laravel\Model`, `Laravel\Gate`, `Laravel\Vite`.
- Disabled (commented): `Form`, `Html`, `Legacy\Facades`.
- Registered Twig functions: `elixir`, `head`, `last`, `mix`, `app`, `public_path`, `file_exists`, `__`, `session`.
- Registered filters: `get` → `data_get`.

> **Potential Issue.** `app/Twig/Extensions/` exists but contains no PHP — there are no first-party Twig extensions. The Multilingual plan introduces `app/Twig/LocaleExtension.php` registering `current_locale`, `available_locales`, `default_locale`, `locale_url`, `trans_field`. The SEO Schema plan introduces `App\Twig\SchemaExtension`. Either will be the first occupant of this directory.

### View Namespaces

Registered in `app/Providers/AppServiceProvider.php::boot()`:

```php
View::addNamespace('templates', resource_path('templates'));
View::addNamespace('admin', resource_path('views/admin'));
```

`TemplateRouteDriver`'s constructor calls `View::replaceNamespace('admin', [])` per-request so site templates cannot reach admin views.

> **Potential Issue.** No `_fields` namespace is registered. Field partials resolve through Laravel's default loader because `resources/views/_fields/` sits under the root view path. Refactoring `_fields/*.twig` to a non-root location later will require an explicit namespace registration.

### Admin Layout

`resources/views/admin/_inc/_layout.twig` (303 lines). Every admin page extends this layout via `{% extends 'admin._inc._layout' %}`.

**Layout grid.** `<aside id="primary-sidebar">` (sticky left sidebar) plus content column with `<header>`, `<main>`, `<footer>`.

**Variables seeded at the top** (lines 13–23): `primary_nav_sticky`, `active_nav`, `secondary_sidebar_template`, `has_secondary_sidebar`, `current_user = auth_user()`, `account_menu_links` (default array of Profile/Preferences/API Tokens/Logout).

**Blocks**:

| Block | Purpose |
|---|---|
| `title` | `<title>` content; defaults to `page_title` |
| `vendor_scripts` | extra `<script>` tags in head |
| `head` | misc head additions |
| `primary_navigation` | the entire left-nav body |
| `header` | header bar (breadcrumbs + search + account menu) |
| `breadcrumbs` | breadcrumb trail |
| `secondary_sidebar` | shown when `has_secondary_sidebar` is true and no template is passed |
| `content` | main body (rendered twice depending on `has_secondary_sidebar`) |
| `footer` | footer bar |
| `scripts` | extra `<script>` tags before `</body>` |

**Navigation pattern.** Disclosure groups (`Content`, `Members`, `Settings`) toggle via CSS-only hidden checkboxes. Active state is set from `active_nav` / `active_secondary_nav` / `active_user_section` variables that each page sets.

**Search bar.** `<form action="{{ admin_search_url|default('#') }}" method="GET">` — only wired when the page provides `admin_search_url`.

**Account menu.** Avatar button (`data-account-menu-button`) plus hidden panel (`data-account-menu-panel`). A vanilla-JS IIFE at the bottom of `_layout.twig` (lines 246–298) handles open/close on click and Escape.

**Message rendering.** Every content variant includes `{{ include('admin._inc._message') }}` immediately above the `content` block.

### Admin Includes

`resources/views/admin/_inc/`:

| File | Role |
|---|---|
| `_layout.twig` | Master layout (the only one extended by admin pages) |
| `_message.twig` | Flash banner; renders `session('failure')`, `session('success')`, and `errors.count()` blocks with Tailwind alert styling |
| `_schema-tab-elements.twig` | Two Twig macros: `tabButton(tab)` and `tabContent(tab, title_field_set, entry, field_values)`; renders the field-layout loop |
| `_form-fields.twig` | Reusable input macros: `input`, `password`, `file`, `textarea`, `select`, `color`, `slider`, `toggle`, `checkbox_card` |
| `_structured-rows.twig`, `_structured-rows-example.twig` | UI for the `StructuredRows` editor |
| `_header.twig`, `_header_bar.twig`, `_footer.twig`, `_sidebar.twig` | **Legacy** Bootstrap/jQuery-era includes from a prior theme |

> **Potential Issue.** `_header.twig`, `_header_bar.twig`, `_footer.twig`, and `_sidebar.twig` are vestigial — `_layout.twig` does not include them and they reference assets under `/assets/vendor/...` that no longer exist. Candidate for removal in a cleanup pass.

### Site Templates

`resources/templates/`:

- `site/index.twig`, `site/tree.twig` — site home (config'd default is `templates::site.index`).
- `about/*.twig`, `entries/content.twig`, `tailwind/index.twig` — sample/legacy templates.
- `tailwind2/*.twig` — the new TailwindCSS reference theme (60+ files), with role-specific layouts (`auth-layout.twig`, `layout.twig`) and one-file-per-page samples used as design references.

Resolution: see [Template Driver](#template-driver) above. Entry templates resolved by `EntryTreeRouteDriver` use the `templates::` namespace and fall back through `node.template → entryType.default_template → 'entries.show'`.

### Vite Asset Pipeline

`vite.config.js`:

- `laravel-vite-plugin` with `input: ['resources/css/app.css', 'resources/js/app.js']`, `refresh: true`.
- `@tailwindcss/vite` plugin (Tailwind 4 — no separate `tailwind.config.js`).
- Dev server host pinned to `eric.laravel-dev.com`; CORS allows `127.0.0.1:8000`, `localhost:8000`, and the host; HMR over `ws`.

`package.json`:

- `type: module`, `scripts.build = vite build`, `scripts.dev = vite`.
- Tailwind 4 + `@tailwindcss/forms`, axios 1.11, concurrently 9, vite 7, `laravel-vite-plugin` 2.
- **No Vue, React, Alpine, or Livewire**. All admin JS is hand-written vanilla in `_layout.twig` or `resources/js/app.js`/`bootstrap.js`.

---

## Controller Layer

The codebase has three controller families: **Site** (public catch-all), **Admin** (Twig HTML), and **API** (`v1/*`). Each has a distinct base class and conventions.

### Base Classes and Helpers

**`App\Http\Controllers\Controller`** — abstract root. Constructor pulls `App\Settings` from the container and reads `general.items_per_page` into `$total_per_page` (default 10). Single helper: `can(string $permission): bool` which delegates to `Gate::allows()`.

**`App\Http\Controllers\Admin\Controller`** — extends root. Constructor `abort(403)`s unless `Gate::allows('access admin')`. Exposes `view(string $path, array $data = [])` which prepends the `admin::` namespace.

**`App\Http\Controllers\Api\Controller`** — extends root. Holds API pagination/filter helpers used uniformly across `v1\*`:

- `limit(Request)` — capped at 100, defaults 10
- `page(Request)` — defaults 1
- `sort(Request, array $allowed)` — whitelisted column, falls back to `'id'`
- `sortDir(Request)` — `asc`/`desc`, default `asc`
- `buildWhere(array $where, Request)` — appends `created_at <=` / `>=` filters
- `createdBefore` / `createdAfter` — raw query inputs

The API base also carries class-level OpenAPI schemas for `Meta`, `Links`, `PaginationInfo`, `RelatedItem`, and the Sanctum security scheme.

### Standard CRUD Shape

Admin resource controllers follow `index / create / store / show / edit / update / destroy / confirm`:

- `index` — paginate, return `$this->view('foo.index', ...)`.
- `create` — load supporting collections (groups, layouts), return `$this->view('foo.create', ...)`.
- `store(StoreFooRequest)` — `app(CreateNewFoo::class)->create($request->validated())`; redirect to `foo.show` with flash.
- `show(string $id)` — find or 404; eager-load relations; return view.
- `edit(string $id)` — same but for the edit form.
- `update(EditFooRequest, $id)` — `app(EditFoo::class)->edit($model, $request->validated())`; redirect.
- `destroy(DeleteFooRequest, $id)` — find, call action or `$model->delete()`, redirect.
- `confirm($id)` — separate GET endpoint returning the delete-confirmation view. Routes are wired **before** the `Route::resource(...)` so `{id}/confirm` matches before the resource `show`.

API controllers follow `index / store / show / update / destroy` only. They return `JsonResource` / `JsonResourceCollection` or `JsonResponse(null, 204)` for destroy.

**Permission pattern**:

- **Admin**: enforced two ways: (a) the constructor's `access admin` gate, then (b) per-action `authorize()` inside the injected FormRequest. A few outliers do an inline `$user->can(...)` (e.g. `Account\Token::create`, `User\Token::create`, `User\Status::destroy`).
- **API**: `auth:sanctum` middleware on the whole `/api/v1` group, then **every public method calls `$this->can('<permission>')` and `abort(404)` (read) or `abort(403)` (write)** before touching the model — in addition to whatever the FormRequest does.

**Error / response surface**:

| Surface | Admin | API |
|---|---|---|
| Not found | `abort(404)` or `redirect()->route('foo.index')->with('failure', trans('foo.not_found'))` (inconsistent — both forms used) | `abort(404)` |
| Validation failure | FormRequest auto-redirects with `withErrors()` + old input | FormRequest returns 422 JSON |
| Success | `redirect()->route(...)->with('success' or 'status', trans('foo.<verb>'))` | `JsonResource` or 201/204 `JsonResponse` |
| Permission denied | `abort(403)` (mostly from base constructor or FormRequest `authorize()`) | `abort(403)` or `abort(404)` (reads prefer 404) |

> **Potential Issue.** Two flash keys are used for the same purpose: `success` (most controllers) and `status` (`Category::store`, `CategoryGroup::store`, `Role::store`, sometimes `EntryGroup::store`). The Twig layout reads both, but the inconsistency is gratuitous. Pick one.

**API logging.** Every `/api/v1/*` route in `routes/api.php` chains `->middleware(LogRequestResponse::class)` per-resource (not at group level).

### Site Controllers

#### `App\Http\Controllers\Site`

Single-method catch-all for the public site.

- `show(SiteRouter $router, ?string $uri = null): View|RedirectResponse` — `GET /{uri?}` (`site.show`). Delegates to `SiteRouter::render($uri)`. No permission check (public).

#### `App\Http\Controllers\Login`

Socialite OAuth callback handler for `/login/{provider}`.

- `redirectToProvider(string $provider)` — `GET /login/{provider}` (`social.login.provider`). Returns `Socialite::driver($provider)->redirect()`.
- `handleProviderCallback(Request, string $provider)` — `GET /login/{provider}/callback` (`social.login.callback`). Calls `Users::firstOrCreateFromSocial(email, name, provider, ip)`, checks `canAccessSystem()`, `Auth::login($user, true)`, `redirect()->intended('/')`. Surfaces `InvalidStateException` and `accessDeniedReason()` via `withErrors`.

### Admin Controllers — Catalog

All under `Route::prefix('admin')->middleware(['auth'])`.

#### `Admin\Dashboard`

- `index()` — `GET /admin/dashboard` (`dashboard`). Aggregates dashboard counters directly on models (`Entry::count()`, `Users::getTotalCount()`, `Media::count()`, `ApiLog::count()`, recent error counts, latest entries, top API routes via raw `DB::raw`). Returns `$this->view('dashboard', ...)`.
- `chart()` — `GET /admin/dashboard/chart` (`dashboard-chart`). Returns JSON.

> **Potential Issue.** `Admin\Dashboard` does its own SQL aggregation (including a `selectRaw` for top API routes) rather than delegating to a dashboard service. Modest size (~50 lines) but the only admin controller doing real query logic.

#### `Admin\Index`

**Dead code.** Single `index()` method whose first statement is `return redirect('/login');`. Everything after that line (a `Rest\Client` call, `print_r`, commented-out user-token spelunking) is unreachable. Not registered in `routes/admin.php`.

> **Potential Issue.** Either route to it (and remove the dead code) or delete the file.

#### `Admin\Account`

Self-service for the authenticated user.

- `index()` — `GET /admin/account` (`account`).
- `details()` — `GET /admin/account/details` (`account.details`). Loads `UserFieldLayout::resolve()`, `$user->fieldArray()`.
- `password()` — `GET /admin/account/password`.
- `change_password(EditPasswordRequest)` — `PUT /admin/account/password` (`account.password.update`). `Hash::make` + `forceFill`.
- `update(EditUserRequest)` — `PUT /admin/account` (`account.edit`). `app(UpdateUserProfileInformation::class)->update($user, $validated)`.

#### `Admin\Account\Settings`

User-level setting overrides.

- `show()` — `GET /admin/account/settings` (`account.settings`). Walks `SettingDomain::ordered()`, calls `$domain->overridableConfigFields()`, fetches `Settings::all($domain->handle, $user)` and the user's stored `SettingValue` rows.
- `update(UpdateUserSettingsRequest)` — `PUT /admin/account/settings` (`account.edit_settings`). Delegates to `UpdateUserSettings`.

> **Potential Issue.** `Account\Settings::update()` does `redirect()->route('settings.user')`. That route name **does not exist** in `routes/admin.php`. Submitting the account-settings form raises `RouteNotFoundException`. The redirect should target `account.settings`. Live bug.

#### `Admin\Account\Token`

Sanctum tokens for the *current* user.

- `index()` / `create()` / `store(StoreAccountTokenRequest)` / `edit(string $token_id)` / `update(EditAccountTokenRequest, $token_id)` / `destroy(DeleteAccountTokenRequest, $token_id)` / `confirm($token_id)`.
- `create()` does an inline permission check: `if (!$user->can('api')) abort(404)`.
- `show($id)` is bound to `abort(404)` (not routed).

#### `Admin\User`

Resource for managing arbitrary users.

- `index()` / `create()` / `store(StoreUserRequest)` — `Users::paginate(20)`; `RoleModel::all()` + `UserFieldLayout::resolve()`; `app(CreateNewUser::class)->create(...)`.
- `show(string $id)` / `edit(string $id)` — `Users::find` + eager-load `roles, tokens, fieldValues.field.fieldType, statusLogs (10)`.
- `update(EditUserRequest, $id)` — `UpdateUserProfileInformation` action.
- `destroy(DeleteUserRequest, $id)` — `Users::delete()`.
- `confirm(string $id)` — `GET /admin/users/{id}/confirm` (`users.confirm`); returns the delete view.
- `password(PasswordUserRequest, $id)` — `PUT /admin/users/{id}/password`.
- `changePassword(string $id)` — `GET /admin/users/{id}/password`.

#### `Admin\User\Token`

Same shape as `Account\Token` for *other* users. Uses `Users::find`, `Users::getToken`, `Users::updateToken`, `Users::revokeToken`.

#### `Admin\User\Status`

- `update(UserStatusRequest, $id)` — `PATCH /admin/users/{id}/status`. Suspends or sets status.
- `destroy(Request, $id)` — `DELETE /admin/users/{id}/lock`. **Inline** permission check (no FormRequest). Calls `Users::unlockUser($user)`.

#### `Admin\User\Layout`

- `show()` — renders the system user field layout. **Scaffold otherwise** — six other methods are empty `//`.

#### `Admin\Role`

`index/create/store/show/edit/update/destroy/confirm`. Uses `CreateNewRole`, `EditRole`. `show()` is empty (`//`).

#### `Admin\Category`

Note: category create/store routes are `categories/{group_id}/create` — group is in the URL, not on the resource. The plain `categories` resource handles `edit/update/destroy/show`.

- `index()` — redirects to `categories.groups`.
- `create($group_id)` — loads group with `fieldLayout.tabs.elements.field.fieldType`, builds the parent-category tree via private helpers (`buildCategoryTree`, `collectDescendantIds`, `flattenFromMap`).
- `store(StoreCategoryRequest)` — `CreateNewCategory` action. Redirect destination depends on whether `parent_id` is set.
- `show($id)` — redirects to `categories.edit`.
- `edit/update/destroy/confirm` — standard, with `EditCategory` for update.

#### `Admin\Category\Group`

Standard CRUD via `CreateNewCategoryGroup` / `EditCategoryGroup`.

> **Potential Issue.** `Admin\Category\Group::edit()` runs `$group->fieldGroups()->allRelatedIds()` and discards the result — leftover from an earlier refactor.

#### `Admin\Entry`

Create/store are scoped to a group via URL: `/admin/entries/groups/{group_id}/create`. `Route::resource('entries', Entry::class)->only(['edit', 'update', 'destroy'])`.

- `store(StoreEntryRequest)` — `app(CreateNewEntry::class)->create($request->validated())`.
- `create($group_id, Request)` — eager-loads entry types, field layout, status group, category groups. Picks entry type by `?type=` query string or falls back to first.
- `edit($id)` — `Entries::get((int)$id)`, eager-loads the kitchen sink.
- `update(EditEntryRequest, $id)` — `Entries::findMeta((int)$id)` + `app(UpdateEntry::class)->update(...)`.
- `destroy(DeleteEntryRequest, $id)` — `Entries::delete($entry)`.
- `confirm($id)` — standard delete view.

#### `Admin\Entry\Group`

Uses `EntryGroups` facade. `show()` runs a one-shot `selectRaw('status_id, COUNT(*) as count')->groupBy('status_id')` to avoid lazy per-status queries in the view. Private `formData()` builds the available-entry-types list.

#### `Admin\Entry\Type`

Standard CRUD via the `EntryTypes` facade. Loads `EntryBehavior::orderBy('name')->get()` and `FieldLayout::orderBy('name')->get()` for the form.

#### `Admin\Field`

`index()` returns `abort(404)` — no fields index page. Standard CRUD via `CreateNewField` / `EditField`.

- `typeSettings(Request): Response` — `GET /admin/fields/type-settings` (`fields.type_settings`). AJAX endpoint that re-renders `admin.fields._settings_panel` when the user changes the field-type dropdown. Validates `type_id`, looks up `FieldType`, calls `$instance->settingsForm()` and `settingsFormOptions()` via private `buildSettingsForm()`. Returns a raw view response (HTML fragment), not JSON.

> **Potential Issue.** `Admin\Field::index()` unconditionally `abort(404)`s. Defined to satisfy `Route::resource`'s implicit index route — but that means `GET /admin/fields` is reachable as a 404. Either the resource should be `->except(['index'])` or this is intentional.

#### `Admin\Field\Group`

Standard `FieldGroup` CRUD via `CreateNewFieldGroup` / `EditFieldGroup`.

#### `Admin\FieldLayout`

Top-level layout CRUD. Uses `CreateNewFieldLayout`, `EditFieldLayout`, `DeleteFieldLayout`. Private `sidebarData()` is shared by `create/edit/confirm`.

#### `Admin\FieldLayout\Tab`

Nested tab CRUD: `field-layouts/{layout_id}/tabs/{tab_id}`. Every action manually verifies parent ownership. Uses `CreateNewTab`, `EditTab`, `DeleteTab`. `edit()` additionally loads `Field::orderBy('name')->get()` as `available_fields`.

#### `Admin\FieldLayout\TabElement`

Nested under tab: `field-layouts/{layout_id}/tabs/{tab_id}/elements/{element_id}`. Double parent-ownership verification. Uses `CreateTabElement`, `EditTabElement`, `DeleteTabElement`. Only `store`, `update`, `confirm`, `destroy` are routed — element create/edit happen inline from the tab edit view.

#### `Admin\Status`

Create/store scoped to a status group via URL. Resource registered with `->except(['index', 'create', 'store', 'show'])`. Standard CRUD via `CreateNewStatus`/`EditStatus`. Always redirects back to `statuses.groups.show`.

#### `Admin\Status\Group`

Standard CRUD. `index()` paginates with `withCount('statuses')->withCount('entryGroups')`.

#### `Admin\Media`

- `index()` — paginate media.
- `create($library_id)` — load library or redirect to `media.libraries` with `failure`.
- `store()` — **stub**; redirects to `media.libraries`. Uploads happen via `Library::upload`.
- `show($id)` / `edit($id)` / `update(EditMediaRequest)` — standard.
- `destroy(DeleteMediaRequest, $id)` — `(new DeleteMediaAction)->delete($media)` (instantiated directly, not from container).
- `confirm($id)` — delete view.
- `download($id)` — `GET /admin/media/{id}/download`. `Storage::disk($media->disk)->download($media->path, $media->original_name)`.

Resource is wired as `->parameters(['media' => 'media_item'])` because `media` is a reserved-feeling word.

> **Potential Issue.** `Admin\Media::destroy` directly instantiates `(new DeleteMediaAction)` instead of `app(DeleteMediaAction::class)`. Stylistic inconsistency with the rest of the codebase.

#### `Admin\Media\Library`

Library CRUD via `CreateNewMediaLibrary`, `EditMediaLibrary`, `DeleteMediaLibrary`.

- `upload(UploadMediaRequest, $id)` — `POST /admin/media/libraries/{library_id}/upload`. Calls `app(UploadMedia::class)->upload($request, $library)`. **Content-negotiated** — returns JSON for `expectsJson()`, otherwise redirects.

#### `Admin\Settings\Domain`

System-level settings.

- `index()` — defined but **not routed** in `routes/admin.php`.
- `show(string $handle)` — `GET /admin/settings/{handle}` (`settings.show`). Hydrates `options_callback` closures, groups fields by `group` key, filters out hidden fields.
- `update(UpdateDomainSettingsRequest, $handle)` — `PUT /admin/settings/{handle}` (`settings.update`).

> **Potential Issue.** `Admin\Settings\Domain::index()` exists but has no `Route::get('settings', ...)` binding. Either wire it up or remove the method.

#### `Admin\Settings\UserSettings`

**Functional near-duplicate of `Admin\Account\Settings`** — same logic, renders `settings.user` instead of `account.settings`.

> **Potential Issue.** `Admin\Settings\UserSettings` is **unreachable**: no route in `routes/admin.php` points at it. The `Account\Settings::update` redirect target `settings.user` does not exist either. The two files should be reconciled — pick one and remove the other.

### API Controllers — Catalog

All under `Route::prefix('v1')->middleware('auth:sanctum')` with `LogRequestResponse` per-resource. Full OpenAPI attributes via `OpenApi\Attributes`.

#### `Api\v1\User`

`apiResource('users')` → `index/store/show/update/destroy`.

- `index(Request)` — `$this->can('read users')` → `UserModel::with(['roles','fieldValues'])` + `buildWhere/sort/sortDir/limit`. Returns `UserCollection`.
- `store(StoreUserRequest)` — `Users::create($validated)` → `UserResource` with 201.
- `show(int $user)` — `read users` gate, eager-load, `UserResource`.
- `update(EditUserRequest, $user)` — `Users::update`, returns `UserResource`.
- `destroy(int $user)` — `delete user` gate; rejects self-delete with 403; `Users::delete`; 204.

#### `Api\v1\Account`

**Mostly placeholder stubs.** Only `show` is routed (`GET /api/v1/account`). All five methods (`show`, `update`, `updatePassword`, `updateAvatar`, `updateEmail`) just return `response()->json(['message' => '...'], 200)`. No actual work, no model lookup.

> **Potential Issue.** `Api\v1\Account` is entirely placeholders. Known Gap in `OVERVIEW.md`; still real per `ACTION_PLAN.md` 2026-05-16 verification.

#### `Api\v1\Entries`

Nested: `apiResource('entry-groups.entries')` with `entry-groups => group_id`, `entries => entry`.

- `index(Request, $group_id)` — `read entries` gate, scoped query `where('entry_group_id', $group_id)`, eager-load `fieldValues, authors, categories`, then `buildWhere/sort/sortDir/limit`. Allowed sort columns: `id, title, handle, published_at, created_at, updated_at`.
- `store(StoreEntryRequest)` — `app(CreateNewEntry::class)->create(...)` → `EntryResource(Content::find(...))` with 201.
- `show($group_id, $entry)` — `Content::find($entry)` + scoping check. `EntryResource`.
- `update(EditEntryRequest, $group_id, $entry)` — `EntryModel::find` + scope, `app(UpdateEntry::class)->update(...)`.
- `destroy($group_id, $entry)` — `delete entry` gate; `Content::delete($model)`; 204.

#### `Api\v1\EntryGroups`

`apiResource('entry-groups')`. Uses the `EntryGroups` facade. `index` eagerly loads `withCount(['entries','entryTypes'])`. Sort columns: `id, name, handle, created_at, updated_at`.

#### `Api\v1\Categories`

Nested: `apiResource('category-groups.categories')`. `index` accepts `?all=1` to include child categories (otherwise `whereNull('parent_id')`). Uses `CreateNewCategory` and `EditCategory`.

#### `Api\v1\CategoryGroups`

`apiResource('category-groups')`. Uses `CreateNewCategoryGroup` / `EditCategoryGroup`.

#### `Api\v1\StatusGroups`

`apiResource('status-groups')`. Uses `CreateNewStatusGroup` / `EditStatusGroup`.

#### `Api\v1\Statuses`

Flat resource (`apiResource('statuses')`). `index` accepts `?status_group_id=N` to filter. Uses `CreateNewStatus` / `EditStatus`.

---

## Model Creation and Modification

The canonical write chain is: **Route → Controller → FormRequest → Action → Service → Repository → Model save + Observer → Event → Listener**. Not every domain uses every layer.

### Services Layer

`app/Services/`:

| File | Container binding | Facade |
|---|---|---|
| `AbstractService.php` | — (base class only) | — |
| `EntryService.php` | singleton, aliased to `ContentService` | `Entries`, `Content` |
| `ContentService.php` | alias of `EntryService` | `Content` |
| `EntryGroupService.php` | singleton | `EntryGroups` |
| `EntryTypeService.php` | singleton | `EntryTypes` |
| `CategoryService.php` | singleton | `Categories` |
| `UserService.php` | singleton | `Users` |
| `EntryAuthorService.php` | singleton | `EntryAuthors` |
| `FieldService.php` | bound as `'fields-service'` | — |
| `FilesService.php` | bound as `'files-service'` | — |
| `MediaStorageService.php` | bound as `'media-service'` | — |
| `Media/GDTransformationDriver.php`, `ImagickTransformationDriver.php`, `NullTransformationDriver.php` | bound for `TransformationDriverInterface` at boot via extension detection | — |
| `SiteRouting/SiteRouter.php`, `RouteResult.php`, `RouteDrivers/*.php` | resolved per request | — |
| `OAuth/TokenRefreshService.php` | resolved per request | — |

The `EntryService` is the largest single file by method count. Its public surface includes `create/update/delete`, `find/get/findMeta/getMeta/findByHandle/findOrFailByHandle`, `loadRelatedRecursive`, `fieldArray`, `getFieldValue/setFieldValue`, `resolveLayout/resolveFields`, `recordMetric`, `createTreeNode/moveTreeNode/rebuildTreeUri/deleteTreeNode`, and `query()` (returns `EntryQueryBuilder`).

### Repositories Layer

`app/Repositories/`:

| File | Notes |
|---|---|
| `AbstractFieldableRepository.php` | Abstract. Declares `resolveLayoutFields(Model): Collection`, provides `applyFieldValues(Model, array)` and `upsertFieldValue()` with race-safe SQLSTATE 23000 retry. Implements `RepositoryInterface`. |
| `EntryRepository.php` | Does **not** extend `AbstractFieldableRepository` — has its own implementation because entries also handle relational fields (`entry_relationships`), authors, categories, and statuses. Singleton bound in `ContentServiceProvider::register()`. |
| `CategoryRepository.php` | Extends `AbstractFieldableRepository`. Includes cycle prevention in `applyCoreAttributes`. |
| `MediaRepository.php` | Extends `AbstractFieldableRepository`. `applyData(Media, array)` (no `create` — uploads create via library trait), `delete(Media)`. |

There is no `UserRepository`, `EntryGroupRepository`, `EntryTypeRepository`, `StatusRepository`, or `MediaLibraryRepository`. Those services interact with Eloquent directly.

> **Potential Issue.** `TODOS.md` item 8 (prefixed `--` for done) calls for a Repository base class. `AbstractFieldableRepository` exists but is intentionally limited to Fieldable models. Whether a broader `AbstractRepository` should exist is an open design question.

### Actions Layer

`app/Actions/`. All extend `AbstractAction.php` (empty base class). Convention: controllers resolve actions via `app(ActionName::class)`; some Fortify-required actions implement Fortify contracts.

Grouped:

```
Actions/
├── Entry/
│   ├── CreateNewEntry.php
│   ├── UpdateEntry.php
│   ├── RecordEntryMetric.php
│   ├── Group/{CreateNewEntryGroup, EditEntryGroup}.php
│   ├── Type/{CreateNewEntryType, EditEntryType}.php
│   └── Tree/{CreateEntryTreeNode, MoveEntryTreeNode, RebuildEntryTreeUri}.php
├── Category/
│   ├── CreateNewCategory.php
│   ├── EditCategory.php
│   └── Group/{CreateNewCategoryGroup, EditCategoryGroup}.php
├── Field/
│   ├── CreateNewField.php
│   ├── EditField.php
│   ├── Concerns/FiltersFieldSettings.php   (trait)
│   └── Group/{CreateNewFieldGroup, EditFieldGroup}.php
├── FieldLayout/
│   ├── CreateNewFieldLayout.php
│   ├── EditFieldLayout.php
│   ├── DeleteFieldLayout.php
│   ├── Tab/{CreateNewTab, EditTab, DeleteTab}.php
│   └── Tab/Element/{CreateTabElement, EditTabElement, DeleteTabElement}.php
├── Status/
│   ├── CreateNewStatus.php
│   ├── EditStatus.php
│   └── Group/{CreateNewStatusGroup, EditStatusGroup}.php
├── User/
│   ├── CreateNewUser.php             (Fortify CreatesNewUsers contract)
│   ├── UpdateUserPassword.php
│   ├── UpdateUserProfileInformation.php
│   ├── ResetUserPassword.php
│   └── Token/CreateNewUserToken.php
├── Role/{CreateNewRole, EditRole}.php
├── Media/
│   ├── EditMedia.php
│   ├── DeleteMedia.php
│   └── Library/{CreateNewMediaLibrary, EditMediaLibrary, DeleteMediaLibrary, UploadMedia}.php
└── Settings/{UpdateDomainSettings, UpdateUserSettings}.php
```

Actions are very thin — typically a 4-line method that delegates to the facade/service or the repository. Example:

```php
// app/Actions/Entry/CreateNewEntry.php
public function create(array $input): Entry {
    $typeHandle = $input['type_handle'];
    return Content::create($typeHandle, $input);
}
```

> **Potential Issue.** `CreateNewCategory::create()` calls `CategoryRepository::create($group, $input)` directly, bypassing `CategoryService` (every other domain routes through its service). Either elevate `CategoryService` to be the single entry point or document this as deliberate.

### FormRequests Layer

All extend `App\Http\Requests\FormRequest` (`app/Http/Requests/FormRequest.php`), which provides three helpers used by every dynamic form:

```php
schemaFieldRules(?Model $schema): array          // walks fieldLayout.tabs[].elements[], prefixes 'fields.<handle>', merges field-type getRules()
schemaFieldAttributes(?Model $schema): array     // maps 'fields.<handle>' → $field->name
schemaFieldMessages(): array                      // stub
```

`layoutFrom()` accepts either a `FieldLayout` directly or a model with a `fieldLayout` relation.

All request classes follow the same shape: `authorize()` does `Auth::user()->can('<permission>')`; `rules()` returns `array_merge([...static rules...], $this->schemaFieldRules($groupOrTypeOrLayout))`.

Groups:

- **Entry**: `StoreEntryRequest`, `EditEntryRequest`, `DeleteEntryRequest`, `Entry/Group/{Store,Edit,Delete}EntryGroupRequest`, `Entry/Type/{Store,Edit,Delete}EntryTypeRequest`.
- **Category**: `StoreCategoryRequest`, `EditCategoryRequest`, `DeleteCategoryRequest`, `Category/Group/...`.
- **Field**: `Store/Edit/DeleteFieldRequest`, `Field/Group/...`.
- **FieldLayout**: `Store/Edit/DeleteFieldLayoutRequest`, `FieldLayout/Tab/...`, `FieldLayout/Tab/Element/...`.
- **Status**: `Store/Edit/DeleteStatusRequest`, `Status/Group/...`.
- **User**: `Store/Edit/DeleteUserRequest`, `PasswordUserRequest`, `UserStatusRequest`, `User/Token/...`.
- **Account**: `EditUserRequest`, `EditPasswordRequest`, `Account/Token/...`.
- **Media**: `EditMediaRequest`, `DeleteMediaRequest`, `Media/Library/{StoreMediaLibraryFormRequest, EditMediaLibraryRequest, DeleteMediaLibraryRequest, UploadMediaRequest}`.
- **Role**: `Store/Edit/DeleteRoleRequest`.
- **Settings**: `UpdateDomainSettingsRequest`, `UpdateUserSettingsRequest`, `SettingFormRequest`.

Uniqueness scoping:
- Entry `handle` scoped by `entry_group_id`; `status` scoped by `status_group_id`.
- Category `parent_id` scoped by `group_id`; Category `handle` unique per group.
- Status `handle` unique per status group.

`UploadMediaRequest` is the one outlier: `authorize() = true`, and rules are dynamically built from the resolved `Library`'s `allowed_types` and `max_size`.

### Observers, Events, and Listeners

`app/Observers/`:

| Observer | Model | Events | Behaviour |
|---|---|---|---|
| `StatusObserver` | `Status` | `updating` | When `is_public` changes, bulk-updates `entries.status_is_public` for every entry pointing at the status. |
| `EntryTreeObserver` | `EntryTree` | `deleting`, `deleted` | `deleting` snapshots direct-child IDs into a static array; `deleted` re-fetches each child after the FK `nullOnDelete` runs at the DB level and calls `EntryService::rebuildTreeUri()` on each. |
| `FieldValueObserver` | `FieldValue` | `saved`, `deleted` | Only acts when the field type is `FileUpload`. On save: parses `value_json`, batched upserts into the `mediables` pivot, deletes stale rows. On delete: removes the corresponding pivot rows. |

Registration is in `AppServiceProvider::boot()` lines 121–123 via `Model::observe(Observer::class)`.

`app/Events/`:

- `UserStatusChanged(User, ?string $previousStatus, string $newStatus, ?string $reason, array $context)` — from `UserService::setStatus()` and `suspend()`.
- `UserLockChanged(User, mixed $previousLockedUntil, mixed $newLockedUntil, string $reason)` — from `lockUser()` and `unlockUser()`.
- `NewSocialUserRegistered(User, string $provider, string $ip)` — from `firstOrCreateFromSocial()` when `wasRecentlyCreated`.

`app/Listeners/`:

- `WriteUserStatusLog` — registered for both `UserStatusChanged` and `UserLockChanged` via explicit `Event::listen(...)` in `AppServiceProvider::boot()` (lines 126–127). Writes `UserStatusLog` rows; reads `Auth::id()` for `changed_by_user_id`.

There is no `EventServiceProvider::$listen` array — everything is wired by hand.

`NewSocialUserRegistered` has no in-tree listener.

> **Potential Issue.** `FieldValueObserver::syncMediables()` prunes `mediables` rows scoped only by `(fieldable_type, fieldable_id, field_id)`. When the Multilingual plan lands, a FileUpload field will have one `field_values` row per locale and saving one locale will orphan the others' pivot rows. `MULTILINGUAL_PLAN.md` Fix 1 documents the union-across-all-locales correction. The same observer is also a candidate for a Category observer that doesn't yet exist (Category has no model-level observer registered).

### Transactional Behaviour

Explicit `DB::transaction(...)` calls:

- `EntryService::update()` (`app/Services/EntryService.php:64-75`) — wraps `repository->applyData()` + `syncTreeNode()`.
- `EntryService::deleteTreeNode()` (line 128).
- `EntryService::createTreeNode()` (line 305) — validates uniqueness then inserts `EntryTree`.
- `EntryService::moveTreeNode()` (line 557) — rebalances siblings, re-parents, rebuilds URIs.
- `EntryRepository::create()` (lines 28–48) — wraps `beforeCreate` + core attribute application + `applyFieldValues`. `afterCreate` runs **after** commit.
- `EntryRepository::applyData()` (lines 307–330) — mirrors create: `beforeUpdate` outside the transaction, all writes inside, `afterUpdate` after commit.
- `UserService::upsertOauthToken()` (line 486) — revoke-then-insert in a single transaction.

Entry-type lifecycle hook ordering:

- `validate(array $data, ?Entry $entry): array` — runs **before** any transaction starts.
- `beforeCreate(array $data): array` — inside the transaction.
- `afterCreate(Entry $entry, array $data): void` — outside the transaction, only on commit.
- `beforeUpdate(Entry, array $data): array` and `afterUpdate(Entry, array $data): void` — same in/out positioning.

`FieldValue` writes use a race-safe pattern (`upsertFieldValue`): on `QueryException` with SQLSTATE `23000` (unique constraint violation from a concurrent INSERT race), retry once so the second caller updates the row the first caller just committed.

---

## End-to-End Chain Traces

### Entry create chain

`POST /admin/entries/groups/{group_id}/create` (route name `entries.store`):

1. **Route**: `routes/admin.php:161` — `Route::post('entries/groups/{group_id}/create', [Entry::class, 'store'])->name('entries.store');` inside `Route::prefix('admin')->middleware(['auth'])`.
2. **Controller**: `app/Http/Controllers/Admin/Entry.php:17-25` — `app(CreateNewEntry::class)->create($request->validated())` → `redirect()->route('entries.groups.show', $entry->entry_group_id)->with('status', trans('entry.created'))`.
3. **Admin guard**: `Admin\Controller::__construct()` calls `abort(403)` unless `Gate::allows('access admin')`.
4. **FormRequest**: `StoreEntryRequest::authorize()` → `Auth::user()->can('create entry')`. `rules()` builds `EntryGroup::resolvedFields($groupId)` + `EntryType::resolvedFields(...)` and merges `schemaFieldRules(...)` for both, plus static core rules.
5. **Action**: `CreateNewEntry::create($input)` → `Content::create($typeHandle, $input)`.
6. **Service**: `EntryService::create()` at `app/Services/EntryService.php:411-434`. Resolves the concrete `AbstractEntryType` via `EntryTypeRegistry::resolveByHandle($typeHandle)`. Calls `$entryType->validate($data)`; throws `ValidationException::withMessages($errors)` on failure. Calls `$this->repository->create($entryType, $data)`. If the type's `has_entry_tree` is true and `$entry->handle` is filled, calls `$this->createTreeNode(...)`.
7. **Repository**: `EntryRepository::create()` at `app/Repositories/EntryRepository.php:22-53`. Inside `DB::transaction`:
   - `$data = $entryType->beforeCreate($data)`.
   - Builds the `Entry`, sets `entry_group_id`, `entry_type_id`, `created_by_user_id = Auth::id()`.
   - `applyCoreAttributes()` — writes `title`, `handle`, `published_at`.
   - `applyStatus()` — resolves the status by handle within the group's status group; defaults if no handle. Auto-fills `published_at` if status is public.
   - `$entry->save()`.
   - `syncAuthors()`.
   - `syncCategories()`.
   - `applyFieldValues()` — iterates `$fields`; routes each handle to `EntryRelationship` insert (relational types) or `FieldValue::updateOrCreate()` (scalar types). Scalar upsert uses race-safe SQLSTATE 23000 retry.
   - Returns `$entry->refresh()`.
   - After commit: `$entryType->afterCreate($entry, $data)`.
8. **Observers**: `FieldValueObserver::saved` fires on each `FieldValue::updateOrCreate`. For FileUpload fields it syncs `mediables`.
9. **Tree side-effect**: `EntryService::createTreeNode()` opens a nested `DB::transaction`, validates uniqueness, inserts the `EntryTree` row.
10. **Response**: redirect with flash key `status`.

### Entry update chain

`PUT /admin/entries/{entry}` (route `entries.update`):

1. Route: `Route::resource('entries', Entry::class)->only(['edit', 'update', 'destroy'])`.
2. Controller `Admin\Entry::update(EditEntryRequest, $id)`: `Entries::findMeta((int)$id)` → `app(UpdateEntry::class)->update($entry, $request->validated())`.
3. Action: `UpdateEntry::update($entry, $data)` → `Content::update($entry, $data)`.
4. Service: `EntryService::update()` wraps `repository->applyData($entry, $data)` and `syncTreeNode($entry, $data)` in a `DB::transaction`.
5. Repository: `EntryRepository::applyData()` opens a nested transaction; calls `$entryType->beforeUpdate($entry, $data)` outside it; writes core attributes, status, authors, categories, field values inside; calls `$entryType->afterUpdate($entry, $data)` outside it.
6. Observers fire on each `FieldValue::save`.
7. Response: redirect with flash.

### Category create chain

`POST /admin/categories/{group_id}/create` (route `categories.store`):

1. Route: `routes/admin.php:106` — `Route::post('categories/{group_id}/create', [Category::class, 'store'])`.
2. Controller: `Admin\Category::store(StoreCategoryRequest)` merges `group_id` from the route into `validated()` and calls `app(CreateNewCategory::class)->create(...)`.
3. Action: `CreateNewCategory::create()` calls `CategoryRepository::create($group, $input)` **directly** (no service intermediary).
4. Repository: `CategoryRepository::create(Group, array)` builds `Category`, calls `applyCoreAttributes` (slugifies handle, validates `parent_id` against cycles), saves, calls `applyFieldValues()` to upsert field values.
5. **No model-level observer fires** for `Category` — none is registered.
6. Redirect: parent-aware — to `categories.edit` on the parent if `parent_id` is set, else `categories.groups.show`.

### Media upload chain

`POST /admin/media/libraries/{library_id}/upload` (route `media.libraries.upload`):

1. Route: `routes/admin.php:86`.
2. Controller: `Admin\Media\Library::upload(UploadMediaRequest, $id)` — resolves `Library`, calls `app(UploadMedia::class)->upload($request, $library)`. Returns JSON for `expectsJson()`, else redirects.
3. FormRequest: `UploadMediaRequest::rules()` builds `file` rules from the library's `allowed_types` (`mimetypes:` rule) and `max_size` (MB→KB).
4. Action: `UploadMedia::upload(FormRequest, Library)` calls `app('media-service')->upload($library, $request->file('file'), $attributes)` and syncs `categories`.
5. Service: `MediaStorageService::upload()` delegates to `$library->addMediaFromUpload($file, $attributes)` from the `HasMediaItems` trait.
6. Trait: stores the file on the configured disk, creates the `Media` row inside a transaction, assigns `sort_order`, deletes the physical file on DB persistence failure.

Note: `MediaRepository` is **not** involved on upload; it only handles `applyData` (edits) and `delete`.

### User create chain

`POST /admin/users` (route `users.store`):

1. Route: `Route::resource('users', User::class)`.
2. Controller: `Admin\User::store(StoreUserRequest)` — `app(CreateNewUser::class)->create($request->validated())`.
3. Action: `CreateNewUser` (implements `Laravel\Fortify\Contracts\CreatesNewUsers`) → `Users::create($input)`.
4. Service: `UserService::create(array $data)` — hashes password, applies system default status, calls `User::create($attributes)`, syncs roles via `syncRoles()`, writes field values via `PersistsFieldValues::setFields()`, dispatches `EntryAuthorService::sync()` if `is_author` is present.
5. No status event fires on creation; `UserStatusChanged` is only dispatched by `setStatus`, `suspend`, `lockUser`, `unlockUser`.

---

## API Layer

### API Routes

`routes/api.php` (under `Route::prefix('v1')->middleware('auth:sanctum')`):

```
GET    /api/v1/account
PUT    /api/v1/account/{...}                              (all placeholders)

apiResource users                                          → users.index/store/show/update/destroy
apiResource entry-groups                                   → entry_groups.index/store/show/update/destroy
apiResource entry-groups.entries                           → entries nested under group
apiResource category-groups                                → category_groups.index/store/show/update/destroy
apiResource category-groups.categories                     → categories nested under group
apiResource status-groups                                  → status_groups.*
apiResource statuses                                       → statuses.*  (flat; ?status_group_id filter)
```

Each `Route::apiResource` chains `->middleware(LogRequestResponse::class)`.

### API Resources

`app/Http/Resources/Api/`:

- `EntryResource` — `id, entry_group_id, entry_type_id, title, handle, status_handle, status_is_public, published_at, fields (via fieldArray()), authors, categories, created_at, updated_at`. OpenAPI attributes match. (This was a known gap on `OVERVIEW.md`; resolved per 2026-05-16 verification.)
- `EntryCollection`, `EntryGroupResource`, `EntryGroupCollection`.
- `CategoryResource` — `id, group_id, parent_id, name, handle, sort_order, fields, children, created_at, updated_at`.
- `CategoryCollection`, `CategoryGroupResource`, `CategoryGroupCollection`.
- `StatusResource`, `StatusCollection`, `StatusGroupResource`, `StatusGroupCollection`.
- `UserResource`, `UserCollection`.
- `RelatedItem`, `RelatedItems`, `AbstractCollection`.

> **Potential Issue.** No `MediaResource` exists. `media-status-implementation-plan.md` flags this as a follow-up gap. Any future API consumer of Media will need this resource built.

### API Request/Response Logging

`LogRequestResponse` middleware writes:

| Column | Source |
|---|---|
| `request_route` | Request path |
| `method` | HTTP method |
| `user_id` | Current authenticated user ID |
| `request_payload` | Sanitised JSON request input |
| `request_headers` | Sanitised JSON request headers |
| `response_payload` | JSON body or error/body summary |
| `response_headers` | Sanitised JSON response headers |
| `response_status_code` | HTTP response status |

Sensitive keys and headers are redacted (passwords, tokens, authorization headers, cookies, CSRF headers, secrets, client secrets). Logged JSON is truncated to 4000 characters.

`ApiLog` uses `Prunable` and `routes/console.php` schedules `model:prune --model App\Models\ApiLog` daily at 02:00. `App\Jobs\PruneApiLogs` provides an alternate self-rescheduling job (not active).

---

## Admin Route Map

| Area | Route Pattern | Main Controllers |
|---|---|---|
| Dashboard | `/admin/dashboard`, `/admin/dashboard/chart` | `Admin\Dashboard` |
| Account | `/admin/account`, `/admin/account/details`, `/admin/account/password`, `/admin/account/settings`, `/admin/account/tokens/*` | `Admin\Account`, `Admin\Account\Settings`, `Admin\Account\Token` |
| Users | `/admin/users/*`, `/admin/users/layouts`, `/admin/users/{id}/tokens/*`, `/admin/users/{id}/status`, `/admin/users/{id}/lock`, `/admin/users/{id}/password` | `Admin\User`, `Admin\User\Token`, `Admin\User\Status`, `Admin\User\Layout` |
| Roles | `/admin/roles/*` | `Admin\Role` |
| Category groups | `/admin/categories/groups/*` | `Admin\Category\Group` |
| Categories | `/admin/categories/{group_id}/create`, `/admin/categories/{id}/{edit,update,destroy,confirm,show}` | `Admin\Category` |
| Media libraries | `/admin/media/libraries/*`, `/admin/media/libraries/{id}/upload` | `Admin\Media\Library` |
| Media items | `/admin/media/{media_item}/*`, `/admin/media/{id}/download` | `Admin\Media` |
| Field groups | `/admin/fields/groups/*` | `Admin\Field\Group` |
| Fields | `/admin/fields/*`, `/admin/fields/type-settings` | `Admin\Field` |
| Status groups | `/admin/statuses/groups/*` | `Admin\Status\Group` |
| Statuses | `/admin/statuses/{group_id}/create`, `/admin/statuses/{id}/*` | `Admin\Status` |
| Entry groups/types | `/admin/entries/groups/*`, `/admin/entries/groups/{group_id}/types/*` | `Admin\Entry\Group`, `Admin\Entry\Type` |
| Entries | `/admin/entries/groups/{group_id}/create`, `/admin/entries/{id}/*` | `Admin\Entry` |
| Field layouts | `/admin/field-layouts/*`, `.../tabs/*`, `.../elements/*` | `Admin\FieldLayout`, `Admin\FieldLayout\Tab`, `Admin\FieldLayout\TabElement` |
| Settings | `/admin/settings/{handle}` (show + update) | `Admin\Settings\Domain` |

Destructive flows include a `confirm` GET route before the `DELETE`. Route ordering hack: `users/layouts` GET is registered before `Route::resource('users', ...)` so the literal `layouts` segment doesn't get captured by the resource's `{user}` placeholder. Same pattern for every `{id}/confirm` route preceding its resource registration.

---

## Validation Strategy

Two concerns owned by FormRequests:

1. **Authorisation** — `authorize(): bool` calls `Auth::user()->can('<permission>')`. Admin and API write paths both use the same FormRequest class, so the permission check happens twice in the API path (once in `authorize()`, once in the controller's explicit `$this->can(...)` call).
2. **Validation** — `rules()` merges:
   - Static core rules (e.g. `title => 'required|string|max:255'`)
   - Dynamic field rules from `schemaFieldRules($schema)` — each `field.<handle>` gets `required|nullable` + the field type's `getRules()` array
   - Uniqueness rules with appropriate scoping (`entry_group_id`, `group_id`, `status_group_id`)

Common static rules:

- `title` — `required|string|max:255`
- `handle` — `required|string|max:255` + scoped uniqueness via `Rule::unique(...)->where(...)`
- `status` — `nullable|string|exists:statuses,handle,status_group_id,<group's status_group_id>`
- `published_at` — `nullable|date`
- `authors` — `nullable|array`; `authors.*` — `integer|exists:entry_authors,id,is_active,1`
- `categories` — `nullable|array`; `categories.*` — `integer|exists:categories,id`
- `fields` — `nullable|array` (then dynamic per-handle rules)

Validation messages are translated via `lang/en/*.php`.

---

## Bot Blocking, Webhooks, and External Integrations

`App\Models\BotBlock` (`bot_block` table) — IP-keyed reputation cache. Public routes check `BotBlock::shouldBlock($ip)` via the `BotBlock` middleware.

Outbound webhooks and external integrations are sparse in the live codebase. Socialite is the primary external integration (OAuth providers).

> **Potential Issue.** `TenantPlan.md` references `spatie/laravel-webhook-client` as a future install for tenant-scoped webhooks. Not currently in `composer.json`.

---

## Known Gaps and Implementation Status

Per the 2026-05-16 verification pass in `ACTION_PLAN.md`, four of the original eight Known Gaps are resolved; four remain:

**Resolved:**

- `EntryResource` returns the correct entry shape (title, handle, status, fields, etc.).
- `Api\v1\User` permission strings match the seeded `read users` permission.
- Entries API has full CRUD landed.
- Media uses the `Fieldable` trait by default; `media_libraries.field_layout_id` and `mediables.field_id` both exist.

**Still real:**

- `Api\v1\Account@show` returns a placeholder `'Profile updated successfully'` message — see `Account` controller catalog entry above.
- `EntryType.max_depth` and `EntryType.allowed_parent_types` are stored but not enforced anywhere.
- `app:refresh-tokens` is a scaffold; `handle()` is empty.
- `site.templates.base_path` and `site.templates.not_found_template` are not read by any route driver (`default_template` IS now wired).

`OVERVIEW.md` itself is stale on the four resolved items — refresh next time it is touched (or treat this document as its successor).

---

## Potential Issues — Aggregate Register

Consolidated from the inline Potential Issue callouts throughout this document, ordered by severity:

### High severity (live bugs)

1. **`Account\Settings::update` redirects to a non-existent route `settings.user`.** Submitting the account-settings form raises `RouteNotFoundException`. Either add the named route or change the redirect to `account.settings`.
2. **`Admin\Settings\UserSettings` is unreachable** — no route binds to it. Either wire it up or remove the file.
3. **`Admin\Settings\Domain::index()`** is defined but not routed. Either wire it up or remove the method.
4. **No `_fields/html.twig` partial exists** despite the `Html` field type calling `view('_fields.html', $params)`. Any entry form with an `Html` field will fail to render.

### Medium severity (latent bugs that will surface)

5. **`FieldValueObserver::syncMediables` is locale-blind.** Once Multilingual lands, saving one locale's FileUpload field will orphan another locale's `mediables` pivot rows (see `MULTILINGUAL_PLAN.md` Fix 1). The fix is to compute the union of media IDs across all locales before pruning. This is a latent bug exposed by the planned multilingual rollout.
6. **`EntryService::syncTreeNode` reads `$entry->handle` directly.** Same latency — once Multilingual lands, saving a non-default-locale translation will overwrite the default-locale tree row's handle. `MULTILINGUAL_PLAN.md` Fix 2.
7. **Form-request `unique:` rules are locale-blind.** Same latency — `StoreEntryRequest` et al. will need scoping to default-locale rows once translations exist. `MULTILINGUAL_PLAN.md` Fix 3.
8. **Tree-create is not atomic with entry-create.** `EntryService::createTreeNode` runs after the `EntryRepository::create` transaction commits, so a tree failure leaves an entry without a tree row (and therefore no URL). `ENTRY_LAYER_OVERVIEW.md` issue #2.
9. **`EntryType.max_depth` / `allowed_parent_types` are stored but never enforced** at tree-insertion time. Belongs in `EntryService::createTreeNode/syncTreeNode/treeAssertUniqueHandleWithinParent`.
10. **`app:refresh-tokens` is a scaffold.** `TokenRefreshService` exists; the command just needs wiring.
11. **`site.templates.base_path` and `site.templates.not_found_template` are not read.** `default_template` IS. Wire the other two or delete them.

### Low severity (style / hygiene)

12. **`Admin\Index` is dead code** — unreachable code after an unconditional `redirect('/login')`. Delete or refactor.
13. **`Admin\User\Layout` is mostly empty stubs** (six of seven methods). Misleading.
14. **`Admin\Field::index()` abort(404)s by design** — the resource route exists but returns 404. Use `->except(['index'])` instead.
15. **`Admin\Role::show` is empty.** Routed but does nothing.
16. **`Admin\Media::destroy` instantiates the action with `new`** instead of via the container. Stylistic.
17. **Two flash keys (`success` vs `status`)** are used inconsistently. Pick one.
18. **`Admin\Category\Group::edit` discards `$group->fieldGroups()->allRelatedIds()`.** Leftover from a refactor.
19. **`Admin\Dashboard` does its own SQL aggregation.** Modest size, but should ideally route through a service.
20. **Legacy includes `_sidebar.twig`, `_header.twig`, `_header_bar.twig`, `_footer.twig`** are not used by `_layout.twig` and reference dead asset paths. Remove.
21. **`Api\v1\Account` is entirely placeholder stubs.** Known Gap.
22. **`BASICS.md`** lists field types and omits eight of them (`Html`, `FileUpload`, `Slider`, `Select`, `MultiSelect`, `RadioGroup`, `Users`, `StructuredRows`).
23. **`OVERVIEW.md`** is stale on four resolved Known Gaps — should be replaced by this document or updated in lockstep.
24. **`StatusObserver`** keeps `entries.status_is_public` in sync but provides no enforcement of "cannot change status handle while entries are assigned" (`TODOS.md` item 13).
25. **`Category` has no observer.** Cycle prevention and field-value sync live elsewhere.
26. **`CreateNewCategory` bypasses `CategoryService`** — calls `CategoryRepository::create` directly. Document or refactor.

---

## Future Plans and Agenda

The `docs/` directory contains nine forward plans plus three reference docs. Below is a snapshot of each as of 2026-05-16; ordering follows `ACTION_PLAN.md`'s recommended sequence.

### Plan documents in `docs/`

#### `ACTION_PLAN.md` — meta-triage doc

The master triage and ordering document. Recommended sequence: **Step 0 OVERVIEW gaps → Step 1 Media status follow-up → Step 2 TenantPlan → Step 3 Multilingual → Step 4 Search V2 → Step 5 SEO Schema → Step 6 Discussion layer → Step 7 Shop.** Contains a 2026-05-12 status note (Media native layer landed) and a 2026-05-16 verification pass (audited the eight Known Gaps; four resolved, four remain). Includes a "Design Discussion Log" preserving architectural rationale for the SEO Schema and Multilingual plans. **No schema changes of its own.**

#### `MEDIA_LAYER_OVERVIEW.md` — current-state media reference

Authoritative reference for the native Media layer (complete and in testing). Describes `Media\Library`, `Media`, `HasMedia`, `HasMediaItems`, `HasTransformations`, `TransformationDriverInterface`, `mediables` pivot (with `field_id` sentinel), two-stage soft-delete + `PurgeDeletedMedia` purge, `FieldValueObserver`-driven FileUpload sync. `media.library_id` deliberately has no FK so the purge job can find rows after library deletion.

#### `media-status-implementation-plan.md` — follow-up to native media

Adds optional status governance to the completed Media layer. **Schema:** `media_libraries` + `status_group_id` (nullable); `media` + `status_id` + `status_handle` + `status_is_public` (mirroring `Entry`). Extracts a `HasStatusGroup` trait shared with `EntryGroup`. **Known gaps in the plan itself:** `FileUpload::validate()` status check, bulk status update UI, missing `MediaResource`, missing admin status filter UI. Verified 2026-05-12.

#### `TenantPlan.md` — multi-tenancy foundation

Shared-DB + `tenant_id` strategy. **Schema:** new `tenants`, `tenant_users`, `plans`, `tenant_usage`. Adds `tenant_id` to 28 existing tables with revised composite uniques (e.g. `(tenant_id, handle)` for groups, `(tenant_id, uri)` for `entry_trees`). Three-step migration pattern: nullable → backfill → NOT NULL+FK. **Key concepts:** `BelongsToTenant` trait fails closed (LogicException if no bound tenant); `ResolveTenant` middleware resolves custom domain → subdomain → path prefix; `setPermissionsTeamId($tenant->id)` for Spatie teams mode; `TenantAwareJob` trait; `TenantProvisioningJob` deliberately does NOT use the trait (tenant not yet bound); `WithTenantContext` test trait; `EntryRelationship` observer prevents cross-tenant links. Comprehensive Risk Register (18 rows). Listed in `ACTION_PLAN.md` Step 2.

> **Cross-doc contradiction.** `TenantPlan.md` Step 4 says "Spatie MediaLibrary internal jobs need `TenantAwareJob` subclasses" — stale (Spatie was removed before this plan was written).

#### `TenantModel.md` — earlier tenant exploration

Largely superseded by `TenantPlan.md`. Lists tables that should and should not get `tenant_id`; describes options (single-DB, DB-per-tenant, schema-per-tenant). No concrete migration pattern. Use `TenantPlan.md` as the build plan.

#### `MULTILINGUAL_PLAN.md` — content translation

Adds `locale` to `field_values` and `entry_trees`. New `entry_translations`, `category_translations`, `media_translations` tables for canonical text columns. Container-level `is_translatable` flag on `EntryGroup`, `CategoryGroup`, `MediaLibrary`. Per-field `is_translatable`. Locale-prefixed URLs (`/en/about`) via `SetLocaleFromUri` middleware. Read-time fallback to default locale at per-field-value granularity. Localization `Settings` domain. Three latent bugs bundled in as Critical Fixes — see the [Potential Issues](#potential-issues--aggregate-register) register above. Listed in `ACTION_PLAN.md` Step 3.

#### `SEARCH_PLAN_V2.md` — full-text search (current)

Adds `is_searchable` + `search_weight` (tinyInt 1–10) to `field_layout_tab_elements`. New `search_index` table with FULLTEXT-indexed `keywords` mediumText, `owner_id`/`owner_type`, `subtype_id` for entry types. `search_collections` + `search_collection_scopes` with sentinel `''`/`0` instead of NULL. `entry_groups` + `search_settings` JSON. **Key insight:** weights are baked in at index time (word repetition) so MySQL's term-frequency relevance produces weighted ranking with no JOINs. `Searchable` trait contract (`searchableLayout`, `searchableElements`, `searchableSyntheticSources`, `searchOwner`, `searchSubtypeId`, `searchOwnerKey`). Listed in `ACTION_PLAN.md` Step 4.

#### `SEARCH_PLAN.md` — superseded V1

V1 of the search plan. Per-Field `is_searchable`/`search_weight`. Per-content-unit `search_documents` rows with priority cascade resolved at query time via JOINs. Phase 0 spike to validate FULLTEXT scale. **Superseded by V2** — V2 puts the flags on `field_layout_tab_elements` (the "field in context" junction) and moves weight to index time. The two are mutually exclusive.

> **Cross-doc contradiction.** Two Search plans coexist in `docs/`. Only V2 is referenced by `ACTION_PLAN.md`. Consider archiving V1 or marking it superseded inside the file.

#### `SEO_SCHEMA_PLAN.md` — schema.org JSON-LD

Per-entry `schema_type` resolved against a registry of generator classes. **Schema:** `entries` + `schema_type`; `entry_types` + `default_schema_type` (seed value only); `field_layout_tab_elements` + `schema_property`. `AbstractField::schemaValue()` (field types know their own format). `EntryRepository::resolveLayoutElements()` — same primitive Search V2 needs (`FieldLayout::elements()` parallel to `fields()`). `BreadcrumbList` derived from `EntryTree`. Twig-only output (`schema_json(entry)`); no REST API exposure. Listed in `ACTION_PLAN.md` Step 5.

#### `DISCUSSION_LAYER_PLAN.md` — comments/reviews

Polymorphic comments/reviews/discussions attachable via `HasDiscussions` trait. **Schema:** new `discussions` (polymorphic `discussable_*`, `parent_id`, `status` enum, optional 1–5 `rating`, `is_pinned`, moderation columns) and `discussion_reactions` (likes/upvotes/etc.). One-level threading enforced at the service. `DiscussionRepository` + `DiscussionService` pair. Six deferred decisions: soft deletes, depth >1, auto-approval, notifications, read tracking, API/controllers. Listed in `ACTION_PLAN.md` Step 6.

#### `SHOP_PLAN.md` — e-commerce module

`mithra62/Shop` module — products, cart, orders, payments, discounts, tax, shipping, subscriptions, digital delivery. **Schema:** new `carts`, `cart_items`, `download_tokens`, `sequences`, `payment_methods`, `tax_snapshots`, `discount_redemptions`, `product_inventory`, `gateway_credentials`. New shop EntryGroups (`shop_products`, `shop_orders`, `shop_subscriptions`, `shop_discounts`). New `shop` settings domain. **Platform-level needs:** SoftDeletes on Entry, `EntryQueryBuilder::whereField($handle, $operator, $value)`. 11 phases (~14–16 weeks). Listed in `ACTION_PLAN.md` Step 7.

> **Cross-doc contradictions.** (a) `SHOP_PLAN.md` repeatedly refers to `mithra72/Shop` (typo) in the directory tree; composer and the title use `mithra62/Shop`. (b) The plan says "Spatie MediaLibrary is installed" — contradicted by `MEDIA_LAYER_OVERVIEW.md` (Spatie removed). (c) `TODOS.md` item 12 ("Remove `mithra62/Shop` from composer") directly contradicts this plan's premise.

#### `Core50.md` — product built on the platform

"Core 50" — a Referral Relationship Management SaaS product, planned as a `mithra72/Core50/` module on top of the laravel-base CMS. **Out-of-band** — a product built on the platform, not core platform work. Tenancy is a hard precondition. Dated 2026-05-03; planning-only.

> **Cross-doc contradictions.** (a) Uses `mithra72` in file paths (vs `mithra62` elsewhere). (b) Treats media refactor as in-progress (it's done). (c) References `media-refactor-plan.md` which was removed.

#### `BASICS.md` — onboarding/orientation doc

Plain-English orientation for new contributors. Same paradigms as `OVERVIEW.md` but condensed. Living doc.

> **Cross-doc contradiction.** Lists built-in field types and omits eight of them (`Html`, `FileUpload`, `Slider`, `Select`, `MultiSelect`, `RadioGroup`, `Users`, `StructuredRows`).

#### `ENTRY_LAYER_OVERVIEW.md` — entry layer reference + audit

Detailed current-state reference for the Entry layer plus 27 numbered "Potential Issues and Risks" each with a recommended solution. The most detailed single-domain reference doc in the directory. **No schema proposals.**

#### `api-documentation-migration-plan.md` — OpenAPI migration

Plan to complete the OpenAPI/Swagger surface by migrating from `@OA\` docblock annotations to PHP 8 attributes, adding missing endpoint annotations, and solving the dynamic-fields documentation problem via a three-layer strategy (per-field-type value schemas, runtime schema discovery endpoint, generated named entry-type schemas with `oneOf` discriminator). Stays on `darkaonline/l5-swagger ^9.0`.

> **Cross-doc contradiction.** The plan lists `Entries::store/update/destroy` as undocumented, but `ACTION_PLAN.md`'s 2026-05-16 verification says full CRUD has landed with OpenAPI attributes. Refresh the plan against the live source.

#### `TODOS.md` — personal TODO list

Bare numbered TODO list (25 items) of small platform improvements. Items 1, 4, 8 are prefixed `--` (done). Open items include soft deletes, status-handle change protection, default-status-delete prevention, removing `mithra62/Shop` from composer.

> **Cross-doc contradiction.** Item 12 ("Remove `mithra62/Shop` from composer") contradicts `SHOP_PLAN.md`.

### Cross-plan contradictions

Consolidated from the per-doc notes above. Items to reconcile during the next docs pass:

1. **`mithra62` vs `mithra72` namespace typo** — `Core50.md` and `SHOP_PLAN.md` use `mithra72` in directory diagrams. Composer and live code use `mithra62`.
2. **Stale Spatie references** — `TenantModel.md`, `TenantPlan.md` Step 4, `SHOP_PLAN.md` §1 still mention Spatie MediaLibrary as installed. `MEDIA_LAYER_OVERVIEW.md` is authoritative: removed.
3. **`media-refactor-plan.md` is gone** but `Core50.md` still cites it as a prerequisite. Update to point at `MEDIA_LAYER_OVERVIEW.md`.
4. **Two Search plans coexist.** `SEARCH_PLAN.md` (V1, query-time JOIN weighting) is superseded by `SEARCH_PLAN_V2.md` (index-time weighting via word repetition). Mark V1 as superseded in the file header.
5. **`TODOS.md` item 12 vs `SHOP_PLAN.md`.** "Remove `mithra62/Shop` from composer" contradicts the active Shop plan. Decision point.
6. **`BASICS.md` field type list is incomplete.** Eight built-in types are missing.
7. **`api-documentation-migration-plan.md`** treats `Entries::store/update/destroy` as undocumented; in fact they're documented.
8. **`OVERVIEW.md` Known Gaps list** is stale on four items (`EntryResource`, `Api\v1\User` permission, entries API endpoints, Media is Fieldable). Refresh per the 2026-05-16 verification or replace with this document.
9. **`field_layout_tab_elements` becomes the "field in context" junction** for three independent plans: Search V2 adds `is_searchable`/`search_weight`; SEO Schema adds `schema_property`; Multilingual leaves it alone but the per-element render must accept a `locale`. Whoever lands first owns `resolveLayoutElements()`.

---

## Key Data Flow Summary

### Write path (entry creation)

```
Route /admin/entries/groups/{group_id}/create
  └── Admin\Entry::store(StoreEntryRequest)
        └── app(CreateNewEntry::class)->create($input)
              └── Content::create('type_handle', $input)
                    └── EntryService::create()
                          ├── EntryTypeRegistry::resolveByHandle('type_handle')
                          │     └── resolves EntryType row → instantiates PHP class
                          ├── $entryType->validate($data)        // throws ValidationException
                          └── EntryRepository::create(AbstractEntryType, $data)
                                ├── DB::transaction {
                                │     $data = $entryType->beforeCreate($data)
                                │     Load entryGroup (statusGroup, fieldLayout)
                                │     applyCoreAttributes($entry, $data)
                                │     applyStatus($entry, $data)
                                │     $entry->save()
                                │     syncAuthors($entry, $data)
                                │     syncCategories($entry, $data)
                                │     applyFieldValues($entry, $data)
                                │       ├── resolveLayoutFields() (type + group merged, type precedence)
                                │       ├── scalar    → FieldValue::updateOrCreate() (race-safe SQLSTATE 23000 retry)
                                │       │                  └── FieldValueObserver::saved fires (FileUpload → mediables sync)
                                │       └── relational → EntryRelationship::create()
                                │   }
                                ├── $entryType->afterCreate($entry, $data)   // outside transaction, after commit
                                └── EntryService::createTreeNode()           // separate DB::transaction
                                          └── EntryTreeObserver fires on subsequent edits
        └── redirect()->route('entries.groups.show', ...)->with('status'|'success', trans('entry.created'))
```

### Read path (entry query)

```
Content::query()
  └── EntryQueryBuilder
        ├── Chainable: inGroup, ofType, published, withStatus,
        │   withAuthor, withCategory, whereField, where, orderBy, latest
        └── Terminal: get() / paginate() / first() / firstOrFail()
              └── ->with([
                    'entryGroup', 'entryType', 'creator', 'authors',
                    'categories', 'fieldValues.field.fieldType',
                    'entryRelationships.field',
                    'entryRelationships.relatedEntry',
                  ])

$entry->field('handle')
  ├── Scalar:     fieldValues → resolvedValue() (cast by field type's value() method)
  └── Relational: entryRelationships → Collection<Entry> sorted by sort_order
```

### Render path (admin)

```
Admin\Entry::edit($id)
  └── Entries::get($id) (eager-loads everything above)
  └── $this->view('entries.edit', $data)
        └── extends 'admin._inc._layout'
              └── tabs.tabContent(tab, ...) loop
                    └── {{ field.render({value, id})|raw }}
                          └── Field::render() → typeInstance()->render($params)
                                └── view('_fields.<handle>', $params)->render()
                                      └── <input name="fields[handle]" value="...">
```

### Render path (public site)

```
GET /{uri?}
  └── Site::show(SiteRouter, $uri)
        └── SiteRouter::render($uri)
              └── iterate config('site.routing.priority')
                    ├── EntryTreeRouteDriver::resolve($uri)
                    │     └── EntryTree::where('uri', $normalized) + published
                    │     └── RouteResult(template: 'templates::' . $node->template or $entryType->default_template or 'entries.show', data: [entry, entryType, node])
                    └── TemplateRouteDriver::resolve($uri)
                          └── reserved-group rejection
                          └── '/'      → config('site.templates.default_template')
                          └── '/g'    → templates::g.index
                          └── '/g/s' → templates::g.s or templates::g.entry (handle=s)
              └── view($result->template, $result->data)
```

### Field value storage

```
field_values (
  id, field_id, fieldable_id, fieldable_type,
  value_text, value_integer, value_float, value_date, value_boolean, value_json,
  timestamps,
  unique (field_id, fieldable_id, fieldable_type)
)

Relational fields → entry_relationships (entry_id, related_entry_id, field_id, sort_order)
FileUpload fields → field_values.value_json + mediables pivot (synced by FieldValueObserver)
```

---

## Morph Map Aliases

Declared in `AppServiceProvider::boot()` via `Relation::morphMap([...])`. Aliases decouple the polymorphic type strings from PHP class names so renaming a class never requires a database UPDATE. Defined aliases include `entry → App\Models\Entry`, `category → App\Models\Category`, `user → App\Models\User`, `media → App\Models\Media`, and the corresponding tables on the `fieldable`, `categorizable`, `mediable`, and `discussable` (planned) morph relations.

When adding a new polymorphic model (e.g. a future `Product` from the Shop plan, or a `Discussion` from the Discussion plan), register it in the morph map — do not rely on the default class-name resolution. The Tenancy plan, in particular, depends on stable morph type strings for cross-tenant integrity checks.
