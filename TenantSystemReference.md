# Tenant System — Technical Reference

Migration list, object model, implementation plan, and model usage examples for the multi-tenant layer on top of `laravel-base`.

---

## Part 1 — Migration Inventory

### New Migrations (to create)

These are the net-new tables the tenant system needs.

| Migration name | Purpose |
|---|---|
| `create_tenants_table` | Anchor of the whole system — one row per customer workspace |
| `create_tenant_users_table` | Pivot linking users to tenants with a role |
| `create_plans_table` | Billing plan definitions |
| `create_subscriptions_table` | Active subscription per tenant (Cashier provides this, but listed for clarity) |
| `create_tenant_usage_table` | Per-tenant metered usage counters (entries, users, storage, API calls) |

#### `tenants`
```
id                   bigint unsigned PK
name                 string
slug                 string unique          -- subdomain identifier: acme.yourplatform.com
domain               string nullable unique -- custom domain: cms.acme.com
plan_id              bigint unsigned FK → plans.id, nullable
trial_ends_at        timestamp nullable
provisioning_status  enum: pending|provisioning|active|failed  default: pending
is_active            boolean default true
settings             json nullable          -- tenant-level overrides for setting_values
created_at / updated_at
```

#### `tenant_users`
```
tenant_id   bigint unsigned FK → tenants.id
user_id     bigint unsigned FK → users.id
role        enum: owner|admin|member
created_at
PRIMARY KEY (tenant_id, user_id)
```

#### `plans`
```
id              bigint unsigned PK
name            string
slug            string unique
price_monthly   decimal(10,2)
price_annual    decimal(10,2)
limits          json    -- { "users": 5, "entries": 1000, "storage_bytes": 5368709120, "api_calls_monthly": 10000 }
features        json    -- { "custom_domain": false, "api_access": true }
stripe_price_id string nullable
is_public       boolean default true
sort_order      int default 0
created_at / updated_at
```

#### `tenant_usage`
```
id          bigint unsigned PK
tenant_id   bigint unsigned FK → tenants.id
metric      string   -- entries | users | storage_bytes | api_calls
value       bigint unsigned default 0
period      string nullable  -- YYYY-MM for monthly metrics, null for rolling totals
updated_at
UNIQUE (tenant_id, metric, period)
```

---

### Existing Migrations — `tenant_id` additions

Each of these needs an `add_tenant_id_to_{table}_table` migration that adds:
- `tenant_id bigint unsigned NOT NULL` with an index
- A foreign key to `tenants.id`

For existing data (single-tenant bootstrap), seed a default tenant and backfill `tenant_id` in the same migration.

#### Content tables
| Table | Notes |
|---|---|
| `entries` | Add after `entry_group_id` |
| `entry_groups` | Core container |
| `entry_types` | Scoped through entry_group but add directly for performance |
| `entry_authors` | Entry is already scoped; add for direct join efficiency |
| `entry_tree` | Add after `entry_id` |
| `entry_metrics` | Add after `entry_id` |

#### Category tables
| Table | Notes |
|---|---|
| `category_groups` | |
| `categories` | |

#### Field tables
| Table | Notes |
|---|---|
| `fields` | Field definitions are per-tenant |
| `field_groups` | |
| `field_values` | Polymorphic — add `tenant_id` directly (fastest, avoids join-based scoping) |
| `field_layouts` | |
| `field_layout_tabs` | Scoped through field_layout; add directly |
| `field_layout_tab_elements` | Same |

#### Status & settings
| Table | Notes |
|---|---|
| `status_groups` | |
| `statuses` | |
| `setting_domains` | |
| `setting_values` | |

#### Media
| Table | Notes |
|---|---|
| `media` | Spatie MediaLibrary table — add `tenant_id` |
| `media_libraries` | |

#### Polymorphic pivot tables
| Table | Notes |
|---|---|
| `fieldables` | Add `tenant_id` directly — morphable_type alone can't be scope-joined reliably |
| `categorizables` | Same |
| `category_groupables` | Same |
| `field_groupables` | Same |

