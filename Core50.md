# Core 50 — Implementation Action Plan

> **Author:** Generated analysis for Eric Lamb, 2026-05-03
> **Status:** Planning only — no code changed
> **Source documents:** `Product Vision Statement — Core 50.pdf`, `Core 50 Tucson - 2025 (1).pdf`, `ACTION_PLAN.md`, `TenantPlan.md`, `OVERVIEW.md`, `SHOP_PLAN.md`, and live codebase review.

---

## Executive Summary

Core 50 is a Referral Relationship Management (RRM) SaaS — account-based marketing distilled to a personal scale. It helps solopreneurs and salespeople maintain deliberate, high-value relationships with their ~50 most important connectors, replacing the spreadsheet + manual LinkedIn workflow shown in the Tucson reference sheet with an intelligent, automated system.

The `laravel-base` codebase is a genuinely strong foundation for this product. Its Entry/EntryType/Field/Status architecture maps cleanly to the Core 50 domain, the API layer is production-ready, and the queue system can support the async sync and AI jobs the product requires. However, three realities must be confronted head-on before a single Core 50 feature is written:

**First: Tenancy is not optional — it is the precondition.** Core 50 is inherently a per-user SaaS. Every contact list, every interaction log, every relationship score is private to the person it belongs to. The `TenantPlan.md` is already written and well-designed. It must be fully implemented before Core 50 data models land, or every table will have to be retrofitted with `tenant_id` under live production load. This is the single highest-priority item.

**Second: Core 50 is an application, not a CMS.** The `laravel-base` was architected as a headless CMS. Core 50 is a relationship intelligence tool. The Entry/Field system is flexible enough to serve as the data layer for contacts and interactions, but the product will require a purpose-built module — similar in structure to the planned `mithra72/Shop` — with its own services, domain models, and AI integration layer on top of the CMS infrastructure.

**Third: External integrations are the hardest part.** The vision calls for automatic sync with LinkedIn, email, calendar, phone, and SMS. LinkedIn's API is severely restricted for third-party apps. Email and calendar sync require OAuth and ongoing webhooks. These integrations carry more technical and regulatory risk than all the internal Laravel work combined, and should be treated as a separate engineering stream with explicit scope decisions made per channel.

The recommended implementation sequence is: complete **TenantPlan Steps 1–5** first, then build Core 50 as a standalone module (`mithra72/Core50/`) using the tenant-aware infrastructure. The MVP can be delivered in approximately 8–10 weeks after tenancy is stable. The full V3 predictive referral engine is a 6–12 month roadmap from a standing start.

---

## 1. Product Understanding

### What Core 50 Is

Core 50 is a **Referral Relationship Management (RRM)** system — a new category the product vision explicitly defines as distinct from CRM. Where CRMs track pipelines, tasks, and activity counts, Core 50 tracks *human relationship health* and prompts *meaningful interactions* with the specific people most likely to generate referrals.

