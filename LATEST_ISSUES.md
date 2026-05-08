# LATEST_ISSUES.md

Codebase audit performed 2026-05-07 against `D:\Projects\mithra62\laravel-base\develop`.

The findings below are grouped by severity. Severity reflects user-visible / data-integrity impact, not the size of the fix. File paths are workspace-relative; line numbers refer to the version inspected.

---

## 1. Critical / Data-Integrity Issues

### [RESOLVED] 1.1 OAuth callback prints "broken" and dies on `InvalidStateException`
**File:** `app/Http/Controllers/Login.php` (lines 22–25)

```php
} catch (InvalidStateException $e) {
    echo "broken";
    exit;
}
```

A legitimate user whose OAuth state token expires or is replayed will see a literal `broken` page in their browser. The session is never cleaned up and there is no telemetry. Replace with a redirect back to `route('login')` carrying a translatable error and log the exception.

### 1.2 `Api\v1\Account` controller is entirely stubbed
**File:** `app/Http/Controllers/Api/v1/Account.php`

Every method (`update`, `updatePassword`, `show`, `updateAvatar`, `updateEmail`) just returns a hard-coded JSON success message; `show()` even returns the message *"Profile updated successfully"*. The route `GET /api/v1/account` is wired in `routes/api.php` and advertised in the Swagger docs, so callers receive HTTP 200 with bogus content while making no actual changes. Either remove the routes/Swagger annotations or implement the methods against `UserService`.

### 1.3 [RESOLVED] `api_logs` table is missing the `response_payload` column referenced by the middleware
**Files:** `app/Http/Middleware/LogRequestResponse.php` (line 69), `app/Models/ApiLog.php`, `database/migrations/2025_11_07_174041_create_api_log_table.php`

`LogRequestResponse::handle()` calls `ApiLog::create([... 'response_payload' => …])`, but:
* `ApiLog::$fillable` does not include `response_payload`, so the value is silently dropped before the INSERT.
* The migration never created the column at all, so even removing the fillable filter would throw `SQLSTATE[42S22]` once a real DB enforces strict columns (MySQL strict mode, Postgres).

Add `$table->longText('response_payload')->nullable();` in a follow-up migration and add the column to fillable/casts.

### 1.4 [RESOLVED] `api_logs` migration `down()` drops the wrong table
**File:** `database/migrations/2025_11_07_174041_create_api_log_table.php`

```php
public function down(): void {
    Schema::dropIfExists('api_log'); // table created above is `api_logs`
}
```

Rolling back this migration is a no-op. Fix to `dropIfExists('api_logs')`.

### 1.5 [RESOLVED] `EntryRepository::applyData()` is not transactional
**File:** `app/Repositories/EntryRepository.php` (lines 285–317)

`create()` is wrapped in `DB::transaction(...)`, but `applyData()` (the update path) writes core attributes, then status, then authors, then categories, then field values, then runs `afterUpdate`, all without a transaction. A failure during `applyFieldValues()` leaves the entry partially updated (e.g., new title and authors saved but new field values rolled forward only halfway). Wrap `applyData()` in `DB::transaction(...)` symmetric to `create()`.

The same risk extends to `EntryService::update()` which separately calls `syncTreeNode()` after `applyData()` returns; the tree sync should also live inside the transaction.

### 1.6 [RESOLVED] `Status::observe()` registered twice
**File:** `app/Providers/AppServiceProvider.php` (lines 92 and 116)

```php
Status::observe(StatusObserver::class);   // line 92
…
Status::observe(StatusObserver::class);   // line 116 — duplicate
```

Every status `updating` event fires the cascading `Entry::where(...)->update(...)` twice. With small datasets this is just wasted I/O, but on large tables the duplicate UPDATE doubles the lock window and chews queue/replication budget. Delete the second registration.

### 1.7 [RESOLVED] Hard-coded super-admin in `UsersSeeder`
**File:** `database/seeders/UsersSeeder.php`

`UsersSeeder::run()` always creates `eric@mithra62.com` with password `password` and the `super admin` role. If `db:seed` ever runs against production (CI mistake, deployment hook, fresh-install script) you ship a known admin credential. Drive the email/password from `.env` or a config helper, refuse to seed in `production`, and rotate the default password.

