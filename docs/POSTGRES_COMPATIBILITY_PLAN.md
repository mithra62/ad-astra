# Plan: Postgres Compatibility for laravel-base

## Context

The project currently targets MySQL in production and SQLite in tests. The codebase is, in fact, already well-positioned for Postgres: a Postgres connection block exists at [config/database.php:86-99](../config/database.php), the `docker/8.2/Dockerfile` installs `php8.2-pgsql`, and a `docker/pgsql/create-testing-database.sql` script is present — clear evidence that Postgres support was intended but never finished or verified. The audit (see "Audit Findings" below) confirms there are no architectural blockers; the codebase is largely portable because it uses Laravel's query builder and Eloquent casts consistently, with no FULLTEXT indexes, no stored procedures, no raw JSON path queries, and almost no engine-specific raw SQL.

The goal of this plan is to make Postgres a **first-class, CI-verified** supported engine alongside MySQL — without per-engine code branching in app/services — so that operators can pick MySQL or Postgres at deploy time via `DB_CONNECTION`.

The work has three workstreams:
1. **Fix case-insensitivity assumptions** (biggest item; ~50+ lookup sites)
2. **Fix three small schema/query incompatibilities**
3. **Add a Postgres CI lane** so this stays working

---

## Workstream 1: Case-insensitivity via lowercase-on-write

**The problem.** MySQL's default `utf8mb4_unicode_ci` collation makes `WHERE handle = ?` case-insensitive; Postgres is case-sensitive. ~50+ queries assume case-insensitive matching on `handle`, `slug`, `uri`, `status_handle`, and `email` — the most critical being [FortifyServiceProvider.php:60](../app/Providers/FortifyServiceProvider.php) which would let a user register as `John@example.com` and then fail to log in as `john@example.com` on Postgres.

**The approach.** Normalize these identifier-shaped values to lowercase at write time via Eloquent attribute mutators (and at the auth boundary), so all stored values are already lowercased. This means every existing `->where('handle', $value)` keeps working unchanged — provided callers also lowercase `$value` before the lookup, which we handle by lowercasing at the entry points (HTTP requests, services, Fortify).

This is engine-agnostic, has a single mental model ("these columns are stored lowercase"), and requires a one-time data normalization migration to bring existing rows into compliance.

### 1a. Add mutators to identifier columns

Add a `casts()` entry or `set{Column}Attribute()` mutator on each model that owns one of these columns. Inventory to confirm via grep before implementation, but the known set includes:

| Model | Columns to lowercase |
|---|---|
| `app/Models/User.php` | `email` |
| `app/Models/Entry.php` | `handle`, `status_handle` |
| `app/Models/EntryGroup.php` | `handle` |
| `app/Models/EntryType.php` | `handle` |
| `app/Models/Field.php` | `handle` |
| `app/Models/Status.php` | `handle` |
| `app/Models/Category.php` | `handle` |
| `app/Models/Category/Group.php` | `handle` |
| `app/Models/EntryTree.php` | `handle`, `uri` |
| `app/Models/Media/Library.php` | `handle` |
| `app/Models/FieldType.php` | `handle` (if present) |
| `app/Models/StatusGroup.php` | `handle` |

Before implementing, run `Grep -rn "'handle'" app/Models` and `Grep "string('handle')" database/migrations` to confirm the complete set; do not rely on this table alone.

Pattern (use `Attribute` casts, Laravel 11+ style):

```php
protected function handle(): Attribute
{
    return Attribute::set(fn ($v) => $v === null ? null : Str::lower($v));
}
```

### 1b. Lowercase at the auth boundary

[FortifyServiceProvider.php:60](../app/Providers/FortifyServiceProvider.php) — wrap the email lookup in `Str::lower($request->email)`. The rate-limiter key at line 82 already does this; the auth lookup must too. Also confirm `fortify.lowercase_usernames` (config/fortify.php:63) is honored by Fortify's own login flow on this version.