The conceptual model is simple: most referrals come from a small set of relationships (Pareto Principle). Humans can only actively manage a limited number of relationships without technological help (Dunbar's Number). Therefore, the product manages that small set — approximately 50 people — with precision and intentionality.

### The Tucson Reference Sheet

The uploaded spreadsheet (`Core 50 Tucson - 2025`) is the manual version of this system — a grid of ~50 contacts with columns tracking LinkedIn activity, email, referrals given/received, and interaction frequency across time periods (weekly, bi-weekly, monthly). Color-coding and symbols indicate relationship health states. This is the exact UX analogue the MVP must replace with automation.

### Release Trajectory

The product vision defines three releases:

**MVP** — Replace the spreadsheet. Import a Core 50 list, auto-log interactions from LinkedIn and email, show "days since last interaction," surface daily engagement suggestions, basic relationship dashboard.

**V2** — Move from tracking to prompting. Multi-channel sync (SMS, calendar, phone), AI-suggested messages and comments, relationship health scores, reciprocity tracking (value given vs. value received), content matching (surface relevant articles to forward), warm-intro assistant.

**V3** — Predictive referral engine. Predict who will generate the next referral, referral pipeline attribution, identify emerging connectors, auto-segment the Core 50 list, AI resonance mapping for tone tuning per person, social graph intelligence across networks.

---

## 2. What the Existing Codebase Provides

### Strong Alignments

**The Entry/EntryType/Field system** is the most valuable asset. Contacts can be modeled as Entries in a `Core50Contacts` EntryGroup, with a `ContactEntryType` class that enforces business rules (e.g., maximum 50 active contacts per tenant, required LinkedIn URL). Interactions can be a second EntryGroup (`Core50Interactions`) with a `Relationship` field linking each interaction to a contact. The field system's polymorphic `field_values` storage covers every attribute the product needs — names, URLs, touchpoint frequency settings, relationship notes — without any schema changes.

**The Status system** maps directly to relationship health states. A `RelationshipHealthStatusGroup` with four statuses — `hot`, `warm`, `cooling`, `cold` — is precisely what the Tucson sheet implements manually. Status transitions can be triggered by the scoring engine as interaction frequency changes.

**The Queue/Job system** is production-ready and already used for background work. The sync jobs (LinkedIn polling, email scanning, calendar pull) and AI suggestion jobs all run naturally as queued jobs dispatched on a schedule. The existing `console.php` scheduler pattern already supports per-tenant job dispatch.

**The Settings system** is perfect for per-tenant and per-user Core 50 preferences: touchpoint frequency targets per contact, preferred channels, notification timing, Core 50 capacity cap (default 50, plan-limited). The four-tier resolution cascade (tenant user → tenant → system → config default) handles all of this without new infrastructure.

**The API layer** (Sanctum, `routes/api.php`, `LogRequestResponse` middleware) gives the REST API foundation that the mobile app or web frontend will consume. The existing `EntryResource` pattern extends naturally to `ContactResource` and `InteractionResource`.

**The User model** with roles and permissions (Spatie) supports the permission model Core 50 will need: a user can manage their own Core 50 list, an admin can see aggregate tenant analytics, a super-admin can view platform-wide data.

**`EntryQueryBuilder`** with its fluent `inGroup()`, `ofType()`, `withField()`, and `whereField()` methods means the query layer for "show me contacts I haven't touched in 30 days" or "sort by days since last interaction" is already built — it just needs to be composed correctly.

### Adequate but Extensible

**The Media system** (currently Spatie, planned for native refactor) will handle contact profile photos and any uploaded documents. The planned `media-refactor-plan.md` should complete before Core 50 media features land; building on Spatie now and then migrating is unnecessary overhead.

**The Twig/template layer** may not be the right rendering surface for Core 50's dashboard UI. A dedicated frontend (React, Inertia, or a decoupled SPA) consuming the API is a better fit for the real-time, interactive nature of the relationship dashboard. The Twig layer remains valuable for any public-facing pages (landing pages, onboarding).

### Not Present — Must Be Built

The following do not exist in the codebase and represent net-new engineering work:

- External OAuth integration layer (LinkedIn, Google/Gmail, Microsoft/Outlook, iCloud)
- LinkedIn API sync (polling for contact activity, job changes, posts, reactions)
- Email sync (IMAP/Gmail API/Microsoft Graph API scanning for interactions with Core 50 contacts)
- Calendar sync (Google Calendar, Outlook Calendar — meeting detection)
- AI inference layer (integration with an LLM API for message drafting, content matching, and signal detection)
- Relationship health scoring engine (algorithm computing health score from interaction frequency, reciprocity, channel diversity, recency)
- Notification/reminder system (daily digest emails, push notifications, in-app nudges)
- Referral outcome tracking and attribution
- "Warm intro" assistant workflow
- Content matching/surfacing engine (finding articles relevant to a specific contact)
- Social graph intelligence (V3 — understanding network topology across contacts)

---

## 3. The Mandatory First Step: Tenancy

Core 50 cannot be built correctly without tenancy in place. This is not a soft recommendation — it is a hard architectural constraint.

Every Core 50 data object is private to the user it belongs to. Contact lists cannot cross user boundaries. Interaction logs are personal data. AI suggestions are context-specific. If `tenant_id` is not on the data tables from day one, there are two paths: build without isolation (a prototype, not a product) or retrofit under live load (expensive and risky).

The `TenantPlan.md` is already fully designed. It covers the `BelongsToTenant` trait, `ResolveTenant` middleware, the three-step migration pattern, settings scoping, queue/job tenancy, and billing. The recommended sequence from `ACTION_PLAN.md` is:

```
Media Refactor → TenantPlan Steps 1–5 → Search V2 → SEO Schema → Shop
```

For Core 50 specifically, the media refactor and tenancy are the only prerequisites. Search V2, SEO Schema, and Shop are independent of Core 50 and can proceed in parallel on separate branches once tenancy is stable. The sequencing becomes:

```
Media Refactor → TenantPlan Steps 1–5 → Core 50 MVP
                                      ↓ (parallel, separate branch)
                               Search V2 → SEO Schema → Shop
```

One nuance worth noting: in a Core 50 context, the concept of "tenant" maps slightly differently than in a pure B2B SaaS. Each individual user is effectively their own tenant — their Core 50 list is personal, not shared across an organization. The tenant model should accommodate both a one-person-one-tenant scenario (solopreneur) and a team scenario (a sales team where a manager might see aggregate data). The `tenant_users` pivot with roles (owner, admin, member) already supports this.

---

## 4. Domain Model Design

Core 50 should be implemented as a dedicated Laravel module at `mithra72/Core50/`, following the same structural pattern as the planned `mithra72/Shop/`. This keeps it decoupled from the core CMS and independently deployable or extractable.

### Core Entities

**Contact** (`ContactEntryType` in a `core50_contacts` EntryGroup)

The 50 people in a user's referral network. Each contact is an Entry with:
- Standard Entry fields: `title` (full name), `handle` (slug), `status_id` (relationship health)
- Field values: `linkedin_url`, `email`, `phone`, `company`, `role`, `source_of_relationship`, `touchpoint_frequency` (weekly/biweekly/monthly), `notes`, `tags`
- A `ContactEntryType` class that enforces: maximum active contacts per tenant (configurable, default 50), required `linkedin_url` or `email` (at least one channel), graduation logic (moving a contact in or out of the Core 50)

**Interaction** (`InteractionEntryType` in a `core50_interactions` EntryGroup)

Each logged touchpoint with a contact. Fields:
- `contact_id` (Relationship field → Contact entry)
- `channel` (LinkedIn, Email, Calendar, Phone, SMS, In-Person, Other)
- `interaction_type` (Like, Comment, DM, Meeting, Referral Given, Referral Received, Intro Sent, Intro Received, Article Forwarded, Event Invite, Other)
- `direction` (Outbound, Inbound, Mutual)
- `occurred_at` (date)
- `notes`
- `auto_logged` (boolean — system vs. manual entry)
- `source_raw` (JSON — raw payload from the sync source, for debugging and AI training)

**Outcome** (`OutcomeEntryType` in a `core50_outcomes` EntryGroup)

Referral and pipeline results linked back to a contact:
- `contact_id` (Relationship field)
- `outcome_type` (Referral Lead, Intro, Meeting, Deal Closed)
- `value` (estimated or actual deal value, if known)
- `occurred_at`
- `notes`

**Suggestion** (dedicated `core50_suggestions` table — not an Entry)

AI-generated daily suggestions are ephemeral and high-volume — not a good fit for the Entry system. A dedicated table is cleaner:

```
id, tenant_id, user_id, contact_id, suggestion_type, channel, suggested_message,
context_summary, score, status (pending/acted/dismissed/snoozed), expires_at,
created_at, acted_at
```

**RelationshipHealthScore** (dedicated `core50_health_scores` table or computed on the fly)

A snapshot of each contact's health score, computed by the scoring engine:

```
id, tenant_id, contact_id, score (0–100), health_state (hot/warm/cooling/cold),
days_since_last_touch, interaction_count_30d, interaction_count_90d,
reciprocity_ratio (outbound vs. inbound), computed_at
```

### Service Layer

The module's service layer mirrors the existing CMS pattern:

- `ContactService` — wraps `EntryService` for Contact-specific operations, enforces the 50-contact limit, manages health status transitions
- `InteractionService` — creates and retrieves interactions, triggers health score recalculation on write
- `ScoringEngine` — pure computation class; takes a contact + interaction history, returns a health score and health state
- `SuggestionService` — reads the suggestion queue, applies dismissal/snooze logic, surfaces the daily shortlist
- `SyncService` — orchestrates external channel sync; delegates to per-channel adapters
- `OutcomeService` — logs referral outcomes, computes ROI per contact

### Facade Layer

Following the CMS convention, expose the module through facades:

| Facade | Backs |
|---|---|
| `Core50\Contacts` | `ContactService` |
| `Core50\Interactions` | `InteractionService` |
| `Core50\Suggestions` | `SuggestionService` |
| `Core50\Outcomes` | `OutcomeService` |

---

## 5. External Integrations — The Hard Part

This section deserves frank treatment because it is where the most optimistic product assumptions collide with the most constrained technical realities.

### LinkedIn

LinkedIn is the primary channel in the current manual workflow. However, LinkedIn's public API (Marketing Developer Platform) is intentionally restricted and does not permit third-party apps to read a user's feed, notifications, or connection activity in the way Core 50 requires. The available OAuth scopes for standard applications cover posting, profile reading, and messaging (UGC), but not "what did my connections post this week" or "who changed jobs."

**Options, in order of viability:**

1. **Manual + structured logging only (MVP).** The user logs interactions manually — Core 50 provides a fast-entry UI (one tap to log "commented on [contact]'s post"). This is what the Tucson spreadsheet does. It is not glamorous but it ships on schedule.

2. **Browser extension (V2).** A companion Chrome extension that detects when the user interacts with a Core 50 contact on LinkedIn and auto-logs it. This is legal under LinkedIn's ToS for personal data (the user's own actions) and has been done successfully by tools like Dux-Soup in limited scopes.