### 1.8 Mass-assignment of `value="foo"` in BotBlock honeypot, and middleware never registered
**Files:** `app/Http/Middleware/BotBlockRequest.php`, `app/Providers/BotBlockServiceProvider.php`, `resources/views/_inc/_bb.twig`

* `BotBlockRequest` is not appended in `bootstrap/app.php` or any route group, so the middleware never runs. Forms using the partial therefore have no real bot protection.
* The honeypot input is `<input type="text" id="__bb" name="__bb" value="foo"/>` — visible by default until the script swaps it. Should be `type="hidden"` (or a CSS-hidden field) and start with a random value.
* `BbValue::where(['field_value' => …])->first()` then `->delete()` is racy across concurrent submissions; if you intend to keep this layer, replace with an atomic `where(...)->delete()` returning the affected row count.

---

## 2. High Severity

### 2.1 `TemplateController` is dead but still present
**File:** `app/Http/Controllers/TemplateController.php`

No route or service references this controller (confirmed via `grep -rn TemplateController`). The site routing flow was migrated to `SiteController` + `SiteRouter` + `RouteDrivers`. The legacy file still contains:

* `View::replaceNamespace('admin', []);` in the constructor — destroys the admin view namespace if it ever runs.
* `print_r($tail); exit;` debug code in `renderWithTail()` (which is unreachable because the function returns on its first line).

Delete `TemplateController.php` and prune the autoload classmap. The same `View::replaceNamespace('admin', [])` line lives in `app/Services/SiteRouting/RouteDrivers/TemplateRouteDriver.php` constructor — verify it is intentional there too; nuking the admin namespace because the public template driver was instantiated is surprising behaviour.

### 2.2 `Admin\Playground` controller and its view are orphans
**Files:** `app/Http/Controllers/Admin/Playground.php`, missing view `playground.index`

Not referenced by any route. Returns `view('playground.index')` which does not exist anywhere under `resources/views`. Delete.

### 2.3 Public template scaffolding directories are abandoned
**Path:** `resources/templates/tailwind/`, `resources/templates/tailwind2/`, `resources/templates/tailwind-updates/`

Three parallel design iterations (one named `tailwind-updates` and one numbered `tailwind2`) live under the templates namespace alongside the active `site/`, `entries/`, and `about/` folders. They are accessible via the Twig view loader and could be hit accidentally by `templates::tailwind2.create-article`. None of the templates in these directories are referenced from PHP or other Twig files. Move them out of `resources/templates/` (e.g. into `docs/design-history/`) or delete.

### 2.4 Default site templates are placeholder strings
**Files:** `resources/templates/site/index.twig`, `resources/templates/entries/content.twig`

* `site/index.twig` is two lines: `Homepage\n{{ auth_user().email }}`.
* `entries/content.twig` is one line: `fsda` (looks like an accidental keystroke).

These ship as the default landing pages. `ExampleTest` only asserts that `GET /` returns 200, so this slips through CI. Replace with proper starter templates and add a feature test that asserts something meaningful in the response body.

### 2.5 `EntryTreeRouteDriver` falls back to a non-existent `entries.show` template
**File:** `app/Services/SiteRouting/RouteDrivers/EntryTreeRouteDriver.php` (lines 31–33)

```php
$template = $node->template
    ?? $entry->entryType?->default_template
    ?? 'entries.show';
```

There is no `resources/templates/entries/show.twig`. Any tree node with no `template` and no `default_template` on its entry type renders `templates::entries.show`, which raises a Twig "Unable to find template" exception. Either ship a default `entries/show.twig`, throw a clearer `NotFoundHttpException`, or use `config('site.templates.not_found_template')` (currently declared but not wired — see 4.4).

### 2.6 Settings keys read by code but never declared in `config/settings.php`
**File:** `app/Services/UserService.php`

* `Settings::get('users', 'social_default_status')` (line 102)
* `Settings::get('users', 'default_status')` (line 507)

There is no `users` domain in `config/settings.php`, so the calls always fall through to the hardcoded fallback (`UserStatus::PENDING`/`UserStatus::ACTIVE`). Admins cannot change these defaults from the settings UI. Add a `users` domain block with both fields.

