# Multi-Tenant Build Plan

Single authoritative reference for implementing multi-tenancy on `laravel-base`. Consolidates all prior planning, caveats, and examples. Build from top to bottom.

---

## Database Strategy

Shared database, `tenant_id` column on every tenant-owned table. One misconfigured query is the risk; the mitigation is the fail-closed `BelongsToTenant` trait (see Step 1). This approach has lower operational cost, simpler migrations, and is the right starting point for an unknown tenant count. High-value tenants can be migrated to their own database later without rearchitecting the application.

---

## Migration Inventory

### New tables

#### `tenants`
```
id                    bigint unsigned PK
name                  string
slug                  string unique           -- subdomain: acme.yourplatform.com
domain                string nullable unique  -- custom domain: cms.acme.com
plan_id               bigint unsigned nullable FK → plans.id
trial_ends_at         timestamp nullable
provisioning_status   enum(pending,provisioning,active,failed)  default: pending
is_active             boolean default true
settings              json nullable           -- tenant-level overrides, keyed by domain.handle
created_at / updated_at
```

#### `tenant_users`
```
tenant_id   bigint unsigned FK → tenants.id
user_id     bigint unsigned FK → users.id
role        enum(owner,admin,member)
created_at
PRIMARY KEY (tenant_id, user_id)
```

#### `plans`
```
id               bigint unsigned PK
name             string
slug             string unique
price_monthly    decimal(10,2)
price_annual     decimal(10,2)
limits           json  -- { "users": 5, "entries": 1000, "storage_bytes": 5368709120, "api_calls_monthly": 10000 }
features         json  -- { "custom_domain": false, "api_access": true }
stripe_price_id  string nullable
is_public        boolean default true
sort_order       int default 0
created_at / updated_at
```

#### `tenant_usage`
```
id          bigint unsigned PK
tenant_id   bigint unsigned FK → tenants.id
metric      string          -- entries | users | storage_bytes | api_calls
value       bigint unsigned default 0
period      string nullable -- YYYY-MM for monthly metrics, null for rolling totals
updated_at
UNIQUE (tenant_id, metric, period)
```

`subscriptions` is provided by `laravel/cashier` — listed here for clarity, not manually created.

---

### Existing tables — `tenant_id` additions

Every migration in this list follows the three-step pattern below. Do not add `NOT NULL` in a single step on a populated table.

**Three-step migration pattern:**
```php
public function up(): void
{
    // 1. Add nullable — succeeds on populated tables
    Schema::table('entries', function (Blueprint $table) {
        $table->unsignedBigInteger('tenant_id')->nullable()->after('entry_group_id')->index();
    });

    // 2. Backfill — run before tightening the constraint
    DB::table('entries')->whereNull('tenant_id')->chunkById(500, function ($rows) {
        DB::table('entries')
            ->whereIn('id', $rows->pluck('id'))
            ->update(['tenant_id' => 1]); // seed tenant id
    });

    // 3. Tighten to NOT NULL + FK
    Schema::table('entries', function (Blueprint $table) {
        $table->unsignedBigInteger('tenant_id')->nullable(false)->change();
        $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
    });
}
```

`create_tenants_table` must run first so the seed tenant (`id = 1`) exists for the backfill.

Where a table has a globally unique handle, slug, URI, or polymorphic composite key, drop the old unique index and recreate it including `tenant_id`.

#### Apply in this order (FK dependencies)