3. **LinkedIn Partner Program (V3).** If the product reaches scale, applying for LinkedIn's Marketing API partner program unlocks more data — but this is a months-long approval process, not a quick API key.

4. **Screen-scraping / automation (never).** Browser automation of LinkedIn actions violates their ToS and will result in account bans. This path is off the table regardless of how it is framed.

### Email (Gmail, Outlook)

Email sync is achievable and well-supported. Gmail API and Microsoft Graph API both offer OAuth 2.0 flows with scopes for reading message metadata and threads. The engineering work is real — OAuth flows, token refresh, webhook setup, deduplication of interactions, handling large mailboxes — but it is well-documented and standard. Plan 2–3 weeks for a solid email sync adapter per provider.

**Privacy consideration:** Email sync requires explicit user consent and a clear data handling policy. The system should store only metadata (sender, recipient, date, subject hash) and never full email bodies unless the user explicitly opts in to note-taking.

### Calendar

Google Calendar and Outlook Calendar APIs are similarly accessible. Meeting detection (a meeting with a Core 50 contact = an interaction log entry) is straightforward. This is lower complexity than email sync — calendars have simple, well-structured event objects.

### Phone / SMS

There is no general API for reading a user's phone call history or SMS without device-level integration (iOS/Android app). A mobile companion app that reads native call/SMS logs with explicit user permission is the only realistic path. This is V2 or V3 scope — plan for it, do not block on it.