### 2.7 Configured settings that nothing reads
**File:** `config/settings.php`

These keys are declared but nothing in `app/` references them:

* `content.entries_per_page`
* `content.default_entry_status`
* `media.max_upload_size`, `media.allowed_extensions`, `media.image_quality`
* `email.email_from_name`, `email.email_from_address`, `email.email_reply_to`

Either wire each into the relevant service (e.g. `MediaStorageService`, mail config sender, `EntryRepository::applyStatus()`) or remove from settings so the admin UI does not advertise non-functional knobs.

### 2.8 `EntryQueryBuilder` is missing `whereField()` advertised in `CLAUDE.md`
**File:** `app/Builders/EntryQueryBuilder.php`

`CLAUDE.md` lists `whereField()` as part of the fluent API, but the builder only has `inGroup`, `ofType`, `published`, `withStatus`, `withAuthor`, `withCategory`, `where`, and `orderBy`. Either drop the docs claim or add the method (likely a join through `field_values` keyed on field handle).

The injected `EntryRepository $repository` is also unused inside the builder (line 17). Either remove the dependency or push the field eager-load list onto the repository for centralisation.

### 2.9 `User::canAccessSystem()` does not check the lock when auto-expiring a suspension
**File:** `app/Models/User.php` (lines 74–103)

```php
if ($this->status === UserStatus::SUSPENDED && $this->suspended_until?->isPast()) {
    return true;   // ← does not consult $this->isLocked()
}
```

A user whose suspension expired but who is also `locked_until = future` still gets `canAccessSystem() === true`. The next branches are skipped, so the lock is bypassed. Move the lock check above the early-return, or fold it into the conditional.

`accessDeniedReason()` then has the inverse inconsistency for users with status SUSPENDED and `suspended_until = null` — `canAccessSystem()` returns false but no human-readable reason is wired specifically for "suspended without expiry".

### 2.10 `users.created_by_user_id` blocks user deletion silently
**File:** `database/migrations/2026_04_18_000009_create_entries_table.php`

`created_by_user_id` is `restrictOnDelete()` and there is no soft-delete trait on `User`. `UserService::delete()` simply calls `$user->delete()`; if the user authored any entry the FK throws and the controller surfaces a 500 to the operator. Either:

* Use `nullOnDelete()` and accept "creator unknown" entries, or
* Add `SoftDeletes` to `User` and migrate the column, or
* Pre-detach / reassign in `UserService::delete()` and surface a 422 when the user owns content.

### 2.11 `recordMetric` race retry loop assumes specific SQLSTATE
**File:** `app/Services/EntryService.php` (lines 94–129) and `app/Repositories/EntryRepository.php::upsertFieldValue` (lines 231–253)

`if ($e->getCode() !== '23000')` is correct on PDO MySQL/Postgres but other drivers may surface different codes. More importantly, both methods catch the QueryException and retry without `DB::transaction(...)` around the *whole* SELECT-then-INSERT/UPDATE sequence, which means repeated retries can interleave. Use `Model::upsert()` (Laravel 9+) or wrap in `DB::transaction(fn() => …, attempts: 3)`.

### 2.12 `EntryTypeRegistry` keeps singleton instances across requests/tenants
**File:** `app/EntryTypes/EntryTypeRegistry.php`, registered as singleton in `app/Providers/ContentServiceProvider.php`

The registry stores `AbstractEntryType` instances on the registry's *own* arrays, which are then shared across the whole application lifecycle. If any concrete type ever stores per-call state on `$this` (sequence counters, last-fetched record, request data), you have cross-request bleed. Today only `PodcastEpisodeEntryType::beforeCreate` mutates `$data` (no instance state), but this is brittle — document the no-instance-state contract on `AbstractEntryType` and consider an `app('entry_types')->fresh($handle)` accessor for cases that need a clean instance.

Caches also drift: `resolveByHandle()` populates both `handleCache` and `idCache`, but `resolveByRecord()` only writes to `idCache`. Two consecutive calls with the same record produce different memoization paths.

### 2.13 `ValidateClassReferences` artisan command treats a null `class` as broken
**File:** `app/Console/Commands/ValidateClassReferences.php` (line 22)

