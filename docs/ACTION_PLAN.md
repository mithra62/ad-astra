# Pending Plans — Triage & Action Plan

> Triage of `OVERVIEW.md`, `media-refactor-plan.md`, `SEARCH_PLAN_V2.md`, `SHOP_PLAN.md`, `TenantPlan.md`, `SEO_SCHEMA_PLAN.md`, and `DISCUSSION_LAYER_PLAN.md`. The recommendation below is an ordering with rationale, not a re-plan — each plan stands on its own; this file decides which one to start.

---

## 2026-05-12 Status Update

The native Media and Media Library layer is complete and in testing. Treat the former Media refactor as landed. The remaining media-specific planning item is `media-status-implementation-plan.md`, which adds optional status governance on top of the completed native layer. See `MEDIA_LAYER_OVERVIEW.md` for how the implemented layer operates.

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
| `SEO_SCHEMA_PLAN.md` | Feature — schema.org JSON-LD generation from the Entry layer; per-entry `schema_type`; field-to-schema-property mapping at the `FieldLayoutTabElement` level; `BreadcrumbList` from `EntryTree`; Twig rendering via `schema_json(entry)`. 9 delivery phases. | Plan. Not started. No blocking dependencies on other plans. |
| `DISCUSSION_LAYER_PLAN.md` | Feature — discussion/commenting layer on the Entry layer. Scope and phases TBD. | Plan. Not yet written. Registered to hold its place in the sequence. |

`OVERVIEW.md` is the **only** one that's not a plan. Everything in it that needs work is in *Known Gaps and Implementation Status* — eight small items that mostly affect the API layer and a few config flags that aren't being read.

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
3. Search V2              (~2-3 weeks)
4. SEO Schema             (~2-3 weeks)
5. Discussion layer       (~TBD; DISCUSSION_LAYER_PLAN.md not yet written)
6. Shop                   (~14-16 weeks; Phase 11 collapses because tenancy is already real)
```

### Step 0 — OVERVIEW Known Gaps (warm-up, ~3-5 days)

Knock these out first. They're small, they're isolated, and getting them green builds momentum before the bigger work starts. Per the OVERVIEW *Known Gaps* section:

- Fix `EntryResource` — currently has user-shaped fields (`name`, `email`); should expose `title`, `handle`, status, type, group, fields.
- Fix permission name in `Api\v1\User` — checks `read users`, the seeded permission is `view user`.
- Implement `Api\v1\Account@show` — currently returns a placeholder; should return the authenticated user resource.
- Build out the `entries` API endpoints beyond `show()`.
- Enforce `EntryType.max_depth` and `EntryType.allowed_parent_types` in the Entry Tree service.
- Implement `app:refresh-tokens` (currently a scaffold).
- Wire `site.templates.base_path` and `site.templates.not_found_template` into the route drivers, or remove them from config.
- Media is now `Fieldable` by default. Treat custom media fields as part of the current native layer.

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

### Step 3 — Search V2 (~2-3 weeks)

**Why third.** With Media native and Tenancy live, Search V2 is straightforward:

- `field_layout_tab_elements` already has `tenant_id` → search settings are scoped per tenant by inheritance.
- `search_index` adds `tenant_id` from day one — no retrofit.
- Media has a `field_layout_id` → adding Media to search is trait + 5 methods, exactly as Search Plan §12 describes.
- All the `Searchable` dispatch points (`EntryRepository`, `UserService`, etc.) are tenant-aware already, so reindex jobs run in the right scope.

The plan's §11 delivery phases (1: Migrations → 2: Trait + model implementations → 3: Indexer + jobs → 4: Dispatch wiring → 5: Query builder → 6: Collections → 7: Admin UI) are clean. Run them in order.

**Optional during this step:** apply the `Searchable` trait to `Media` while you're in there — Media has a field layout now, and Search Plan §12 says this is a one-shot trait + implementation job. Cheap to do here, expensive to retrofit later.

### Step 4 — SEO Schema (~2-3 weeks)

**Why fourth.** With Search V2 in place, the `resolveLayoutElements` primitive (SEO
Phase 2) may already exist — Search V2 needs the same method. Schema is entirely
additive and isolated; landing it before the Discussion layer and Shop keeps the
content foundation complete before the heavier feature work begins.

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

### Step 5 — Discussion Layer (~TBD)

Plan not yet written (`DISCUSSION_LAYER_PLAN.md`). Registered here to hold its
place in the sequence. Sits after SEO Schema and before Shop because:

- The Entry layer is fully stable by this point — Media, Tenancy, Search, and SEO
  have all landed. The Discussion layer will almost certainly attach to entries and
  users; having those foundations settled first avoids retrofitting.
- Shop brings its own substantial data model and a long timeline. Landing Discussions
  first keeps the two feature namespaces separate and independently deployable.

Scope, phases, and duration to be determined when `DISCUSSION_LAYER_PLAN.md` is
written. Do not start this step until the plan exists.

### Step 6 — Shop (~14-16 weeks)

**Why last.** By the time Shop starts:

- Tenancy is real → Phase 11 collapses to "add `tenant_id` columns to new tables
  and add the trait" instead of "build a stub and migrate later." That alone is
  several days saved.
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

- Don't start two of (Media, Tenancy, Search) at the same time. They overlap on the same tables (`field_layout_tab_elements`, `media_libraries`, the morph pivots) and on the `BelongsToTenant` rollout.

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
- **TenantPlan.md** → multi-tenant foundation. Do **second** so Search, SEO, Discussions, and Shop are born tenant-aware.
- **SEARCH_PLAN_V2.md** → keyword search via index-time weighting. Do **third**; tack Media on while you're there.
- **SEO_SCHEMA_PLAN.md** → schema.org JSON-LD from the Entry layer. Do **fourth**; additive and isolated, completes the content foundation before feature work begins.
- **DISCUSSION_LAYER_PLAN.md** → discussion/commenting layer. Do **fifth**; plan not yet written — must exist before this step starts.
- **SHOP_PLAN.md** → e-commerce module. Do **last**; Phase 11 (tenancy integration) collapses to nothing because tenancy is already real.

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