---

## 6. AI Integration Layer

The V2 and V3 product features require AI inference: message drafting, content matching, signal detection, and (eventually) referral prediction. The codebase has no AI integration currently.

The recommended approach is a thin, provider-agnostic `AI` service layer within the module:

```
mithra72/Core50/AI/
├── AiService.php              # Orchestrator — routes tasks to the right provider
├── Providers/
│   ├── AbstractAiProvider.php
│   ├── AnthropicProvider.php  # Claude API — recommended primary
│   └── OpenAiProvider.php     # Fallback / comparison
├── Tasks/
│   ├── DraftMessage.php       # Given contact context, draft an outreach message
│   ├── SummarizeContact.php   # Summarize a contact's recent activity
│   ├── MatchContent.php       # Find articles relevant to a contact's interests
│   └── PredictEngagement.php  # V3 — score likelihood of referral
└── Contracts/
    └── AiProviderContract.php
```

Each task object receives the minimum context needed (contact profile, recent interactions, user preferences) and returns a structured response. Prompts are stored as Blade/Twig templates, not hardcoded strings, so they can be iterated without code deploys.

**Cost management** is a real concern for AI features in a SaaS context. Use the `tenant_usage` table (already designed in `TenantPlan.md`) to meter AI calls — add `ai_calls_monthly` as a metric alongside `entries`, `users`, and `storage_bytes`. Cap AI calls per plan tier. Use lighter models (Haiku) for classification and health assessment; reserve heavier models (Sonnet/Opus) for message drafting and resonance mapping.

---

## 7. What Would Need to Change in the Existing Codebase

The following changes to `laravel-base` core are required or strongly recommended. None are large — Core 50 is intended to be additive, not transformative, to the base.

**`AppServiceProvider` morphmap additions.** Add `core50_contact`, `core50_interaction`, and `core50_outcome` morph aliases when the module registers its Entry types. Follows the existing pattern exactly.

**`EntryTypeRegistry` registration.** `ContactEntryType`, `InteractionEntryType`, and `OutcomeEntryType` are registered in the DB during module provisioning (same pattern as the existing seeded Entry types).