`if (!class_exists($type->class))` fires for entry types whose `class` column is NULL — but the migration `2026_04_28_000001_make_entry_types_class_nullable.php` and `EntryTypeRegistry::instantiate()` explicitly support NULL (fall back to `GeneralEntryType`). The command therefore reports false positives any time a generic entry type exists. Skip rows where `$type->class` is empty.

### 2.14 `app:refresh-tokens` artisan command is empty scaffold
**File:** `app/Console/Commands/refreshTokens.php`

`handle()` body is one comment block of pseudo-code; nothing executes. The command is registered automatically by Laravel discovery so operators can call it and get silent success. Either implement using `OauthToken::active()->each(fn()=>$svc->tryRefresh($t))` (the comment shows the intended call) or remove the command.

The class name is also lowercase (`refreshTokens` instead of `RefreshTokens`), violating PSR-1 — anyone running `php artisan make:test` or PHPStorm-aware tooling will trip on that.

---

## 3. Medium Severity

### 3.1 Two parallel `api_logs` pruning mechanisms
**Files:** `routes/console.php`, `app/Jobs/PruneApiLogs.php`

`Schedule::command('model:prune', ['--model' => [ApiLog::class]])->dailyAt('02:00')` runs the prune command. `PruneApiLogs::handle()` *also* prunes and re-dispatches itself for 02:00 the next day. If both are active you double-prune every night, plus the jobs queue grows because `PruneApiLogs::dispatch()` chains forever even if the schedule already handled it. Pick one and document it.

### 3.2 `Api\Controller::sort()` accepts arbitrary columns
**File:** `app/Http/Controllers/Api/Controller.php` (lines 86–98)

`$request->input('sort', 'id')` flows into `$query->orderBy($this->sort($request), …)` without a whitelist. Eloquent will happily order by `password`, `two_factor_secret`, `remember_token`, etc. — these are eager-loaded into a paginator and exposed via response headers/links. Even if the value never appears in the body, an attacker can use timing differentials (`ORDER BY two_factor_confirmed_at`) to enumerate accounts. Add a per-controller `$sortable = ['id', 'name', …]` allowlist.

The `sortDir` value is similarly unvalidated; non-`asc|desc` strings cause a SQL syntax error 500. Validate against `['asc', 'desc']`.

### 3.3 `users` admin route ordering is fragile
**File:** `routes/admin.php`

`Route::resource('users', User::class)` is registered between sets of token-related routes. `Route::get('users/layouts', …)` is registered *before* the resource (good) but the post-resource token routes `users/{id}/tokens/...` rely on path uniqueness rather than ordering — any future `users/{user}/tokens` resource would collide. Group all user/token routes under `Route::prefix('users')` for clarity.

### 3.4 Missing `nullable()` on `api_logs.user_id`
**File:** `database/migrations/2025_11_07_174041_create_api_log_table.php`

`$table->foreignId('user_id')->constrained()->cascadeOnDelete();` cannot accept the `null` value `LogRequestResponse` will pass for unauthenticated requests (and the `__OA_Get` `/api/v1/account` route does not enforce auth before the middleware runs). Add `->nullable()` between `foreignId(...)` and `->constrained()`.

### 3.5 `entry_trees.uri` has both unique and index
**File:** `database/migrations/2026_04_23_200641_create_entry_tree_table.php`

```php
$table->string('uri')->unique();
…
$table->index('uri');
```

Unique constraints already create a backing index in MySQL/Postgres. The second `index('uri')` is wasteful and slows writes. Remove it.

### 3.6 `__pending__<uniqid>` URI placeholder leaks on rare failures
**File:** `app/Services/EntryService.php` (line 337)

`createTreeNode` inserts a row with `'uri' => '__pending__' . uniqid()` then immediately rebuilds and saves the real URI. The wrapping `DB::transaction(...)` covers normal aborts, but if the second `save()` succeeds but `treeBuildUri` throws asynchronously (e.g. observer side effect), the placeholder URI is committed. Build the URI before the first `create()`, or compute the URI eagerly and skip the placeholder.

### 3.7 `entries.schema_type` and `entry_types.default_schema_type` columns are unused
**Files:** `database/migrations/2026_04_18_000008_create_entry_types_table.php`, `database/migrations/2026_04_18_000009_create_entries_table.php`

