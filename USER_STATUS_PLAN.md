# User Status System Plan

## Overview

This plan introduces a `status` column on the `users` table that controls whether a user is
permitted to authenticate and access the system. Roles continue to govern *what* a user can
do once inside; status governs *whether* they can get in at all. The two concerns are kept
deliberately separate so that a user's role assignments are preserved regardless of status.

---

## Status Values

| Value | Triggered by | Meaning |
|---|---|---|
| `active` | Admin | Normal, fully-authenticated user. No restrictions. |
| `inactive` | Admin | Account disabled. Cannot log in. |
| `pending` | System / Admin | Account created but not yet approved. Cannot log in. |
| `suspended` | Admin | Temporarily blocked until `suspended_until` passes. Cannot log in; auto-expires. |
| `banned` | Admin | Permanently removed. `banned_at` records when. Cannot log in. |

Only `active` users may complete authentication. All other statuses are access-denied at the
login gate.

**Default for new users:** driven by the `users` settings domain — see Section 13.

### Locked — a parallel flag, not a status value

`locked` is intentionally *not* a status value. Locking is a distinct, admin-triggered
mechanism. The lock lives entirely in the `locked_until` column:

- `locked_until IS NULL` — not locked
- `locked_until <= NOW()` — lock has expired; treat as not locked (auto-expiry)
- `locked_until > NOW()` — actively locked; access denied regardless of `status`

`canAccessSystem()` checks *both* the `status` column *and* the lock state independently.

### Status precedence for messaging

```
banned > suspended > locked > pending > inactive
```

---

## Columns on `users`

All columns representing current operational state live directly on `users` — no join needed
at authentication time.

| Column | Type | Nullable | Purpose |
|---|---|---|---|
| `status` | `string(20)` | no | Admin-set status. Default `active`. |
| `suspended_until` | `timestamp` | yes | When a suspension expires. Auto-expiry checked at auth time. |
| `banned_at` | `timestamp` | yes | When the ban was applied. Kept in sync by `setStatus()`. Informational/reporting only; `status` drives enforcement. |
| `locked_until` | `timestamp` | yes | When the account lock expires. Null = not locked. |

History and every state change live in the append-only `user_status_logs` table (Section 11).

### Auto-expiry

No cron job is required for enforcement. `canAccessSystem()` compares `suspended_until` and
`locked_until` against `now()` at runtime. An optional nightly cleanup task can sweep
`status` back to `active` where `suspended_until` has passed and null out expired
`locked_until` values to keep the DB tidy, but the auth gate does not depend on it.

---

## 1. Migration Strategy

**Rule: update existing migrations for columns on existing tables; create new migrations only
for genuinely new tables.**

### Update: `0001_01_01_000000_create_users_table.php`

Add all new `users` columns directly to the existing table definition:

```php
$table->string('status', 20)->default('active')->after('email_verified_at');
$table->timestamp('suspended_until')->nullable()->after('status');
$table->timestamp('banned_at')->nullable()->after('suspended_until');
$table->timestamp('locked_until')->nullable()->after('banned_at');
```

### New: `user_status_logs` table migration

New schema; gets its own migration file timestamped to run after the users table.

```php
Schema::create('user_status_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
    $table->string('previous_status', 20)->nullable();
    $table->string('new_status', 20)->nullable();
    $table->timestamp('previous_locked_until')->nullable();
    $table->timestamp('new_locked_until')->nullable();
    $table->string('reason', 500)->nullable();
    $table->json('context')->nullable();
    $table->timestamp('created_at')->useCurrent();
    // No updated_at — append-only, immutable.
});
```

`cascadeOnDelete` on `user_id`: if a user is hard-deleted their log rows go with them.
`nullOnDelete` on `changed_by_user_id`: if an admin actor is later deleted, the log row is
preserved with a null actor — audit integrity outweighs the actor reference.

---

## 2. `UserStatus` Constants Class (`app/Enums/UserStatus.php`)

A plain PHP class with string constants rather than a backed enum. This gives centralised,
IDE-friendly values and array helpers for validation without the ceremony of a PHP enum.

