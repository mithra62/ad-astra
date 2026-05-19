# BotBlock Layer — Code Review & Improvement Suggestions

## Overview

The BotBlock layer is a lightweight, homegrown anti-bot mechanism designed to block automated POST submissions to the application's public authentication forms (login, forgot-password, reset-password). It uses a JavaScript-dependent, server-issued token approach: a one-time token is generated on page load, stored in the database, and validated on form submission. Requests that arrive without a valid token are rejected with a `403 Forbidden` response.

---

## How It Works

### Components

| Component | File |
|---|---|
| Service Provider | `app/Providers/BotBlockServiceProvider.php` |
| Middleware | `app/Http/Middleware/BotBlockRequest.php` |
| Model | `app/Models/BbValue.php` |
| DB Migration | `database/migrations/2025_12_02_031009_create_bot_block_table.php` |
| Form Template | `resources/views/_inc/_bb.twig` |
| Registration | `config/fortify.php` (middleware stack) |

### Request Lifecycle

**1. Page render — token issuance**

When a form that includes `{{ include('_inc._bb') }}` is rendered, the Twig template calls `app('bb-field')`, which resolves the `bb-field` singleton registered in `BotBlockServiceProvider`. The singleton (resolved once per request):

- Generates a random MD5 hash: `md5(str()->random())`
- Generates a random field name: `'_' . str()->random()`
- Persists a row to `bb_values` with the hash, the field name, and the requesting IP address
- Returns the hash value

The template then renders an invisible `<div>` containing a hidden input (`name="__bb"`) with a placeholder value of `"foo"`. An inline `<script>` tag immediately overwrites the input's value with the real token:

```html
<div style="position: absolute !important; height: 0 !important; overflow: hidden !important;">
    <input type="text" id="__bb" name="__bb" value="foo"/>
</div>
<script type="text/javascript">
    document.getElementById("__bb").value = '{{ app('bb-field') }} '
</script>
```

**2. Form submission — token validation**

On every `POST` request from an unauthenticated user, `BotBlockRequest` middleware intercepts the request:

- Looks up `bb_values` for a record where `field_value` matches the submitted `__bb` parameter
- If no matching record is found → `abort(403)`
- If a record is found → delete it (one-time use) and let the request proceed

```php
public function handle(Request $request, Closure $next): mixed
{
    $user = Auth::user();
    if (strtolower($request->method()) === 'post' && !$user) {
        $bb = BbValue::where(['field_value' => $request->post('__bb')])->first();
        if (!$bb instanceof BbValue) {
            abort(403);
        }
        $bb->delete();
    }
    return $next($request);
}
```

**3. Middleware registration**

`BotBlockRequest` is registered in `config/fortify.php` as part of the Fortify middleware group, meaning it applies to all Fortify-managed routes: login, two-factor auth, password reset, email verification, etc.

### Bot-prevention logic

The approach relies on the assumption that bots do not execute JavaScript. A raw HTTP scraper submitting the form directly will carry either no `__bb` value or the default placeholder `"foo"`, neither of which exists in `bb_values`, so the middleware blocks them. A real browser executes the inline script and sends the correct server-issued token.

---

## Issues and Suggested Improvements

### 1. The `field_name` column is stored but never validated

The service provider generates a random `field_name` string and stores it alongside the token, but `BotBlockRequest` only queries by `field_value`. The `field_name` plays no role in validation at all. This looks like an unfinished design — perhaps the intent was to randomize the HTML `name` attribute of the hidden input (making the field name itself unpredictable per load), which would add another layer of bot resistance. As it stands, the column is dead weight.

**Suggestion:** Either implement the randomized field name (the template renders `name="{{ field_name }}"` and the middleware checks for that dynamic key), or drop the `field_name` column entirely to avoid confusion.

---

### 2. Tokens never expire — the `bb_values` table grows without bound

There is no TTL, no scheduled pruning, and no max-age on `bb_values` rows. Every page load that renders `_bb.twig` writes a row. If the user abandons the form (very common), the row is never consumed and never cleaned up. Over time this table will grow indefinitely.

**Suggestion:** Add a `expires_at` timestamp column (e.g., 15–30 minutes from creation), index it, and create an artisan command or scheduled task that prunes expired rows:

```php
// In migration
$table->timestamp('expires_at')->nullable()->index();

// In a scheduled command or AppServiceProvider::boot()
BbValue::where('expires_at', '<', now())->delete();
```

Register the cleanup in `app/Console/Kernel.php` (or the `schedule` method in `routes/console.php`) to run hourly.

---

### 3. IP address is stored but not validated during submission

The token record stores the IP address of the page-loader, but the middleware does not check whether the submitting request comes from the same IP. A sophisticated bot could load the form page (to obtain a valid token) and then replay that token from a different IP, or harvest tokens from legitimate users via CSRF-style side channels.

**Suggestion:** Validate IP on submission:

```php
$bb = BbValue::where([
    'field_value' => $request->post('__bb'),
    'ip_address'  => $request->ip(),
])->first();
```

Be aware this can cause false positives for users behind NAT that changes mid-session, or those using VPNs with rotating egress IPs — so the trade-off should be a conscious decision.

---

### 4. No logging of blocked requests

When the middleware aborts with 403, the event is completely silent. There is no record of how often bots are blocked, which IPs are attacking, or whether any legitimate users are being incorrectly blocked (false positives).