#### Roles & permissions
| Table | Notes |
|---|---|
| `roles` | Spatie `teams` mode adds `team_id` — rename to `tenant_id` via `team_foreign_key` config |
| `model_has_roles` | Same; Spatie handles automatically when teams enabled |
| `model_has_permissions` | Same |
| `role_has_permissions` | Same |

#### API & logs
| Table | Notes |
|---|---|
| `api_logs` | |
| `user_schema` | Existing user schema overrides |

#### Tables that do NOT get `tenant_id`
| Table | Reason |
|---|---|
| `users` | Global — tenant membership lives in `tenant_users` |
| `personal_access_tokens` | Add `tenant_id` column separately (not a migration addition — see API section) |
| `user_oauth_tokens` | Scoped by user; tenant checked through user |
| `sessions` | Infrastructure |
| `jobs`, `failed_jobs` | Infrastructure |
| `cache`, `cache_locks` | Infrastructure |
| `password_reset_tokens` | Infrastructure |
| `bb_values` | Bot-block — platform-level |
| `tags`, `taggables` | Global tag registry; tenant context handled through owning model |
| `field_types` | Global definitions, not per-tenant data |

---

## Part 2 — Object Model

```
Tenant
 ├── hasMany         TenantUser (pivot with role)
 ├── belongsToMany  User  (through tenant_users)
 ├── belongsTo      Plan
 ├── hasOne         Subscription (Cashier)
 ├── hasMany        TenantUsage
 │
 ├── hasMany        EntryGroup
 │    ├── belongsTo  StatusGroup
 │    ├── belongsTo  FieldLayout
 │    ├── hasMany    EntryType
 │    │    └── belongsTo  FieldLayout
 │    └── hasMany    Entry
 │         ├── belongsTo  Status
 │         ├── belongsToMany  User (authors, via entry_authors)
 │         ├── hasOne     EntryTree
 │         ├── hasMany    EntryMetric
 │         ├── morphMany  FieldValue    (via Fieldable trait)
 │         └── morphToMany Category     (via HasCategories trait)
 │
 ├── hasMany        CategoryGroup
 │    └── hasMany   Category
 │         ├── belongsTo  self (parent)
 │         └── hasMany    self (children)
 │
 ├── hasMany        FieldLayout
 │    └── hasMany   FieldLayoutTab
 │         └── hasMany  FieldLayoutTabElement → belongsTo Field
 │
 ├── hasMany        Field
 │    ├── belongsTo  FieldType          (global, no tenant_id)
 │    └── hasMany    FieldValue
 │
 ├── hasMany        StatusGroup
 │    └── hasMany   Status
 │
 ├── hasMany        SettingDomain
 │    └── hasMany   SettingValue
 │
 ├── hasMany        MediaLibrary
 │    └── hasMany   Media              (Spatie)
 │
 ├── hasMany        ApiLog
 └── hasMany        Role               (Spatie team-scoped)

User  (global — no tenant_id)
 ├── belongsToMany  Tenant  (through tenant_users, with role)
 ├── hasMany        UserOauthToken
 ├── hasMany        PersonalAccessToken (tenant_id on token, not user)
 └── morphMany      FieldValue          (via Fieldable trait)

Plan  (global)
 └── hasMany        Tenant

FieldType  (global — platform-level definitions)
 └── hasMany        Field
```

### Tenant-resolution context flow

```
HTTP Request
  └── ResolveTenant middleware
        ├── parse Host header → slug → tenants.slug lookup
        ├── or parse Host → tenants.domain lookup
        ├── or read X-Tenant-Slug header (API clients)
        └── bind: app()->instance(Tenant::class, $tenant)
              └── setPermissionsTeamId($tenant->id)   ← Spatie teams
                    └── Every subsequent model query
                          └── BelongsToTenant global scope
                                └── WHERE tenant_id = {resolved id}
```