| # | Table | Model | Unique index revision |
|---|---|---|---|
| 1 | `entry_groups` | `EntryGroup` | `(tenant_id, handle)` |
| 2 | `entry_types` | `EntryType` | — |
| 3 | `entries` | `Entry` | `(tenant_id, entry_group_id, handle)` |
| 4 | `entry_trees` | `EntryTree` | `(tenant_id, uri)`; enforce `is_home` uniqueness per tenant at app layer |
| 5 | `entry_metrics` | `EntryMetric` | — |
| 6 | `entry_relationships` | `EntryRelationship` | — |
| 7 | `entry_authors` | pivot — no trait | — |
| 8 | `category_groups` | `CategoryGroup` | `(tenant_id, handle)` |
| 9 | `categories` | `Category` | `(tenant_id, group_id, handle)` |
| 10 | `field_layouts` | `FieldLayout` | — |
| 11 | `field_layout_tabs` | `FieldLayoutTab` | — |
| 12 | `field_layout_tab_elements` | `FieldLayoutTabElement` | — |
| 13 | `fields` | `Field` | `(tenant_id, handle)` |
| 14 | `field_groups` | `FieldGroup` | — |
| 15 | `field_values` | `FieldValue` | `(tenant_id, field_id, fieldable_id, fieldable_type)` |
| 16 | `status_groups` | `StatusGroup` | — |
| 17 | `statuses` | `Status` | — |
| 18 | `setting_domains` | `SettingDomain` | — |
| 19 | `setting_values` | `SettingValue` | add `tenant_id` to all unique constraints |
| 20 | `media_libraries` | `MediaLibrary` | — |
| 21 | `media` | `Media` | — |
| 22 | `api_logs` | `ApiLog` | — |
| 23 | `user_schema` | `UserSchema` | `(tenant_id, user_id)` |
| 24 | `fieldables` | pivot — no trait | — |
| 25 | `categorizables` | pivot — no trait | — |
| 26 | `category_groupables` | pivot — no trait | — |
| 27 | `field_groupables` | pivot — no trait | — |
| 28 | `taggables` | pivot — no trait | `(tenant_id, tag_id, taggable_id, taggable_type)` |

For `roles`, `model_has_roles`, and `model_has_permissions`: Spatie's teams feature adds `tenant_id` (via `team_foreign_key` config) — handle these in Step 1d, not here.

#### Tables that stay global

| Table | Reason |
|---|---|
| `users` | Global — membership lives in `tenant_users` |
| `personal_access_tokens` | Gets `tenant_id` separately in Step 4, not here |
| `user_oauth_tokens` | User-scoped; tenant checked through user |
| `tags` | Global registry; tenant-specific assignment lives in `taggables.tenant_id`. The admin UI must filter tag lists to tags that have at least one `taggables` row for the current tenant — otherwise tag names from other tenants appear in the picker. |
| `field_types` | Platform-level definitions |
| `sessions`, `jobs`, `failed_jobs`, `cache`, `cache_locks`, `password_reset_tokens` | Infrastructure |
| `bb_values` | Platform-level bot-block |

---

## Object Model

```
Tenant
 ├── hasMany         TenantUser (pivot with role)
 ├── belongsToMany   User  (through tenant_users)
 ├── belongsTo       Plan
 ├── hasOne          Subscription (Cashier)
 ├── hasMany         TenantUsage
 │
 ├── hasMany         EntryGroup
 │    ├── belongsTo   StatusGroup
 │    ├── belongsTo   FieldLayout
 │    ├── hasMany     EntryType
 │    │    └── belongsTo  FieldLayout
 │    └── hasMany     Entry
 │         ├── belongsTo    Status
 │         ├── belongsToMany  User (authors, via entry_authors)
 │         ├── hasOne       EntryTree
 │         ├── hasMany      EntryMetric
 │         ├── morphMany    FieldValue    (via Fieldable trait)
 │         └── morphToMany  Category     (via HasCategories trait)
 │
 ├── hasMany         CategoryGroup
 │    └── hasMany    Category
 │         ├── belongsTo  self (parent)
 │         └── hasMany    self (children)
 │
 ├── hasMany         FieldLayout
 │    └── hasMany    FieldLayoutTab
 │         └── hasMany  FieldLayoutTabElement → belongsTo Field
 │
 ├── hasMany         Field
 │    ├── belongsTo   FieldType  (global)
 │    └── hasMany     FieldValue
 │
 ├── hasMany         StatusGroup
 │    └── hasMany    Status
 │
 ├── hasMany         SettingDomain
 │    └── hasMany    SettingValue
 │
 ├── hasMany         MediaLibrary
 │    └── hasMany    Media  (Spatie)
 │
 ├── hasMany         ApiLog
 └── hasMany         Role  (Spatie team-scoped)

User  (global)
 ├── belongsToMany  Tenant  (through tenant_users, withPivot('role'))
 ├── hasMany        UserOauthToken
 ├── hasMany        PersonalAccessToken (tenant_id on token row)
 └── morphMany      FieldValue  (via Fieldable trait)

Plan  (global)
 └── hasMany  Tenant

FieldType  (global)
 └── hasMany  Field
```