**Settings domains.** Add a `core50` settings domain in `config/settings.php` with keys for: `max_contacts` (default 50), `default_touchpoint_frequency`, `enable_email_sync`, `enable_calendar_sync`, `enable_ai_suggestions`, `daily_suggestion_count` (default 5), `health_score_thresholds`.

**Queue channels.** Add `core50-sync` and `core50-ai` queue channels in `config/queue.php` so long-running sync jobs cannot starve CMS content jobs.

**API route group.** Core 50 API routes live under `/api/v1/core50/` — no changes to existing API structure, new route groups registered from the module's service provider.

**OVERVIEW.md known gaps.** Resolve the eight known gaps before Core 50 lands. `EntryResource` exposing wrong fields and the permission name mismatch in `Api\v1\User` will affect the Core 50 API if left in place.

---

## 8. Major Risks and Problem Areas

### Risk 1 — Tenancy Retrofit (Critical)
If Core 50 data models are built before `TenantPlan.md` is implemented, every table will need the three-step nullable → backfill → `NOT NULL` migration treatment under live data. Do not start Core 50 data models before TenantPlan Steps 1–3 are merged.

### Risk 2 — The LinkedIn Wall (High)
The product vision's primary channel has the most API restrictions. If the MVP launches with manual LinkedIn logging only, that is acceptable and honest. If "auto-log interactions from LinkedIn" is marketed as a launch feature without a working implementation, it creates a trust deficit with early users. Decide explicitly what LinkedIn support looks like at MVP before writing a line of code for it.

### Risk 3 — Privacy and Data Handling (High)
Core 50 stores personal data about third parties (the contacts) who have not consented to be in the system. This is legally similar to a CRM. GDPR, CCPA, and PIPEDA all have clear requirements: what data is stored about contacts, how long it is retained, whether contacts can request deletion, and how it is secured. This must be resolved before any paid tier launches.

### Risk 4 — Entry System Impedance Mismatch (Medium)
The Entry/EntryType system was designed for content objects, not people records. Using it for contacts is technically valid but adds abstraction overhead — every contact query routes through `EntryQueryBuilder`, every update through `EntryService`. Consider whether a purpose-built `Contact` Eloquent model (alongside, not inside, the Entry system) would be cleaner, with the Entry system reserved for interactions and outcomes. This is an early architectural decision; changing it mid-build is expensive.

### Risk 5 — AI Cost at Scale (Medium)
50 contacts × N users × daily suggestion generation scales inference costs quickly. Batch where possible, cache results where the answer doesn't change hourly (a daily contact summary doesn't need regeneration every 15 minutes), and enforce hard per-tenant metering limits from day one.

### Risk 6 — The 50-Contact Rule (Low, but watch it)
The system must enforce a maximum active contact count per user. This is a `ContactEntryType::validate()` check — straightforward. But the UX around graduation (who enters and leaves the Core 50 list) needs intentional design: a contact that graduates out should have their history preserved, not deleted.

---

## 9. Implementation Phases

### Pre-Phase: Prerequisites (~14–18 weeks from current state)

These must complete before Core 50 work begins:

1. **OVERVIEW.md known gaps** (~3–5 days) — Fix `EntryResource`, permission name mismatch, `Account@show`, and the two unread config keys.
2. **Media Refactor** (~3–5 weeks) — Replace Spatie MediaLibrary. Contact profile photos need the stable native media API.
3. **TenantPlan Steps 1–5** (~6–8 weeks) — The full multi-tenant foundation. Every Core 50 model is born tenant-aware.

### Phase 1 — Core 50 MVP (~8–10 weeks after prerequisites)

**Goal:** Replace the Tucson spreadsheet.

- `mithra72/Core50/` module scaffold (service provider, facades, route registration)
- `core50_contacts` EntryGroup and `ContactEntryType` (50-contact cap, validation)
- `core50_interactions` EntryGroup and `InteractionEntryType`
- `core50_outcomes` EntryGroup and `OutcomeEntryType`
- `core50_health_scores` table and `ScoringEngine` (algorithm: days since last touch, interaction counts over 30/90 days, reciprocity ratio)
- `core50_suggestions` table and basic suggestion generation (rule-based, not AI: "you haven't touched [contact] in 28 days")
- Relationship health status group (Hot / Warm / Cooling / Cold) with automated status transitions
- Admin UI: contact list, contact detail with interaction timeline, basic relationship dashboard
- API endpoints: contacts CRUD, interaction logging, dashboard summary, suggestions list
- CSV import from the existing Tucson spreadsheet format
- Weekly digest email via the existing queue/mail stack
- Tenant provisioning hook: Core 50 module seeded automatically during onboarding

