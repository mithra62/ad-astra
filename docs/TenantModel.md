# TenantModel

A detailed analysis of what a multi-tenant system would look like on top of this codebase, and what needs to be built to turn it into a hosted platform.

---

## Deployment Modes

This codebase supports both an **installed distribution** and a **SaaS multi-tenant** deployment from the same codebase — the GitLab/Craft CMS/Statamic model. A single environment variable controls which mode is active:

```
TENANCY_ENABLED=false   # installed distribution (default)
TENANCY_ENABLED=true    # SaaS multi-tenant
```

In **installed mode**, the tenant middleware, global query scopes, and all tenant-specific routing are inert. Customers install and own their own instance. No `tenants` table is required.

In **SaaS mode**, the full multi-tenant stack is active: `ResolveTenant` runs on every request, all tenant-owned models are automatically scoped by `BelongsToTenant`, and billing/plans are enforced.

The core content model — entries, fields, media, statuses — is mode-agnostic. Only the tenant resolution and scoping layer changes between modes.

Treat `TENANCY_ENABLED` as a deploy-time constant, not a runtime toggle. Switching an existing installed-mode database to SaaS mode requires seeding the `tenants` table and backfilling `tenant_id` across all tenant-owned tables before flipping the flag.

---

## The Core Decision: Database Strategy

Before anything else, you need to pick a tenancy model, because it affects nearly every downstream decision.

**Option A — Single database, `tenant_id` column on every table.** Simpler to operate, lower cost per tenant, but one misconfigured query leaks data across tenants. All migrations run once. Backup/restore is complex (you can't easily restore just one tenant's data).

**Option B — Separate database per tenant.** Total isolation, easy per-tenant backup/restore, and you can give high-value tenants their own database server. The downside is migration management: every schema change has to run against every tenant database, and you need infrastructure to track and execute that. Cost scales with tenant count.

**Option C — Separate schema per tenant (PostgreSQL).** A middle path — one server, isolated schemas. Works well at medium scale.

For a hosted platform with unknown tenant count, **Option A is the right starting point.** You can always migrate high-value tenants to their own database later. The rest of this document assumes shared-database with `tenant_id` scoping, but notes where the separate-database path diverges.

---

## Phase 1: The Tenant Model

You need a `tenants` table as the anchor of the whole system. At minimum it needs:

```
id, name, slug, domain, plan_id, trial_ends_at, is_active, settings (JSON), created_at, updated_at
```

The `slug` is the URL-safe identifier used in subdomains (`acme.yourplatform.com`) or path prefixes (`yourplatform.com/acme/admin`). The `domain` field is for custom domains (optional feature, but plan for it now). The `plan_id` will connect to a `plans` table once you add billing. The `settings` JSON column is a tenant-level override for the existing settings system — things like custom branding, feature flags per plan, etc.

You'll also need a `tenant_users` pivot table:

```
tenant_id, user_id, role (owner/admin/member), created_at
```

This is how you answer "which tenants does this user belong to?" and "which users belong to this tenant?" Note that a single `User` record can belong to multiple tenants — that's the right design for a hosted platform where someone might administer multiple accounts.

---

## Phase 2: Tenant Resolution Middleware

Every request needs to resolve which tenant it belongs to before any controller code runs. You'll build a `ResolveTenant` middleware that runs very early in the stack (add it to `bootstrap/app.php` before route middleware).

The middleware is registered unconditionally — installed-mode deployments do not need to touch route files. When `TENANCY_ENABLED=false` it short-circuits immediately and calls `$next($request)` with no resolution logic. All resolution behaviour described below applies only when `TENANCY_ENABLED=true`.

Resolution logic, in order of preference:

1. **Subdomain matching** — parse `tenant-slug` from `Host: tenant-slug.yourplatform.com`, look it up in the `tenants` table, reject if inactive
2. **Custom domain matching** — look up the full hostname in the `domain` column
3. **Header-based** (for API clients) — `X-Tenant-ID` or `X-Tenant-Slug` header, validated against a Sanctum token's allowed tenants
4. **Path prefix** (fallback) — `/t/acme/admin/...` style routing

The resolved `Tenant` model gets bound into the container: `app()->instance(Tenant::class, $tenant)` and stored on the request object. Every subsequent piece of code pulls it from there — no global state.

If no tenant resolves and the request isn't for your platform's marketing site or super-admin panel, return a 404 or redirect to your signup page.

---

## Phase 3: Global Query Scoping

This is the most pervasive change in the codebase. Every model that holds tenant-specific data needs a `tenant_id` column and a global query scope that automatically appends `WHERE tenant_id = ?` to every query.

When `TENANCY_ENABLED=false`, the `BelongsToTenant` trait is a no-op: both the global scope callback and the `creating` hook return early before applying any scoping or stamping. Models with the trait behave identically to models without it in installed mode.

The tables that need `tenant_id` added:

- `entries`, `entry_groups`, `entry_types`, `entry_tree`, `entry_metrics`, `entry_relationships`
- `categories`, `category_groups`
- `fields`, `field_groups`, `field_types`, `field_values`
- `field_layouts`, `field_layout_tabs`, `field_layout_tab_elements`
- `statuses`, `status_groups`
- `setting_values`, `setting_domains`
- `media`, `media_library`
- `api_logs`
- `roles` and the Spatie permission tables (see note below)

Tables that should **not** get `tenant_id`:

- `users` — users are global, tenant membership lives in `tenant_users`
- `personal_access_tokens` — scoped by user, tenant checked differently
- `user_oauth_tokens` — scoped by user
- `sessions` — fine to leave global
- `jobs`, `cache`, `password_reset_tokens` — infrastructure tables

You'll want a `BelongsToTenant` trait that each model uses. It should:

1. Boot a global scope that always filters by the current tenant
2. On `creating`, automatically set `tenant_id` from the container-bound `Tenant`
3. Provide a `withoutTenantScope()` method for super-admin and system contexts

The tricky part is the **polymorphic tables** — `field_values`, `categorizables`, `fieldables`, `field_groupables`. These use `morphable_id` + `morphable_type` rather than a direct model, so the scope needs to either (a) add a `tenant_id` column directly, or (b) rely on join-based scoping through the owning model. Adding `tenant_id` directly is simpler and more performant.

**Spatie Roles/Permissions** deserves special attention. The default Spatie tables are global. You have two choices: use Spatie's built-in `teams` feature (adds `team_foreign_key` to all permission tables, which maps naturally to your tenant concept), or scope roles per-tenant manually. The Spatie `teams` feature is the right call — enable it in `config/permission.php`, set `team_foreign_key` to `tenant_id`, and call `setPermissionsTeamId($tenant->id)` after resolving the tenant in middleware.

---

## Phase 4: Tenant-Aware Services and AppServiceProvider

The `AppServiceProvider` currently registers singletons like `Settings`, `EntryService`, `UserService`, etc. With multi-tenancy, singletons are dangerous — they're instantiated once per process, not per request. Most of these need to switch to **scoped bindings** (`$this->app->scoped(...)`) or re-register as transient bindings that receive the current `Tenant` at construction time.

The `Settings` resolver is particularly important. Right now it has two tiers (user > system). You need a third tier: **user > tenant > system > config default**. Tenant-level settings would live in the existing `setting_values` table filtered by `tenant_id`, filling the gap between user preferences and platform defaults.

The `FilesService` needs a tenant-aware storage disk. Each tenant's media should be isolated in storage: `tenants/{tenant-id}/media/...`. The Spatie MediaLibrary `disk` can be set dynamically per model if you override `getMediaCollection()` methods.

---

## Phase 5: Routing Architecture

Currently the admin lives at `/admin`. For a hosted platform you have two common approaches:

**Subdomain-based:** `acme.yourplatform.com/admin` — the subdomain resolves the tenant, the path is just `/admin`. Clean, professional, and easy to implement with the middleware approach described above.

**Path-based:** `yourplatform.com/acme/admin` — tenant slug is a path prefix. Requires prefixing all route groups and injecting the tenant slug into every URL generation call. More painful to build.

Subdomain-based is strongly preferred. You'll also want to carve out a **super-admin panel** at a separate domain or protected subdomain (e.g., `platform.yourplatform.com/admin`) that operates outside tenant scoping entirely.

The current `routes/admin.php` becomes the **per-tenant admin** — it stays as-is but runs behind tenant resolution. A new `routes/platform.php` (or `routes/super-admin.php`) handles cross-tenant operations: creating tenants, impersonating tenants, viewing global metrics, managing plans.

---

## Phase 6: Tenant Onboarding Flow

When a new customer signs up, you need a provisioning pipeline. This isn't just creating a row in `tenants` — it involves:

1. Create the `Tenant` record with the chosen slug/subdomain
2. Create the owner `User` (or attach an existing one)
3. Create the `tenant_users` pivot row with `role = owner`
4. Seed the tenant's default data: default status groups and statuses, default field layouts, any required entry groups for your platform's use case
5. Send a welcome email
6. If doing separate databases per tenant: provision the database, run migrations, seed it
7. Set up any default media library configuration

This pipeline should be a queued job (or a series of chained jobs) because provisioning can be slow. Show the user a "setting up your workspace..." screen while it runs. A `tenant_provisioning_status` column on the `tenants` table (`pending`, `provisioning`, `active`, `failed`) lets you poll for completion.

You also need a **tenant selection screen** for users who belong to multiple tenants — a simple dashboard at `yourplatform.com/app` that lists their workspaces and lets them jump to the right subdomain.

---

## Phase 7: Billing and Plans

A hosted platform needs billing. You need:

A `plans` table: `id, name, price_monthly, price_annual, limits (JSON), stripe_price_id, is_public`

The `limits` JSON defines resource caps per plan: max users, max entries, max storage bytes, max API requests per month, which features are enabled.