```php
namespace App\Enums;

class UserStatus
{
    const ACTIVE    = 'active';
    const INACTIVE  = 'inactive';
    const PENDING   = 'pending';
    const SUSPENDED = 'suspended';
    const BANNED    = 'banned';

    /** All valid status values — use with Rule::in(UserStatus::ALL). */
    const ALL = [
        self::ACTIVE,
        self::INACTIVE,
        self::PENDING,
        self::SUSPENDED,
        self::BANNED,
    ];

    /**
     * Values permitted at user creation time.
     * 'suspended' and 'banned' are post-creation actions only.
     */
    const CREATION_ALLOWED = [
        self::ACTIVE,
        self::INACTIVE,
        self::PENDING,
    ];

    public static function label(string $status): string
    {
        return match($status) {
            self::ACTIVE    => 'Active',
            self::INACTIVE  => 'Inactive',
            self::PENDING   => 'Pending Approval',
            self::SUSPENDED => 'Suspended',
            self::BANNED    => 'Banned',
            default         => ucfirst($status),
        };
    }
}
```

No cast needed — `status` is stored and read as a plain string. Comparisons throughout the
codebase use `UserStatus::ACTIVE`, `UserStatus::SUSPENDED`, etc.

---

## 3. Model Changes (`app/Models/User.php`)

**Add columns to `$fillable`:**

```php
protected $fillable = [
    'name', 'email', 'password',
    'status', 'suspended_until', 'banned_at', 'locked_until',
];
```

**Casts** (`status` is a plain string — no enum cast required):

```php
protected $casts = [
    'suspended_until' => 'datetime',
    'banned_at'       => 'datetime',
    'locked_until'    => 'datetime',
];
```

**Helper methods:**

```php
public function isActive(): bool
{
    return $this->canAccessSystem();
}

public function isLocked(): bool
{
    return $this->locked_until !== null && $this->locked_until->isFuture();
}

public function isSuspended(): bool
{
    return $this->status === UserStatus::SUSPENDED
        && $this->suspended_until !== null
        && $this->suspended_until->isFuture();
}

public function canAccessSystem(): bool
{
    // Lock is independent of status — always denied while locked.
    if ($this->isLocked()) {
        return false;
    }

    // Auto-expiry: a Suspended user whose window has passed is treated as
    // active at runtime without needing a DB sweep.
    if ($this->status === UserStatus::SUSPENDED) {
        return $this->suspended_until !== null && $this->suspended_until->isPast();
    }

    return $this->status === UserStatus::ACTIVE;
}

public function accessDeniedReason(): string
{
    if ($this->status === UserStatus::BANNED)    return 'banned';
    if ($this->isSuspended())                     return 'suspended';
    if ($this->isLocked())                        return 'locked';
    if ($this->status === UserStatus::PENDING)   return 'pending';
    if ($this->status === UserStatus::INACTIVE)  return 'inactive';
    return 'unknown';
}
```

**Query scopes:**

```php
public function scopeActive(Builder $query): Builder
{
    return $query->where('status', UserStatus::ACTIVE);
}

public function scopeWhereStatus(Builder $query, string $status): Builder
{
    return $query->where('status', $status);
}
```

---

## 4. Authentication Gate (Fortify Pipeline)

Block non-active users before any session is written via Fortify's `authenticateUsing`
callback in `FortifyServiceProvider`.

```php
Fortify::authenticateUsing(function (Request $request) {
    $user = User::where('email', $request->email)->first();

    if (! $user || ! Hash::check($request->password, $user->password)) {
        return null;
    }

    if (! $user->canAccessSystem()) {
        $reason = $user->accessDeniedReason();

        // Banned and inactive users receive a generic message — no confirmation
        // to a bad actor that their account is specifically banned vs. disabled.
        $messageKey = in_array($reason, ['banned', 'inactive'])
            ? 'auth.account_inactive'
            : "auth.account_{$reason}";

        throw ValidationException::withMessages([
            Fortify::username() => [__($messageKey)],
        ]);
    }

    return $user;
});
```

---

## 5. Middleware: Enforce Status on Already-Authenticated Sessions

Create `app/Http/Middleware/EnforceUserStatus.php`. Register in the `web` middleware group
after `StartSession` and `AuthenticateSession`.

```php
public function handle(Request $request, Closure $next): Response
{
    $user = $request->user();

    if ($user && ! $user->canAccessSystem()) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->withErrors([Fortify::username() => __('auth.account_inactive')]);
    }

    return $next($request);
}
```

---

## 6. `UserService` Changes

**`create(array $data): User`**

- Accept `status` as an input key; fall back to
  `app(Settings::class)->get('users', 'default_status')` if omitted.
- Accept `suspended_until`, `banned_at`, `locked_until` but do not require them on creation.
- When `status` differs from the default, route through `setStatus()` after creation so the
  initial non-default state is recorded in the audit log.

**`update(User $user, array $data): User`**