---

## Part 3 — Core Implementation: `BelongsToTenant` Trait

This trait is the engine of the whole system. Every tenant-scoped model uses it.

```php
// app/Traits/BelongsToTenant.php

namespace App\Traits;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        // Auto-scope every query to the current tenant
        static::addGlobalScope('tenant', function (Builder $query) {
            if (app()->bound(Tenant::class)) {
                $query->where(
                    (new static)->qualifyColumn('tenant_id'),
                    app(Tenant::class)->id
                );
            }
        });

        // Auto-set tenant_id on creation
        static::creating(function ($model) {
            if (empty($model->tenant_id) && app()->bound(Tenant::class)) {
                $model->tenant_id = app(Tenant::class)->id;
            }
        });
    }

    /**
     * Escape the global scope for super-admin / system operations.
     * Use sparingly — every call site should be code-reviewed.
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

---

## Part 4 — Model Usage Examples

### The happy path — tenant scope is invisible

In the overwhelming majority of cases, tenant scoping is invisible to calling code. The global scope fires automatically on every query.

```php
// In a controller, after ResolveTenant middleware has run:

// ✅ Returns only entries belonging to the current tenant — no changes to existing code needed
$entries = Entry::published()->with('status')->paginate(20);

// ✅ Creates an entry stamped with current tenant_id automatically
$entry = Entry::create([
    'entry_group_id' => $group->id,
    'entry_type_id'  => $type->id,
    'title'          => 'Hello World',
    'handle'         => 'hello-world',
    'status_id'      => $status->id,
]);

// ✅ find() scoped — returns null if entry belongs to a different tenant
$entry = Entry::find($id);   // never returns another tenant's entry

// ✅ Relationships also scoped — EntryGroup is tenant-scoped, so ->entries() respects it
$entries = $group->entries()->ofType($type)->get();
```

### Updating a model — still invisible

```php
// ✅ No tenant_id gymnastics needed
$entry->update(['title' => 'Updated Title']);

// ✅ Save also works as expected
$entry->title = 'Updated Title';
$entry->save();
```

### Services — unchanged call signatures

Because scoping is at the model level, `EntryService` and `EntryRepository` require zero signature changes. The scope fires inside them automatically.

```php
// EntryService::create() — existing signature, no tenant changes
$entry = $this->entryService->create($entryGroup, $entryType, $data, $user);

// The EntryRepository::applyFieldValues() call inside also works unchanged —
// FieldValue::create() will auto-stamp tenant_id.
```

### Settings — three-tier resolution

The `Settings` resolver gains a tenant tier between user and system.

```php
// Before (two-tier): user → system
// After (three-tier): user → tenant → system → config default

// No call-site changes needed — Settings::get() handles the cascade internally:
$value = settings()->get('site_name');
// Resolves: user pref → tenant settings JSON → setting_values (system) → config default

// Setting a tenant-level override (in a tenant admin settings controller):
$tenant->setSetting('site_name', 'Acme Workspace');  // writes to tenants.settings JSON
```

### Scoped `find` vs. `findOrFail` — behaves correctly

```php
// Tenant A is active. Entry #42 belongs to Tenant B.
$entry = Entry::find(42);      // returns null — not found in tenant A's scope
$entry = Entry::findOrFail(42); // throws ModelNotFoundException — correct behavior

// This means existing 404 handling in controllers works without modification.
```

### Bypassing the scope — super-admin and system jobs

`withoutTenantScope()` is the escape hatch. It should only appear in:
- Super-admin controllers (`routes/platform.php`)
- System-level Artisan commands
- The `TenantAwareJob` trait (to re-bind the tenant, not to bypass it)
- Migration seeders

```php
// ✅ Correct: super-admin listing all tenants' entries for a global audit
$allEntries = Entry::withoutTenantScope()->where('created_at', '>', now()->subDay())->get();