---

## Core Trait: `BelongsToTenant`

The engine of the whole system. Fails closed — if a tenant-scoped model is queried without a bound tenant and the code hasn't explicitly opted out, it throws rather than silently returning unscoped data.

```php
// app/Traits/BelongsToTenant.php

namespace App\Traits;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $query) {
            if (! app()->bound(Tenant::class)) {
                if (app()->bound('tenant.scope.optional') && app('tenant.scope.optional') === true) {
                    return;
                }
                throw new LogicException(static::class . ' queried without a bound tenant.');
            }
            $query->where(
                (new static)->qualifyColumn('tenant_id'),
                app(Tenant::class)->id
            );
        });

        static::creating(function ($model) {
            if (! empty($model->tenant_id)) {
                return;
            }
            if (! app()->bound(Tenant::class)) {
                if (app()->bound('tenant.scope.optional') && app('tenant.scope.optional') === true) {
                    return;
                }
                throw new LogicException(static::class . ' created without a bound tenant.');
            }
            $model->tenant_id = app(Tenant::class)->id;
        });
    }

    /**
     * Escape hatch for super-admin and system contexts only.
     * Every usage should be code-reviewed. Never use in tenant controllers.
     */
    public static function withoutTenantScope(): Builder
    {
        return (new static)->newQueryWithoutScope('tenant');
    }

    public function tenant(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
```

Platform routes, migrations, seeders, and cross-tenant commands opt out via `withoutTenantScope()`. For tightly-scoped platform operations that need to temporarily suppress the scope without bypassing it entirely, bind `tenant.scope.optional = true` and restore the previous state immediately after.

---

## Tenant Resolution

```
HTTP Request
  └── ResolveTenant middleware
        ├── Browser/session routes:
        │     custom domain (tenants.domain) → subdomain (tenants.slug) → path prefix
        ├── API routes (Step 4+):
        │     authenticated token's tenant_id → X-Tenant-Slug only if it matches
        └── bind: app()->instance(Tenant::class, $tenant)
              └── setPermissionsTeamId($tenant->id)
                    └── Every BelongsToTenant model query
                          └── WHERE tenant_id = {resolved id}
```

**Security rules:**
- `X-Tenant-Slug` is only valid on authenticated API routes and only when it matches the token's own `tenant_id`. A header alone must never switch tenant context.
- If no active tenant resolves on a tenant route, return `404` or `403` before any controller code runs.
- After resolving a tenant, verify the authenticated user belongs to it via `tenant_users`, unless the route serves public content or is an authorized impersonation session.
- Tenant resolution is an authorization boundary, not a routing convenience.

---

## Step 1 — Working Foundation

**Goal:** Multiple tenants exist, every request resolves to one, all queries are automatically scoped, data is fully isolated.

### 1a — Tenant model and pivot

- Write and run `create_tenants_table` migration (seed a default tenant with `id = 1` for the backfill migrations that follow)
- Write and run `create_tenant_users_table` migration
- Create `app/Models/Tenant.php`:
  - `belongsToMany(User::class, 'tenant_users')->withPivot('role')`
  - `belongsTo(Plan::class)` (nullable until Step 6)
  - `hasMany` for all tenant-owned top-level resources (EntryGroup, CategoryGroup, FieldLayout, StatusGroup, SettingDomain, MediaLibrary, ApiLog, Role)
  - `withinLimits(string $resource): bool` stub that returns `true` until Step 6
- Add to `User`: `belongsToMany(Tenant::class, 'tenant_users')->withPivot('role')`

### 1b — `BelongsToTenant` trait

- Create `app/Traits/BelongsToTenant.php` as shown above
- Validate it in isolation on a single model before rolling it out (`EntryGroup` is a good candidate)
- Write a two-tenant isolation test: create data in tenant A and tenant B, assert neither can query the other's records. This test pattern is reused against every model as you apply the trait.

### 1c — `ResolveTenant` middleware