- Accept all status-related keys alongside existing keys.
- When `status` is in `$data`, delegate to `setStatus()` so the audit log is always written.

**New and updated methods:**

```php
public function setStatus(User $user, string $status, ?string $reason = null, array $context = []): User
{
    $previous = $user->status;

    $attributes = ['status' => $status];

    // Keep banned_at in sync automatically.
    if ($status === UserStatus::BANNED) {
        $attributes['banned_at'] = now();
    } elseif ($previous === UserStatus::BANNED) {
        $attributes['banned_at'] = null; // ban lifted
    }

    $user->update($attributes);
    event(new UserStatusChanged($user, $previous, $status, $reason, $context));

    return $user->refresh();
}

public function suspend(User $user, \DateTimeInterface $until, string $reason): User
{
    $previous = $user->status;
    $user->update([
        'status'          => UserStatus::SUSPENDED,
        'suspended_until' => $until,
    ]);
    event(new UserStatusChanged($user, $previous, UserStatus::SUSPENDED, $reason, [
        'suspended_until' => $until,
    ]));
    return $user->refresh();
}

public function lockUser(User $user, \DateTimeInterface $until, string $reason = 'admin'): User
{
    $previous = $user->locked_until;
    $user->update(['locked_until' => $until]);
    event(new UserLockChanged($user, $previous, $until, $reason));
    return $user->refresh();
}

public function unlockUser(User $user): User
{
    $previous = $user->locked_until;
    $user->update(['locked_until' => null]);
    event(new UserLockChanged($user, $previous, null, 'manual_unlock'));
    return $user->refresh();
}
```

---

## 7. API Enforcement (Sanctum) — Middleware on API Routes

Create `app/Http/Middleware/EnforceUserStatusApi.php` and register it in the `api`
middleware group.

```php
public function handle(Request $request, Closure $next): Response
{
    $user = $request->user();

    if ($user && ! $user->canAccessSystem()) {
        return response()->json(['message' => __('auth.account_inactive')], 403);
    }

    return $next($request);
}
```

---

## 8. Social Login — `firstOrCreateFromSocial()` Hardening

**New accounts (email not found):**

- Status is set to `app(Settings::class)->get('users', 'social_default_status')`, which
  defaults to `pending`. This is configurable by an admin via the settings UI without a
  code deploy — useful for installations that trust their OAuth provider enough to auto-activate
  users.
- No roles assigned automatically.
- `NewSocialUserRegistered` event fired so notification listeners can be wired in without
  touching this method.
- Provider name and request IP written into the `context` JSON of the initial audit log entry.

**Returning accounts (email already found):**

- Retrieve the user; immediately call `canAccessSystem()`.
- If blocked, throw `ValidationException` with the same messaging as the Fortify gate.
- If allowed, return the user.

**Updated method signature:**

```php
public function firstOrCreateFromSocial(string $email, string $name, string $provider, string $ip): User
```

The OAuth callback controller passes `$provider` and `$ip`. Identify and update all callers
during implementation.

---

## 9. Admin UI Changes

### User Creation Form

Status select using `UserStatus::CREATION_ALLOWED` as the value set: `active`, `inactive`,
`pending`. `suspended` and `banned` are post-creation actions only.

### User Edit Form

All values from `UserStatus::ALL` available. When `suspended` is selected, reveal a
`suspended_until` date/time picker. When `banned` is selected, require secondary confirmation.

### User Index

- **Status badge column:** `active` → green, `pending` → yellow, `suspended`/locked → orange,
  `inactive` → grey, `banned` → red.
- Badge reflects *effective* status via `canAccessSystem()` / `isSuspended()`, not the raw
  `status` column. A `suspended` user whose `suspended_until` has passed shows as effectively
  active pending the cleanup sweep.
- Filter dropdown by status.
- Lock indicator with `locked_until` timestamp on hover where applicable.

### User Detail

- Effective status prominently displayed, with `suspended_until` or `locked_until` timestamps
  where relevant.
- `banned_at` displayed for banned users.
- Quick-action buttons: Activate / Suspend / Ban / Unlock, routed to the toggle endpoint.

### Status Toggle Endpoint

```
PATCH /admin/users/{user}/status
```

Requires `manage user status` permission (Section 17). Accepts `status`, `reason` (required
for suspend/ban), `suspended_until` (required when setting `suspended`). Handled by
`UserStatusController`.

Separate lock management endpoint:

```
DELETE /admin/users/{user}/lock   → UserService::unlockUser()
```

---