### 1c. One-time data normalization migration

Write a single migration that lowercases existing rows for every column in the table above. Use raw `UPDATE` with `LOWER()` — portable across MySQL, Postgres, SQLite. Wrap in a transaction. Include a `down()` that no-ops (lossy operation, intentional).

### 1d. Add a validation rule / guard

Add a small CI check (or a phpstan rule, if time permits) that scans for new `string('handle')` / `string('slug')` / `string('uri')` migration calls without a corresponding mutator — to prevent regression. **Stretch goal** — skippable on the first pass.

---

## Workstream 2: Schema and query fixes

### 2a. Fix the `enum` column in entry_authors

[entry_authors migration:20](../database/migrations/2026_04_18_000010_create_entry_authors_table.php) uses `$table->enum('status', ['active', 'pending', 'disabled'])`. Laravel emits `CREATE TYPE` on Postgres, but altering enum values later is painful. Replace with a string + CHECK constraint:

```php
$table->string('status', 16)->default('pending');
// Then via DB::statement for the CHECK — portable enough since both engines support it
```

Since this migration is dated 2026-04-18 (recent) and may not be deployed in long-lived environments yet, **prefer modifying the migration in place** over writing a new alter migration. Confirm with the user before doing so if any production environment has already run it.

### 2b. Fix the LIKE ESCAPE in MediaPicker

[MediaPicker.php:66-67](../app/Http/Controllers/Admin/MediaPicker.php) uses `whereRaw("name LIKE ? ESCAPE '\\'", [$like])` with backslash escapes. On Postgres with `standard_conforming_strings = on` (the modern default), backslash isn't a string escape, so the escape semantics differ.

Rewrite to use a non-backslash escape character (universally portable):

```php
$like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q) . '%';
$query->where(function ($w) use ($like) {
    $w->whereRaw("LOWER(name) LIKE LOWER(?) ESCAPE '!'", [$like])
      ->orWhereRaw("LOWER(original_name) LIKE LOWER(?) ESCAPE '!'", [$like]);
});
```