**Suggestion:** Log blocked attempts before aborting:

```php
if (!$bb instanceof BbValue) {
    logger()->warning('BotBlock: rejected POST', [
        'ip'     => $request->ip(),
        'path'   => $request->path(),
        'bb_val' => $request->post('__bb'),
    ]);
    abort(403);
}
```

This makes attack analysis and false-positive debugging possible.

---

### 5. `abort(403)` gives no user-friendly feedback

When JavaScript is disabled (or runs very slowly), a genuine user will submit the form with `"foo"` as the token value and receive a raw 403 with no context. The user has no idea what went wrong or what to do.

**Suggestion:** Redirect back with a validation-style error rather than a hard abort:

```php
return redirect()->back()->withErrors([
    '__bb' => 'Form validation failed. Please ensure JavaScript is enabled and try again.',
]);
```

Or, at minimum, serve a rendered view instead of Laravel's default 403 blade.

---

### 6. Trailing space inside the JavaScript string literal

The template contains a trailing space inside the string passed to JavaScript:

```javascript
document.getElementById("__bb").value = '{{ app('bb-field') }} '
//                                                               ^ space here
```

This means the submitted token will always have a trailing space appended. The middleware currently uses an exact-match query (`where(['field_value' => $request->post('__bb')])`), so this only works because the browser submits the trailing space and it happens to match. Any future change that trims input (e.g., request sanitization middleware) will silently break all bot-block validation. It also means the stored `field_value` does *not* match what's rendered — the stored value is the clean hash, but the submitted value has a space appended.

**Suggestion:** Remove the trailing space from the template:

```javascript
document.getElementById("__bb").value = '{{ app('bb-field') }}'
```

---

### 7. `str()->random()` produces a URL-safe string that doesn't need MD5

`str()->random()` already returns a cryptographically adequate random string. Wrapping it in `md5()` adds nothing and could be misleading (MD5 is deprecated for cryptographic purposes, though it is fine here since the input is already random). The double-pass also obscures the intent.

**Suggestion:** Use `Str::uuid()` or `bin2hex(random_bytes(16))` directly for clarity and semantic accuracy:

```php
$value = bin2hex(random_bytes(16)); // 32-char hex string, cryptographically random
```

---

### 8. Middleware scope is broader than expected

`BotBlockRequest` is registered in `config/fortify.php`'s `middleware` array, which applies to *all* Fortify-managed routes — including routes that don't render the `_bb.twig` template (e.g., two-factor challenge, email verification). On those routes, the middleware will block every unauthenticated POST because no token was ever issued for them.

This may be intentional (those routes are expected to be reached only by users who already passed through a token-protected step), but it is not documented and could cause surprising 403s if new Fortify features are enabled.

**Suggestion:** Audit which Fortify routes are actually reachable by unauthenticated users without a prior token-issuing page load, and either document the assumption or apply the middleware selectively via route-level assignment.

---

### 9. No middleware tests; model tests don't test behavior

`BbValueTest` only asserts that fillable attributes and the table name are set correctly. There are no tests for:

- `BotBlockRequest` allowing a valid token through
- `BotBlockRequest` returning 403 for an invalid/missing token
- `BotBlockRequest` skipping the check for authenticated users
- `BotBlockRequest` skipping the check for non-POST methods
- `BotBlockServiceProvider` writing a row on resolution
- One-time-use behavior (the record is deleted after a valid submission)

**Suggestion:** Add feature tests covering these paths. A minimal test class for the middleware would look like:

```php
class BotBlockRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_token_allows_post(): void
    {
        $token = app('bb-field'); // resolves singleton, writes row
        $this->post('/login', ['__bb' => trim($token), ...])
             ->assertRedirect(); // not 403
    }

    public function test_invalid_token_returns_403(): void
    {
        $this->post('/login', ['__bb' => 'bogus'])->assertForbidden();
    }

    public function test_missing_token_returns_403(): void
    {
        $this->post('/login', [])->assertForbidden();
    }

    public function test_authenticated_user_bypasses_check(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/some-protected-route', [])
             ->assertNotForbidden(); // 403 should not come from BotBlock
    }
}
```

---

### 10. Singleton is shared across all form inclusions in a single request

Because `app('bb-field')` returns a singleton, if a single HTTP response renders `_inc._bb` more than once (e.g., a page with multiple forms), all instances will share the same token and only one DB row is created. This is currently harmless, but it means only one of the forms can be submitted before the token is consumed.

**Suggestion:** If multi-form pages are ever added, switch from `singleton` to `bind` (resolved fresh each time), or generate tokens per-form with a unique key. Document the current limitation as a known constraint.

---

## Summary Table

| # | Issue | Severity | Effort |
|---|---|---|---|
| 1 | `field_name` stored but never used | Low | Low |
| 2 | No token expiry / table grows unbounded | High | Low |
| 3 | IP not validated on submission | Medium | Low |
| 4 | No logging of blocked requests | Medium | Low |
| 5 | Hard 403 with no user feedback | Medium | Low |
| 6 | Trailing space in JS string literal | Medium | Trivial |
| 7 | Unnecessary MD5 wrapping | Low | Trivial |
| 8 | Middleware scope may be broader than intended | Medium | Low |
| 9 | Middleware and provider have no tests | High | Medium |
| 10 | Singleton behavior with multi-form pages | Low | Low |