// ✅ Correct: platform command iterating all tenants
$tenants = Tenant::all();
foreach ($tenants as $tenant) {
    app()->instance(Tenant::class, $tenant);
    setPermissionsTeamId($tenant->id);
    // Now all model queries inside this loop are scoped to $tenant
    Entry::where('published_at', '<', now()->subYear())->each(fn($e) => $e->archive());
}

// ❌ Wrong: bypassing scope in a tenant controller to "just grab it quickly"
$entry = Entry::withoutTenantScope()->find($id);  // ← data leak risk, never do this in tenant code
```

### Field values — polymorphic, still invisible

The `Fieldable` trait uses `morphMany` on `FieldValue`. Because `FieldValue` has `BelongsToTenant`, queries through the trait are automatically scoped.

```php
// ✅ Entry::field() uses the morphMany — scoped automatically
$value = $entry->field('body_text');

// ✅ Direct FieldValue query — scoped
$values = FieldValue::where('field_id', $field->id)->get();

// ✅ Saving a field value — tenant_id stamped automatically
FieldValue::updateOrCreate(
    ['field_id' => $field->id, 'fieldable_id' => $entry->id, 'fieldable_type' => 'entry'],
    ['value_text' => $content]
);
```

### Roles & Permissions — Spatie teams mode

After enabling `teams` in `config/permission.php` and calling `setPermissionsTeamId($tenant->id)` in middleware, role checks are tenant-scoped with no call-site changes.

```php
// ✅ Checks if user has 'edit entries' role within the current tenant
$user->hasRole('editor');         // scoped to current tenant automatically
$user->can('edit entries');       // same

// ✅ Assigning a role scoped to the current tenant
$user->assignRole('editor');      // stored with team_id = current tenant id

// ✅ Super-admin (platform.php) checking across tenants
setPermissionsTeamId(null);        // clear team scope
$user->hasRole('super-admin');    // now checks global roles
```

### Multi-tenant user — switching workspaces

A user belonging to multiple tenants gets a workspace picker. The jump to a subdomain re-runs `ResolveTenant`, which re-binds the new tenant context for that request.

```php
// Workspace selection controller (yourplatform.com/app)
// No tenant is bound here — this route is outside ResolveTenant middleware

public function index(Request $request)
{
    // Fetch tenants this user belongs to — no global scope active here
    $tenants = $request->user()
        ->tenants()          // belongsToMany through tenant_users
        ->where('is_active', true)
        ->withPivot('role')
        ->get();

    return view('workspace-picker', compact('tenants'));
}

// The "enter workspace" link is simply a redirect to the subdomain:
// https://acme.yourplatform.com/admin
// ResolveTenant picks up from there.
```

### Plan limit enforcement — in services

```php
// In EntryService::create() — wraps the existing create call

public function create(EntryGroup $group, EntryType $type, array $data, User $user): Entry
{
    $tenant = app(Tenant::class);

    if (! $tenant->withinLimits('entries')) {
        throw new PlanLimitExceededException('entries');
    }

    // Existing create logic unchanged below
    return $this->repository->create($group, $type, $data, $user);
}
```

The `withinLimits` method on `Tenant`:

```php
public function withinLimits(string $resource): bool
{
    $limit = $this->plan?->limits[$resource] ?? PHP_INT_MAX;
    $usage = $this->usage()->where('metric', $resource)->value('value') ?? 0;
    return $usage < $limit;
}
```

### Tenant-aware queued jobs

```php
// app/Traits/TenantAwareJob.php

trait TenantAwareJob
{
    public int $tenantId;

    public function initializeTenantAwareJob(): void
    {
        if (app()->bound(Tenant::class)) {
            $this->tenantId = app(Tenant::class)->id;
        }
    }

    public function middleware(): array
    {
        return [new ResolveTenantForJob($this->tenantId)];
    }
}

// Usage in any job:
class ProcessEntryExport implements ShouldQueue
{
    use TenantAwareJob;