## 10. Form Request Validation

Validation uses the constants class arrays — no hard-coded string values in rules.

**`StoreUserRequest`** — add:

```php
'status' => ['nullable', 'string', Rule::in(UserStatus::CREATION_ALLOWED)],
```

**`EditUserRequest`** — add:

```php
'status'          => ['required', 'string', Rule::in(UserStatus::ALL)],
'suspended_until' => ['nullable', 'date', 'after:now', 'required_if:status,' . UserStatus::SUSPENDED],
```

**New `UserStatusRequest`** for the toggle endpoint:

```php
'status'          => ['required', 'string', Rule::in(UserStatus::ALL)],
'reason'          => ['required_unless:status,' . UserStatus::ACTIVE, 'string', 'max:500'],
'suspended_until' => ['nullable', 'date', 'after:now', 'required_if:status,' . UserStatus::SUSPENDED],
```

Using the constant in `required_if` / `required_unless` keeps validation rules in sync with
the constants class automatically — changing a status string value updates both the class and
the rules in one place.

---

## 11. Audit Log

### Table: `user_status_logs`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint unsigned | PK |
| `user_id` | bigint unsigned FK | Affected user. `cascadeOnDelete`. |
| `changed_by_user_id` | bigint unsigned FK | Actor. Nullable. `nullOnDelete`. |
| `previous_status` | string(20) | Nullable — null for lock-only changes. |
| `new_status` | string(20) | Nullable — null for lock-only changes. |
| `previous_locked_until` | timestamp | Nullable. Prior lock expiry. |
| `new_locked_until` | timestamp | Nullable. New lock expiry. |
| `reason` | string(500) | Nullable. Admin-entered or system reason. |
| `context` | json | Nullable. IP, provider, request metadata, etc. |
| `created_at` | timestamp | When the change occurred. |

No `updated_at`. Append-only and immutable.

### Model: `App\Models\UserStatusLog`

- `belongsTo(User::class)` — the affected user
- `belongsTo(User::class, 'changed_by_user_id')` — the actor

### Events + Listeners

Two events, one listener (`WriteUserStatusLog`):

- `UserStatusChanged($user, $previousStatus, $newStatus, $reason, $context)` — fired from
  `UserService::setStatus()` and `suspend()`.
- `UserLockChanged($user, $previousLockedUntil, $newLockedUntil, $reason)` — fired from
  `UserService::lockUser()` and `unlockUser()`.

The listener resolves `Auth::id()` for `changed_by_user_id`, falling back to null for
system-triggered events.

---

## 12. Seeders

### `UserFactory` (`database/factories/UserFactory.php`)

Add all new columns to `definition()`:

```php
'status'          => UserStatus::ACTIVE,
'suspended_until' => null,
'banned_at'       => null,
'locked_until'    => null,
```

Add named factory states for use in tests and the `FakeDataSeeder`:

```php
public function pending(): static {
    return $this->state(['status' => UserStatus::PENDING]);
}
public function inactive(): static {
    return $this->state(['status' => UserStatus::INACTIVE]);
}
public function suspended(int $forMinutes = 60): static {
    return $this->state([
        'status'          => UserStatus::SUSPENDED,
        'suspended_until' => now()->addMinutes($forMinutes),
    ]);
}
public function banned(): static {
    return $this->state([
        'status'    => UserStatus::BANNED,
        'banned_at' => now(),
    ]);
}
public function locked(int $forMinutes = 30): static {
    return $this->state(['locked_until' => now()->addMinutes($forMinutes)]);
}
```

### `FakeDataSeeder` (`database/seeders/FakeDataSeeder.php`)

Add a weighted status distribution to generated users so the dev environment exercises the
admin UI realistically. Reference `UserStatus` constants rather than strings:

```php
private const USER_STATUS_WEIGHTS = [
    UserStatus::ACTIVE    => 75,
    UserStatus::PENDING   => 10,
    UserStatus::INACTIVE  => 8,
    UserStatus::SUSPENDED => 5,
    UserStatus::BANNED    => 2,
];
```

When the generated status is `SUSPENDED`, also generate a `suspended_until` 1–72 hours in
the future. When `BANNED`, set `banned_at` to a random past timestamp within the last year.
Apply `locked_until` (30–120 minutes in the future) to a random ~5% of `active` users.

### `RolesPermissionsSeeder` (`database/seeders/RolesPermissionsSeeder.php`)

Add one new permission:

```php
'manage user status' => 'Allows changing a user\'s status (activate, suspend, ban, unlock)',
```

