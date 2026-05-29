# Alpha Readiness Report

> **Date:** 2026-05-25
> **Reviewer:** Codebase audit pass against `develop`
> **Scope:** Security, correctness, and consistency issues blocking a public Alpha
> announcement. No new features — every finding here is a "tighten before ship" item.
>
> Each finding lists severity, where it lives, the actual ramification, and one or
> more code-level resolutions. Where there is room for product judgement, multiple
> options are given.

---

## How to read this report

| Severity     | Meaning                                                                                                            |
|--------------|--------------------------------------------------------------------------------------------------------------------|
| **Critical** | Active or near-active security defect. Fix before any external eye sees the build.                                 |
| **High**     | Exploitable under realistic conditions or causes data corruption / lockout. Fix in Alpha week.                     |
| **Medium**   | Real bug that will leak into bug reports during Alpha. Visible to users but not catastrophic.                      |
| **Low**      | Hardening / hygiene. Worth scheduling, won't sink the announcement.                                                |
| **Info**     | Documentation drift or stale claim — `docs/OVERVIEW.md` says X, code does Y. Pick one and align before announcing. |

Counts at the time of writing: **3 Critical, 8 High, 7 Medium, 6 Low, 5 Info.**

---

## Table of Contents

- [Critical](#critical)
    - [C-1. `UpdateUserPassword::update()` has its current-password check commented out](#c-1-updateuserpasswordupdate-has-its-current-password-check-commented-out)
    - [C-2. Authenticated upload endpoint has no permission check](#c-2-authenticated-upload-endpoint-has-no-permission-check)
    - [C-3. `User` and `Entry` role/role-assignment validation allows privilege escalation](#c-3-user-and-entry-rolerole-assignment-validation-allows-privilege-escalation)
- [High](#high)
    - [H-1. OAuth callback does not regenerate the session — fixation risk](#h-1-oauth-callback-does-not-regenerate-the-session--fixation-risk)
    - [H-2. `LogRequestResponse` never writes `response_payload`](#h-2-logrequestresponse-never-writes-response_payload)
    - [H-3. `redirect_url` field is unvalidated — open redirect / XSS via `javascript:`](#h-3-redirect_url-field-is-unvalidated--open-redirect--xss-via-javascript)
    - [H-4. Personal access token flashed in URL session flash string](#h-4-personal-access-token-flashed-in-url-session-flash-string)
    - [H-5. `type_handle` in entry create is not bound to the route's `group_id`](#h-5-type_handle-in-entry-create-is-not-bound-to-the-routes-group_id)
    - [H-6. `User` fillable exposes status columns to mass-assignment](#h-6-user-fillable-exposes-status-columns-to-mass-assignment)
    - [H-7. `Html` field type silently accepts arbitrary script content (stored XSS)](#h-7-html-field-type-silently-accepts-arbitrary-script-content-stored-xss)
    - [H-8. CORS is wide-open (`*` origins, `*` methods, `*` headers)](#h-8-cors-is-wide-open--origins--methods--headers)
- [Medium](#medium)
    - [M-1. `categories.*` doesn't enforce membership in the entry's group](#m-1-categories-doesnt-enforce-membership-in-the-entrys-group)
    - [M-2. `Api\v1\User` gate-checks the wrong permission name](#m-2-apiv1user-gate-checks-the-wrong-permission-name)
    - [M-3. `Api\v1\Account@show` returns a placeholder](#m-3-apiv1accountshow-returns-a-placeholder)
    - [M-4. `Api\v1\User::update()` does not require a permission check](#m-4-apiv1userupdate-does-not-require-a-permission-check)
    - [M-5. `Api\v1\Entries::update()` and `::store()` skip permission checks](#m-5-apiv1entriesupdate-and-store-skip-permission-checks)
    - [M-6. `UserService::updateToken()` accepts arbitrary fields from the request](#m-6-userserviceupdatetoken-accepts-arbitrary-fields-from-the-request)
    - [M-7. Social-login user can be created with status `active` if setting is null](#m-7-social-login-user-can-be-created-with-status-active-if-setting-is-null)
- [Low](#low)
    - [L-1. `TemplateRouteDriver` clobbers the `admin` view namespace as a side effect](#l-1-templateroutedriver-clobbers-the-admin-view-namespace-as-a-side-effect)
    - [L-2. Media library `handle` is unrestricted — path-traversal via storage folder](#l-2-media-library-handle-is-unrestricted--path-traversal-via-storage-folder)
    - [L-3. SVG / HTML uploads on a public disk become XSS vectors when served](#l-3-svg--html-uploads-on-a-public-disk-become-xss-vectors-when-served)
    - [L-4. `BotBlockRequest` ignores `PUT`, `PATCH`, `DELETE`](#l-4-botblockrequest-ignores-put-patch-delete)
    - [L-5. `Entry` fillable exposes `created_by_user_id`](#l-5-entry-fillable-exposes-created_by_user_id)
    - [L-6. `EntryTreeRouteDriver` redirect-status is hard-coded to 302](#l-6-entrytreeroutedriver-redirect-status-is-hard-coded-to-302)
- [Info / Documentation drift](#info--documentation-drift)
    - [I-1. `docs/OVERVIEW.md` "EntryResource is user-shaped" is now stale](#i-1-docsoverviewmd-entryresource-is-user-shaped-is-now-stale)
    - [I-2. `docs/OVERVIEW.md` "`Api\v1\Account@show` returns a placeholder" — confirm and remove](#i-2-docsoverviewmd-apiv1accountshow-returns-a-placeholder--confirm-and-remove)
    - [I-3. `Html` field `allowed_tags` setting is dead](#i-3-html-field-allowed_tags-setting-is-dead)
    - [I-4. `app:refresh-tokens` is still a scaffold](#i-4-apprefresh-tokens-is-still-a-scaffold)
    - [I-5. `site.templates.base_path` / `not_found_template` are unused](#i-5-sitetemplatesbase_path--not_found_template-are-unused)
- [Pre-Alpha checklist](#pre-alpha-checklist)

---

## Critical

### C-1. `UpdateUserPassword::update()` has its current-password check commented out

**Location:** [app/Actions/User/UpdateUserPassword.php](app/Actions/User/UpdateUserPassword.php:22-31)

The action implements Fortify's `UpdatesUserPasswords` contract but its `current_password` and `password` validation block is commented out:

```php
public function update(User $user, array $input): void
{
//        Validator::make($input, [
//            'current_password' => ['required', 'string', 'current_password:web'],
//            'password' => $this->passwordRules(),
//        ], [
//            'current_password.current_password' => __('The provided password does not match your current password.'),
//        ])->validateWithBag('updatePassword');

    app(UserService::class)->setPassword($user, $input['password']);
}
```

**Ramification.**

1. The Fortify `/user/password` endpoint (enabled via `Features::updatePasswords()` in [config/fortify.php](config/fortify.php:155)) **does not verify the current password**. A hijacked session is enough to fully take over the account — there is no second factor on the password-change action.
2. The same action is bound through `Fortify::updateUserPasswordsUsing(UpdateUserPassword::class)` in [app/Providers/FortifyServiceProvider.php](app/Providers/FortifyServiceProvider.php:77), so this affects all front-channel password updates.
3. `PasswordValidationRules` (length, complexity) is also bypassed — any string will be accepted, including an empty one if `password` happens to be empty (it won't be, but there is no `min:8` guard either).

**Resolution.** Re-enable the validation. There is no architecturally valid reason for this block to be commented; if there was a test failure that drove the commenting-out, fix the test instead of the action.

```php
// app/Actions/User/UpdateUserPassword.php
public function update(User $user, array $input): void
{
    Validator::make($input, [
        'current_password' => ['required', 'string', 'current_password:web'],
        'password'         => $this->passwordRules(),
    ], [
        'current_password.current_password' => __('The provided password does not match your current password.'),
    ])->validateWithBag('updatePassword');

    app(UserService::class)->setPassword($user, $input['password']);
}
```

If admin-driven password resets (the `/admin/users/{id}/password` flow) genuinely need to skip the current-password check, route that flow through `ResetUserPassword` (which is the no-current-check action — see [app/Actions/User/ResetUserPassword.php](app/Actions/User/ResetUserPassword.php)) and require either the `manage user status` or a new `reset user password` permission on `PasswordUserRequest`:

```php
// app/Http/Requests/User/PasswordUserRequest.php
public function authorize(): bool
{
    // edit user is not enough — this bypasses the current-password gate.
    return Auth::user()->can('manage user status');
}
```

---

### C-2. Authenticated upload endpoint has no permission check

**Location:**
- Route: [routes/admin.php:90](routes/admin.php:90)
- Controller: [app/Http/Controllers/Admin/Media/Library.php:141-162](app/Http/Controllers/Admin/Media/Library.php:141)
- Request: [app/Http/Requests/Media/Library/UploadMediaRequest.php:10-13](app/Http/Requests/Media/Library/UploadMediaRequest.php:10)

```php
// UploadMediaRequest.php
public function authorize(): bool
{
    return true;
}
```

The seeded `user` role only carries `access admin`. Combined with the wide-open `authorize()`, **any logged-in user — including a freshly self-registered one — can upload files to any media library**. The only constraints are the library's `allowed_types` and `max_size`. With the default avatars library accepting images, this becomes a free file-hosting service tied to your domain, plus a denial-of-service vector (consume disk, fill quotas).

**Resolution — two options:**

*Option A (recommended).* Bind upload to existing seeded permissions; reuse `edit media library` since uploading is functionally a write into the library.

```php
// app/Http/Requests/Media/Library/UploadMediaRequest.php
public function authorize(): bool
{
    return Auth::user()?->can('edit media library') === true;
}
```

*Option B.* Add a dedicated permission (cleaner long-term):

```php
// database/seeders/RolesPermissionsSeeder.php  — add to the seeded permission list
['name' => 'upload media', 'description' => 'Upload files into a media library'],

// app/Http/Requests/Media/Library/UploadMediaRequest.php
public function authorize(): bool
{
    return Auth::user()?->can('upload media') === true;
}
```

Either way, also remove the lazy `return true;` everywhere it appears in `app/Http/Requests/**`. A quick `grep -rn 'return true' app/Http/Requests` will surface any other unguarded request classes.

---

### C-3. `User` and `Entry` role/role-assignment validation allows privilege escalation

**Location:**
- [app/Http/Requests/User/StoreUserRequest.php:29-30](app/Http/Requests/User/StoreUserRequest.php:29)
- [app/Http/Requests/User/EditUserRequest.php:25-26](app/Http/Requests/User/EditUserRequest.php:25)

```php
'roles'   => ['required', 'array'],
'roles.*' => ['string', 'exists:roles,name'],
```

`exists:roles,name` allows **any** role row in the table — including `super admin`. A user with `create user` or `edit user` permission can therefore mint a super-admin, or promote themselves to one via edit. Combined with C-2 and L-1 this becomes a full account takeover chain.

`UserService::create()` / `UserService::update()` then call `$user->syncRoles((array)$data['roles']);` with no further gate ([app/Services/UserService.php:264-269](app/Services/UserService.php:264) and [:528-531](app/Services/UserService.php:528)), so there is no second line of defence.

**Resolution.** Constrain `roles.*` to the set the *current actor* is allowed to assign. The simplest rule that doesn't require new tables: only `super admin` can assign `super admin`.

```php
// app/Http/Requests/User/StoreUserRequest.php
use Illuminate\Validation\Rule;

public function rules(): array
{
    $assignable = $this->assignableRoleNames();

    return array_merge([
        // ...
        'roles'   => ['required', 'array'],
        'roles.*' => ['string', Rule::in($assignable)],
    ], $this->schemaFieldRules(UserFieldLayout::resolve()));
}

private function assignableRoleNames(): array
{
    $actor = Auth::user();

    return \Spatie\Permission\Models\Role::query()
        ->when(! $actor?->hasRole('super admin'), fn ($q) => $q->where('name', '!=', 'super admin'))
        ->pluck('name')
        ->all();
}
```

Apply the same to `EditUserRequest`. Then, as defence in depth, harden the service:

```php
// app/Services/UserService.php
public function syncRoles(User $user, array $roles): User
{
    $actor = auth()->user();

    if (in_array('super admin', $roles, true) && ! $actor?->hasRole('super admin')) {
        throw \Illuminate\Auth\Access\AuthorizationException::class
            ::denyAsNotFound('Only a super admin may assign the super admin role.');
    }

    $user->syncRoles($roles);
    return $user;
}
```

For Alpha, the FormRequest fix is sufficient — the service-layer guard can ship in the next sprint.

---

## High

### H-1. OAuth callback does not regenerate the session — fixation risk

**Location:** [app/Http/Controllers/Login.php:43](app/Http/Controllers/Login.php:43)

```php
Auth::login($localUser, true);
```

Fortify's stock login pipeline calls `$request->session()->regenerate()` after successful authentication; the custom social-login controller does not. If an attacker can plant a known session ID on a victim's browser (XSS on a sibling subdomain, malicious link with `?phpsessid=…`, anything that survives the OAuth round-trip), the attacker keeps using that same session ID and inherits the victim's authenticated state once they complete OAuth.

**Resolution.**

```php
// app/Http/Controllers/Login.php
public function handleProviderCallback(Request $request, string $provider)
{
    try {
        $socialUser = Socialite::driver($provider)->user();
    } catch (InvalidStateException) {
        return redirect()->route('login')
            ->withErrors(['oauth' => __('auth.oauth_state_invalid')]);
    }

    $localUser = Users::firstOrCreateFromSocial(
        $socialUser->getEmail(),
        $socialUser->getName(),
        $provider,
        $request->ip(),
    );

    if (! $localUser->canAccessSystem()) {
        return redirect()->route('login')
            ->withErrors(['email' => trans('auth.' . ($localUser->accessDeniedReason() ?? 'account_inactive'))]);
    }

    Auth::login($localUser, true);
    $request->session()->regenerate();          // <-- add this
    $request->session()->regenerateToken();     // <-- and this (CSRF token)

    return redirect()->intended('/');
}
```

Also add a `throttle:10,1` middleware to the social login routes — they currently have no rate limit:

```php
// routes/web.php
Route::middleware('throttle:10,1')->group(function () {
    Route::get('login/{provider}',          [Login::class, 'redirectToProvider'])->name('social.login.provider');
    Route::get('login/{provider}/callback', [Login::class, 'handleProviderCallback'])->name('social.login.callback');
});
```

---

### H-2. `LogRequestResponse` never writes `response_payload`

**Location:** [app/Http/Middleware/LogRequestResponse.php:59-73](app/Http/Middleware/LogRequestResponse.php:59)

`ApiLog::create([...])` writes `request_payload`, `request_headers`, `response_headers`, `response_status_code` — but **not** `response_payload`, despite the column existing and `summarizeResponse()` being defined in the same file (lines 151-165). The method is dead code.

`docs/OVERVIEW.md` § "API Request/Response Logging" claims this column carries the JSON body or error summary; today it is always `NULL`. That's a P0 for incident response: when a real customer hits a 500, ops has the request, status code, and timing — but not the rendered body. Postmortems become guesswork.

**Resolution.**

```php
// app/Http/Middleware/LogRequestResponse.php
public function handle(Request $request, Closure $next): mixed
{
    $response = $next($request);

    ApiLog::create([
        'request_route'        => $request->getPathInfo(),
        'method'               => $request->method(),
        'user_id'              => Auth::id(),
        'request_payload'      => $this->encodeForLog($this->sanitizeValue($request->all())),
        'request_headers'      => $this->encodeForLog($this->sanitizeHeaders($request->headers->all())),
        'response_payload'     => $this->summarizeResponse($response),  // <-- add
        'response_headers'     => $this->encodeForLog($this->sanitizeHeaders($response->headers->all())),
        'response_status_code' => $response->status(),
    ]);

    return $response;
}
```

While editing, fix the implicit assumption on line 70 that `$response->headers` is always populated — for `StreamedResponse` from `Storage::download()` it can be partially populated when the middleware ends. Today this is fine in practice because the middleware is only wired onto the API group, but a future engineer wiring this onto admin downloads would be surprised.

---

### H-3. `redirect_url` field is unvalidated — open redirect / XSS via `javascript:`

**Location:**
- Validation: [app/Http/Requests/Entry/StoreEntryRequest.php:58](app/Http/Requests/Entry/StoreEntryRequest.php:58) and [EditEntryRequest.php:59](app/Http/Requests/Entry/EditEntryRequest.php:59)
- Consumer: [app/Services/SiteRouting/RouteDrivers/EntryTreeRouteDriver.php:29-39](app/Services/SiteRouting/RouteDrivers/EntryTreeRouteDriver.php:29)

```php
'redirect_url' => ['nullable', 'string', 'prohibited_if:is_home,true'],
```

…then:

```php
return new RouteResult(
    type: 'entry_tree_redirect',
    template: '',
    data: ['url' => $node->redirect_url, 'status' => 302],
    resource: $node,
);
```

`SiteRouter::render()` consumes this with `redirect()->away($result->data['url'], 302)`. There is no scheme allowlist. An admin (or anyone with content-edit) can store `javascript:alert(document.cookie)` and turn the public-facing tree node into a one-tap account-stealer for every signed-in visitor who clicks the link. `redirect()->away()` happily emits arbitrary `Location:` headers and the browser will navigate to anything with a parseable scheme.

It's also an **open redirect** — any external URL routes through your domain, which is the standard phishing-launchpad pattern.

**Resolution.** Validate with `url` and an explicit scheme allowlist. Two layers (request + router) for defence in depth.

```php
// app/Http/Requests/Entry/StoreEntryRequest.php and EditEntryRequest.php
'redirect_url' => [
    'nullable',
    'url:http,https',
    'max:2048',
    'prohibited_if:is_home,true',
],
```

```php
// app/Services/SiteRouting/RouteDrivers/EntryTreeRouteDriver.php
private function isSafeRedirect(?string $url): bool
{
    if (! $url) {
        return false;
    }
    $scheme = parse_url($url, PHP_URL_SCHEME);
    return in_array(strtolower((string) $scheme), ['http', 'https'], true);
}

public function resolve(?string $uri): ?RouteResult
{
    // ... same lookup as before ...

    if (filled($node->redirect_url) && $this->isSafeRedirect($node->redirect_url)) {
        return new RouteResult(
            type: 'entry_tree_redirect',
            template: '',
            data: ['url' => $node->redirect_url, 'status' => 302],
            resource: $node,
        );
    }

    // ... fall through to template render ...
}
```

If product wants to allow relative-path redirects (`/about` style), branch:

```php
private function isSafeRedirect(?string $url): bool
{
    if (! $url) return false;
    if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) return true;  // relative
    $scheme = parse_url($url, PHP_URL_SCHEME);
    return in_array(strtolower((string) $scheme), ['http', 'https'], true);
}
```

---

### H-4. Personal access token flashed in URL session flash string

**Location:** [app/Http/Controllers/Admin/User/Token.php:38](app/Http/Controllers/Admin/User/Token.php:38) (the user-token flow) — and presumably the same pattern in `Admin/Account/Token.php`.

```php
return redirect()->route('users.edit', $user)
    ->with('success', __('user.token_created') . ' - ' . $token);
```

The plain-text token is concatenated into a generic "success" flash message. This is then rendered into the next page. The consequences:

1. The token will appear in any tool that captures the flash-bag (Telescope, Debugbar, Sentry's breadcrumbs, ELK pipelines that include `session.*` keys).
2. If a user accidentally screenshots and shares the success banner, the token is in the screenshot.
3. The token is not flagged in `LogRequestResponse`'s `sensitiveKeys` array (none of `success`, `flash`, etc. match), so if the post-redirect page is itself an API endpoint, it ends up in `api_logs` cleartext.

**Resolution.** Show the token exactly once on a dedicated view, marked clearly as one-time:

```php
// app/Http/Controllers/Admin/User/Token.php
public function store(StoreUserTokenRequest $request, string $id)
{
    $user = Users::find((int) $id);

    if (! $user instanceof UserModel) {
        return redirect()->route('users.index')->with('failure', trans('user.not_found'));
    }

    $newAccessToken = app(CreateNewUserToken::class)->create($user, $request->validated());

    // One-shot session value, displayed once and immediately forgotten.
    return redirect()
        ->route('users.token.show_once', ['id' => $user->id, 'token_id' => $newAccessToken->accessToken->id])
        ->with('one_time_token', $newAccessToken->plainTextToken);
}

public function showOnce(string $id, string $token_id)
{
    $token = session()->pull('one_time_token');  // pull = read + forget

    if (! $token) {
        return redirect()->route('users.edit', $id)
            ->with('failure', trans('user.token_already_revealed'));
    }

    return $this->view('users.tokens.show_once', [
        'plain_text_token' => $token,
        'user'             => Users::find((int) $id),
    ]);
}
```

Add `'plain_text_token'`, `'one_time_token'` to `LogRequestResponse::$sensitiveKeys` so the middleware redacts them even if some future code path logs the value:

```php
// app/Http/Middleware/LogRequestResponse.php
private array $sensitiveKeys = [
    'password', 'password_confirmation', 'current_password',
    'token', 'api_token', 'access_token', 'refresh_token', 'id_token',
    'plain_text_token', 'one_time_token',                                // <-- add
    'secret', 'client_secret',
    'authorization', 'cookie', 'set-cookie',
];
```

---

### H-5. `type_handle` in entry create is not bound to the route's `group_id`

**Location:**
- Validation: [app/Http/Requests/Entry/StoreEntryRequest.php:28](app/Http/Requests/Entry/StoreEntryRequest.php:28)
- Creation: [app/Actions/Entry/CreateNewEntry.php:13-15](app/Actions/Entry/CreateNewEntry.php:13) and [app/Services/EntryService.php](app/Services/EntryService.php) (`create()`)

```php
'type_handle' => ['required', 'string', 'exists:entry_types,handle'],
```

The rule only checks that the handle exists *somewhere*. The route is `POST /api/v1/entry-groups/{group_id}/entries`, so a caller can submit `group_id = 5` (an entry group they have visibility into) with `type_handle = blog_post` (a type that belongs to group 1). `EntryTypeRegistry::resolveByHandle()` then resolves to whichever type row matches the handle globally — and creates the entry under `entry_group_id = $record->entry_group_id` (i.e., **group 1**, not 5).

Today's seeded data has globally unique type handles, so the failure is hidden. As soon as a customer reuses handles like `article` across groups (which the schema allows: the unique key is `(entry_group_id, handle)`), this becomes a confused-deputy bug: validated as group 5, persisted under group 1.

**Resolution.** Add a closure rule that re-resolves by `(group_id, handle)`:

```php
// app/Http/Requests/Entry/StoreEntryRequest.php
use Illuminate\Validation\Rule;

'type_handle' => [
    'required',
    'string',
    Rule::exists('entry_types', 'handle')->where(fn ($q) =>
        $q->where('entry_group_id', $this->route()->parameter('group_id'))
    ),
],
```

Then in `CreateNewEntry`, prefer resolving by `(group_id, handle)` rather than the global registry path, so the runtime guard matches the validation guard:

```php
// app/Actions/Entry/CreateNewEntry.php
public function create(array $input): Entry
{
    $groupId   = $input['entry_group_id'] ?? request()->route()?->parameter('group_id');
    $typeRecord = \App\Models\EntryType::where('handle', $input['type_handle'])
        ->where('entry_group_id', $groupId)
        ->firstOrFail();

    return Content::create($typeRecord, $input);   // if Content::create() accepts a record;
                                                   // otherwise pass the resolved handle + group.
}
```

If the registry can't accept a record yet, the smaller change is to wire `entry_group_id` into the registry's `resolveByHandle()` second argument, and have `StoreEntryRequest` inject it.

---

### H-6. `User` fillable exposes status columns to mass-assignment

**Location:** [app/Models/User.php:25-33](app/Models/User.php:25)

```php
protected $fillable = [
    'name',
    'email',
    'password',
    'status',
    'suspended_until',
    'banned_at',
    'locked_until',
];
```

`UserService::update()` ([app/Services/UserService.php:222-232](app/Services/UserService.php:222)) explicitly `Arr::except`s these keys, and the status methods use `forceFill()` — good. But the fillable means **any other call site** (Fortify's `CreateNewUser`, future code paths, factory boots, an unrelated controller someone writes next month) can mass-assign them. The careful audit log and event firing infrastructure can be bypassed silently.

**Ramification today.** Modest — Fortify's `CreateNewUser` passes only `name/email/password`. The harm is latent.

**Ramification next month.** Anyone who adds a new endpoint that does `User::create($request->validated())` will be one typo away from letting users self-set their status to `active`, bypass `pending`, etc.

**Resolution.**

```php
// app/Models/User.php
protected $fillable = [
    'name',
    'email',
    'password',
];
```

Then update `UserService::create()` to use a small private helper instead of relying on fillable:

```php
// app/Services/UserService.php
private function buildUserAttributes(array $data): array
{
    $attributes = Arr::only($data, ['name', 'email', 'password', 'status']);

    if (!empty($attributes['password'])) {
        $attributes['password'] = Hash::make($attributes['password']);
    }

    if (empty($attributes['status'])) {
        $attributes['status'] = app(Settings::class)->get('users', 'default_status') ?? UserStatus::ACTIVE;
    }

    return $attributes;
}

public function create(array $data): User
{
    $attributes = $this->buildUserAttributes($data);

    $user = new User();
    $user->forceFill($attributes)->save();   // bypass fillable, intentional for status

    // ... roles, fields, is_author handling unchanged ...
}
```

Update `firstOrCreateFromSocial()` similarly — `forceFill` the status column.

---

### H-7. `Html` field type silently accepts arbitrary script content (stored XSS)

**Location:** [app/Field/Types/Html.php:11-15](app/Field/Types/Html.php:11)

```php
protected array $rules = [
    'nullable',
];
```

The `allowed_tags` setting is declared in `settings_form` but never read anywhere in the application — confirmed with `grep`: the only file mentioning `allowed_tags` is the field type itself. Content-editing users can store `<script>` in any HTML field. When the front-end template renders it (`{{ entry.field('body')|raw }}` or `{!! $entry->field('body') !!}`), the script executes in the visitor's browser.

**Threat model.** "Only editors can edit" is true but insufficient. The whole point of a CMS is multi-author content. A self-XSS-by-an-editor where the editor scripts every other user's session is still a security finding.

**Resolution.** Sanitise on the **write** path (not just render) using `mews/purifier` or `ezyang/htmlpurifier` directly. Sanitisation on render is fragile because too many templates already exist.

```bash
composer require mews/purifier
php artisan vendor:publish --provider="Mews\Purifier\PurifierServiceProvider"
```

```php
// app/Field/Types/Html.php
use Mews\Purifier\Facades\Purifier;

public function prepareForStorage(mixed $value): mixed
{
    if ($value === null || $value === '') {
        return $value;
    }

    return Purifier::clean((string) $value, 'cms');   // 'cms' config defined below
}
```

```php
// config/purifier.php — add a 'cms' profile
'cms' => [
    'HTML.Doctype'             => 'HTML 4.01 Transitional',
    'HTML.Allowed'             => 'h1,h2,h3,h4,p,br,strong,em,u,ol,ul,li,a[href|title],img[src|alt|title],blockquote,code,pre,hr,table,thead,tbody,tr,th,td',
    'HTML.AllowedAttributes'   => 'a.href,a.title,img.src,img.alt,img.title',
    'AutoFormat.AutoParagraph' => false,
    'AutoFormat.RemoveEmpty'   => true,
    'URI.AllowedSchemes'       => ['http' => true, 'https' => true, 'mailto' => true],
],
```

If product wants per-field config, read `allowed_tags` from the field settings and feed it into a per-call Purifier config rather than the global profile:

```php
public function prepareForStorage(mixed $value): mixed
{
    if (blank($value)) {
        return $value;
    }

    $allowed = $this->getSetting('allowed_tags');
    $config  = config('purifier.cms');

    if (! empty($allowed)) {
        $config['HTML.Allowed'] = $allowed;
    }

    return Purifier::clean((string) $value, $config);
}
```

The `allowed_tags` field becomes meaningful instead of decorative.

---

### H-8. CORS is wide-open (`*` origins, `*` methods, `*` headers)

**Location:** [config/cors.php:18-29](config/cors.php:18)

```php
'paths' => ['api/*', 'sanctum/csrf-cookie'],
'allowed_methods' => ['*'],
'allowed_origins' => ['*'],
'allowed_headers' => ['*'],
'supports_credentials' => false,
```

`supports_credentials: false` saves you from cookie-based exfiltration, so the immediate risk is mostly:

1. **Token API abuse.** Anyone's frontend on any origin can hit your API with a Bearer token. If a customer leaks a token, *every* third-party site can use it. There is no Origin check.
2. **Browser-side SSRF.** A malicious site can use any visitor's Bearer token (if they bring one) against your API.

For a public Alpha you almost certainly want a closed allowlist.

**Resolution.** Switch to an env-driven allowlist:

```php
// config/cors.php
'paths'                    => ['api/*', 'sanctum/csrf-cookie'],
'allowed_methods'          => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
'allowed_origins'          => array_filter(explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))),
'allowed_origins_patterns' => array_filter(explode(',', (string) env('CORS_ALLOWED_ORIGINS_PATTERNS', ''))),
'allowed_headers'          => ['Accept', 'Authorization', 'Content-Type', 'X-Requested-With', 'X-XSRF-TOKEN'],
'exposed_headers'          => [],
'max_age'                  => 600,
'supports_credentials'     => filter_var(env('CORS_SUPPORTS_CREDENTIALS', false), FILTER_VALIDATE_BOOLEAN),
```

```dotenv
CORS_ALLOWED_ORIGINS=https://app.example.com,https://docs.example.com
```

If you genuinely want a public, anonymous-readable API (e.g. read-only entry listings for unauthenticated consumers), keep `*` only for those `paths` and split the config:

```php
'paths' => ['api/v1/public/*'],
'allowed_origins' => ['*'],
```

…and gate authenticated endpoints under a separate allowlisted config.

---

## Medium

### M-1. `categories.*` doesn't enforce membership in the entry's group

**Location:** [app/Http/Requests/Entry/StoreEntryRequest.php:49-50](app/Http/Requests/Entry/StoreEntryRequest.php:49) and the same line in `EditEntryRequest`.

```php
'categories'   => ['nullable', 'array'],
'categories.*' => ['integer', 'exists:categories,id'],
```

Any category ID is acceptable, even from a `CategoryGroup` not attached to this entry's `EntryGroup`. The admin UI never offers them, but a hand-crafted request gets through.

**Ramification.** Data integrity. Listings filtered by category produce surprise results; "blog posts tagged with `gardening-zone-7b`" becomes possible because nothing prevents it.

**Resolution.** Scope `exists:` to the attached category groups:

```php
'categories.*' => [
    'integer',
    Rule::exists('categories', 'id')->where(fn ($q) => $q->whereIn(
        'group_id',
        \DB::table('category_groupables')
           ->where('group_type', (new \App\Models\EntryGroup)->getMorphClass())
           ->where('group_id', $this->route()->parameter('group_id'))
           ->pluck('category_group_id')
    )),
],
```

If the pivot table or morph alias differs, adjust accordingly — the principle is the same.

---

### M-2. `Api\v1\User` gate-checks the wrong permission name

**Location:** [app/Http/Controllers/Api/v1/User.php:60](app/Http/Controllers/Api/v1/User.php:60) and `:149`.

```php
if (!$this->can('read users')) {
    abort(404);
}
```

Seeded permissions ([docs/OVERVIEW.md § Built-in Permissions](docs/OVERVIEW.md)) carry `view user`, **not** `read users`. The admin role doesn't have `read users`. The super-admin gate bypass means super admins still work, but anyone else hits a 404 even with the documented permission set.

**Resolution.** Pick a side and align everywhere.

*Option A — change the API gate:*

```php
if (! $this->can('view user')) {
    abort(404);
}
```

*Option B — add the alias to the seeder and prefer plural in the API* (recommended, since the API really is collection-shaped):

```php
// database/seeders/RolesPermissionsSeeder.php
['name' => 'view user',  'description' => 'View a user'],
['name' => 'read users', 'description' => 'List users via the API'],

Role::findByName('admin')->givePermissionTo(['view user', 'read users']);
```

Then leave `'read users'` in the controller. Update the OVERVIEW.md Built-in Permissions table either way.

---

### M-3. `Api\v1\Account@show` returns a placeholder

**Location:** [app/Http/Controllers/Api/v1/Account.php:38-41](app/Http/Controllers/Api/v1/Account.php:38)

```php
public function show()
{
    return response()->json(['message' => 'Profile updated successfully'], 200);
}
```

The OpenAPI annotation right above it advertises a `User` schema response. Customers reading the Swagger doc will write integrations against a shape that does not exist — and "Profile updated successfully" on a GET is just embarrassing.

**Resolution.**

```php
// app/Http/Controllers/Api/v1/Account.php
use App\Http\Resources\Api\UserResource;
use Illuminate\Support\Facades\Auth;

public function show(): UserResource
{
    $user = Auth::user();
    abort_unless($user, 401);

    $user->load(['roles', 'fieldValues.field.fieldType']);

    return new UserResource($user);
}
```

Same treatment for the other Account methods — they all `200 OK 'Profile updated successfully'` regardless of input. Either implement or remove the routes; do not ship dead placeholders behind documented contracts.

---

### M-4. `Api\v1\User::update()` does not require a permission check

**Location:** [app/Http/Controllers/Api/v1/User.php:202-213](app/Http/Controllers/Api/v1/User.php:202)

`store()`, `show()`, `destroy()` all gate-check. `update()` doesn't:

```php
public function update(EditUserRequest $request, int $user): UserResource
{
    $model = UserModel::find($user);
    // ... no $this->can('edit user') check
    Users::update($model, $request->validated());
    // ...
}
```

`EditUserRequest::authorize()` checks `edit user`, so the gate is enforced by the request — but only because of the FormRequest, not the controller. If someone refactors the request away or swaps to `Request $request`, this becomes a public update endpoint. Defence in depth.

**Resolution.**

```php
public function update(EditUserRequest $request, int $user): UserResource
{
    if (! $this->can('edit user')) {
        abort(403);
    }
    // ... rest unchanged
}
```

---

### M-5. `Api\v1\Entries::update()` and `::store()` skip permission checks

**Location:** [app/Http/Controllers/Api/v1/Entries.php:125-132](app/Http/Controllers/Api/v1/Entries.php:125) (store) and `:215-226` (update)

Same shape as M-4 — relies on FormRequest authorize() for `create entry` / `edit entry`. Worth a controller-level guard for symmetry with `destroy()` and to keep the API resilient to refactors.

```php
public function store(StoreEntryRequest $request): JsonResponse
{
    if (! $this->can('create entry')) {
        abort(403);
    }
    // ...
}
```

---

### M-6. `UserService::updateToken()` accepts arbitrary fields from the request

**Location:** [app/Services/UserService.php:182-193](app/Services/UserService.php:182)

```php
public function updateToken(User $user, int|string $tokenId, array $data): ?PersonalAccessToken
{
    $token = $this->getToken($user, $tokenId);
    if (!$token instanceof PersonalAccessToken) {
        return null;
    }
    $token->update($data);   // <-- whatever is in $data, including abilities, expires_at, tokenable_id
    return $token->refresh();
}
```

The caller is `Admin\User\Token::update()`, which passes `$request->validated()`. If `EditUserTokenRequest::rules()` doesn't lock down the allowed keys, a token holder could rewrite `tokenable_id` (transferring the token to another user) or grant `abilities = ['*']`.

**Resolution.** Constrain at the service:

```php
public function updateToken(User $user, int|string $tokenId, array $data): ?PersonalAccessToken
{
    $token = $this->getToken($user, $tokenId);

    if (! $token instanceof PersonalAccessToken) {
        return null;
    }

    $allowed = \Illuminate\Support\Arr::only($data, ['name', 'abilities', 'expires_at']);

    if (isset($allowed['abilities']) && ! is_array($allowed['abilities'])) {
        $allowed['abilities'] = [];
    }

    $token->update($allowed);

    return $token->refresh();
}
```

And re-confirm `EditUserTokenRequest::rules()` covers exactly those keys with strict typing.

---

### M-7. Social-login user can be created with status `active` if setting is null

**Location:** [app/Services/UserService.php:102-127](app/Services/UserService.php:102)

```php
$socialDefaultStatus = app(Settings::class)->get('users', 'social_default_status') ?? UserStatus::PENDING;
```

This is correct — falls back to `pending`. However, look at the table seed: if `users.social_default_status` is later set to `active` by a careless admin under `/admin/settings`, the next social signup is auto-approved. That's exactly what the setting is for, but worth a guard:

**Resolution.** Constrain valid values for that setting at the schema level:

```php
// config/settings.php — under the users domain
'social_default_status' => [
    'handle' => 'social_default_status',
    'label'  => 'Default status for social-login signups',
    'type'   => 'text',
    'default' => 'pending',
    'rules'  => ['required', 'string', 'in:active,inactive,pending'],
    // ^ Rule::in equivalent; document that suspended/banned aren't valid creation-time states
],
```

This way "auto-approve social signups" is a deliberate, validated decision rather than a typo.

---

## Low

### L-1. `TemplateRouteDriver` clobbers the `admin` view namespace as a side effect

**Location:** [app/Services/SiteRouting/RouteDrivers/TemplateRouteDriver.php:17-22](app/Services/SiteRouting/RouteDrivers/TemplateRouteDriver.php:17)

```php
public function __construct(protected Request $request)
{
    View::replaceNamespace('admin', []);
}
```

The constructor mutates a global facade as a side effect every time the container resolves this class. It works today because (a) the public-facing route never re-resolves admin views afterwards, and (b) the container is fresh per request. But this is the kind of footgun that explodes silently — e.g., if a future test boots both an admin view and a `SiteRouter::render()` call in the same request lifecycle, the admin namespace disappears mid-test.

**Resolution.** Use a render-time guard rather than a constructor mutation:

```php
public function __construct(protected Request $request) {}

protected function result(string $view, array $segments, array $extra = []): RouteResult
{
    // Refuse to leak admin views through the public catch-all even if a
    // template happens to share a name with an admin partial.
    if (str_starts_with($view, 'admin::')) {
        throw new \RuntimeException('Refusing to render admin view from public router: ' . $view);
    }

    return new RouteResult(/* ... */);
}
```

The `templates::` prefix is already explicit (line 138), so an `admin::` view should never appear here in practice — but the guard documents intent without action-at-a-distance.

---

### L-2. Media library `handle` is unrestricted — path-traversal via storage folder

**Location:** [app/Http/Requests/Media/Library/StoreMediaLibraryFormRequest.php:26-31](app/Http/Requests/Media/Library/StoreMediaLibraryFormRequest.php:26) and consumer [app/Traits/HasMediaItems.php:35-38](app/Traits/HasMediaItems.php:35).

```php
'handle' => ['required', 'string', 'max:255', Rule::unique(...)],
```

then later:

```php
$folder = $this->handle;
$path   = $file->storeAs($folder, $fileName, $disk);
```

An admin can create a library with handle `../../etc` and uploads land outside the disk root. Laravel's `storeAs` doesn't path-sanitise the directory segment. Today the impact is bounded by the disk visibility (`local`), but on the `public` disk this becomes serving arbitrary files from `storage/app/public/../../etc/whatever`.

**Resolution.** Force the handle to be a slug:

```php
'handle' => [
    'required',
    'string',
    'max:255',
    'regex:/^[a-z0-9][a-z0-9-_]*$/',
    Rule::unique('media_libraries', 'handle')->ignore($library),
],
```

…and add a runtime guard in `HasMediaItems` for defence in depth:

```php
public function addMediaFromUpload(UploadedFile $file, array $attributes = []): Media
{
    // ...
    $folder = preg_replace('/[^a-z0-9_-]/i', '', (string) $this->handle);

    if ($folder === '' || $folder !== $this->handle) {
        throw new \InvalidArgumentException(
            "Library handle [{$this->handle}] is not a valid storage folder name."
        );
    }
    // ...
}
```

---

### L-3. SVG / HTML uploads on a public disk become XSS vectors when served

**Location:** [app/Traits/HasMediaItems.php](app/Traits/HasMediaItems.php), [app/Http/Requests/Media/Library/UploadMediaRequest.php:23-25](app/Http/Requests/Media/Library/UploadMediaRequest.php:23)

```php
if (!empty($library->allowed_types)) {
    $fileRules[] = 'mimetypes:' . implode(',', $library->allowed_types);
}
```

If `allowed_types` for a public-disk library includes `image/svg+xml` (or `text/html`), an attacker uploads `evil.svg` containing `<script>` and the storage URL serves it with the matching `Content-Type`. Browsers execute SVG scripts when fetched as a top-level navigation — instant stored XSS on your domain.

**Resolution.** Either explicitly deny these mimetypes, or force `Content-Disposition: attachment` for public-disk libraries. Easiest first:

```php
// app/Http/Requests/Media/Library/UploadMediaRequest.php
public function rules(): array
{
    $library = $this->resolveLibrary();

    $fileRules = [
        'required',
        'file',
        'not_in_mimetypes:image/svg+xml,text/html,application/xhtml+xml',
    ];
    // ...
}

// Then register the rule in AppServiceProvider::boot()
\Illuminate\Support\Facades\Validator::extend('not_in_mimetypes', function ($attribute, $value, $params) {
    return $value instanceof \Illuminate\Http\UploadedFile
        && ! in_array($value->getMimeType(), $params, true);
}, 'The :attribute has a disallowed file type.');
```

For SVG support that customers genuinely want, sanitise on upload via `enshrined/svg-sanitize`:

```bash
composer require enshrined/svg-sanitize
```

```php
// in HasMediaItems::addMediaFromUpload, before storeAs:
if ($file->getMimeType() === 'image/svg+xml') {
    $sanitizer = new \enshrined\svgSanitize\Sanitizer();
    file_put_contents(
        $file->getRealPath(),
        $sanitizer->sanitize(file_get_contents($file->getRealPath()))
    );
}
```

---

### L-4. `BotBlockRequest` ignores `PUT`, `PATCH`, `DELETE`

**Location:** [app/Http/Middleware/BotBlockRequest.php:15](app/Http/Middleware/BotBlockRequest.php:15)

```php
if (strtolower($request->method()) === 'post' && !$user) {
    // bot-block check
}
```

Bot blockers usually only need to gate registration / login, both of which are POST, so this is mostly fine. Flagged because if any future *unauthenticated* form uses `PUT` or `PATCH` (e.g. an email-magic-link "set password" endpoint), it bypasses the block.

**Resolution.** Block all modifying methods:

```php
public function handle(Request $request, Closure $next): mixed
{
    $modifying = in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true);

    if ($modifying && ! Auth::user()) {
        $bb = BbValue::where('field_value', $request->post('__bb'))->first();

        if (! $bb instanceof BbValue) {
            abort(403);
        }

        $bb->delete();
    }

    return $next($request);
}
```

---

### L-5. `Entry` fillable exposes `created_by_user_id`

**Location:** [app/Models/Entry.php:28-38](app/Models/Entry.php:28)

`EntryRepository::create()` correctly sets `created_by_user_id` from `Auth::id()`, but a future caller doing `Entry::create($request->validated())` (skipping the repository) lets the requester pick the creator. Same defence-in-depth argument as H-6.

**Resolution.**

```php
protected $fillable = [
    'entry_group_id',
    'entry_type_id',
    'title',
    'handle',
    'published_at',
    'status_id',
    'status_handle',
    'status_is_public',
];
```

Drop `created_by_user_id` from fillable. The repository already assigns it directly (line 37 of `EntryRepository`) so nothing else needs to change.

---

### L-6. `EntryTreeRouteDriver` redirect-status is hard-coded to 302

**Location:** [app/Services/SiteRouting/RouteDrivers/EntryTreeRouteDriver.php:30-36](app/Services/SiteRouting/RouteDrivers/EntryTreeRouteDriver.php:30)

A useful CMS allows admins to pick 301 vs 302 (and increasingly 307/308). Today the driver always emits 302 even though the comment hints at status configurability.

**Resolution.** Add a `redirect_status` column to `entry_trees`, default 302, validated in the request:

```php
// migration
$table->unsignedSmallInteger('redirect_status')->default(302);

// validation
'redirect_status' => ['nullable', 'integer', 'in:301,302,307,308'],

// driver
data: [
    'url'    => $node->redirect_url,
    'status' => $node->redirect_status ?: 302,
],
```

Low priority because it's a feature, not a defect — but ships well with H-3's fix.

---

## Info / Documentation drift

These don't gate the Alpha but should be reconciled before the announcement so the docs and reality match. Two-line PRs each.

### I-1. `docs/OVERVIEW.md` "EntryResource is user-shaped" is now stale

The "Known Gaps" section at [docs/OVERVIEW.md:2856-2858](docs/OVERVIEW.md:2856) claims `EntryResource` returns `name`/`email`. Code is correct ([EntryResource.php](app/Http/Resources/Api/EntryResource.php) returns `title`/`handle`/`status_handle`/etc.). Remove this bullet from OVERVIEW.md.

### I-2. `docs/OVERVIEW.md` "`Api\v1\Account@show` returns a placeholder" — confirm and remove

This one is still accurate (see [M-3](#m-3-apiv1accountshow-returns-a-placeholder)). When M-3 ships, also remove the OVERVIEW bullet.

### I-3. `Html` field `allowed_tags` setting is dead

`grep -rn allowed_tags app/` returns only the declaration in `app/Field/Types/Html.php`. Either wire it up (see [H-7](#h-7-html-field-type-silently-accepts-arbitrary-script-content-stored-xss)) or remove it from `settings_form` so the admin UI stops promising a feature that doesn't exist.

### I-4. `app:refresh-tokens` is still a scaffold

OVERVIEW.md acknowledges this. For Alpha either implement it or remove the command from `routes/console.php` so customers don't `php artisan app:refresh-tokens` and assume it worked.

### I-5. `site.templates.base_path` / `not_found_template` are unused

OVERVIEW.md flags these as "present in config for future use." For Alpha either wire them or delete the unused keys to avoid customer confusion:

```php
// app/Services/SiteRouting/RouteDrivers/TemplateRouteDriver.php — resolveHome / resolveGroupSecond
$notFoundTemplate = config('site.templates.not_found_template');
if ($notFoundTemplate && View::exists($notFoundTemplate)) {
    return $this->result($notFoundTemplate, $segments);
}
```

---

## Pre-Alpha checklist

In priority order. C-1, C-2, C-3 must merge before any external sign-up link is shared.

- [ ] **C-1** Re-enable `current_password` check in `UpdateUserPassword`
- [ ] **C-2** Add permission check to `UploadMediaRequest::authorize()`
- [ ] **C-3** Constrain `roles.*` validation (no privilege escalation)
- [ ] **H-1** Regenerate session after OAuth login; add throttle middleware
- [ ] **H-2** Persist `response_payload` in `LogRequestResponse`
- [ ] **H-3** Validate `redirect_url` scheme; runtime guard in EntryTreeRouteDriver
- [ ] **H-4** One-time-view flow for new personal access tokens
- [ ] **H-5** Bind `type_handle` validation to `group_id`
- [ ] **H-6** Remove status columns from `User::$fillable`; use `forceFill`
- [ ] **H-7** HTML purification on Html field write path
- [ ] **H-8** Lock CORS to an env allowlist
- [ ] **M-1** `categories.*` membership rule
- [ ] **M-2** Reconcile `read users` vs `view user` permission
- [ ] **M-3** Implement `Api\v1\Account@show`
- [ ] **M-4 / M-5** Controller-level permission checks for entry/user API mutators
- [ ] **M-6** Constrain `updateToken()` to `name`/`abilities`/`expires_at`
- [ ] **M-7** Add `Rule::in` constraint on `users.social_default_status`
- [ ] **L-1 .. L-6** Schedule for post-Alpha unless time permits
- [ ] **I-1 .. I-5** Reconcile docs (10-minute pass)
- [ ] Final pass: `composer test` green, `php artisan app:validate-class-references` green, `vendor/bin/pint --preset psr12` clean
- [ ] Production `.env` review: `APP_DEBUG=false`, `APP_ENV=production`, `SESSION_SECURE_COOKIE=true`, `SESSION_SAME_SITE=lax`, `CORS_ALLOWED_ORIGINS` populated
