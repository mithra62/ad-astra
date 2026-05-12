# AGENTS.md

Agent guidance for working in this repository. Read `CLAUDE.md` first for commands and architecture.

## Environment

- **PHP:** `C:\php\php.exe` (Windows host). Use `php` if it resolves; otherwise prefix with the full path.
- **Dev stack:** Laravel 12, PHPUnit 11, Vite 7, Tailwind CSS 4, Twig 3 (TwigBridge).
- **Tests:** SQLite, isolated environment. Run `composer test` to validate all changes.
- **Linting:** Run `vendor/bin/pint --dirty` before considering any PHP change complete.

## How to Verify Changes

For any non-trivial change:

1. `vendor/bin/pint --dirty` — fix code style
2. `composer test` — run the full suite; do not ship failing tests
3. `php artisan app:validate-class-references` — after touching entry type or field type class names
4. `php artisan optimize:clear` — after touching config, routes, or service providers

## Making Changes to the Content Model

The `Entry` lifecycle flows through three layers — always touch all three when extending behaviour:

1. **`AbstractEntryType` subclass** (in `app/EntryTypes/`) — lifecycle hooks and validation
2. **`EntryRepository`** — persistence: core attributes, status, authors, categories, field values, relationships
3. **`EntryService`** — orchestrates the repository, fires hooks, handles transactions

Never write directly to `Entry` attributes from controllers. Always go through `EntryService` (or the `Entries` facade).

## Adding a New Field Type

1. Create a class in `app/Field/Types/` extending `AbstractField`.
2. Implement `storageColumn(): string` (one of `value_text`, `value_integer`, `value_float`, `value_date`, `value_boolean`, `value_json`). For relational types, override `isRelational(): bool` to return `true` — values are stored in `entry_relationships`, not `field_values`.
3. Optionally implement `validate(mixed $value)`, `cast(mixed $value)`, and `render(array $params)`.
4. Seed a row in `field_types` pointing to the new class, or add it to `FieldTypeSeeder`.

## Adding a New Setting

No migration required. Add an entry to the appropriate domain's `fields` array in `config/settings.php`. Fields are auto-discovered by `App\Settings`. If you need a pre-seeded system default, add it to `SettingsDomainSeeder`. Mark `user_overridable: true` if users should be able to override it personally. After adding, bust the cache with `php artisan cache:clear`.

## Working with Morphmap

All polymorphic models must be registered in `AppServiceProvider::boot()` via `Relation::morphMap()`. Adding a new morphable model without registering it there will silently orphan rows if the class is ever renamed.

## Route Drivers (Public Site)

To add a new public routing strategy, implement `App\Services\SiteRouting\RouteDrivers\RouteDriverInterface` and register the driver key in `SiteRouter::drivers()`. The driver must return a `RouteResult` or `null`. Drivers are tried in the order defined by `config('site.routing.priority')`.

## API Controllers

All API controllers live under `App\Http\Controllers\Api\v1\`. All API routes must be wrapped with `LogRequestResponse` middleware (already applied at the route group level). Use `auth:sanctum` middleware — it is applied at the route group level in `routes/api.php`.

The `Api\Rest` layer (`app/Rest/`) provides a client and API wrapper for outbound calls; it is separate from the inbound API controllers.

## Admin Controllers

Admin controllers live under `App\Http\Controllers\Admin\`. The `auth` middleware is applied at the route group level. Blade is not used — admin views are Twig templates in `resources/views/admin/`. Use the `admin::` view namespace (e.g. `view('admin::entries.index')`).

## Facades vs Direct Injection

Prefer facades for new code in controllers and commands. Prefer constructor injection in services and repositories. The active facades are: `Entries`, `Content` (alias for `Entries`), `EntryTypes`, `Categories`, `EntryGroups`, `Users`. `Settings` should be injected as `App\Settings` or resolved via `app('settings')`.

## Pending Plans — Do Not Conflict With

The following major plans are queued and touch large surface areas. When making any change, check that it does not contradict or duplicate work described in these files:

| File | Area | Status |
|---|---|---|
| `TenantPlan.md` | Multi-tenant foundation (`tenant_id` everywhere) | Not started — do second |
| `SEARCH_PLAN_V2.md` | Keyword search, `search_index`, `Searchable` trait | Not started — do third |
| `SHOP_PLAN.md` | `mithra62/Shop` e-commerce module | Not started — do last |

Key constraints:
- Do not add new polymorphic pivot tables without a `tenant_id` column stub ready — TenantPlan will add it and conflicts are expensive.
- `field_layout_tab_elements` is touched by both the Search plan (adds `is_searchable`, `search_weight`) and TenantPlan (adds `tenant_id`). Coordinate any migrations to this table carefully.

## TODOS Reference

`TODOS.md` contains the active task list. Notable items that affect agent work:
- Field changes should be blocked if data already exists for that field (item 3)
- Repositories should extend a common base and follow a consistent interface (item 8)
- Field layouts should be auto-created when Entry/Category Groups are created (items 10, 14)
- `mithra62/Shop` should be removed from `composer.json` until Shop work begins (item 12)
- Custom field layer needs more types: select, multiselect (item 17)
