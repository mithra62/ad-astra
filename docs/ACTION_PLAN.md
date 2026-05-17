# Pending Plans — Triage & Action Plan

> Triage of `OVERVIEW.md`, `MEDIA_LAYER_OVERVIEW.md`, `media-status-implementation-plan.md`, `SEARCH_PLAN_V2.md`, `SHOP_PLAN.md`, `TenantPlan.md`, `SEO_SCHEMA_PLAN.md`, `MULTILINGUAL_PLAN.md`, and `DISCUSSION_LAYER_PLAN.md`. The recommendation below is an ordering with rationale, not a re-plan — each plan stands on its own; this file decides which one to start.

---

## 2026-05-12 Status Update

The native Media and Media Library layer is complete and in testing. Treat the former Media refactor as landed. The remaining media-specific planning item is `media-status-implementation-plan.md`, which adds optional status governance on top of the completed native layer. See `MEDIA_LAYER_OVERVIEW.md` for how the implemented layer operates.

## 2026-05-16 Verification Pass

The Step 0 Known Gaps list was audited against the live source on 2026-05-16. Four of the eight items have landed since the original triage; four remain. `OVERVIEW.md` (last synchronised 2026-04-29) and its "Known Gaps and Implementation Status" section are stale in the same places — refresh that doc next time it is touched.

**Resolved since the original triage:**

- `EntryResource` — now correctly exposes `id`, `entry_group_id`, `entry_type_id`, `title`, `handle`, `status_handle`, `status_is_public`, `published_at`, `fields`, `authors`, `categories`, and timestamps. The OpenAPI annotation matches the shape. Verified at [app/Http/Resources/Api/EntryResource.php](app/Http/Resources/Api/EntryResource.php).
- `Api\v1\User` permission — the seeder now defines both `view user` (admin/web UI) and `read users` (API). The controller's `$this->can('read users')` check at [app/Http/Controllers/Api/v1/User.php:60](app/Http/Controllers/Api/v1/User.php) matches a seeded permission. Verified at [database/seeders/RolesPermissionsSeeder.php:21](database/seeders/RolesPermissionsSeeder.php).
- Entries API endpoints — full CRUD has landed. `index`, `store`, `show`, `update`, `destroy` all return real responses. Resource is `EntryResource` / `EntryCollection`. Verified at [app/Http/Controllers/Api/v1/Entries.php](app/Http/Controllers/Api/v1/Entries.php).
- `Media` is `Fieldable` by default — `use Fieldable` on [app/Models/Media.php:18](app/Models/Media.php). The `media_libraries.field_layout_id` column and the `mediables.field_id` sentinel column both exist. Custom media fields are first-class.

**Still real:**

- `Api\v1\Account@show` — still returns a placeholder `'Profile updated successfully'` message at [app/Http/Controllers/Api/v1/Account.php:38-41](app/Http/Controllers/Api/v1/Account.php). The OpenAPI annotation promises a `User` schema response that the implementation does not deliver.
- `EntryType.max_depth` and `EntryType.allowed_parent_types` — stored, fillable, cast to `array` on [app/Models/EntryType.php:25-33](app/Models/EntryType.php) and accepted by `StoreEntryTypeRequest` / `EditEntryTypeRequest`, but **no enforcement** in any tree service. A grep for these names hits only the request validators, the model, and `EntryTypeService` set/save calls — never a tree-walk or insertion-time check.
- `app:refresh-tokens` — `handle()` is empty except for commented example code at [app/Console/Commands/RefreshTokens.php:16-35](app/Console/Commands/RefreshTokens.php). Scaffold only.
- `site.templates.base_path` and `site.templates.not_found_template` — still not read by any route driver. `site.templates.default_template` IS now wired up in [TemplateRouteDriver::resolveHome()](app/Services/SiteRouting/RouteDrivers/TemplateRouteDriver.php) at line 49. The `base_path` config key would belong in `viewName()` at line 136-139 where the `templates::` namespace prefix is currently hardcoded; `not_found_template` would belong in the null-return branches throughout that same driver.

**Other deltas worth noting:**

- `DISCUSSION_LAYER_PLAN.md` now exists (703 lines, polymorphic morph design — `discussions` + `discussion_reactions` tables, `HasDiscussions` trait, `DiscussionRepository` + `DiscussionService` pair). Step 6 below is updated accordingly.
- `media-refactor-plan.md` was removed from the docs directory — the original triage intro referenced it. The surviving media docs are `MEDIA_LAYER_OVERVIEW.md` (reference) and `media-status-implementation-plan.md` (follow-up).

---

## What These Files Are