A `subscriptions` table (or integrate Stripe via `laravel/cashier`, which provides this): tracks the active plan, billing cycle, trial period, and cancellation date.

The `Tenant` model gets a `withinLimits(string $resource): bool` method that checks current usage against the plan. This gate gets checked in the relevant services — e.g., `EntryService::create()` checks `withinLimits('entries')` before creating, throwing a `PlanLimitExceededException` if over quota.

Resource usage tracking: you need counters or aggregate queries for each metered resource. A `tenant_usage` table with `tenant_id, metric, value, period` lets you track monthly API calls, current user count, current entry count, and current storage usage. These update via model observers and scheduled jobs.

Stripe webhooks (you already have `spatie/laravel-webhook-client` installed) handle subscription events: `invoice.paid`, `customer.subscription.deleted`, `customer.subscription.updated`. When a subscription lapses, set `tenants.is_active = false` — the middleware already blocks inactive tenants.

---

## Phase 8: Impersonation and Super-Admin

Your platform team needs to be able to enter any tenant's workspace to debug issues or provide support. Build a super-admin panel that:

1. Lists all tenants with their status, plan, usage, and last activity
2. Allows impersonating a tenant (sets an `impersonating_tenant_id` in the super-admin's session; middleware picks this up instead of resolving from the domain)
3. Lets platform admins temporarily bypass plan limits or extend trials
4. Provides audit logs of all super-admin actions (critical for security)

The impersonation session should be clearly surfaced in the UI — a banner saying "You are viewing Acme Corp's workspace" with an exit button. Never let impersonation happen silently.

---

## Phase 9: API Changes

The existing Sanctum-based API at `/api/v1` needs tenant awareness. Personal access tokens are currently global. You need to associate tokens with a specific tenant:

Add a `tenant_id` column to `personal_access_tokens`. When a user generates a token in the admin panel, it gets scoped to the current tenant. The API middleware resolves the tenant from the token's `tenant_id` rather than from a header or subdomain.

Alternatively, use subdomain routing for API access too: `acme.yourplatform.com/api/v1/...` — the subdomain resolves the tenant, the token just authenticates the user. This is cleaner.

Rate limiting needs to become tenant-aware. The current setup rate-limits by IP. For an API platform, rate limits should be per-token or per-tenant, keyed against the plan limits. The `api_logs` table already exists for logging — scope all log queries by tenant.

---

## Phase 10: Queue and Background Jobs

Queued jobs currently run without tenant context. When a job is dispatched, the tenant context from the request isn't automatically carried into the worker process. You need to serialize the `tenant_id` into every job.

The cleanest approach is a `TenantAwareJob` trait (or abstract base class) that:

1. Has a `$tenantId` property
2. In the constructor, reads `app(Tenant::class)->id` and stores it
3. In a `middleware()` method, resolves and re-binds the tenant before `handle()` runs

Every job in `app/Jobs/` should use this trait. Adding it to an abstract `BaseJob` means all existing and future jobs inherit it automatically.

For scheduled commands (Artisan's scheduler), you'll need to run tenant-scoped variants. A common pattern: a single scheduled task that queries all active tenants and dispatches a per-tenant job for each one. For example, a "daily digest email" job would be dispatched once per active tenant.

---

## What You're NOT Changing (Yet)

To keep scope manageable, several things can stay as-is in v1:

- **The Twig template system** — templates are per-instance anyway; per-tenant template overrides are a future enhancement
- **The Entry Type class system** — works fine with tenant scoping added; custom entry type classes are a platform-level concern
- **The field type registry** — field types are global definitions; tenant data is in `field_values` which gets scoped
- **The OAuth/OIDC system** — social login works at the user level, not tenant level; no changes needed

---

## Recommended Build Order

Given the dependencies between phases, build in this sequence:

1. Tenant model and provisioning skeleton (Phases 1 and 6 skeleton)
2. Tenant resolution middleware (Phase 2)
3. Global query scoping — the longest phase (Phase 3)
4. Tenant-aware AppServiceProvider and Settings (Phase 4)
5. Routing split: per-tenant admin vs. super-admin (Phase 5)
6. Full onboarding flow and tenant selection screen (Phase 6)
7. Billing and plans (Phase 7)
8. API tenant scoping (Phase 9)
9. Queue tenant awareness (Phase 10)
10. Impersonation tools (Phase 8)

---

## Key Risks

The biggest risk is Phase 3 — adding `tenant_id` to 15+ tables with retroactive migration, updating all query scopes, and finding every place where a scope has been accidentally bypassed. Budget significant testing time there. Every service method, every query builder call, and every raw query needs auditing.

The polymorphic field system (`field_values`, `categorizables`, `fieldables`, `field_groupables`) is the most fiddly area to scope correctly, but it's localized to a handful of files.

The good news: the codebase already uses a service layer consistently, and the `EntryRepository` / `CategoryRepository` pattern means there are fewer places to patch than in a typical CRUD-heavy Laravel app. The separation of concerns makes this tractable.