- Create `app/Http/Middleware/ResolveTenant.php`
- Browser resolution order: custom domain → subdomain → path prefix
- Reject inactive tenants; reject authenticated users not in `tenant_users`
- Bind `app()->instance(Tenant::class, $tenant)` and call `setPermissionsTeamId($tenant->id)`
- Register early in `bootstrap/app.php`
- Apply to `routes/web.php` and `routes/admin.php` only
- **Do not apply to `routes/api.php` yet.** API tokens have no `tenant_id` until Step 4. Applying middleware to API routes now breaks every API call.
- Create `routes/platform.php` group that explicitly bypasses `ResolveTenant` — super-admin lives here

### 1d — Spatie teams mode

- In `config/permission.php`: set `'teams' => true`, set `'team_foreign_key' => 'tenant_id'`
- Write migrations to add `tenant_id` to `roles`, `model_has_roles`, `model_has_permissions` (the column name comes from `team_foreign_key`)
- Do **not** add `tenant_id` to `role_has_permissions` — Spatie's stock teams schema doesn't key it, and tenant isolation comes from tenant-specific role rows
- Rule: create tenant-specific roles per tenant, keep platform roles global with `tenant_id = null`
- After any role or permission write: call `PermissionRegistrar::forgetCachedPermissions()`

### 1e — Add `tenant_id` to content tables

Follow the ordered table list and three-step migration pattern above. After each table group (content, categories, fields, statuses, media, polymorphic pivots), run the full test suite before continuing.

**Polymorphic pivot note:** Adding `tenant_id` to `categorizables`, `fieldables`, and `taggables` is necessary but not sufficient. The `morphToMany` relationship definitions in `HasCategories` and `Fieldable` must also add `wherePivot` so the join condition is tenant-qualified:

```php
// In the HasCategories trait:
public function categories(): MorphToMany
{
    return $this->morphToMany(Category::class, 'categorizable')
                ->wherePivot('tenant_id', app()->bound(Tenant::class)
                    ? app(Tenant::class)->id
                    : null);
}
```

**`EntryRelationship` note:** Adding `tenant_id` scopes reads but does not prevent a write linking an entry from tenant A to an entry from tenant B. Add a model observer to `EntryRelationship` that validates both `entry_id` and `related_entry_id` share the same `tenant_id`.

### 1f — `AppServiceProvider` scoped bindings

Change every `singleton()` to `scoped()` for these bindings so they re-instantiate per request rather than per process:

- `Settings::class`
- `'api'`
- `'files-service'`
- `'fields-service'`
- `UserService::class`
- `CategoryService::class`

### 1g — Settings resolver

Update `Settings::get()` to cascade through four tiers:

```
tenant user preference → tenants.settings JSON → setting_values (tenant-scoped) → config default
```

- Tenant-level overrides live in `tenants.settings` as JSON keyed by `domain.handle`
- `setting_values` stores typed system-level defaults editable inside a tenant admin; its unique indexes must include `tenant_id`
- All settings cache keys must include `tenant_id`:
  ```
  settings.tenant.{tenantId}.json
  settings.tenant.{tenantId}.system.{domain}
  settings.tenant.{tenantId}.user.{userId}.{domain}
  ```
- Any write to tenant settings must bust the relevant cache keys

### 1h — Test infrastructure

Build alongside 1b and 1e — not after.

```php
// app/Testing/WithTenantContext.php

trait WithTenantContext
{
    protected Tenant $tenant;

    protected function setUpTenant(?Tenant $tenant = null): void
    {
        $this->tenant = $tenant ?? Tenant::factory()->active()->create();
        app()->instance(Tenant::class, $this->tenant);
        setPermissionsTeamId($this->tenant->id);
    }

    protected function actingAsTenantUser(string $role = 'member', ?User $user = null): User
    {
        $user ??= User::factory()->create();
        $this->tenant->users()->attach($user, ['role' => $role]);
        return $this;
    }
}
```

Also create `TenantFactory` with states: `active()`, `onTrial()`, `suspended()`.

**✅ End of Step 1:** Requests resolve to a tenant. All web and admin queries are scoped. Data is isolated. API routes are intentionally unscoped — that is resolved in Step 4.

---

## Step 2 — Routing, Storage, and Service Hardening

**Goal:** Clean separation between tenant and platform surfaces; isolated media storage.