| File | Type | Status |
|---|---|---|
| `OVERVIEW.md` | Reference doc — current state of the CMS, synchronised against the live source on 2026-04-29. | Living doc. Has a **Known Gaps** section (~8 small, mostly API-layer bugs) that is the only actionable content. |
| `MEDIA_LAYER_OVERVIEW.md` | Reference doc — native Laravel media layer, Media Library containers, FileUpload integration, transformations, and purge flow. | Done. In testing. |
| `media-status-implementation-plan.md` | Follow-up — status governance for media libraries and media records. | Plan. Not started. |
| `SEARCH_PLAN_V2.md` | Feature — `is_searchable`/`search_weight` on `field_layout_tab_elements`, a `search_index` table, `Searchable` trait, `Indexer`, jobs, collection scoping. 7 delivery phases. | Plan, V2. Not started. Media explicitly out of scope but accommodated by the contract. |
| `SHOP_PLAN.md` | Feature — `mithra62/Shop` e-commerce module: products, cart, orders, payments, discounts, tax, shipping, subscriptions, digital delivery. 11 phases (~16 weeks). | Plan, v3. Not started. Phase 11 is "Tenancy Integration." |
| `TenantPlan.md` | Foundation — multi-tenant SaaS via shared DB + `tenant_id` on every tenant-owned table; `BelongsToTenant` trait; `ResolveTenant` middleware; Spatie teams mode; queue/job tenancy; super-admin & impersonation; billing. 6 steps. | Plan. Not started. The biggest blast radius of the five. |
| `MULTILINGUAL_PLAN.md` | Feature — translate every Fieldable model (Entry, Category, Media, User). `locale` column on `field_values` and `entry_trees`; `is_translatable` flag on fields and on each container (EntryGroup, CategoryGroup, MediaLibrary); three new `*_translations` tables for canonical text columns; locale-prefixed URLs via `SetLocaleFromUri` middleware; read-time fallback to default locale. | Plan. Not started. Touches the same Fieldable plumbing Tenancy does, so ordering matters. |
| `SEO_SCHEMA_PLAN.md` | Feature — schema.org JSON-LD generation from the Entry layer; per-entry `schema_type`; field-to-schema-property mapping at the `FieldLayoutTabElement` level; `BreadcrumbList` from `EntryTree`; Twig rendering via `schema_json(entry)`. 9 delivery phases. | Plan. Not started. No blocking dependencies on other plans. |
| `DISCUSSION_LAYER_PLAN.md` | Feature — polymorphic discussion/commenting layer attachable to any model via `HasDiscussions` trait. Two tables (`discussions`, `discussion_reactions`); one-level threading; `pending/approved/flagged/spam` moderation; optional 1–5 rating; reactions as a sub-model. `DiscussionRepository` + `DiscussionService` pair. | Plan. Not started. Plan now exists (~700 lines). |

`OVERVIEW.md` is the **only** one that's not a plan. Everything in it that needs work is in *Known Gaps and Implementation Status*. The original eight items have been audited against the live source (see the 2026-05-16 verification update); four are resolved, four remain. `OVERVIEW.md` itself is stale on those four — refresh it next time it is touched.

---

## Cross-Plan Dependencies

These are the constraints the ordering has to respect.

**Tenancy ↔ Media (the critical one).**
TenantPlan Step 1e adds `tenant_id` to `media` and `media_libraries`. The old Spatie MediaLibrary queue-job risk is resolved because the native Media layer is complete and in testing. If the media status follow-up lands, do it before TenantPlan so tenant columns are added to the final table shape.

**Tenancy ↔ Search.**
Search adds two columns to `field_layout_tab_elements`. Tenancy adds `tenant_id` to that same table. Sequencing matters: if Search lands first, `search_index` has to be retrofitted with `tenant_id` later. If Tenancy lands first, Search just adds `tenant_id` from day one. Search-after-Tenancy is the cleaner direction.

**Search ↔ Media.**
Search Plan §12 calls Media out of scope, *but* the `Searchable` trait contract is already shaped to accept Media when Media gains a field layout — which is exactly what Media Plan Phase 1.4 does (`media_libraries.field_layout_id`). Order: Media → Search → free Media-search at launch.

**Shop ↔ Tenancy.**
Shop Plan §14 explicitly handles this: Shop can ship with a stub `BelongsToTenant` and a single seeded `tenant_id = 1`, then formally integrate in Phase 11. So Shop is not strictly blocked on Tenancy — but if Tenancy lands first, Shop's Phase 11 collapses to "add `tenant_id` columns" instead of a retrofit.

**Shop ↔ Media.**
Shop §3.1 wants `product_images` and `download_file` as media fields. If Media is still on Spatie when Shop ships, every product upload path is on the system that's about to be replaced. If Media is native first, Shop is built on the final API.