Assign to `admin` and `super admin` roles.

> **Note:** existing tests in `UserActionsTest` and `UserServiceTest` that assert against
> `edit user` for status-change actions will need updating to `manage user status`.

---

## 13. Settings

Two new settings live in the `users` domain, added to `config/settings.php`. No migration
or extra seeder work is required — `SettingsDomainSeeder` automatically creates the domain
row and seeds the declared defaults when run.

```php
// In config/settings.php — add a 'users' domain entry:
'users' => [
    'name'        => 'Users',
    'description' => 'User account and access configuration.',
    'icon'        => 'ti-users',
    'sort_order'  => 10,
    'fields'      => [
        [
            'handle'       => 'default_status',
            'label'        => 'Default User Status',
            'type'         => 'text',
            'default'      => UserStatus::ACTIVE,
            'rules'        => ['required', 'string', Rule::in(UserStatus::ALL)],
            'instructions' => 'Status assigned to new user accounts created by an admin.',
            'group'        => 'Accounts',
            'hidden'       => false,
            'user_overridable' => false,
        ],
        [
            'handle'       => 'social_default_status',
            'label'        => 'Social Login Default Status',
            'type'         => 'text',
            'default'      => UserStatus::PENDING,
            'rules'        => ['required', 'string', Rule::in(UserStatus::ALL)],
            'instructions' => 'Status assigned to accounts created via OAuth / social login. '
                            . 'Set to "active" only if you fully trust your OAuth provider.',
            'group'        => 'Accounts',
            'hidden'       => false,
            'user_overridable' => false,
        ],
    ],
],
```

**Usage in `UserService`:**

```php
// Default status for admin-created users:
app(Settings::class)->get('users', 'default_status')   // → 'active' unless overridden

// Default status for social-login-created users:
app(Settings::class)->get('users', 'social_default_status')  // → 'pending' unless overridden
```

No `config/users.php` file is needed. All user-related runtime configuration lives in the
Settings layer.

---

## 14. Forgot Password Flow

No changes. Users with non-`active` statuses can still request a password reset link. The
reset link does not grant system access — the `canAccessSystem()` check at login applies.

---

## 15. Language Strings

Add to `lang/en/auth.php`:

```php
'account_inactive'  => 'Your account is not active. Please contact an administrator.',
'account_pending'   => 'Your account is pending approval. You will be notified when access is granted.',
'account_suspended' => 'Your account has been temporarily suspended. Please contact an administrator.',
'account_locked'    => 'Your account has been temporarily locked. Please try again later.',
// Banned users receive 'account_inactive' — deliberately vague.
```

---

## 16. Testing

| Test | Location |
|---|---|
| `active` user can log in | `tests/Feature/Auth/LoginTest.php` |
| `inactive` user rejected — generic message | `tests/Feature/Auth/LoginTest.php` |
| `pending` user rejected — pending message | `tests/Feature/Auth/LoginTest.php` |
| `suspended` user with future `suspended_until` — rejected | `tests/Feature/Auth/LoginTest.php` |
| `suspended` user with past `suspended_until` — allowed (auto-expiry) | `tests/Feature/Auth/LoginTest.php` |
| `banned` user rejected — generic (not ban-specific) message | `tests/Feature/Auth/LoginTest.php` |
| `locked_until` in future rejects login | `tests/Feature/Auth/LoginTest.php` |
| `locked_until` in past allows login (auto-expiry) | `tests/Feature/Auth/LoginTest.php` |
| `suspended` + `locked` — locked message takes precedence | `tests/Feature/Auth/LoginTest.php` |
| Status change mid-session logs user out (web) | `tests/Feature/Auth/LoginTest.php` |
| Sanctum API blocked for non-active user returns 403 | `tests/Feature/Api/v1/UserStatusTest.php` |
| Sanctum API allowed for active user | `tests/Feature/Api/v1/UserStatusTest.php` |
| Social login — new user status comes from `social_default_status` setting | `tests/Feature/Auth/SocialLoginTest.php` |
| Social login — new user gets no roles | `tests/Feature/Auth/SocialLoginTest.php` |
| Social login — `NewSocialUserRegistered` event fired | `tests/Feature/Auth/SocialLoginTest.php` |
| Social login — blocked returning user is rejected | `tests/Feature/Auth/SocialLoginTest.php` |
| `UserService::create()` uses `default_status` setting when no status given | `tests/Unit/Services/UserServiceTest.php` |
| `UserService::setStatus()` fires `UserStatusChanged` | `tests/Unit/Services/UserServiceTest.php` |
| `UserService::setStatus()` sets `banned_at` when banning | `tests/Unit/Services/UserServiceTest.php` |
| `UserService::setStatus()` clears `banned_at` when lifting a ban | `tests/Unit/Services/UserServiceTest.php` |
| `UserService::suspend()` sets status + `suspended_until` atomically | `tests/Unit/Services/UserServiceTest.php` |
| `UserService::lockUser()` sets `locked_until` + fires `UserLockChanged` | `tests/Unit/Services/UserServiceTest.php` |
| `UserService::unlockUser()` clears `locked_until` + fires `UserLockChanged` | `tests/Unit/Services/UserServiceTest.php` |
| Audit log row created on status change | `tests/Unit/Services/UserServiceTest.php` |
| Audit log row created on lock change | `tests/Unit/Services/UserServiceTest.php` |
| Audit log `changed_by_user_id` null for system events | `tests/Unit/Services/UserServiceTest.php` |
| Status toggle endpoint requires `manage user status` permission | `tests/Feature/Admin/UserTest.php` |
| Suspend requires `suspended_until` | `tests/Feature/Admin/UserTest.php` |
| Ban requires `reason` | `tests/Feature/Admin/UserTest.php` |
| `UserFactory::suspended()` produces correct column values | `tests/Unit/Models/UserTest.php` |
| `UserFactory::locked()` produces correct column values | `tests/Unit/Models/UserTest.php` |
| `User::isSuspended()` returns false when `suspended_until` is past | `tests/Unit/Models/UserTest.php` |
| `User::isLocked()` returns false when `locked_until` is past | `tests/Unit/Models/UserTest.php` |

