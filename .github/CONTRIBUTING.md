# Contributing to AdAstra

AdAstra is in alpha, solo-maintained, and the architecture is still moving.
That shapes what's useful right now.

## What's most useful

**Bug reports.** This is the highest-value contribution during alpha by a wide
margin. Use the bug report template and include the `php artisan adastra:doctor`
output; it's read-only and secrets-safe, so it can be pasted directly.

**Small, focused fixes.** A PR that fixes one thing, with a test, is easy to
review and easy to merge.

**Opinions about the content model.** Whether the entry type, field layout, and
behavior abstractions actually fit the content you work with is exactly what
alpha feedback should be answering.

**Opinions about licensing.** The long-term license for core is not settled.
See Section 10 of [LICENSE.md](../LICENSE.md).

## What to hold off on

**Large refactors and architectural changes.** Please open an issue and talk it
through first. Several subsystems are mid-flight, and a big PR against a moving
target usually ends in a rejection that wastes your time more than mine.

**Media layer and dependent field type fixes.** These have known issues already
being worked for Alpha 3. Bug reports are still welcome, so duplicates get
closed rather than fixed twice, but patches may collide with work in progress.

**New field types.** Happy to discuss them in an issue, but the field type
contract may still change before beta.

## Before you open a pull request

Open an issue first for anything beyond a focused fix.

Then:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed

# tests
php artisan test
php artisan test --filter=YourTest

# style, required before submitting
vendor/bin/pint --dirty
```

See [README.md](../README.md#setup) for full setup, including the `DEV_USER_*`
environment variables and why `key:generate` runs last.

Tests live in `packages/core/tests` and run against SQLite at
`database/testing.sqlite`. If the test database is missing or stale:

```bash
php artisan migrate --env=testing
```

## Standards

- PSR-12, enforced by Pint. CI will fail on style violations.
- New behavior needs a test. Bug fixes should include a test that fails without
  the fix.
- Database structure changes need a migration; don't edit existing migrations
  that have shipped in a release.
- Update `CHANGELOG.md` for anything user-facing, matching the existing
  `ADDED` / `FIXED` / `UPDATED` format with the issue number.
- New polymorphic models must be registered in the morph map in
  `AppServiceProvider::boot()`. Don't rely on default class-name resolution.

## Where code goes

Almost everything lives in `packages/core/`, which is the `adastra/core`
Composer package. The repo root is a thin Laravel host application. Composer
and artisan commands still run from the repo root.

## Licensing of contributions

By submitting a pull request you agree to Section 5 of the
[AdAstra Alpha Evaluation License](../LICENSE.md#5-feedback-and-contributions),
which grants a perpetual, sublicensable license to use and relicense your
contribution as part of AdAstra, including under future commercial terms.

You keep copyright in what you write. This grant exists so the project can
change its license later without having to track down every contributor. If
that's not something you want to agree to, a detailed bug report is just as
valuable and carries no such terms.

## Conduct

Be decent. Technical criticism of the software is welcome and encouraged;
criticism of people is not. I reserve the right to close or block on that basis
without a lengthy discussion about it.