Neither column is read or written anywhere in `app/`. These appear to be a partial step toward `SEO_SCHEMA_PLAN.md`. Either complete that work or drop the columns to avoid implying functionality that doesn't exist.

### 3.8 OAuth token revocation is sequential per-row
**File:** `app/Services/UserService.php::upsertOauthToken` (lines 472–481)

```php
$user->oauthTokens()->provider($provider)->active()
    ->each(fn(OauthToken $t) => $t->revoke());
```

Each `revoke()` is its own UPDATE; on a noisy account this is N round-trips inside the same request. Use `$user->oauthTokens()->provider($provider)->active()->update(['revoked_at' => now()])` plus an event broadcast if revocation must be observable.

### 3.9 `EntryAuthorService::demote` uses `update(...)` instead of model events
**File:** `app/Services/EntryAuthorService.php` (line 65)

`EntryAuthor::where('user_id', $user->id)->update(['status' => 'disabled']);` skips Eloquent observers / `updated_at` touch. If the Search Plan or audit log later wants to react to author demotion, this bypass is invisible. Prefer `each(fn ($a) => $a->update([...]))` or a dedicated event.

### 3.10 `Login.php` references undefined `$this->redirectTo`
**File:** `app/Http/Controllers/Login.php` (line 45)

`return redirect($this->redirectTo ?? '/');` always evaluates the right-hand side because `redirectTo` is never declared on `Login` or its parent `Controller`. Under strict property access (`#[AllowDynamicProperties]` not set, PHP 8.2+ deprecation, future PHP 9 removal) this becomes an error. Either declare the property and load it from config, or drop the `??`.

### 3.11 `User\OauthToken` revocation race
**File:** `app/Services/UserService.php::revokeAllOauthTokens` (lines 552–561)

Loops `each(fn(OauthToken $t) => $t->revoke())` while holding query results in memory. If a new OAuth token is issued during the loop it is missed. Wrap in `DB::transaction()` or do a single `update()`.

### 3.12 Authentication middleware uses `Auth::user()->can(...)` without null-guard
**File:** `app/Http/Controllers/Controller.php` (line 29)

If a route ever calls a controller method without the auth middleware applied (test, future direct dispatch, console command), `Auth::user()` is null and `->can()` throws. Defensive code: `Auth::user()?->can($permission) ?? false`.

---

## 4. Low Severity / Hygiene

### 4.1 Commented-out Shop module wiring
**File:** `app/Providers/AppServiceProvider.php` (lines 100–106)

A 7-line commented `Route::group(...)` for `mithra62\Shop\Http\Controllers` sits in `boot()`. Either delete or move under `app/Providers/Shop/` so the Shop plan re-introduces it cleanly.

### 4.2 Empty stub directories
**Paths:** `app/Twig/Extensions/`, `app/Console/Commands/Spike/Support/`

Both directories exist with `.` and `..` only. Twigbridge already provides extension hooks via `config/twigbridge.php`; if no custom extensions are planned, drop the empty folder. The `Spike/Support/` looks like an experiment leftover.

### 4.3 `EntryResource` (per CLAUDE.md "Known Gaps") — already fixed
**File:** `app/Http/Resources/Api/EntryResource.php`

The note in `CLAUDE.md` reads *"EntryResource exposes user-shaped fields; should expose title, handle, status, type, group, fields"*. The current resource already exposes those. Update `CLAUDE.md` (and `OVERVIEW.md`) so the gap list does not mislead new contributors.

### 4.4 `site.templates.base_path` and `site.templates.not_found_template` are declared but unused
**File:** `config/site.php`, `app/Services/SiteRouting/RouteDrivers/*.php`

`TemplateRouteDriver` reads `default_template` only. Either honour `base_path` (prefix all view names) and `not_found_template` (return-when-driver-fails) or remove from config so the values are not seen as configuration knobs.

### 4.5 Lowercase / inconsistent class file names
**Files:**
* `app/Console/Commands/refreshTokens.php` — class name `refreshTokens`, expected `RefreshTokens`.
* `app/Models/Settings.php` exists but `app/Settings.php` is the one bound; check no namespace collisions.

