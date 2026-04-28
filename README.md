# Laravel Base

Laravel Base is a Laravel 12 CMS/application foundation with an authenticated admin area, a Sanctum-protected API, Twig-based views, configurable content types, field layouts, media libraries, settings, users, roles, and permissions.

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
- Spatie Permission for roles and permissions
- Spatie Media Library for uploaded media
- Spatie Tags
- TwigBridge and Twig templates
- L5 Swagger for API documentation
- Vite 7 and Tailwind CSS 4
- PHPUnit 11

## Application Areas

- Public site routing through `SiteController`, with route drivers for entry-tree and template-based pages.
- Admin UI under `/admin` for users, roles, account settings, tokens, entries, entry groups, entry types, categories, statuses, fields, field layouts, media libraries, and domain/user settings.
- API routes under `/api/v1`, protected by Sanctum, for users, entries, and the current account.
- Content modeling through entry groups, entry types, fields, field groups, field layouts, statuses, categories, entry relationships, and entry tree routing.
- Config-driven settings domains in `config/settings.php`.
- Twig templates in `resources/views` and public templates in `resources/templates`.

## Setup

Install PHP and JavaScript dependencies:

```bash
composer install
npm install
```

Create and configure your environment file:

```powershell
Copy-Item .env.example .env
php artisan key:generate
```

Or, from a Unix-style shell:

```bash
cp .env.example .env
php artisan key:generate
```

Set the database values in `.env`, including `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD`. The example environment uses MySQL and a `DB_TABLE_PREFIX` of `chp_`.

Run migrations and seed the application:

```bash
php artisan migrate
php artisan db:seed
```

The default seeder creates a super admin user:

- Email: `eric@mithra62.com`
- Password: `password`

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

API request/response logging is handled by `LogRequestResponse` on the API routes. API log pruning is scheduled daily through Laravel's scheduler.

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
```

`app:validate-class-references` checks database-backed entry type and field type class references before deployment.