### Phase 2 — Multi-Channel Sync and AI Co-Pilot (~8–12 weeks)

**Goal:** The system behaves like a referral-ops co-pilot.

- Gmail API sync adapter (OAuth flow, message metadata scan, interaction auto-logging)
- Microsoft Graph / Outlook sync adapter
- Google Calendar sync adapter (meeting detection and auto-logging)
- `core50-sync` background sync jobs, scheduled per-tenant
- AI integration layer (`mithra72/Core50/AI/`) with Claude as primary provider
- `DraftMessage`, `SummarizeContact`, and `MatchContent` AI tasks
- Improved scoring engine incorporating multi-channel data
- Reciprocity tracking (value given vs. received, per channel)
- Warm-intro assistant workflow (user triggers an intro request; system drafts the introduction email)
- Per-tenant AI usage metering against plan limits
- Web push notifications

### Phase 3 — Predictive Referral Engine (~12–16 weeks)

**Goal:** Core 50 transitions from relationship management to referral prediction.

- Referral attribution model (link closed deals back to the contact chain that originated them)
- Predictive scoring: which contacts are trending toward a referral
- "Emerging connector" detection: contacts outside the Core 50 who should graduate in
- Automatic Core 50 graduation recommendations (data-driven)
- AI resonance mapping: learn each contact's preferred tone, channel, and response patterns
- Social graph intelligence: second-degree connections across the Core 50
- Mobile app foundations (React Native or Flutter consuming the existing API)
- LinkedIn browser extension (auto-log LinkedIn interactions for the authenticated user)
- Team/organization view (aggregate Core 50 health across a sales team)
- Advanced analytics: referral ROI, relationship equity trends, pipeline impact per contact

---

## 10. Recommended Immediate Action Plan

| Step | Work | Duration | Dependency |
|---|---|---|---|
| 0 | OVERVIEW.md known gaps | ~3–5 days | None — do this week |
| 1 | Media Refactor (`media-refactor-plan.md`) | ~3–5 weeks | Step 0 done |
| 2 | TenantPlan Steps 1–5 (`TenantPlan.md`) | ~6–8 weeks | Step 1 merged |
| 3A | Core 50 MVP module | ~8–10 weeks | Step 2 (Steps 1–3) merged |
| 3B | Search V2 + SEO Schema (parallel track) | ~4–6 weeks | Step 2 merged |
| 4 | Core 50 V2 (multi-channel sync + AI) | ~8–12 weeks | Step 3A live + validated |
| 5 | TenantPlan Step 6 (Billing) + Shop | per `ACTION_PLAN.md` | Step 2 done |
| 6 | Core 50 V3 (predictive engine) | ~12–16 weeks | Step 4 live + data |

---

## 11. Open Questions Requiring Decisions Before Building

1. **Contact data model: Entry-based or purpose-built?** The Entry/EntryType path is flexible but CMS-centric. A purpose-built `Contact` Eloquent model may be cleaner for a people-centric product. This decision affects every downstream service, API, and query — make it early.

2. **LinkedIn at MVP: manual only or browser extension?** Decide what "LinkedIn integration" means at launch before it appears in marketing copy.

3. **One-user-one-tenant or team model?** For a solopreneur product, one-user-one-tenant simplifies the permission model significantly. For a sales team product, the full roles model (owner, admin, member) is needed from day one. Which is the primary MVP persona?

4. **Contact privacy posture.** Define the data retention policy, deletion workflow, and legal basis for storage before any personal data is written to the database.

5. **AI provider and model selection.** Claude (Anthropic API) is the recommended primary provider. Confirm budget and rate limits before designing the AI task pipeline. Haiku for classification; Sonnet/Opus for message drafting.

6. **Pricing model.** Core 50's plan limits (max contacts, AI calls per month, number of sync channels) need to be defined before the `plans` table is seeded in TenantPlan Step 6.

---

*This document is a planning artifact only. No code was changed. See `ACTION_PLAN.md` for the master ordering of all pending plans, and `TenantPlan.md` for the detailed multi-tenancy implementation guide.*
