# AdAstra

AdAstra is a Laravel 12 CMS/application foundation with an authenticated admin area, a Sanctum-protected API, Twig-based views, configurable content types, field layouts, media libraries, settings, users, roles, and permissions.

## Requirements

- PHP 8.2 or newer
- Composer
- Node.js and npm
- MySQL 8.x or a compatible database supported by the app configuration
- PHP cURL extension

## Stack

- Laravel 12
- Laravel Fortify for authentication
- Laravel Sanctum for API token authentication
- Laravel Socialite for OAuth/social login
- Spatie Permission for roles and permissions
- Spatie Webhook Client for inbound webhook handling
- Native Laravel Media and Media Library layer for uploaded media
- TwigBridge and Twig templates
- L5 Swagger for API documentation
- Vite 7 and Tailwind CSS 4
- PHPUnit 11

## Application Areas

- Public site routing through `SiteController`, with route drivers for entry-tree and template-based pages.
- Admin UI under `/admin` for users, roles, account settings, tokens, entries, entry groups, entry types, categories, statuses, fields, field layouts, media libraries, and domain/user settings.
- API routes under `/api/v1`, protected by Sanctum, for users, entries, and the current account.
- OAuth/social login via Socialite (`app/Http/Controllers/Login.php`), with `app:refresh-tokens` available to refresh expiring OAuth tokens (not currently scheduled — run manually or add a schedule entry).
- Content modeling through entry groups, entry types, fields, field groups, field layouts, statuses, categories, entry relationships, and entry tree routing.
- Bot-blocking middleware (`BotBlockRequest`) and webhook-client infrastructure for external integrations.
- Config-driven settings domains in `config/settings.php`.
- Twig templates in `resources/views` and public templates in `resources/templates`.

## Setup

Install PHP and JavaScript dependencies:

```bash
composer install
npm install
```

Create your environment file:

```powershell
Copy-Item .env.example .env
```

Or, from a Unix-style shell:

```bash
cp .env.example .env
```

Set the database values in `.env`, including `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD`. The example environment uses MySQL and a `DB_TABLE_PREFIX` of `ada_`.

The seeded super-admin's credentials come from `.env`, not a fixed default — set `DEV_USER_EMAIL`, `DEV_USER_NAME`, and `DEV_USER_PASSWORD` for a predictable local login. Each of the three falls back independently to a random 16-character string if left blank, so leaving all three empty does **not** produce a known login. `UsersSeeder` also skips itself entirely when `APP_ENV=production` — see [Deployment](#deployment) below.

Reset, migrate, and seed the database in one command:

```bash
php artisan migrate:fresh --seed
```

Generate the app key (run this last — it's the final setup step, not a prerequisite for migrating/seeding):

```bash
php artisan key:generate
```

For uploaded media, create the public storage link:

```bash
php artisan storage:link
```

## Development

Run the Laravel server, queue listener, and Vite dev server together:

```bash
composer run dev
```

Or run them separately:

```bash
php artisan serve
php artisan queue:listen --tries=1
npm run dev
```

Build frontend assets for production:

```bash
npm run build
```

## Deployment

`php artisan serve` and `composer run dev` are development-only. To run AdAstra
under a real web server instead (upload and run, no PHP dev server), point the
web root at `public/`, not the repo root — `public/index.php` is the front
controller and `public/.htaccess` already has the standard Laravel Apache
rewrite rules. Run `npm run build` before uploading; the server only needs PHP
and Composer dependencies at runtime, not Node.

- **Apache**: set `DocumentRoot` to `.../public` with `AllowOverride All` (or
  an equivalent `mod_rewrite` block) so `.htaccess` is honored.
- **Nginx + PHP-FPM**: route everything through `index.php` with
  `try_files $uri $uri/ /index.php?$query_string;` and a `fastcgi_pass` to
  your PHP-FPM socket.

Full vhost/server-block examples and a production caveat — `UsersSeeder`
skips itself when `APP_ENV=production`, so the super-admin user isn't seeded
automatically and must be created manually (e.g. via `php artisan tinker`) —
are in [docs/OVERVIEW_FINAL.md § Running without `php artisan serve`](docs/OVERVIEW_FINAL.md#running-without-php-artisan-serve-apachenginx--php-fpm).

The scheduler cron and a queue worker are required in production regardless
of which web server fronts the app — see [Useful Commands](#useful-commands)
below.

## Testing

Run the test suite:

```bash
composer test
```

The PHPUnit configuration uses the `testing` environment and `database/testing.sqlite`. The suite includes unit and feature coverage for actions, models, services, entry types, field types, middleware, settings, repositories, seeders, and admin flows.

## API

API endpoints are registered under `/api/v1` and require Sanctum authentication.

Current API resources:

- `GET /api/v1/account`
- `/api/v1/users`
- `/api/v1/entries`
- `/api/v1/entry-groups`
- `/api/v1/category-groups`
- `/api/v1/status-groups`
- `/api/v1/statuses`

Every API route is wrapped in `LogRequestResponse`, which records the request route, method, user ID, sanitized request payload/headers, sanitized response headers, and the response status code to `api_logs`. Response *bodies* are intentionally not captured — only an allowlist-based redaction was feasible for response shapes, which would require ongoing maintenance as resources change, so response-body logging was dropped rather than risk silently leaking sensitive data. `api_logs` rows are pruned daily (90-day retention) via Laravel's scheduler.

Generate Swagger documentation with:

```bash
php artisan l5-swagger:generate
```

## Useful Commands

```bash
php artisan optimize:clear
php artisan config:clear
php artisan route:list --except-vendor
php artisan app:validate-class-references
php artisan schedule:run
php artisan queue:work
php artisan app:refresh-tokens
```

`app:validate-class-references` checks `entry_behaviors.class` (morph alias) and `field_types.object` (FQCN) references before deployment, failing if any are broken.

`app:refresh-tokens` refreshes expiring OAuth tokens through `TokenRefreshService`. It is implemented but not registered in the scheduler, so run it manually or add a schedule entry if automatic refresh is needed.

In production, run the Laravel scheduler every minute (e.g. via cron) so the daily jobs execute: `App\Jobs\PruneApiLogs` at 02:00 and `PurgeDeletedMedia` at 03:00 (`routes/console.php`). Both are dispatched as queued jobs, so an active queue worker must also be running for them to actually execute.