    public function __construct(public int $entryGroupId)
    {
        $this->initializeTenantAwareJob(); // captures tenant_id at dispatch time
    }

    public function handle(): void
    {
        // By the time handle() runs, the tenant is bound in the container.
        // Entry queries here are automatically scoped — no manual tenant handling.
        $entries = Entry::inGroup($this->entryGroupId)->get();
    }
}
```

---

## Part 5 — Implementation Steps

### Step 1 — Workable foundation

#### 1a. Tenant model + pivot
- Write `create_tenants_table` migration
- Write `create_tenant_users_table` migration
- Create `app/Models/Tenant.php` with `belongsToMany(User::class)`, `belongsTo(Plan::class)`, `hasMany` for all tenant-owned top-level resources
- Add `belongsToMany(Tenant::class, 'tenant_users')->withPivot('role')` to `User`

#### 1b. BelongsToTenant trait
- Create `app/Traits/BelongsToTenant.php` (see Part 3)
- Do not apply it to any model yet — write and test it in isolation with a single model first (`EntryGroup` is a good candidate)

#### 1c. ResolveTenant middleware
- Create `app/Http/Middleware/ResolveTenant.php`
- Resolution order: subdomain → custom domain → `X-Tenant-Slug` header → path prefix
- Bind resolved tenant: `app()->instance(Tenant::class, $tenant)`
- Call `setPermissionsTeamId($tenant->id)`
- Register early in `bootstrap/app.php`
- Create a `routes/platform.php` group that explicitly bypasses this middleware — super-admin lives here

#### 1d. Spatie teams mode
- In `config/permission.php`, set `'teams' => true`
- Change `'team_foreign_key' => 'team_id'` to `'team_foreign_key' => 'tenant_id'`
- Write the migration to rename `team_id` → `tenant_id` on all Spatie permission tables, and add `tenant_id` to `roles`

#### 1e. Add tenant_id — content tables (the long phase)
Apply migrations and `BelongsToTenant` trait in this order (respecting FK dependencies):

1. `entry_groups` → `EntryGroup` model
2. `entry_types` → `EntryType` model
3. `entries` → `Entry` model
4. `entry_tree` → `EntryTree` model
5. `entry_metrics` → `EntryMetric` model
6. `entry_authors` → (pivot, add column; no model trait needed)
7. `category_groups` → `CategoryGroup` model (app/Models/Category/Group.php)
8. `categories` → `Category` model
9. `field_layouts` → `FieldLayout` model
10. `field_layout_tabs` → `FieldLayoutTab` model
11. `field_layout_tab_elements` → `FieldLayoutTabElement` model
12. `fields` → `Field` model
13. `field_groups` → `FieldGroup` model
14. `field_values` → `FieldValue` model
15. `status_groups` → `StatusGroup` model
16. `statuses` → `Status` model
17. `setting_domains` → `SettingDomain` model
18. `setting_values` → `SettingValue` model
19. `media_libraries` → `MediaLibrary` model
20. `media` → `Media` model
21. `api_logs` → `ApiLog` model
22. `user_schema` → `UserSchema` model
23. Polymorphic pivots: `fieldables`, `categorizables`, `category_groupables`, `field_groupables` — add `tenant_id` column (no model trait, but update any raw queries against these tables)

After each batch, run the full test suite. Don't move to the next table group until the previous one is green.

#### 1f. AppServiceProvider — scoped bindings
- Change all `singleton()` calls for `Settings`, `FilesService`, `FieldService`, `UserService`, `CategoryService` to `scoped()` so they re-instantiate per request

**✅ End of Step 1:** Requests resolve to a tenant, all queries are scoped, data is isolated.

---

### Step 2 — Settings, storage, and routing hygiene

- Update `Settings` resolver to cascade: user → tenant (`tenants.settings` JSON) → `setting_values` → config default
- Make `FilesService` write tenant media to `tenants/{tenant-id}/media/...` — override `getMediaCollection()` on `Media` model to set disk path dynamically
- Formalize `routes/platform.php` with a `PlatformAdmin` middleware that requires a `super-admin` role (no tenant scope)
- Move super-admin CRUD (tenant list, user management) behind `routes/platform.php`
- Verify `routes/admin.php` is fully behind `ResolveTenant`

---

### Step 3 — Onboarding & workspace switching

- Build `TenantProvisioningJob` (chained job sequence: create tenant → attach owner → seed defaults → send welcome email)
- Add `provisioning_status` column to `tenants` — poll from a "setting up your workspace…" screen
- Build workspace picker at `yourplatform.com/app` (outside `ResolveTenant` middleware, lists `$user->tenants`)
- Build public signup flow that fires the provisioning job

---

### Step 4 — API & queue tenant awareness

- Add `tenant_id` to `personal_access_tokens` via migration
- Update API token generation in tenant admin to stamp `tenant_id`
- Switch API rate limiting from IP-based to per-tenant/per-token, using `tenant_usage` counters
- Create `app/Traits/TenantAwareJob.php` (see Part 4)
- Apply `TenantAwareJob` to an abstract `BaseJob` or update existing jobs: `ProcessMediaLibraryRemoval`, `PruneApiLogs`
- Add scheduler pattern for per-tenant recurring jobs

---

### Step 5 — Super-admin & impersonation

- Build super-admin tenant list view (behind `routes/platform.php`) — shows status, plan, usage, last activity
- Implement impersonation via `impersonating_tenant_id` in session; `ResolveTenant` checks this first
- Add impersonation banner to admin UI layout
- Add audit log for all super-admin actions (reuse `api_logs` with a `super_admin` channel, or a dedicated `audit_logs` table)

---

### Step 6 — Billing & plans

- Write `create_plans_table` and `create_tenant_usage_table` migrations
- Create `Plan` model; seed starter plans (Free / Pro / Business)
- Install `laravel/cashier`; link `Subscription` to `Tenant` (not `User`)
- Add `withinLimits(string $resource): bool` to `Tenant` model
- Add limit checks to `EntryService::create()`, user invite flow, media upload
- Add `TenantUsage` model observers on `Entry`, `User` (tenant member count), `Media`
- Add a nightly scheduled job to reconcile usage counters
- Wire Stripe webhooks via `spatie/laravel-webhook-client`: `invoice.paid`, `customer.subscription.deleted`, `customer.subscription.updated`
- Build plan selection into the signup flow and an upgrade screen in tenant admin

---

## Part 6 — Risk Register

| Risk | Severity | Mitigation |
|---|---|---|
| Missing `tenant_id` on a query — data leaks between tenants | Critical | Write a test that boots two tenants, creates data in each, and asserts neither can see the other's records. Run it against every model. |
| `withoutTenantScope()` used in tenant code | Critical | Grep for `withoutTenantScope` in every PR. Consider a custom Psalm/PHPStan rule. |
| Polymorphic pivot tables (`fieldables`, `categorizables`) not scoped through joins | High | Add `tenant_id` directly to these tables (already called for in migrations above). Avoids the join-based approach entirely. |
| `AppServiceProvider` singletons carrying stale tenant context across requests | High | Switching to `scoped()` (Step 1f) eliminates this. Cover with a test using two sequential requests. |
| Queued jobs running without tenant context | High | `TenantAwareJob` trait (Step 4). Every job should fail loudly if `tenant_id` is missing. |
| Spatie permission cache not invalidated per-tenant | Medium | `setPermissionsTeamId()` in middleware invalidates correctly. Add a test asserting role assignments don't bleed between tenants. |
| Migration backfill on large existing tables blocking deploys | Medium | Use chunked updates (`Entry::withoutTenantScope()->chunkById(500, ...)`) in the migration's `up()` method rather than a single `UPDATE`. |