The `LOWER()` on both sides gives MySQL-style case-insensitive matching on Postgres for this user-facing search. (Postgres `ILIKE` would be cleaner but isn't supported on MySQL.)

### 2c. Verify the two `groupBy` queries

[Api.php:18](../app/Rest/Api.php) and [Admin/Dashboard.php:37-46](../app/Http/Controllers/Admin/Dashboard.php) use `groupBy()`. Postgres is strict — every non-aggregated selected column must appear in `GROUP BY`. Read these two and confirm/fix as needed. Likely a 5-minute check.

---

## Workstream 3: Postgres CI lane

The project currently has **no `.github/workflows/` directory** and no automated CI. To prevent Postgres regressions, add CI that exercises both engines.

### 3a. Add a `testing_pgsql` connection

Add a new connection block in [config/database.php](../config/database.php) alongside the existing `testing` block, driving from `DB_*` env vars with `pgsql` defaults. The phpunit.xml override and CI workflow will point to it.

### 3b. Add GitHub Actions workflow

Create `.github/workflows/tests.yml` with a matrix job:
- `db: [mysql, pgsql, sqlite]`
- Services block spins up MySQL 8 / Postgres 16 as appropriate
- Steps: composer install, `php artisan migrate --env=testing`, `php artisan test`
- Reuse `docker/pgsql/create-testing-database.sql` for DB bootstrap if convenient

### 3c. Document supported engines

Update [README.md:10](../README.md) to list Postgres as supported, and add a one-paragraph note to CLAUDE.md under a new "Database Engines" section explaining the lowercase-on-write invariant so future contributors don't add naive `where('handle', $userInput)` calls.

---

## Out of scope (deliberate)

- **SQL Server support** — separate effort. SQL Server's JSON support is weaker and reserved-word landmines are worse; warrants its own audit.
- **Search (planned `search_index` table)** — covered by `SEARCH_PLAN_V2.md`. That plan must pick a portable strategy (likely app-level tokenization, not engine-native full-text), but it's not blocking Postgres support.
- **MediaPicker search improvements** — the LIKE rewrite above is a portability fix, not a feature change. Real search ergonomics belong in the search work.
- **CITEXT / ICU collations** — considered and rejected in favor of lowercase-on-write (engine-agnostic, simpler).

---

## Critical files

- [config/database.php](../config/database.php) — Postgres connection already present; needs `testing_pgsql` added
- [app/Providers/FortifyServiceProvider.php](../app/Providers/FortifyServiceProvider.php) — auth lookup
- [app/Models/User.php](../app/Models/User.php) and the ~12 models listed in Workstream 1a — add mutators
- [database/migrations/2026_04_18_000010_create_entry_authors_table.php](../database/migrations/2026_04_18_000010_create_entry_authors_table.php) — enum fix
- [app/Http/Controllers/Admin/MediaPicker.php](../app/Http/Controllers/Admin/MediaPicker.php) — LIKE rewrite
- [app/Rest/Api.php](../app/Rest/Api.php) and [app/Http/Controllers/Admin/Dashboard.php](../app/Http/Controllers/Admin/Dashboard.php) — `groupBy` audit
- New: `database/migrations/<date>_normalize_identifier_casing.php`
- New: `.github/workflows/tests.yml`

---

## Verification

End-to-end checks to run after implementation:

1. **MySQL still works.** `composer test` against the existing testing connection should pass unchanged.
2. **Postgres works locally.** Create a local Postgres DB, set `DB_CONNECTION=pgsql` + creds, run `php artisan migrate:fresh --seed --env=local`, then exercise:
   - Log in to `/admin` with a seeded user (proves case-insensitive auth)
   - Log in again with the email in a different case (proves Workstream 1b)
   - Navigate to a seeded entry tree URI (proves URI lookups work)
   - Open `/admin/media` picker and search (proves Workstream 2b)
   - Create / edit an entry, attach media via FileUpload field (proves JSON casts and morph relations work)
3. **Postgres tests pass.** `DB_CONNECTION=testing_pgsql php artisan test` runs the full suite green.
4. **CI green on both engines.** Push a branch; both matrix jobs (mysql, pgsql) pass.
5. **Data normalization is idempotent.** Run the casing-normalization migration, then run it again (or via `migrate:fresh` after a seed). No errors, no double-lowercasing damage.

---

## Audit findings (reference)

Captured during planning, retained for context:

- **No raw SQL of consequence.** `DB::raw()` / `selectRaw` usages ([Dashboard.php:39-40](../app/Http/Controllers/Admin/Dashboard.php), [EntryService.php:107](../app/Services/EntryService.php), [Api.php:16](../app/Rest/Api.php), [Category.php:51](../app/Models/Category.php)) all use portable syntax.
- **No `whereJsonContains` / raw JSON path queries.** All JSON columns (`value_json`, `value_object`) round-trip through Eloquent `'array'` casts and are filtered in PHP, not SQL.
- **No FULLTEXT, no stored procedures, no triggers, no backtick identifiers in raw SQL.**
- **No reserved-word collisions** in table or column names against Postgres reserved-word list.
- **Boolean handling is clean** — all boolean columns use Eloquent's `'boolean'` cast; no raw `= 0` / `= 1` comparisons.
- **`sort_order` columns are non-nullable in practice**, so the MySQL-vs-Postgres NULLS FIRST/LAST difference is a non-issue.
- **Tests already run on SQLite** via `RefreshDatabase`, with no MySQL-specific tricks — a strong signal of engine-portability.
- **Framework-emitted `longText`/`mediumText`** in `sessions`, `cache`, `jobs`, `failed_jobs` migrations are mapped to `text` automatically by Laravel's Postgres grammar. Informational, not a bug.