Composer/autoload tolerates these on case-insensitive filesystems but ships broken on Linux for some files; rename and let the autoloader regenerate.

### 4.6 `EntryAuthorService::findByUser()` does not eager-load the user
**File:** `app/Services/EntryAuthorService.php` (line 32)

Pickers usually want the related user; consider `with('user')` to remove the inevitable N+1 in callers.

### 4.7 `BotBlockServiceProvider` registered? Confirm
**Search:** `grep -n BotBlockServiceProvider config/app.php bootstrap/providers.php`

The provider must be in `bootstrap/providers.php` for the `bb-field` singleton to bind. Worth a one-line confirmation; if it isn't, the `_bb.twig` partial throws "Target not found".

### 4.8 Mixed Twig/Blade error views
**Path:** `resources/views/errors/*.blade.php`

Twig is the templating language for this app, but `errors/404.blade.php`, `errors/500.blade.php`, etc. are Blade. Either convert to Twig for consistency or document that error pages are intentionally Blade because Laravel resolves them through the default loader.

### 4.9 `LoginTest` only covers `canAccessSystem` and middleware logout
**File:** `tests/Feature/LoginTest.php`

The full Fortify login form path, the OAuth callback, the suspension-window auto-expire flow, and the `EnforceUserStatusApi` middleware all lack feature tests. (`EnforceUserStatusApi` has no test at all.) Add at least:

* `test_oauth_callback_redirects_to_login_on_invalid_state`
* `test_api_request_with_blocked_status_returns_401`
* `test_locked_user_cannot_access_admin_when_status_is_active`

### 4.10 `Field::with = ['fieldType']` always eager-loads
**File:** `app/Models/Field.php` (line 33)

Fine for the admin UI but every `Field` query — including counts and existence checks — pulls the field type row. Replace with explicit `with('fieldType')` at call sites that need it, or with a global scope that listens to `wherePivot/exists` to short-circuit.

---

## 5. Missing Tests / Coverage Gaps

The Unit suite (≈97 files) is reasonable; the **Feature** suite (7 files, including `ExampleTest`) is dangerously thin. Suggested additions, in priority order:

1. **API layer** — `tests/Feature/Api/v1/{Entries,EntryGroups,Categories,CategoryGroups,Statuses,StatusGroups,Users}Test.php`. Each should at minimum:
   * 200/201 happy path with valid Sanctum token
   * 401 without a token
   * 403 with a token lacking the required permission
   * 422 on validation failure
   * 404 on cross-group lookups (already implemented in `Api\v1\Entries::show`)
2. **`SiteRouter`** — currently only the `EntryTreeRouteDriver` has a unit test. Add an integration test that fires `GET /any/uri` and verifies driver priority order (`entry_tree` first, then `template`).
3. **Admin CRUD flows** — there are no Feature tests for entry/category/role create/update/delete. The seeder data is rich enough to write smoke tests that fill out the admin form requests and assert DB state.
4. **`EnforceUserStatusApi`** middleware — no unit or feature test exists.
5. **Settings cache busting** — `SettingsResolverTest` verifies resolution but not the 1-hour cache invalidation on writes.
6. **Bot-block flow** — once the middleware is wired up (1.8), add a test asserting POST without `__bb` returns 403 and POST with the right value succeeds and deletes the row.
7. **Lifecycle hooks for every concrete `EntryType`** — there are unit tests for the type classes themselves, but no Feature tests confirm `beforeCreate`/`afterCreate`/`beforeUpdate`/`afterUpdate` are invoked end-to-end through `EntryService`.
8. **`recordMetric` concurrency** — simulate the race by manually inserting a duplicate row inside a transaction and verifying the retry path increments cleanly.

---

## 6. Suggested New Seeders