**Shop ↔ Search.**
Shop §18 (and §19 correction #8) explicitly pulled `EntryQueryBuilder::whereField()` into Shop Phase 2 — that's the *minimum* search needed for a storefront. Full keyword/relevance search (this is what `SEARCH_PLAN_V2.md` delivers) is a richer answer that the storefront and admin both want, but Shop has a working fallback. Search is a strong add-on for Shop, not a hard blocker.

**Multilingual ↔ Tenancy.**
Multilingual edits the same migrations Tenancy edits (`entry_groups`, `category_groups`, `media_libraries`, `fields`, `field_values`, `entry_trees`) and creates three new translation tables. If Multilingual lands first, every `*_translations` table needs `tenant_id` retrofitted by Tenancy and the per-locale handle uniqueness constraints have to be widened to include `tenant_id`. If Tenancy lands first, Multilingual adds `tenant_id` to translation tables from day one. Tenancy-before-Multilingual is the cleaner direction.

**Multilingual ↔ Search.**
`SEARCH_PLAN_V2.md` builds a `search_index` table. With Multilingual in place, that index needs a `locale` column from day one so French and English content are indexed and retrieved independently. If Search V2 lands first, the index has to be rebuilt with `locale` later. Multilingual-before-Search is the cleaner direction.

**Multilingual ↔ Media.**
The Media follow-up plan touches `media_libraries` (where Multilingual adds `is_translatable`) and `media` (where Multilingual reads via the new `media_translations` table). The `FieldValueObserver::syncMediables` bug surfaced in `MULTILINGUAL_PLAN.md` (Fix 1) is also a bug *today* whenever the same FileUpload field is edited from two contexts — Multilingual is the work item that forces the fix to ship, but the fix itself is independently correct.

**Multilingual ↔ SEO Schema.**
A multilingual site emits per-locale schema with `inLanguage` set and `alternate` URLs for translated variants. If Multilingual lands first, the SEO Schema generators read `current_locale` and emit the right blocks on first build. If SEO Schema lands first, the generators have to be retrofitted with locale awareness. Multilingual-before-SEO is the cleaner direction.

**Multilingual ↔ Shop.**
Product names, descriptions, and metadata are all translatable content. With Multilingual in place, Shop's product entries inherit translation for free — `EntryGroup.is_translatable = true` on the "Products" group and the entire Shop catalogue becomes localisable. If Shop lands first, every Shop-specific text column has to be migrated into translation tables later. Multilingual-before-Shop saves migration work.

**SEO Schema ↔ everything else.**
Minimal overlap. `field_layout_tab_elements` gains `schema_property` (SEO) alongside
`is_searchable` and `search_weight` (Search V2) — separate migrations, no conflict.
If Search V2 lands first, the `resolveLayoutElements` repository refactor (SEO Phase 2)
may already be partially complete; check before duplicating. Tenancy adds `tenant_id`
to `entries`, `entry_types`, and `field_layout_tab_elements` — the schema columns are
nullable strings with no tenant logic and require no scoping changes. The media refactor
upgrades `MediaField::schemaValue()` from null to a real `ImageObject` emitter as a
byproduct of that work; no changes to the schema layer itself. Shop entries participate
in schema output automatically once `schema_type` is set — no schema layer changes
needed.

**OVERVIEW gaps.**
Independent of all of the above. Each can be knocked out in isolation — they're API-layer bugs and unread config flags.

---

## Risk / Blast Radius / Strategic Value

| Plan | Blast radius | If delayed | If rushed | Strategic value |
|---|---|---|---|---|
| TenantPlan | Massive — every tenant-owned table, every model, every query path | Every other feature has to be retrofitted with `tenant_id` later | Critical: cross-tenant data leak | Foundation for SaaS |
| Media layer | Done; currently in testing | Status governance may need a second migration pass if delayed until after TenantPlan | Treating the old Spatie plan as current would duplicate completed work | Native uploads, `FileUpload`, transformations, soft-delete purge flow, MediaLibrary field layouts |
| Multilingual | Medium — `locale` column on `field_values` and `entry_trees`, three new translation tables, in-place migration edits on fresh schema | Content stays single-locale; Search index, SEO schema, and Shop catalogue all built single-locale and need retrofitting later | Half-translated admin surface, unclear fallback behaviour, broken `mediables` sync (Fix 1) if observer fix is skipped | Foundation for global content; prerequisite for any multi-language storefront or marketing site |
| Search V2 | Medium — additive (new tables, new trait, new dispatch points in repos/services) | Storefront and admin search stay absent | Wrong weights baked in (mitigated: opt-in per element, easy to retune) | Required for Shop UX and admin productivity |
| Shop | Largest in pure code volume; mostly *additive* under `mithra62/Shop/` | Revenue feature delayed | Order/payment correctness bugs are unforgiving | The reason for the project |
| SEO Schema | Small — additive columns, new `App\Schema\` namespace, one Twig function | Public-facing pages lack structured data | Low — no correctness-critical paths | SEO quality; Google Rich Results eligibility |
| OVERVIEW gaps | Small — API resources, permission strings, two unread config keys | Public API documentation lies a bit | Low | Quality polish |

---

## Recommended Ordering

```
0. OVERVIEW gaps        (warm-up — ~3-5 days; or interleave throughout)
1. Media status follow-up (optional, smaller than original refactor)
2. TenantPlan             (~6-8 weeks; Steps 1-5; defer Step 6 until just before Shop)
3. Multilingual           (~3-4 weeks; lands the Fieldable trait + repository changes once everything is tenant-aware)
4. Search V2              (~2-3 weeks)
5. SEO Schema             (~2-3 weeks)
6. Discussion layer       (~TBD; DISCUSSION_LAYER_PLAN.md not yet written)
7. Shop                   (~14-16 weeks; Phase 11 collapses because tenancy is already real)
```

### Step 0 — OVERVIEW Known Gaps (warm-up, ~2-3 days)

Knock these out first. They're small, they're isolated, and getting them green builds momentum before the bigger work starts. The list below reflects the **2026-05-16 verification pass** — four of the original eight items have landed (see the verification update above for details); only the four below remain.

- **Implement `Api\v1\Account@show`** — currently returns a placeholder `'Profile updated successfully'` message at [app/Http/Controllers/Api/v1/Account.php:38-41](app/Http/Controllers/Api/v1/Account.php) despite an OpenAPI annotation promising a `User` schema response. Should return the authenticated user via `UserResource`. The other `Account` actions (`update`, `updatePassword`, `updateAvatar`, `updateEmail`) are also placeholders worth wiring up at the same time since they're trivial extensions of the same pattern.
- **Enforce `EntryType.max_depth` and `EntryType.allowed_parent_types`** — both are stored, fillable, and cast on [app/Models/EntryType.php:25-33](app/Models/EntryType.php) and accepted by the type form requests, but **no service or repository reads them at insertion time**. The enforcement belongs in `EntryService::createTreeNode` / `syncTreeNode` (and the `treeAssertUnique*` helpers next to them): when an entry is being placed under a parent, check `max_depth` against the parent's depth and check `allowed_parent_types` against the parent's `entry_type_id`. The Multilingual plan will be touching these same tree methods (Fix 2 in MULTILINGUAL_PLAN.md) — landing this gap before Multilingual avoids two passes through the same code.
- **Implement `app:refresh-tokens`** — `handle()` is empty except for commented example code at [app/Console/Commands/RefreshTokens.php](app/Console/Commands/RefreshTokens.php). The example sketches the intended pattern: query `OauthToken::provider($p)->active()` and feed each to `TokenRefreshService::tryRefresh()`. `TokenRefreshService` already exists at [app/Services/OAuth/TokenRefreshService.php](app/Services/OAuth/TokenRefreshService.php). Wire it up; uncomment; add a basic scheduler entry.
- **Wire `site.templates.base_path` and `site.templates.not_found_template`** — `default_template` is now read by `TemplateRouteDriver::resolveHome()` (line 49), but the other two config keys are still ignored. `base_path` belongs in `viewName()` at line 136-139 where the `templates::` namespace prefix is currently hardcoded. `not_found_template` belongs in the null-return branches where `View::exists()` fails — instead of returning null and letting the SiteRouter fall through, render the configured not-found template. If the consensus is that these keys should just be deleted, do that and remove the dead config.

Don't try to do this in parallel with Step 1 — it's "loosen the muscles" before the big lifts, not real engineering capacity.

### Step 1 — Media Status Follow-Up

The native Media refactor is complete and in testing. The remaining media work currently documented is the status-governance follow-up in `media-status-implementation-plan.md`.

**Why before tenancy if it ships.** It adds status columns and relationships to `media` and `media_libraries`, which TenantPlan later touches for `tenant_id`. If the status layer is desired, land it before TenantPlan to avoid a second coordinated migration pass.

**What you get for the next plans:**

- `media_libraries.field_layout_id` exists → Media is ready to plug into Search via the existing `Searchable` contract when you get there.
- `FileUpload` field type exists → Shop's `product_images` and `download_file` fields are first-class field types, not Spatie hooks.
- Two fewer Spatie deps to think about during Tenancy.
- The `mediables` pivot adds a `field_id` column → multi-field media references work correctly from day one.

**Gate.** The old "remove Spatie first" gate is satisfied. Before TenantPlan, decide whether the media status layer is part of the near-term scope; if yes, complete it first so tenancy lands on the final media table shape.

### Step 2 — TenantPlan (Steps 1-5, ~6-8 weeks)

**Why second.** Tenancy fundamentally changes how every query is shaped. Doing it after Media but before Search/Shop means:

- Search and Shop are designed-in to tenancy, not retrofitted.
- The native Media tables (`media`, `mediables`, `media_transformations`) already have stable shapes when `tenant_id` is added — no double migration.
- The "two-tenant isolation test" that TenantPlan §1b makes you write against every model becomes a permanent contract for everything new.

**Run Steps 1-5; defer Step 6 (Billing & Plans) until just before Shop.** Step 6 is Stripe + Cashier + plan limits + lapsed-subscription locking — that's a lot of code, and the way it carries usage counts (`tenant_usage`) overlaps Shop's own subscription/billing concerns. Land Step 6 *immediately before* Shop Phase 4 (Payment Gateway) so the same Stripe wiring serves both.

**Gates documented in the plan that I'm restating because they matter:**

- Do not apply `ResolveTenant` to `routes/api.php` until Step 4 (per TenantPlan §1c).
- The seed tenant (`id = 1`) must be created before any backfill migrations run.
- All settings cache keys must include `tenant_id` (Step 1g).
- `morphToMany` traits (`HasCategories`, `Fieldable`) need `->wherePivot('tenant_id', ...)` added when the pivot column is added (Step 1e). Don't ship one without the other.

### Step 3 — Multilingual (~3-4 weeks)

**Why third.** Multilingual edits the same migrations Tenancy edits (`entry_groups`, `category_groups`, `media_libraries`, `fields`, `field_values`, `entry_trees`) and creates three new `*_translations` tables. Landing it immediately after Tenancy means:

- Every `*_translations` table gets `tenant_id` from day one — no retrofit later, no widening of per-locale handle uniqueness constraints to include tenant.
- `BelongsToTenant` is already a familiar trait, so the new translation tables wire it on first creation.
- The Search and SEO Schema plans, which follow, are designed-in to both tenancy and locale rather than retrofitted.

**What this delivers:**

- `is_translatable` flag on `EntryGroup`, `CategoryGroup`, `MediaLibrary`, and `fields` — translation gates live on the container, parallel pattern across all three Fieldable container models.
- `locale` column on `field_values` (4-tuple unique) and `entry_trees` (per-locale handle and URI uniques).
- New `entry_translations`, `category_translations`, `media_translations` tables for canonical text columns (`title`, `handle`, `name`); default-locale data stays on the canonical row.
- `SetLocaleFromUri` middleware (site) and `SetAdminLocale` middleware (admin); locale-prefixed URLs (`/en/about`, `/fr/a-propos`); `Localization` settings domain (`available_locales`, `default_locale`, `fallback_strategy`, `strip_default_locale_prefix`, `users_translatable`, user-overridable `ui_locale`).
- Read-time fallback to default locale at per-field-value granularity.
- Three latent bugs fixed as part of the work: `FieldValueObserver::syncMediables` orphaning shared media (Fix 1), `EntryService::syncTreeNode` deriving tree URIs from `$entry->handle` (Fix 2), locale-blind form-request uniqueness (Fix 3).

**Why before Search V2 and SEO Schema.** Search V2's `search_index` must carry `locale` from day one; SEO Schema must emit `inLanguage` and `alternate` URLs from day one. Both are noticeably easier to build with locale as a first-class concept than to retrofit.

**Why before Shop.** Shop's product names, descriptions, and metadata are translatable content. With Multilingual live first, the Products EntryGroup flips on `is_translatable` and the entire catalogue becomes localisable without per-Shop migration work.

**Run the plan in its rollout order** (Schema → Settings + middleware + Twig → Read path → Write path → Observer fix → Admin UI). The fresh-system assumption means the existing migrations are edited in place rather than ALTERed.

### Step 4 — Search V2 (~2-3 weeks)

**Why fourth.** With Media native, Tenancy live, and Multilingual landed, Search V2 is straightforward:

- `field_layout_tab_elements` already has `tenant_id` → search settings are scoped per tenant by inheritance.
- `search_index` adds `tenant_id` AND `locale` from day one — no retrofit on either dimension.
- Media has a `field_layout_id` → adding Media to search is trait + 5 methods, exactly as Search Plan §12 describes.
- All the `Searchable` dispatch points (`EntryRepository`, `UserService`, etc.) are tenant-aware and locale-aware already, so reindex jobs run in the right scope.

The plan's §11 delivery phases (1: Migrations → 2: Trait + model implementations → 3: Indexer + jobs → 4: Dispatch wiring → 5: Query builder → 6: Collections → 7: Admin UI) are clean. Run them in order.

**Optional during this step:** apply the `Searchable` trait to `Media` while you're in there — Media has a field layout now, and Search Plan §12 says this is a one-shot trait + implementation job. Cheap to do here, expensive to retrofit later.

**Note on multilingual.** The `Indexer` must index translatable fields once per (record, locale) pair and the query builder must scope by `App::getLocale()` with fallback. These are small additions, not architectural changes — `Searchable::toSearchableArray()` becomes `toSearchableArray(string $locale)` and the dispatch points loop over `available_locales`.

### Step 5 — SEO Schema (~2-3 weeks)

**Why fifth.** With Search V2 in place, the `resolveLayoutElements` primitive (SEO
Phase 2) may already exist — Search V2 needs the same method. Schema is entirely
additive and isolated; landing it before the Discussion layer and Shop keeps the
content foundation complete before the heavier feature work begins.

**Note on multilingual.** Generators emit `inLanguage = current_locale` on every
schema block and an `alternate` URL block per available translation. Both are small
additions in `AbstractSchemaGenerator` rather than per-generator concerns.

**What you get:**
- Every public Entry can emit schema.org JSON-LD via a single Twig function call.
- `BreadcrumbList` is derived automatically from the `EntryTree` hierarchy with no
  configuration beyond the tree structure already being in place.
- Field-to-schema-property mapping lives at the `FieldLayoutTabElement` level, so
  the same field can behave differently across layouts.
- Google Rich Results eligibility for Article, BlogPosting, WebPage, and Event types
  at launch, with additional types added by registering a new generator class.

**Note on Search V2 overlap.** SEO Phase 2 (`FieldLayout::elements()` +
`EntryRepository::resolveLayoutElements()`) is the same primitive Search V2 needs.
If Search V2 has already landed, Phase 2 of this plan may be fully done — verify
before starting.

**Note on media.** The media refactor (Step 1) upgrades `MediaField::schemaValue()`
from null to a real `ImageObject` emitter. If the media refactor has not landed,
image schema properties will be absent or bare URL strings — valid per the spec, but
not as rich. Not a blocker; an automatic quality improvement when Step 1 is done.

Run the SEO Schema plan in phase order (1: Migrations → 2: Model and repository
additions → 3: Field layer → 4: Generator foundation → 5: Concrete generators →
6: BreadcrumbList → 7: Twig layer → 8: Admin UI → 9: Tests and documentation).

### Step 6 — Discussion Layer (~2-3 weeks)

`DISCUSSION_LAYER_PLAN.md` is now written (~700 lines, design complete). Sits after SEO Schema and before Shop because:

- The Entry layer is fully stable by this point — Media, Tenancy, Multilingual, Search, and SEO have all landed. The Discussion layer attaches to entries and users via the `HasDiscussions` trait; having those foundations settled first avoids retrofitting trait-aware migrations and resources.
- Shop brings its own substantial data model and a long timeline. Landing Discussions first keeps the two feature namespaces separate and independently deployable. Shop products will gain reviews/ratings for free by adding `HasDiscussions` to the Product model.

**What this delivers:**

- Two new tables: `discussions` (polymorphic via `discussable_id`/`discussable_type`, with `parent_id` threading, `status` enum, optional 1–5 `rating`, `is_pinned`, moderation columns) and `discussion_reactions` (likes, upvotes, etc. — unique per discussion/user/type).
- `App\Models\Discussion` + `App\Models\Discussion\Reaction` (the sub-model pattern mirrors `Media\Library`).
- `App\Traits\Discussion\HasDiscussions` for any model to opt in.
- `DiscussionRepository` + `DiscussionService` pair so consumers never touch Eloquent directly.
- One-level threading enforced at the service layer (a reply to a reply is collapsed to a reply to the root).
- Moderation states: `pending / approved / flagged / spam`, with `approved_at` and `approved_by_user_id` audit columns.

**Multilingual interaction.** Discussion bodies are user-generated and not translated by the editorial workflow, so `discussions.body` stays a single column. If `Entry.is_translatable=true` and a user reads the FR locale, the same discussion thread renders — the comment is in whatever language the commenter wrote it. No multilingual-specific work in this step.

**Tenancy interaction.** `discussions.tenant_id` and `discussion_reactions.tenant_id` are added from day one because Tenancy is live by this point.

Run the plan in its file order (Schema → Models → Trait → Repository → Service → Migrations → Eager-load wiring), then wire `HasDiscussions` onto `Entry` and `Media` as the first consumers.

### Step 7 — Shop (~14-16 weeks)

**Why last.** By the time Shop starts:

- Tenancy is real → Phase 11 collapses to "add `tenant_id` columns to new tables
  and add the trait" instead of "build a stub and migrate later." That alone is
  several days saved.
- Multilingual is real → the Products EntryGroup flips on `is_translatable` and
  the entire catalogue becomes localisable without per-Shop migration work.
  Product names, descriptions, and metadata translate via the same Fieldable
  paths used everywhere else.
- `FileUpload` is a field type → `product_images` / `download_file` use it directly.
- Search V2 exists → product browsing has full-text relevance ranking on top of the
  `whereField()` work Shop Phase 2 already plans. The §18 caveat *"a storefront with
  no price or attribute filtering is not a storefront"* gets resolved by the
  combination of `whereField()` + Search V2.
- TenantPlan Step 6 (Cashier + Stripe) is in place → Shop's payment/subscription
  work plugs into existing infra rather than building parallel infra.
- SEO Schema is live → product and event entries emit schema.org JSON-LD
  automatically once `schema_type` is set; no extra Shop-specific work needed.

Run the Shop plan in its own order (Phases 1-11). The `mithra62/Shop` namespace is
already wired in `composer.json`; the work is greenfield under that namespace, which
is why this plan is the largest by code volume but the *lowest* coupling risk to the
rest of the codebase.

**One ordering tweak inside Shop**, given the new context:

- **Shop Phase 11 (Tenancy Integration) becomes Shop Phase 1.5.** Because Tenancy
  is already real when Shop starts, every new shop migration adds `tenant_id` from
  day one; every shop model gets `BelongsToTenant` from day one. There's no separate
  "tenancy integration" phase to defer — tenancy is the substrate.

---

## Parallelization Notes

Most of this is sequential because each step de-risks the next. The places real parallel work is possible:

- **OVERVIEW gaps** can be picked up by anyone at any time. They make good "first PR for a new contributor" tasks or filler during waits on review.
- **Tenancy testing infrastructure** (`WithTenantContext` trait, `TenantFactory`) per TenantPlan §1h — build this *during* Step 1b/1e, not after. The plan calls this out explicitly.
- **Search admin UI** (Phase 7 of Search) and **Search dispatch wiring** (Phase 4) can run in parallel with each other — the UI doesn't depend on dispatch being live, and vice versa.
- **Shop greenfield work under `mithra62/Shop/`** can begin as soon as Tenancy Steps 1-3 are merged, even if Search V2 is still in progress. The two don't touch the same code.

What you cannot parallelize:

- Don't start two of (Media, Tenancy, Multilingual, Search) at the same time. They overlap on the same tables (`field_layout_tab_elements`, `media_libraries`, `field_values`, `entry_trees`, the morph pivots) and on the `BelongsToTenant` rollout. Multilingual specifically edits the migrations Tenancy edits, and the Search index needs locale columns Multilingual provides — concurrent work on any two creates merge conflicts in migrations and trait method signatures.

---

## Decision Points You'll Hit

These are flagged in the plans but easy to lose sight of when you're ordering them at the strategic level. Surface them now so they don't surprise the team mid-step.

1. **Image transformation library** — Media Plan defers this; the driver interface and `NullTransformationDriver` are stubbed so the refactor doesn't block on the choice. Pick one (Intervention v3 is the obvious default) before Shop Phase 2 needs real product image variants.
2. **Shop tenancy stub vs. waiting** — `SHOP_PLAN §14` explicitly recommends shipping a stub. With this ordering, the stub is unnecessary; Tenancy is real before Shop starts. Update Shop Plan Phase 1 to remove the stub work.
3. **TenantPlan Step 6 timing** — the plan lists it as Step 6, last in the sequence. With Shop coming after, you have flexibility: land Step 6 right before Shop Phase 4 (Payment Gateway) so Cashier is already wired.
4. **Search V2 + Media** — decide whether Media joins Search at launch or in a follow-up. Recommendation: at launch, while you're already in the Search code.
5. **Local dev subdomain strategy** — TenantPlan §Risk Register flags this. Decide before Step 1 starts (Herd wildcard, dnsmasq, or path-prefix fallback) so the team isn't blocked on day one.

---

## Quick Reference — One-Line Summary Per Plan

- **OVERVIEW.md** → reference doc. Eight small Known Gaps; fix as warm-up.
- **MEDIA_LAYER_OVERVIEW.md** → native Media and Media Library layer. Done and in testing.
- **media-status-implementation-plan.md** → optional status governance for Media; do before TenantPlan if it is in near-term scope.
- **TenantPlan.md** → multi-tenant foundation. Do **second** so Multilingual, Search, SEO, Discussions, and Shop are born tenant-aware.
- **MULTILINGUAL_PLAN.md** → translate every Fieldable model via `locale`-keyed `field_values`, per-container `is_translatable` gate, and three new translation tables. Do **third**, immediately after Tenancy, so Search/SEO/Shop are designed-in to locale rather than retrofitted.
- **SEARCH_PLAN_V2.md** → keyword search via index-time weighting. Do **fourth**; tack Media on while you're there. `search_index` carries `tenant_id` AND `locale` from day one because both foundations are live.
- **SEO_SCHEMA_PLAN.md** → schema.org JSON-LD from the Entry layer. Do **fifth**; additive and isolated, completes the content foundation before feature work begins. Emits `inLanguage` and `alternate` URLs because Multilingual is live.
- **DISCUSSION_LAYER_PLAN.md** → polymorphic discussion/commenting layer (HasDiscussions trait, threaded replies, moderation, reactions). Do **sixth**; plan now written and ready to execute.
- **SHOP_PLAN.md** → e-commerce module. Do **last**; Phase 11 (tenancy integration) collapses to nothing because tenancy is already real, and the product catalogue inherits translation from the Multilingual layer.

---

## Design Discussion Log

> Architectural decisions made during planning conversations, recorded here to
> preserve the rationale alongside the ordering.

### SEO Schema — 2026-05-02

**Context.** Designing a schema.org JSON-LD generation layer for the Entry system
from scratch. No existing SEO infrastructure in the codebase.

**`schema_type` on `entries`, not `entry_types`.**
The initial proposal put `schema_type` on `entry_types` so all entries of a given
type share a schema shape. This was revised: a "General Page" EntryType should be
able to produce `Article` entries and `WebPage` entries without needing a separate
type for each. Moving `schema_type` to `entries` gives per-entry control.
`entry_types.default_schema_type` was retained as a seed value only — written to
`entries.schema_type` at creation time, never read at generation time. At generation
time only `entry.schema_type` is consulted; null means no schema emitted, with no
fallback chain.

**`schema_property` on `field_layout_tab_elements`, not on `fields`.**
An early draft put `schema_property` on the `fields` table. This was revised after
observing that the same field (e.g. `summary`) may need to map to `description` in
one layout and `abstract` in another. The `FieldLayoutTabElement` is the "field in
context" junction — the correct place for context-specific behaviour. This decision
is reinforced by Search V2, which puts `is_searchable` and `search_weight` on the
same table for exactly the same reason.

**`resolveLayoutElements` added as a new method; `resolveLayoutFields` untouched.**
The initial plan called for refactoring `resolveLayoutFields` to return
`FieldLayoutTabElement` models. On review, that change has three live call sites
(two internal to the repository, one public via `EntryService::resolveFields()`)
and would break callers silently. More importantly, flat Field Collections from a
layout are a genuinely useful primitive worth keeping. The decision was to add a
parallel `resolveLayoutElements` to `EntryRepository` and a parallel `elements()`
to `FieldLayout` (alongside the existing `fields()`). Schema and search use the new
method; all existing callers of `resolveLayoutFields` are unchanged. Deduplication
in the new method is on `field_id` rather than `id` to preserve type-over-group
precedence correctly.

**`schemaValue()` on `AbstractField`, not special-cased in generators.**
Field types know their own storage format. Putting the schema rendering concern
inside the field type means generators stay thin and output quality improves
automatically as field types become richer (e.g. when `MediaField` lands and
returns an `ImageObject`). The `Relationship` field type returns null from
`schemaValue()` as a stub until its planned redesign.

**BreadcrumbList is conditional on `entryTree` presence, not a separate generator.**
If `$entry->entryTree` exists, `AbstractSchemaGenerator` appends a `BreadcrumbList`
block to the output. If not, nothing is appended. No per-type or per-entry
configuration required. The iterative parent walk (one query per level) is Phase 1;
a recursive CTE replaces it if deep trees become common.

**FAQPage deferred.**
`FAQPage` requires `mainEntity` — a repeating array of structured `Question`/`Answer`
objects. Flat field mapping cannot produce this shape. Deferred until a
`RepeaterField` type exists whose `schemaValue()` can emit the structured array.

**Twig rendering only; no REST API output.**
Schema.org JSON-LD is a browser/crawler concern. Adding it to `EntryResource` would
bloat the REST API with data that API consumers have no use for. A single Twig
function `schema_json(entry)` is the only rendering surface.

### Multilingual — 2026-05-16

**Context.** Designing a multilingual layer for the four Fieldable models (Entry,
Category, Media, User) from scratch. No existing i18n infrastructure beyond
Laravel's stock `lang/en/` admin UI strings.

**Translation gate lives on the container, not on the record or on EntryTree.**
An initial proposal tied translation to EntryTree presence — "only routable
entries are translatable." This was revised when we noticed Categories and Media
plainly need translation (category names, image alt text) and have no tree
concept, so a single "tree-gates-translation" rule cannot cover all three
Fieldable container models. The cleaner rule is that the container's
`is_translatable` flag controls whether translation data exists;
`EntryType.has_entry_tree` independently controls whether the entry gets a
public URL per locale. `entry_groups.is_translatable`,
`category_groups.is_translatable`, and `media_libraries.is_translatable` form
one consistent pattern across the CMS.

**Default-locale storage stays on the canonical row.**
An early shape moved every locale (including the default) into the
`*_translations` tables, treating the canonical columns as legacy. This was
revised. Keeping `entries.title`/`handle` (and equivalents on `categories`,
`media`) as the default-locale storage means existing `findByHandle()` lookups
keep working, admin list views render without joining a translation table, and a
freshly-seeded single-locale install has zero rows in the translation tables.
The translation tables are an extension, not a replacement.

**Content varies per locale; metadata does not.**
`status`, `published_at`, `authors`, `categories`, `parent_id` stay global on
the canonical row. The alternative — a sibling-row-per-locale model (Craft CMS
style) — would give per-locale publishing schedules and authorship but at the
cost of duplicating metadata across translations. Content-only translation is
simpler, matches how editorial teams typically work, and leaves room for an
`entry_locale_metadata` table later if a real workflow demands it.

**Read-time fallback at per-field-value granularity, not per-record.**
If a request asks for French and a given field's French row is empty, the
system transparently reads the default-locale row for that single field.
Granularity is per field value — a half-translated entry shows its translated
fields in French and its untranslated fields in English on the same page. The
alternative (per-record fallback: no French record → render all English) would
force editors to choose between "complete French translation" and "no French
at all" with nothing in between.

**StructuredRows defaults to not translatable in v1.**
The StructuredRows field type stores JSON whose columns may be a mix of text,
numbers, dates, and selects. A single `is_translatable` flag is all-or-nothing
— translating the whole row duplicates non-text columns unnecessarily; leaving
it off leaves the text columns unaddressed. Per-column translatability is the
right answer but adds settings UI and storage-merge logic. v1 ships with the
flag locked to false; per-column translatability is v2 work.

**Three latent bugs surfaced and bundled into the rollout.**
Planning exposed three bugs that the multilingual work must fix in lockstep:
(1) `FieldValueObserver::syncMediables` prunes `mediables` rows scoped only by
`(fieldable_type, fieldable_id, field_id)` and would orphan media still
referenced by other locales — fixed by computing the union of media IDs across
all locales before pruning; (2) `EntryService::syncTreeNode` and `treeBuildUri`
read `$entry->handle` directly and would overwrite the wrong locale's tree row
on save — fixed by making tree sync locale-aware and refusing translation if a
parent has no row in the target locale; (3) `Rule::unique('entries', 'handle')`
and `unique('entry_trees', 'uri')` in `StoreEntryRequest`/`EditEntryRequest`/
`EditCategoryRequest` are locale-blind — fixed by scoping existing requests to
default-locale rows only and adding new `*TranslationRequest` classes that
validate against the translation tables.

**EntryType-level translation gate considered and rejected.**
A finer-grained alternative — `EntryType.is_translatable` so different types
within the same group could opt in or out — was discussed. Rejected for v1
because (a) the parallel-container pattern across EntryGroup, CategoryGroup,
and MediaLibrary is cleaner and (b) most editorial teams configure translation
at the editorial-bucket level, not the schema-type level. The flag can be
pushed down to EntryType in a future release if a concrete need emerges; the
data model would not need to change to support that.
