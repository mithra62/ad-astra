# Extending the AdAstra Doctor

`php artisan adastra:doctor` runs every registered health check and reports pass/warn/fail per subsystem. Any package or layer can add its own checks — this page is everything you need. (Internal design rationale lives in `private/DOCTOR.md`.)

## The 60-second version

Write a check:

```php
use AdAstra\Doctor\AbstractDoctorCheck;

class PaymentGatewayCheck extends AbstractDoctorCheck
{
    protected string $id = 'shop.payment-gateway';
    protected string $name = 'Payment gateway configured';

    public function run(): iterable
    {
        if (config('shop.gateway') === null) {
            yield $this->fail(
                'No payment gateway configured',
                fixCommand: 'set SHOP_GATEWAY in .env',
            );
        }
    }
}
```

Register it in your service provider:

```php
$this->app->tag([PaymentGatewayCheck::class], 'adastra.doctor.checks');
```

Done. Your check now appears in `adastra:doctor` output under a **Shop** heading, in JSON reports, and in `--only=shop` runs. **Yielding nothing means pass** — that's the whole contract.

## Result vocabulary

| Yield | When | Rule of thumb |
|---|---|---|
| `$this->pass($msg)` | the thing you checked is healthy (optional — yielding nothing also passes, but an explicit pass gives the report a friendlier line) | — |
| `$this->warn($msg, ...)` | works today, bites later | *will bite later* |
| `$this->fail($msg, ...)` | the installation is broken or misconfigured | *broken install* |
| `$this->skip($msg)` | the check doesn't apply in this environment | *not applicable here* |

`warn` and `fail` accept optional named arguments: `details` (extra context line), `fixCommand` (the exact command that fixes it — shown in the report and surfaced to `adastra:repair` in the future), and `docsUrl`.

Yield as many results as you find — one per missing table, one per broken reference. Each shows as its own report line.

## IDs and categories

- IDs are `category.check-name`, kebab-case: `shop.payment-gateway`.
- The category derives from the prefix automatically and becomes the report heading (`shop` → **Shop**). New categories need no registration — they just appear.
- **IDs are stable forever.** JSON consumers, `--only`/`--except`, and support tooling key off them. If you rename a check, keep the old ID.

## Dependencies

If your check needs a working database (or anything another check verifies), declare it:

```php
public function dependsOn(): array
{
    return ['database.connection'];  // check ids or whole categories
}
```

When a dependency fails, your check is reported as *skipped* instead of producing a redundant failure — don't re-implement connection guards inside `run()`. Dependencies also run automatically when someone filters with `--only=shop`.

## Hard rules (non-negotiable)

1. **Read-only.** A check never writes, mutates, migrates, or repairs. Doctor's trustworthiness rests on being incapable of surprise.
2. **Secrets-safe.** Reports get pasted into public GitHub issues. Presence and booleans only: "APP_KEY exists", never the value. No credentials, tokens, or connection strings — not even in `details`.
3. **Environment-sensitive.** When your check can't apply (wrong DB driver, missing optional feature), yield `skip` — never `fail`. The test suite runs on SQLite; a check that assumes MySQL will break `composer test`.

A check that throws doesn't take doctor down — the runner converts the crash into a fail result — but treat that as a safety net, not error handling.

## Testing your check

Instantiate it directly and assert on the yielded results — no console plumbing:

```php
public function test_warns_when_gateway_missing(): void
{
    config(['shop.gateway' => null]);

    $results = iterator_to_array((new PaymentGatewayCheck())->run(), false);

    $this->assertSame(DoctorStatus::Fail, $results[0]->status);
}
```

`Tests\Unit\Doctor\DoctorChecksGuardTest` automatically validates every tagged check (unique well-formed IDs, resolvable dependencies, no cycles) — if your registration is wrong, CI tells you.

## What you get for free

Table and JSON output, `--only`/`--except` filtering, `--strict` exit-code semantics, dependency skipping, and crash containment. You write zero presentation code — just yield results.