* **`EnvAdminSeeder`** — replace the current hard-coded `eric@mithra62.com` block in `UsersSeeder` with a seeder that reads `ADMIN_EMAIL`, `ADMIN_PASSWORD`, `ADMIN_NAME` from `.env`. Refuse to run when those are unset *and* the environment is `production`.
* **`UserSettingsDomainSeeder`** — once 2.6 is fixed, seed `users.default_status` and `users.social_default_status` so the admin UI exposes them.
* **`HomePageSeeder`** — create a single root `EntryTree` node with `is_home = true` pointing at a real entry. The current ExampleTest passes only because the catch-all falls through to `templates::site.index`; with a real home node the test will exercise the entry-tree driver path.
* **`MediaTransformationSeeder`** — `media_transformations` table exists but no rows are seeded. Provide a `thumb` and `web` default so the media UI has something to render against.
* **`SampleApiTokenSeeder`** *(local/testing only)* — create a Sanctum personal access token for the seeded super admin and print the plain-text token. Saves every developer 30 seconds when probing the API.

---

## 7. Suggested New Helper Functions

The codebase has plenty of services but a handful of small helpers would pay back immediately:

* **`current_user_setting(string $domain, string $handle, mixed $default = null)`** — wraps `app(Settings::class)->get(...)` against the auth user. Currently every controller does the wrapping by hand.
* **`entry_url(Entry $entry): ?string`** — resolves the public URL by looking up the entry's tree node URI, falling back to the configured base path. Right now templates reach into `$entry->entryTree?->url` directly, which couples views to the model graph.
* **`field_value(string $modelHandle, string $fieldHandle, mixed $default = null)`** — Twig-friendly accessor. The `Fieldable` trait exposes `field()` but that requires a model instance; templates often want a one-liner.
* **`is_super_admin(?User $user = null): bool`** — wrap `Gate::before` semantics so views and controllers stop calling `Auth::user()->hasRole('super admin')` directly (search the codebase: that string appears in seven places).
* **`media_url(int|Media|null $media, string $variant = 'web'): string`** — once `MediaStorageService` is fully wired, expose a Twig-callable that returns either the variant URL or a sane placeholder.
* **`api_log(...)`** *(internal)* — encapsulate the LogRequestResponse INSERT so the missing `response_payload` (1.3) is fixed in one place rather than scattered across the middleware.

---

## 8. Documentation Drift to Reconcile

* `OVERVIEW.md` and `CLAUDE.md` reference `EntryQueryBuilder::whereField()` (does not exist — see 2.8).
* `CLAUDE.md` Known Gaps still lists the `EntryResource` shape mismatch, which is already resolved (4.3).
* `CLAUDE.md` notes that "`Api\v1\User` checks permission `read users`; seeded permission is `view user`" — `view user` *and* `read users` are both seeded in `RolesPermissionsSeeder`. The discrepancy claim is stale.
* `OVERVIEW.md` claims the test database lives at `database/testing.sqlite` and is "separate from dev"; `phpunit.xml` confirms this, but `tests/TestCase.php` should be checked for an `RefreshDatabase` default — several Unit tests rely on factories without explicit refresh and pass only because the DB was previously migrated.
* The `media-refactor-plan.md`, `TenantPlan.md`, `SEARCH_PLAN_V2.md`, and `SHOP_PLAN.md` are still in repo root; consolidate under `docs/plans/` with a single index.

---

## 9. Quick-Win Cleanup Checklist

These are 5–15 minute fixes:

- [ ] Delete `app/Http/Controllers/TemplateController.php`.
- [ ] Delete `app/Http/Controllers/Admin/Playground.php`.
- [ ] Remove duplicate `Status::observe()` in `AppServiceProvider::boot`.
- [ ] Add `response_payload` column to `api_logs` (new migration) and to `ApiLog::$fillable`.
- [ ] Fix `down()` in `2025_11_07_174041_create_api_log_table.php` to drop `api_logs`.
- [ ] Replace `echo "broken"; exit;` in `Login::handleProviderCallback` with a proper redirect-with-error.
- [ ] Skip rows where `class` is null in `ValidateClassReferences`.
- [ ] Drop `tailwind/`, `tailwind2/`, `tailwind-updates/` from `resources/templates/`.
- [ ] Remove the empty `app/Twig/Extensions/` and `app/Console/Commands/Spike/Support/` directories.
- [ ] Replace placeholder `entries/content.twig` content (`fsda`).
- [ ] Add `->nullable()` on `api_logs.user_id` FK.
- [ ] Drop the redundant secondary index on `entry_trees.uri`.
- [ ] Remove the unused `EntryRepository` parameter from `EntryQueryBuilder::__construct`.

---

*End of report.*
