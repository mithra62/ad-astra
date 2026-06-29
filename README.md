# AdAstra

> **A content platform built around your content model.**

AdAstra is an opinionated content platform built for developers who want complete control over how content is modeled, managed, and delivered.

Most CMSs begin with pages, posts, or products, then expect your content to fit within those predefined structures.

AdAstra takes a different approach.

Your content model comes first.

From there, authoring experiences, APIs, routing, templates, workflows, and administration are built around that model.

Built on Laravel 12, AdAstra provides a modern application foundation with an authenticated administration area, REST APIs, Twig templating, configurable content types, media management, users, roles, permissions, and the infrastructure expected from a modern content platform.

---

## Why AdAstra?

After more than twenty years building websites, e-commerce platforms, and custom CMS solutions, I found myself solving the same architectural problems over and over again.

Too often, the platform dictated the content model instead of adapting to it.

AdAstra exists to reverse that relationship.

Instead of asking, "How do I make my content fit this CMS?", the better question becomes, "What does my content actually look like?"

Everything grows from that answer.

Entry types, field layouts, APIs, routing, templates, publishing workflows, and administration all exist to serve the content model, not define it.

---

## Core Principles

* **Content first.** Model your data before building the authoring experience.
* **Built for developers.** The platform should stay out of your way, not fight your architecture.
* **API first.** Every piece of content should be available through a clean, consistent API.
* **Designed to evolve.** Behaviors, field types, routing, and services should grow alongside your application.
* **Modern by default.** Laravel, Twig, Tailwind, queues, testing, and automation are part of the foundation.

---

## The Content Model

At the heart of AdAstra is a flexible content model.

```text
Entry Group
├── Entry Type
│   ├── Field Layout
│   ├── Behaviors
│   ├── Validation
│   └── Publishing Rules
└── Entries
```

Instead of treating blog posts, products, events, documentation, and landing pages as entirely separate systems, AdAstra allows them to share common structure while giving each entry type its own fields, workflows, behaviors, and publishing rules.

The result is a platform that adapts to your application instead of forcing your application to adapt to the platform.

---

## Current Status

AdAstra is currently in active alpha development.

Core systems are functional, but breaking changes should be expected while the platform continues to evolve.

The project is being built in public, and thoughtful feedback is always welcome.

---

## Technology

AdAstra is built on a modern Laravel stack, including:

* Laravel 12
* Twig
* Sanctum
* Fortify
* Tailwind CSS
* Vite
* PHPUnit
* Swagger / OpenAPI

The technology stack supports the architecture. It is not the architecture.

---

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

## Data Modeling Philosophy

AdAstra makes no fixed assumptions about what data a piece of content holds. Rather than hardcoding columns per content type, schemas are composed at runtime through a small set of shared traits, and any model can opt in.

**Fields, everywhere.** The `Fieldable` trait (`app/Traits/Field/Fieldable.php`) gives a model arbitrary, typed field values through one shared mechanism — `fieldValues()`, `field($handle)`, and `fieldArray()`. **Entries, Media, Categories, and Users all use it**, so the same field system that powers a blog post also powers a user profile or a media asset's metadata. Entries, Media, and Categories go a step further and attach to a `FieldLayout` (via `EntryType`/`EntryGroup`, `Media\Library`, and `Category\Group` respectively) to organize those fields into tabs in the admin UI; Users store field values without a configurable layout.

**Categories, everywhere relevant.** The `HasCategories` trait (`app/Traits/Category/HasCategories.php`) gives a model a `categories()` relation through a polymorphic `categorizable` pivot. **Entries and Media both use it**, so arbitrary taxonomy can be applied to either without a content-type-specific tagging system. This is distinct from `HasCategoryGroups`, which `EntryGroup` and `Media\Library` use to scope *which category groups* are available to choose from — `HasCategories` is the tagging relation itself.

The result: adding a new content type doesn't mean inventing a new way to store custom fields or apply categories — it means composing the traits that already exist.

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

**Production caveat:** `UsersSeeder` skips itself when `APP_ENV=production`,
so the super-admin user isn't seeded automatically and must be created
manually (e.g. via `php artisan tinker`).

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
