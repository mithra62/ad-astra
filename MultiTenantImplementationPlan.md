# Multi-Tenant Implementation Plan

Based on `TenantModel.md` — a phased rollout where Step 1 produces a working system and each subsequent step layers in polish, ending with a full payment model.

---

## Step 1 — Working Foundation (Phases 1 + 2 + 3)

**Goal:** A running system where multiple tenants can exist, requests resolve to the right tenant, and data is fully isolated.

### 1a. Tenant Model & Pivot Table
- Create `tenants` migration: `id, name, slug, domain, is_active, settings (JSON), created_at, updated_at`
- Create `tenant_users` migration: `tenant_id, user_id, role (owner/admin/member), created_at`
- Create `Tenant` Eloquent model with `hasMany` / `belongsToMany` relationships to `User`

### 1b. Tenant Resolution Middleware
- Build `ResolveTenant` middleware — resolves tenant from subdomain → custom domain → `X-Tenant-Slug` header → path prefix, in that order
- Bind resolved `Tenant` into the service container: `app()->instance(Tenant::class, $tenant)`
- Return 404 if no tenant resolves (except for marketing/super-admin routes)
- Register middleware early in `bootstrap/app.php`

### 1c. Global Query Scoping
- Add `tenant_id` column to all tenant-owned tables (entries, categories, fields, field layouts, statuses, settings, media, roles, API logs — see full list in `TenantModel.md` Phase 3)
- Create `BelongsToTenant` trait: boots a global scope filtering by current tenant, auto-sets `tenant_id` on `creating`, exposes `withoutTenantScope()` for system contexts
- Enable Spatie permissions `teams` feature — set `team_foreign_key = tenant_id`, call `setPermissionsTeamId($tenant->id)` in middleware
- Apply the trait to every tenant-scoped model

**✅ End state:** You can create tenants, hit `acme.yourplatform.com`, and every query is automatically scoped. No data bleeds between tenants.

---

## Step 2 — Routing Split & Service Provider Hardening (Phases 4 + 5)

**Goal:** Clean URL architecture and safe singleton handling.

- Switch `AppServiceProvider` singleton bindings to `scoped()` bindings so they re-instantiate per request, not per process
- Update the `Settings` resolver to a four-tier hierarchy: **user → tenant → system → config default** — tenant overrides stored in `setting_values` scoped by `tenant_id`
- Make `FilesService` route tenant media to isolated storage paths: `tenants/{tenant-id}/media/...`
- Split routing:
  - `routes/admin.php` stays as the **per-tenant admin** (runs behind `ResolveTenant`)
  - Create `routes/platform.php` as the **super-admin panel** — operates outside tenant scoping, lives at a separate subdomain (e.g., `platform.yourplatform.com`)

**✅ End state:** Tenant admins and platform admins have separate, safe route surfaces. No shared-singleton data leakage risk.

---

## Step 3 — Onboarding Flow & Tenant Selection (Phase 6)

**Goal:** Self-serve signup that provisions a tenant end-to-end.

- Build a provisioning pipeline as a chained queued job sequence:
  1. Create `Tenant` record
  2. Create or attach owner `User`, insert `tenant_users` row with `role = owner`
  3. Seed default statuses, field layouts, entry groups
  4. Send welcome email
- Add `provisioning_status` column to `tenants` (`pending → provisioning → active → failed`) — poll from a "setting up your workspace…" UI screen
- Build a **tenant selection screen** at `yourplatform.com/app` listing all workspaces for users who belong to more than one tenant

**✅ End state:** New customers can sign up, get a provisioned workspace, and land in it without any manual steps.

---

## Step 4 — API & Queue Tenant Awareness (Phases 9 + 10)

**Goal:** Background jobs and API tokens know which tenant they belong to.

- Add `tenant_id` to `personal_access_tokens` — tokens scoped to the tenant they were created in
- For API routing, prefer subdomain-based resolution (`acme.yourplatform.com/api/v1/...`) so the subdomain drives tenant context and the token just authenticates the user
- Upgrade rate limiting from IP-based to per-tenant / per-token, keyed against plan limits (placeholder limits until Step 6)
- Create `TenantAwareJob` trait (or abstract `BaseJob`): captures `tenant_id` at dispatch time, re-binds it as the tenant context when the job runs in a worker
- Add a scheduler pattern: one master task queries all active tenants and dispatches per-tenant jobs for recurring work (digests, cleanup, etc.)

**✅ End state:** API calls and background jobs are fully tenant-isolated, no context bleed through worker processes.

---

## Step 5 — Super-Admin & Impersonation (Phase 8)

**Goal:** Your team can support and debug any tenant's workspace safely.

- Build super-admin panel (behind `routes/platform.php`) with:
  - Tenant list view: status, plan, usage, last activity
  - Trial extension and plan-limit override controls
  - Audit log of all super-admin actions
- Implement impersonation: store `impersonating_tenant_id` in the super-admin's session; `ResolveTenant` middleware checks for this before the normal domain-resolution path
- Add a persistent UI banner when impersonating: "You are viewing [Tenant Name]'s workspace" with a clear Exit button — impersonation must never be silent

**✅ End state:** Your support team can enter any workspace, debug issues, and leave a full audit trail.

---

## Step 6 — Billing & Plans (Phase 7)

**Goal:** The platform charges for itself.

- Create `plans` table: `id, name, price_monthly, price_annual, limits (JSON), stripe_price_id, is_public`
  - `limits` JSON defines: max users, max entries, max storage bytes, max API calls/month, feature flags
- Integrate `laravel/cashier` for Stripe — the `subscriptions` table comes with it; link it to the `Tenant` model (not `User`)
- Add `withinLimits(string $resource): bool` to `Tenant` — called in service layer before resource creation (e.g., `EntryService::create()`, user invite flow)
- Create `tenant_usage` table (`tenant_id, metric, value, period`) updated via model observers and a nightly scheduled job
- Wire up Stripe webhooks (using the already-installed `spatie/laravel-webhook-client`) for:
  - `invoice.paid` — keep subscription active
  - `customer.subscription.deleted` — set `tenants.is_active = false` (middleware already blocks inactive tenants)
  - `customer.subscription.updated` — sync plan/limits
- Build plan selection UI into the onboarding flow and an upgrade/downgrade screen in the tenant admin

**✅ End state:** Tenants are on plans, resource limits are enforced, Stripe handles billing, and a lapsed subscription automatically locks the workspace.

---

## Risk Notes

- **Step 1c is the highest-risk phase** — adding `tenant_id` to 15+ tables with live migrations requires careful testing. Audit every service method, query builder call, and raw query. The polymorphic tables (`field_values`, `categorizables`, `fieldables`) need particular attention.
- The existing service/repository layer is an asset here — fewer places to patch than a typical CRUD app.
- Keep `withoutTenantScope()` usage strictly controlled and always code-reviewed — it's the escape hatch that could cause a data leak if misused.