---

## 17. Permissions

Add to `RolesPermissionsSeeder`:

```php
'manage user status' => 'Allows changing a user\'s status (activate, suspend, ban, unlock)',
```

Assign to `admin` and `super admin` roles. The status toggle endpoint checks this permission,
not `edit user`, enabling a future moderator role with status-only access.

---

## 18. Implementation Order

1. `UserStatus` constants class (`app/Enums/UserStatus.php`)
2. Update `0001_01_01_000000_create_users_table.php` — add four columns
3. New migration — `user_status_logs` table
4. Add `users` domain to `config/settings.php`
5. `User` model (`$fillable`, casts, helpers, scopes)
6. `UserStatusLog` model
7. `UserStatusChanged` + `UserLockChanged` events + `WriteUserStatusLog` listener
8. `UserService` updates (`create`, `update`, `setStatus`, `suspend`, `lockUser`, `unlockUser`)
9. Fortify `authenticateUsing` callback
10. `EnforceUserStatus` web middleware
11. `EnforceUserStatusApi` API middleware
12. `firstOrCreateFromSocial()` refactor + update all callers
13. `UserFactory` — add new columns to `definition()`, add named states
14. Update `FakeDataSeeder` — weighted status distribution + lock simulation
15. Update `RolesPermissionsSeeder` — add `manage user status` permission
16. Form request validation updates + new `UserStatusRequest`
17. `UserStatusController` + status toggle route + unlock route
18. Admin UI (forms, index badges/filter, show page)
19. Language strings
20. Tests

---

## Open Questions (all resolved)

| Question | Decision |
|---|---|
| Social login default status | Configurable via `social_default_status` setting; defaults to `pending`; `NewSocialUserRegistered` event; no auto-roles; provider + IP in audit context |
| Forgot password for blocked users | No change — reset link flow unchanged |
| Audit log | `user_status_logs` table, event + listener pattern, two event types |
| Sanctum API enforcement | Middleware on API routes |
| Locked as parallel flag | `locked_until` column only; no `locked` status value |
| `last_active_at` | Dropped — performance concern |
| Migration strategy | Update existing `create_users_table` migration; new migration for `user_status_logs` only |
| Status at creation vs. post-creation | `suspended` and `banned` are post-creation only; `CREATION_ALLOWED` constant enforced in validation |
| Configuration | Settings layer only — no `config/users.php`; `SettingsDomainSeeder` handles defaults automatically |
| `banned_at` sync | Managed automatically inside `setStatus()` — set on ban, cleared on lift |
| Enum vs. constants class | Plain PHP class with string constants; `UserStatus::ALL` and `UserStatus::CREATION_ALLOWED` arrays used in `Rule::in()` |
| Login throttling integration | Out of scope for this plan — handled separately |
| Token / session revocation | Out of scope for this plan — handled separately |
