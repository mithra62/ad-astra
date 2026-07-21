# AdAstra

[![tests](https://github.com/mithra62/ad-astra/actions/workflows/tests.yml/badge.svg?branch=main)](https://github.com/mithra62/ad-astra/actions/workflows/tests.yml)
[![PHP](https://img.shields.io/badge/PHP-8.2%20%7C%208.3%20%7C%208.4-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white)](https://laravel.com/)
[![code style](https://img.shields.io/badge/code%20style-PSR--12-blue)](https://www.php-fig.org/psr/psr-12/)
[![release](https://img.shields.io/github/v/release/mithra62/ad-astra?include_prereleases&label=release)](https://github.com/mithra62/ad-astra/releases)
[![status](https://img.shields.io/badge/status-alpha-orange)](https://github.com/mithra62/ad-astra/releases)
[![tests](https://img.shields.io/badge/tests-2454%20passing-success)]()
[![last commit](https://img.shields.io/github/last-commit/mithra62/ad-astra)]()

> **A content platform built around your content model, where content types are real classes, not just schema rows.**

AdAstra is an opinionated content platform built for developers who want complete control over how content is modeled, managed, and delivered.

Most CMSs begin with pages, posts, or products, then expect your content to fit within those predefined structures.

AdAstra takes a different approach.

Your content model comes first: structure *and* behavior.

From there, authoring experiences, APIs, routing, templates, workflows, and administration are built around that model.

Built on Laravel 12, AdAstra provides a modern application foundation with an authenticated administration area, REST APIs, Twig templating, configurable content types, media management, users, roles, permissions, and the infrastructure expected from a modern content platform.

---

## Why AdAstra?

After more than twenty years building websites, e-commerce platforms, and custom CMS solutions, I found myself solving the same architectural problems over and over again.

Too often, the platform dictated the content model instead of adapting to it.

AdAstra exists to reverse that relationship.

Instead of asking, "How do I make my content fit this CMS?", the better question becomes, "What does my content actually look like?"

Everything grows from that answer.

Entry types, field layouts, APIs, routing, templates, statuses, and administration all exist to serve the content model, not define it.

And a content model is more than structure. A product doesn't just *store* differently than a blog post; it *behaves* differently: it validates differently, it publishes under different rules, it reacts to changes in its own way. In most platforms that logic ends up scattered across event listeners and hooks, disconnected from the type it governs. In AdAstra, it lives in one place: the entry type's behavior class.

---

## Core Principles

* **Content first.** Model your data before building the authoring experience.
* **Behavior belongs to the type.** Per-type logic lives in a versioned PHP class, not scattered listeners.
* **Built for developers.** The platform should stay out of your way, not fight your architecture.
* **API first.** Every piece of content should be available through a clean, consistent API.
* **Designed to evolve.** Behaviors, field types, routing, and services should grow alongside your application.
* **Modern by default.** Laravel, Twig, Tailwind, queues, testing, and automation are part of the foundation.

---

## The Content Model

At the heart of AdAstra is a flexible content model.

```text
Entry Group
├── Status Group (governed statuses, defaults, publish visibility)
├── Entry Type
│   ├── Behavior (a PHP class: lifecycle hooks + validation)
│   ├── Field Layout
│   └── Entry Tree settings (default template, nesting rules)
└── Entries
```

Instead of treating blog posts, products, events, documentation, and landing pages as entirely separate systems, AdAstra allows them to share common structure while giving each entry type its own fields, statuses, and behaviors.

The result is a platform that adapts to your application instead of forcing your application to adapt to the platform.

---

## Entry Types & Behaviors

This is the part of AdAstra that most separates it from other content platforms.

Every CMS lets you define *what* a content type stores. AdAstra also lets you define *how it behaves*, as a first-class, per-type code artifact. An entry type can be backed by a PHP class extending `AdAstra\EntryTypes\AbstractEntryType` (`packages/core/src/EntryTypes/AbstractEntryType.php`), which participates in the entry's lifecycle:

| Hook | Signature | When it runs |
|---|---|---|
| `beforeCreate` | `beforeCreate(array $data): array` | Inside the create transaction, before the entry is written; may mutate the payload |
| `afterCreate` | `afterCreate(Entry $entry, array $data): void` | After the create transaction commits |
| `beforeUpdate` | `beforeUpdate(Entry $entry, array $data): array` | Inside the update transaction, before changes are applied |
| `afterUpdate` | `afterUpdate(Entry $entry, array $data): void` | After the update is applied |
| `validate` | `validate(array $data, ?Entry $entry = null): array` | Before create/update; returns `['field_handle' => 'error']`, and any errors surface as a 422 `ValidationException` |

A working behavior looks like this:

```php
<?php

namespace App\EntryTypes;

use AdAstra\EntryTypes\AbstractEntryType;
use AdAstra\Models\Entry;

class CaseStudyEntryType extends AbstractEntryType
{
    // Derive a field before the entry is written.
    public function beforeCreate(array $data): array
    {
        $body = $data['fields']['body'] ?? null;

        if ($body !== null) {
            $data['fields']['reading_time'] = (int) ceil(str_word_count((string) $body) / 200);
        }

        return $data;
    }

    // Gate publishing: errors surface as field-keyed 422 responses.
    public function validate(array $data, ?Entry $entry = null): array
    {
        $errors = [];

        $status = $data['status'] ?? $entry?->status_handle;

        if ($status === 'published') {
            $client = $data['fields']['client_name']
                ?? $this->existingFieldValue($entry, 'client_name');

            if (empty($client)) {
                $errors['client_name'] = 'A client name is required before publishing.';
            }
        }

        return $errors;
    }
}
```

Registering it takes three steps: extend `AbstractEntryType`, register a `behavior.*` morph alias for the class in a service provider (`Relation::morphMap(['behavior.case-study' => CaseStudyEntryType::class])`), and create an `entry_behaviors` row whose `class` column holds that alias. Any entry type can then point at the behavior via `entry_behavior_id`.

The binding design matters as much as the hooks. The schema side (entry types, field layouts) stays admin-managed data; the behavior side stays versioned code. The two meet through a morph alias (never a hardcoded class name in a form), so classes can be renamed without orphaning database rows. And the system degrades gracefully: an entry type with no behavior, or a behavior whose class has gone missing, falls back to `GeneralEntryType` (base behavior, no customization) rather than fatally erroring. Hooks are resolved through `EntryTypeRegistry`, which caches one instance per type for the life of the process, so behavior classes are written stateless.

Eleven behaviors ship in core as working examples (`packages/core/src/EntryTypes/`): blog posts compute reading time, products auto-set out-of-stock status and refuse to publish without a SKU, podcast episodes auto-number themselves under a row lock, job listings expire past their closing date, and events, videos, and news articles enforce their own validation rules.

The `adastra:doctor` command keeps this layer honest in production: it warns on entry types silently falling back to `GeneralEntryType` and fails on behavior rows whose class references no longer resolve.

---

## Data Modeling Philosophy

AdAstra makes no fixed assumptions about what data a piece of content holds. Rather than hardcoding columns per content type, schemas are composed at runtime through a small set of shared traits, and any model can opt in.

**Fields, everywhere.** The `Fieldable` trait (`packages/core/src/Traits/Field/Fieldable.php`) gives a model arbitrary, typed field values through one shared mechanism: `fieldValues()`, `field($handle)`, and `fieldArray()`. **Entries, Media, Categories, and Users all use it**, so the same field system that powers a blog post also powers a user profile or a media asset's metadata. Entries, Media, and Categories go a step further and attach to a `FieldLayout` (via `EntryType`/`EntryGroup`, `Media\Library`, and `Category\Group` respectively) to organize those fields into tabs in the admin UI; Users store field values without a configurable layout. Twenty-three field types ship in core (`packages/core/src/Field/Types/`), from Text, Date, and Boolean through Money, StructuredRows, FileUpload, and Relationship, each declaring its own typed storage column.

**Categories, everywhere relevant.** The `HasCategories` trait (`packages/core/src/Traits/Category/HasCategories.php`) gives a model a `categories()` relation through a polymorphic `categorizable` pivot. **Entries and Media both use it**, so arbitrary taxonomy can be applied to either without a content-type-specific tagging system. This is distinct from `HasCategoryGroups`, which `EntryGroup` and `Media\Library` use to scope *which category groups* are available to choose from; `HasCategories` is the tagging relation itself.

**Statuses, everywhere relevant.** The `HasStatus` trait (`packages/core/src/Traits/HasStatus.php`) gives a model a governed status via a denormalized `status_id` / `status_handle` / `status_is_public` triple. **Entries and Media both use it.** Statuses are defined in status groups, each with a default and per-status public visibility, and a record's status must belong to the status group of its owning container (the entry group, or the media library). On entries, moving to a public status sets `published_at` automatically. Status renames cascade to consumers through an observer, so the denormalized handles never drift.

The result: adding a new content type doesn't mean inventing a new way to store custom fields, apply categories, or govern workflow states; it means composing the traits that already exist.

---

## The Media Layer

Media in AdAstra is a first-party implementation (no third-party media package) built on the same primitives as the rest of the content model.

**Libraries are the containers.** A `Media\Library` (`packages/core/src/Models/Media/Library.php`) owns its storage disk and adapter settings, an allowed MIME type list, a max upload size, an optional field layout, category groups, and a status group. Upload validation and physical storage live in the `HasMediaItems` trait: the file is stored first, the database row is created in a transaction, and the physical file is cleaned up if persistence fails.

**Media records are full content objects.** A `Media` record (`packages/core/src/Models/Media.php`) uses `Fieldable`, `HasCategories`, and `HasStatus`, the same field, taxonomy, and status systems entries use, so a media asset can carry custom metadata fields, be categorized, and move through a governed workflow just like an entry.

**Attachment is queryable by design.** Media attaches to models (currently `Entry` and `User`) through the `HasMedia` trait and a polymorphic `mediables` pivot. The pivot's `field_id` records *how* the attachment happened: `0` means a direct attachment (an avatar, a library-browser pick); a positive ID means the media was referenced through a specific FileUpload or Media field. Field-stored media IDs are automatically synced into the pivot by `FieldValueObserver`, so "where is this asset used?" is always answerable with a query.

**Transformations are driver-based.** Image transformations dispatch through `TransformationDriverInterface`, with the provider auto-selecting Imagick, GD, or a null driver based on the extensions available. Derived files are processed on the queue and tracked in `media_transformations`.

**Deletion is two-stage.** Deleting media soft-deletes the row; the scheduled `PurgeDeletedMedia` job permanently removes the physical files and transformations only after a 30-day grace period.

---

## Querying Content

`EntryQueryBuilder` (`packages/core/src/Builders/EntryQueryBuilder.php`) wraps Eloquent with a fluent, content-aware API:

```php
$posts = Entries::query()
    ->inGroup('blog')
    ->ofType('blog-post')
    ->published()
    ->whereField('featured', true)
    ->paginate(15);
```

Beyond `inGroup()`, `ofType()`, `published()`, `withStatus()`, `withAuthor()`, and `withCategory()`, the builder can filter directly on custom field values with `whereField()`, which resolves each field's typed storage column. Every result set is fetched with the full eager-load set applied automatically (groups, types, authors, categories, field values, and relationships), so accessing field data never triggers N+1 queries.

Services are exposed through facades: `Entries`, `Content`, `EntryTypes`, `EntryGroups`, `Categories`, `Users`, `Settings`, `Fields`, `Files`, and `MediaStorage`.

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
- First-party Media and Media Library layer for uploaded media
- TwigBridge and Twig templates
- L5 Swagger for API documentation
- Laravolt Avatar for generated user avatars
- HTMLPurifier (mews/purifier) for HTML field sanitization
- MoneyPHP for the Money field type
- Vite 7 and Tailwind CSS 4
- PHPUnit 11

## Application Areas

- Public site routing through the `Site` controller (`AdAstra\Http\Controllers\Site`) and `SiteRouter`, which tries route drivers in the order set by `site.routing.priority`: entry-tree pages first, then Twig template pages. (Drivers implement `RouteDriverInterface`; adding a new driver currently also requires registering it in `SiteRouter::drivers()`.)
- Admin UI under `/admin` for users, roles, account settings, tokens, entries, entry groups, entry types, categories, statuses, fields, field layouts, media libraries, and domain/user settings.
- API routes under `/api/v1`, protected by Sanctum; see [API](#api) below for the resource list.
- OAuth/social login via Socialite (`packages/core/src/Http/Controllers/Login.php`), with `app:refresh-tokens` available to refresh expiring OAuth tokens (not currently scheduled; run manually or add a schedule entry).
- Content modeling through entry groups, entry types, behaviors, fields, field groups, field layouts, statuses, categories, entry relationships, and entry tree routing.
- Bot-blocking middleware (`BotBlockRequest`) and webhook-client infrastructure for external integrations.
- Config-driven settings domains in `packages/core/config/settings.php`: settings are schema-defined (no migration per setting), stored in typed columns, and resolved user override, then system value, then config default.
- Twig templates in `packages/core/resources/views` and public templates in `packages/core/resources/templates`.

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

The seeded super-admin's credentials come from `.env`, not a fixed default; set `DEV_USER_EMAIL`, `DEV_USER_NAME`, and `DEV_USER_PASSWORD` for a predictable local login. Each of the three falls back independently to a random 16-character string if left blank, so leaving all three empty does **not** produce a known login. `UsersSeeder` also skips itself entirely when `APP_ENV=production`; see [Deployment](#deployment) below.

Reset, migrate, and seed the database in one command:

```bash
php artisan migrate:fresh --seed
```

Generate the app key (run this last; it's the final setup step, not a prerequisite for migrating/seeding):

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
web root at `public/`, not the repo root; `public/index.php` is the front
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
of which web server fronts the app; see [Useful Commands](#useful-commands)
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

- `GET|PUT /api/v1/account`, plus `PUT /api/v1/account/password` and `PUT /api/v1/account/email`
- `/api/v1/users`
- `/api/v1/entry-groups`, with entries nested at `/api/v1/entry-groups/{group}/entries`
- `/api/v1/category-groups`, with categories nested at `/api/v1/category-groups/{group}/categories`
- `/api/v1/status-groups`
- `/api/v1/statuses`

Every API route is wrapped in `LogRequestResponse`, which records the request route, method, user ID, sanitized request payload/headers, sanitized response headers, and the response status code to `api_logs`. Response *bodies* are intentionally not captured; only an allowlist-based redaction was feasible for response shapes, which would require ongoing maintenance as resources change, so response-body logging was dropped rather than risk silently leaking sensitive data. `api_logs` rows are pruned daily (90-day retention) via Laravel's scheduler.

Generate Swagger documentation with:

```bash
php artisan l5-swagger:generate
```

## Useful Commands

```bash
php artisan optimize:clear
php artisan config:clear
php artisan route:list --except-vendor
php artisan adastra:doctor
php artisan adastra:validate-class-references
php artisan schedule:run
php artisan queue:work
php artisan adastra:refresh-tokens
```

`adastra:doctor` produces a read-only health report for the installation (exit 0 healthy / 2 failures; `--strict` promotes warnings, `--format=json` for machine-readable output). Checks are ordinary classes tagged `adastra.doctor.checks` in the service provider, so new checks can be added by tagging additional classes.

`adastra:validate-class-references` checks `entry_behaviors.class` (morph alias) and `field_types.object` (FQCN) references before deployment, failing if any are broken. (Also runs as part of `adastra:doctor`; the old `app:validate-class-references` name still works as an alias.)

`app:refresh-tokens` refreshes expiring OAuth tokens through `TokenRefreshService`. It is implemented but not registered in the scheduler, so run it manually or add a schedule entry if automatic refresh is needed.

In production, run the Laravel scheduler every minute (e.g. via cron) so the daily jobs execute: `AdAstra\Jobs\PruneApiLogs` at 02:00, `PruneGateBypassLogs` at 02:10, and `PurgeDeletedMedia` at 03:00 (`packages/core/routes/console.php`). All are dispatched as queued jobs, so an active queue worker must also be running for them to actually execute.