- Formalize `routes/platform.php` with a `PlatformAdmin` middleware requiring the `super-admin` role (no tenant scope). This route group handles tenant list, user management, and cross-tenant operations.
- Verify `routes/admin.php` is fully behind `ResolveTenant` — no admin route should be reachable without a resolved tenant.
- Make `FilesService` write media to isolated paths: `tenants/{tenant-id}/media/...`. Override `getMediaCollection()` on the `Media` model to set the disk path dynamically per tenant.
- Verify `SiteController` and `SiteRouter` are scoped to the resolved tenant. The catch-all `/{uri?}` route looks up entries by URI through `entry_trees`. In a shared database, two tenants can have `entry_trees.uri = /about` — `SiteRouter` must scope its lookup to the current tenant. Because `EntryTree` uses `BelongsToTenant`, this should be automatic, but verify explicitly. Also enforce that `is_home = true` is unique per tenant at the application layer.

**✅ End of Step 2:** Platform and tenant routes are cleanly separated. Media files are tenant-isolated. Public routing can't cross tenant boundaries.

---

## Step 3 — Onboarding and Workspace Switching

**Goal:** Self-serve signup provisions a tenant end-to-end without manual steps.

- Build the signup flow. The `Tenant` row is created **synchronously** in the signup controller — before dispatching any job — so the provisioning job has a real ID to work with:

```php
// SignupController
$tenant = Tenant::create([
    'name'                => $request->name,
    'slug'                => $request->slug,
    'provisioning_status' => 'pending',
    'is_active'           => false,
]);

TenantProvisioningJob::dispatch($tenant->id, $user->id);
```

- `TenantProvisioningJob` does **not** use `TenantAwareJob` (the tenant is new, not yet bound in the container). The job receives the ID as a plain integer and binds the tenant manually:

```php
public function handle(): void
{
    $tenant = Tenant::withoutTenantScope()->findOrFail($this->tenantId);
    app()->instance(Tenant::class, $tenant);
    setPermissionsTeamId($tenant->id);

    // Now: attach owner, seed defaults, send welcome email, set is_active = true
}
```

- Use a chained job sequence for the provisioning steps: attach owner user → seed default statuses, field layouts, entry groups → send welcome email → mark `provisioning_status = active` and `is_active = true`. Document this job's class clearly — it intentionally doesn't use `TenantAwareJob` and should not be "fixed" to do so.
- Build a workspace picker at `yourplatform.com/app` (outside `ResolveTenant`) that lists `$user->tenants` and links to each subdomain. Users who belong to one tenant skip it and go directly to their workspace.

**✅ End of Step 3:** New tenants provision themselves. Users with multiple workspaces can switch between them.

---

## Step 4 — API and Queue Tenant Awareness

**Goal:** API tokens and background jobs are tenant-scoped. API routes join the tenant system.

- Write migration: add `tenant_id` to `personal_access_tokens`
- Update API token generation in the tenant admin to stamp `tenant_id = current tenant` on every token
- Extend `ResolveTenant` to `routes/api.php`: resolve tenant from the authenticated token's `tenant_id`. Allow `X-Tenant-Slug` only when it matches the token's `tenant_id` — never as a standalone override.
- Switch API rate limiting from IP-based to per-tenant/per-token using `tenant_usage` counters (placeholder limits until Step 6)
- Create `app/Traits/TenantAwareJob.php`:

```php
trait TenantAwareJob
{
    public int $tenantId;

    public function initializeTenantAwareJob(): void
    {
        if (! app()->bound(Tenant::class)) {
            throw new LogicException(static::class . ' dispatched without a bound tenant.');
        }
        $this->tenantId = app(Tenant::class)->id;
    }

    public function middleware(): array
    {
        return [new ResolveTenantForJob($this->tenantId)];
    }
}
```

- Apply `TenantAwareJob` to `ProcessMediaLibraryRemoval` and any future jobs
- **Do not apply `TenantAwareJob` to `PruneApiLogs`.** It is a platform-wide scheduled task (`model:prune`) that runs across all tenants. It uses `withoutTenantScope()` and stays a platform job.
- Override Spatie MediaLibrary's internal queue jobs (`PerformConversions`, `GenerateResponsiveImages`) with custom subclasses that implement `TenantAwareJob`. Register them in `config/media-library.php`. This is the most complex integration point in the entire system — budget extra time.
- Add the scheduler pattern for per-tenant recurring work: one master scheduled task queries all active tenants, dispatches a per-tenant job for each:

```php
// In console.php or a command's handle():
Tenant::where('is_active', true)->each(function (Tenant $tenant) {
    DailyTenantDigestJob::dispatch($tenant->id);
});
```

**✅ End of Step 4:** All surfaces — web, admin, API, queues — are tenant-scoped.

---

## Step 5 — Super-Admin and Impersonation

**Goal:** Platform team can enter any tenant workspace for support and debugging.

- Build super-admin panel behind `routes/platform.php`:
  - Tenant list with status, plan, usage, last activity
  - Trial extension and plan-limit override controls
- Implement impersonation: store `impersonating_tenant_id` in the super-admin's session. `ResolveTenant` checks for this first — if present, it resolves to that tenant instead of from the domain.
- Add a persistent banner in the admin UI layout: **"You are viewing [Tenant Name]'s workspace — Exit"**. Impersonation must never be silent.
- Add audit logging for all super-admin actions. The existing `api_logs` table with a `super_admin` channel is sufficient, or create a dedicated `audit_logs` table if richer structured logging is needed. Every impersonation start, end, and action taken while impersonating must be logged.

**✅ End of Step 5:** Support team can safely enter any workspace with a visible trail.

---

## Step 6 — Billing and Plans

**Goal:** The platform charges for itself. Resource limits are enforced. A lapsed subscription locks the workspace.

- Write and run `create_plans_table` and `create_tenant_usage_table` migrations
- Create `Plan` model; seed starter plans (e.g. Free / Pro / Business) with appropriate `limits` and `features` JSON
- Install `laravel/cashier`; link `Subscription` to `Tenant` (not `User`)
- Replace the `withinLimits()` stub on `Tenant` with the real implementation:

```php
public function withinLimits(string $resource): bool
{
    $limit = $this->plan?->limits[$resource] ?? PHP_INT_MAX;
    $usage = $this->usage()->where('metric', $resource)->value('value') ?? 0;
    return $usage < $limit;
}
```

- Add `withinLimits()` checks in the service layer before resource creation:
  - `EntryService::create()` → checks `entries`
  - User invite flow → checks `users`
  - Media upload → checks `storage_bytes`
  - Throw `PlanLimitExceededException` (custom exception) when over quota; handle it with an appropriate HTTP 402 / UI message
- Add `TenantUsage` model observers on `Entry`, tenant `User` membership, and `Media` to increment/decrement usage counters on create/delete
- Add a nightly scheduled job to reconcile usage counters against actual row counts (guards against observer misses)
- Wire Stripe webhooks via the already-installed `spatie/laravel-webhook-client`:
  - `invoice.paid` → keep subscription active
  - `customer.subscription.deleted` → set `tenants.is_active = false` (the `ResolveTenant` middleware already blocks inactive tenants)
  - `customer.subscription.updated` → sync `plan_id` and any limit overrides
- Build plan selection into the signup flow and an upgrade/downgrade screen in the tenant admin

**✅ End of Step 6:** Tenants are on plans, resource limits are enforced, Stripe handles billing, and a lapsed subscription automatically locks the workspace.

---

## Key Patterns

### Tenant scope is invisible in normal code

```php
// All of these work without any tenant_id handling in calling code:
$entries  = Entry::published()->with('status')->paginate(20);
$entry    = Entry::create(['title' => 'Hello', 'handle' => 'hello', ...]);
$entry    = Entry::find($id);      // returns null if it belongs to another tenant
$entry    = Entry::findOrFail($id); // throws ModelNotFoundException — correct
$value    = $entry->field('body_text');
$user->hasRole('editor');          // scoped to current tenant
$user->assignRole('editor');       // stamped with current tenant_id
```

### Bypassing scope — super-admin and platform commands only

```php
// ✅ Platform command iterating all tenants:
Tenant::all()->each(function (Tenant $tenant) {
    app()->instance(Tenant::class, $tenant);
    setPermissionsTeamId($tenant->id);
    Entry::where('published_at', '<', now()->subYear())->each(fn($e) => $e->archive());
});

// ✅ Super-admin audit query:
$allEntries = Entry::withoutTenantScope()->where('created_at', '>', now()->subDay())->get();

// ❌ Never in tenant controllers:
$entry = Entry::withoutTenantScope()->find($id); // data leak risk
```

### Workspace picker (outside tenant context)

```php
public function index(Request $request)
{
    // ResolveTenant is not active on this route
    $tenants = $request->user()
        ->tenants()
        ->where('is_active', true)
        ->withPivot('role')
        ->get();

    return view('workspace-picker', compact('tenants'));
    // "Enter" links are just: https://acme.yourplatform.com/admin
}
```

---

## Risk Register

| Risk | Severity | Action |
|---|---|---|
| Data leak between tenants via unscoped query | Critical | `BelongsToTenant` fails closed. Two-tenant isolation test covers every model. |
| Spoofed `X-Tenant-Slug` switches tenant context | Critical | Permit only on authenticated API routes when it matches the token's own `tenant_id`. Never on browser routes. |
| Settings cache or unique keys missing `tenant_id` | Critical | All `setting_values` unique indexes and settings cache keys include `tenant_id`. Test one user with different prefs in two tenants. |
| `withoutTenantScope()` used in tenant code | Critical | Grep in every PR. Consider a PHPStan/Psalm rule. |
| API routes receive `ResolveTenant` before tokens have `tenant_id` | Critical | Do not apply `ResolveTenant` to `routes/api.php` until Step 4. Explicitly documented in Step 1c. |
| `SiteRouter` serves another tenant's content on URI collision | Critical | Verify `EntryTree`/`entry_trees` scoping in `SiteRouter` in Step 2. Covered by the two-tenant isolation test if `EntryTree` is included. |
| `morphToMany` pivot not filtered at the join | Critical | Add `->wherePivot('tenant_id', ...)` to `HasCategories` and `Fieldable` trait relationship definitions alongside the column migration. |
| `TenantProvisioningJob` dispatched without a bound tenant | High | Tenant row created synchronously in the controller before dispatch; job binds tenant manually via `withoutTenantScope()->find()`. See Step 3. |
| `PruneApiLogs` incorrectly wrapped in `TenantAwareJob` | High | It is a platform-wide job. Leave it using `withoutTenantScope()`. See Step 4. |
| Cross-tenant `EntryRelationship` write | High | Model observer validates that `entry_id` and `related_entry_id` share the same `tenant_id`. |
| `AppServiceProvider` singletons carrying stale tenant context | High | All listed services converted to `scoped()` in Step 1f, including the `'api'` binding. |
| Spatie MediaLibrary internal jobs run without tenant context | High | Override `PerformConversions` and `GenerateResponsiveImages` with `TenantAwareJob` subclasses. See Step 4. |
| `TenantAwareJob` dispatched without a bound tenant | High | Trait's `initializeTenantAwareJob()` throws `LogicException` immediately — surfaces the problem at dispatch time, not silently in the worker. |
| Shared Spatie role row reused across tenants | High | Assign roles per-tenant; keep platform roles with `tenant_id = null`. Test same role name in two tenants with different permissions. |
| `NOT NULL` migration fails on populated tables | Medium | Three-step migration pattern: nullable → backfill → `nullable(false)`. All Step 1e migrations follow this pattern. |
| Global `tags` table leaks tag names across tenants | Medium | Admin tag pickers must filter to tags with at least one `taggables` row for the current tenant. |
| Test infrastructure built too late | Medium | `TenantFactory` and `WithTenantContext` trait built in Step 1h, not after. |
| Spatie permission cache stale after role writes | Medium | Call `PermissionRegistrar::forgetCachedPermissions()` after every role or permission change. |
| Local dev subdomains require extra setup | Low | Decide whether local dev uses Herd wildcard subdomains, `dnsmasq`, or the path-prefix fallback. Document the dev environment setup before the team starts Step 1. |
| Tenant deletion and data offboarding unplanned | Low | Plan before Step 6: soft-delete tenant row, schedule storage cleanup, define retention window. GDPR requires the ability to delete all personal data on request. |
