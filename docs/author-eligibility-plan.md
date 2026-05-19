# Author Eligibility Layer — Implementation Plan

## Executive Summary

The system currently has no concept of author eligibility. `entry_authors` is a raw pivot table
(`entry_id`, `user_id`, `sort_order`) with no gate — any user ID passes validation and any user
can be placed in the author picker. The picker itself is not even wired to live data yet
(hardcoded placeholder names render in the template).

This plan repurposes `entry_authors` into a proper eligibility registry and adds a new
`entry_author_entry` pivot to carry the entry↔author join. The admin promotes a user to author
via a checkbox on the user create/edit form; only users with an `active` registry record ever
appear in entry author pickers or pass form validation. Entries store the relationship through
the registry layer, not directly against `users`.

**Four things change in one coherent sweep:**

1. **Schema** — `entry_authors` is rewritten in its existing migration to be the eligibility
   registry; a new migration adds `entry_author_entry` as the entry↔author pivot.
2. **User flow** — a checkbox and optional display-name field on the user forms drive
   promote/demote via `UserService`, which delegates to a new `EntryAuthorService`.
3. **Entry flow** — the author picker reads only active registry records; validation and the
   repository both enforce eligibility at write time.
4. **Seeders** — `UsersSeeder` promotes `eric@mithra62.com` to author on creation; `EntrySeeder`
   and `SandboxedEntryTreeSeeder` resolve authors through the eligibility layer before assigning
   them to entries.

Because the system is pre-deployment, the existing `entry_authors` migration is rewritten
directly and no cleanup migration is needed.

---

## Current State (what we're working with)

### Database

| Table | Relevant columns |
|---|---|
| `users` | `id`, `name`, `email` |
| `entry_authors` | `id`, `entry_id`, `user_id`, `sort_order`, `created_at`, `updated_at` |

`entry_authors` today is a **pivot** keyed by `(entry_id, user_id)`. It does not know anything
about eligibility. There is no separate "author profile" record.

### Model layer

- `Entry::authors()` — `belongsToMany(User::class, 'entry_authors')` with `sort_order` pivot.
- `User` — no reciprocal relation back to entries or to any author concept.

### Service / repository layer

- `EntryRepository::syncAuthors()` takes a raw `array $userIds` and calls
  `$entry->authors()->sync(...)` with no eligibility check.
- `UserService::getForDropdown()` queries `users` table directly, limit 50.

### Validation

- `StoreEntryRequest` / `EditEntryRequest` both validate `authors.*` against `exists:users,id`.

### Views

- `admin/entries/_taxonomy.twig` — author picker is currently **hardcoded placeholder options**
  (Avery Stone, Morgan Lee, …). No live data is wired in yet.
- `admin/entries/edit.twig` — references `entry.authors` for pre-selection but feeds a separate
  static `<select>` loop.
- `admin/users/edit.twig` — has role checkboxes, status select, and a couple of account flags.
  No author concept exists here today.

### Entry controller

`Admin\Entry::create()` and `edit()` both call `Users::getForDropdown(10)` and pass the result
as `$users`. That variable is available in templates but currently unused in the author picker
(the hardcoded placeholder is what renders instead).

---

## Target Schema

The existing `entry_authors` table needs to be **repurposed** — it will stop being a pure pivot
and become the **author eligibility registry**. The `entry_id` column is dropped; a new column
`display_name` and a `status` enum are added. A new pivot table takes over the entry↔author
join.

### `entry_authors` (repurposed — eligibility registry)

```
id               — bigint PK
user_id          — FK → users.id (unique; one record per user)
display_name     — string, nullable (public-facing name, defaults to user.name)
status           — enum: active | pending | disabled (default: pending)
created_at
updated_at
```

A unique constraint on `user_id` enforces the one-record-per-user invariant.

### `entry_author_entry` (new pivot — replaces old entry_authors role)

```
entry_id         — FK → entries.id (cascade delete)
entry_author_id  — FK → entry_authors.id (cascade delete)
sort_order       — unsignedInteger, default 0
PRIMARY KEY (entry_id, entry_author_id)
INDEX (entry_id, sort_order)
```

This table carries what `entry_authors` used to carry (the entry↔user join) but now references
`entry_authors.id` rather than `users.id` directly. Entries never touch `users` for author
purposes — they only see the eligibility layer.

---

## Migration Strategy

The system is pre-deployment, so no cleanup migration is needed. Instead:

- **Rewrite** `2026_04_18_000010_create_entry_authors_table.php` in place to produce the new
  eligibility registry schema (`user_id`, `display_name`, `status`). Remove `entry_id` and
  `sort_order`; add the unique constraint on `user_id` and the `status` enum column.
- **Add a new migration** `2026_04_18_000011_create_entry_author_entry_table.php` (or the next
  available timestamp after the existing entry-author migration) that creates `entry_author_entry`
  with `entry_id`, `entry_author_id`, and `sort_order`.

After a fresh `php artisan migrate`, both tables are correct from the start with no intermediate
state to manage.

---

## Model Layer

### New model: `EntryAuthor`

```
app/Models/EntryAuthor.php
```

```php
// Relations
user(): BelongsTo → User::class
entries(): BelongsToMany → Entry::class via 'entry_author_entry'

// Fillable: user_id, display_name, status

// Scopes
scopeActive(Builder $query)   // status = 'active'
scopePending(Builder $query)  // status = 'pending'
scopeDisabled(Builder $query) // status = 'disabled'

// Accessor
getDisplayNameAttribute()     // returns display_name ?? user->name (avoids null display)
```

### Changes to `Entry`

Replace the existing `authors()` relation:

```php
// Before
public function authors(): BelongsToMany
{
    return $this->belongsToMany(User::class, 'entry_authors')
        ->withPivot('sort_order')
        ->orderByPivot('sort_order')
        ->withTimestamps();
}

// After
public function authors(): BelongsToMany
{
    return $this->belongsToMany(EntryAuthor::class, 'entry_author_entry')
        ->withPivot('sort_order')
        ->orderByPivot('sort_order')
        ->withTimestamps();
}
```

Entries now relate to `EntryAuthor` records, not `User` records. Reaching the user from an
entry author is `$entry->authors->first()->user`.

### Changes to `User`

Add a `hasOne` for convenience:

```php
public function entryAuthor(): HasOne
{
    return $this->hasOne(EntryAuthor::class);
}

public function isAuthorEligible(): bool
{
    return $this->entryAuthor?->status === 'active';
}
```

---

## Service Layer

### New service: `EntryAuthorService`

```
app/Services/EntryAuthorService.php
```

Responsibilities:

- `getEligible(): Collection` — returns all `active` `EntryAuthor` records with `user` eager
  loaded. This is the **only** method the author picker should ever call.
- `promote(User $user, ?string $displayName = null): EntryAuthor` — creates or re-activates an
  `entry_authors` row for the user (status = `active`). Called from the user edit flow.
- `demote(User $user): void` — sets the row to `disabled`. Does not remove existing entry
  assignments (historical authorship is preserved).
- `findByUser(User $user): ?EntryAuthor` — look up a user's eligibility record.
- `sync(User $user, bool $eligible, ?string $displayName = null): EntryAuthor` — idempotent
  upsert used from the form save path.

### New facade: `EntryAuthors`

Mirror the pattern of `Users`, `Entries`, etc.:

```
app/Facades/EntryAuthors.php
→ backed by EntryAuthorService
```

### Changes to `UserService`

The `update()` method already accepts an open-ended `array $data`. Add handling for a new
`is_author` boolean key:

```php
if (array_key_exists('is_author', $data)) {
    app(EntryAuthorService::class)->sync(
        $user,
        (bool) $data['is_author'],
        $data['author_display_name'] ?? null,
    );
}
```

This keeps author eligibility entirely inside the user update flow — no new controller endpoint
is needed.

### Changes to `EntryRepository::syncAuthors()`

Currently receives `array $userIds` and calls `$entry->authors()->sync(...)`.

After the change it must receive `EntryAuthor` IDs, not `User` IDs. The input array from the
form will carry `user_id` values (what the picker submits). The repository must resolve them to
`EntryAuthor` IDs:

```php
private function syncAuthors(Entry $entry, array $userIds): void
{
    // Resolve user IDs to EntryAuthor IDs, filtering to active only.
    $authorIds = EntryAuthor::active()
        ->whereIn('user_id', $userIds)
        ->pluck('id', 'user_id');   // keyed by user_id for sort_order mapping

    $sync = [];
    foreach ($userIds as $order => $userId) {
        if (isset($authorIds[$userId])) {
            $sync[$authorIds[$userId]] = ['sort_order' => $order];
        }
    }

    $entry->authors()->sync($sync);
}
```

This double-checks eligibility at write time even if validation was somehow bypassed.

---

## Validation Changes

### `StoreEntryRequest` / `EditEntryRequest`

Replace:

```php
'authors.*' => ['integer', 'exists:users,id'],
```

With:

```php
'authors.*' => [
    'integer',
    Rule::exists('entry_authors', 'user_id')->where('status', 'active'),
],
```

The validation now rejects any submitted user ID that does not have an active `entry_authors`
record. The form submits `user_id` values (not `entry_author_id`) because that is what the
picker knows about — the resolution to `entry_author_id` happens inside `syncAuthors()`.

### `StoreUserRequest` / `EditUserRequest`

Add:

```php
'is_author'           => ['nullable', 'boolean'],
'author_display_name' => ['nullable', 'string', 'max:255'],
```

---

## Controller Changes

### `Admin\Entry::create()` and `edit()`

Replace:

```php
$users = Users::getForDropdown(10);
```

With:

```php
$authors = EntryAuthors::getEligible(); // returns EntryAuthor collection with user
```

Pass `$authors` to the view instead of `$users`.

### `Admin\User` — no new methods needed

The existing `store()` and `update()` paths delegate to `CreateNewUser` and
`UpdateUserProfileInformation` actions, which delegate to `UserService`. Because `UserService`
will handle the `is_author` key, no controller change is needed beyond ensuring the request
classes accept the new fields.

---

## View Changes

### `admin/users/edit.twig` and `admin/users/create.twig`

Add an "Author" section to the Account sidebar card, below the existing status/invite fields:

```twig
<div class="border-t border-slate-100 px-5 py-4">
    <h6 class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-400">Authoring</h6>
    <label class="flex cursor-pointer items-center gap-2.5 text-sm text-slate-700">
        <input type="checkbox" name="is_author" value="1"
               class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
               {{ user.isAuthorEligible ? 'checked' : '' }}>
        Eligible as content author
    </label>
    <div class="mt-3" id="author-display-name-wrap">
        <label class="mb-1.5 block text-sm font-medium text-slate-700"
               for="author_display_name">Display name (optional)</label>
        <input type="text" name="author_display_name" id="author_display_name"
               value="{{ old('author_display_name', user.entryAuthor.display_name) }}"
               class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm ...">
        <p class="mt-1 text-xs text-slate-400">
            Shown in author pickers. Defaults to username if blank.
        </p>
    </div>
</div>
```

The `author-display-name-wrap` div can be shown/hidden with a small JS snippet that watches the
checkbox — only show it when the checkbox is checked.

On the create form the same block applies with empty defaults.

### `admin/entries/_taxonomy.twig`

Replace the hardcoded `<option>` list with a real loop over the `$authors` collection passed
from the controller. The loop key is `user_id` (what the form submits), and the display text
comes from `author.display_name` (which falls back to `author.user.name`):

```twig
<select id="authors" name="authors[]" multiple size="5" ...>
    {% for author in authors %}
        <option value="{{ author.user_id }}"
            {% if entry is defined %}
                {% for assigned in entry.authors %}
                    {% if assigned.user_id == author.user_id %}selected{% endif %}
                {% endfor %}
            {% endif %}
        >{{ author.display_name }}</option>
    {% endfor %}
</select>
```

---

## `EntryQueryBuilder` Changes

`withAuthor(int $userId)` currently queries `whereHas('authors', fn($q) => $q->where('users.id',
$userId))`. After the relation change, `authors` now points to `entry_authors`, not `users`:

```php
public function withAuthor(int $userId): static
{
    $this->query->whereHas('authors', fn($q) => $q->where('user_id', $userId));
    return $this;
}
```

---

## API Layer

### `Api\v1\Entries`

The `authors` property in the OpenAPI annotations and the `EntryResource` shape both currently
expose user-shaped data. After this change, author data comes from `EntryAuthor`, not `User`
directly. The `EntryResource` author map should be updated to expose `user_id` and
`display_name`:

```php
'authors' => $this->whenLoaded(
    'authors',
    fn () => $this->authors->map(fn ($a) => [
        'id'           => $a->user_id,
        'display_name' => $a->display_name,
    ])
),
```

API consumers currently receive `{ id, title }` per author (where `title` was `user->name`).
The shape changes to `{ id, display_name }` — this is a minor breaking change to document.

The `authors.*` validation inside `Api\v1\Entries` should mirror the updated form validation:
`exists:entry_authors,user_id` with a `status = active` constraint.

---

## Seeder Updates

Three seeders touch author data and all need to be updated to work through the eligibility
layer. The guiding rule is simple: **no seeder should write a user ID into an `authors` array
unless that user already has an active `EntryAuthor` record.** The order of operations in
`DatabaseSeeder` already satisfies this — `UsersSeeder` runs before `EntrySeeder`, so the
eligibility record can be created at user-seed time and safely referenced later.

### `UsersSeeder`

This seeder creates the `eric@mithra62.com` super-admin account. After user creation, it must
also call `EntryAuthorService::promote()` to create an active registry record for that user:

```php
public function run(): void
{
    $user = User::factory()->create([
        'name'     => 'Eric Lamb',
        'email'    => 'eric@mithra62.com',
        'password' => Hash::make('password'),
    ]);

    $user->assignRole('super admin');

    // Promote to author so this account appears in all entry author pickers
    // and can be used as the seeded author in EntrySeeder.
    app(EntryAuthorService::class)->promote($user);
}
```

Because `UsersSeeder` runs before `EntrySeeder` in `DatabaseSeeder`, the registry row is
guaranteed to exist by the time any entry is seeded.

### `EntrySeeder`

Currently uses `$author->id` (a `User` ID) directly in the `authors` array passed to
`Content::create()`. After this change, the `syncAuthors()` path in `EntryRepository` will
resolve user IDs through the eligibility layer, so the array values stay as user IDs — but the
seeder must ensure the user is actually eligible before passing the ID.

The cleanest approach is to verify the author's eligibility record exists at the top of `run()`
and fail loudly if it does not:

```php
public function run(): void
{
    $author = User::where('email', 'eric@mithra62.com')->firstOrFail();
    Auth::setUser($author);

    // Guard: this seeder depends on the user having an active author record.
    // UsersSeeder must run first. If this fails, check DatabaseSeeder run order.
    $authorRecord = EntryAuthor::where('user_id', $author->id)
        ->where('status', 'active')
        ->firstOrFail();

    $posts = $this->seedBlogPosts($author);
    $this->linkRelatedPosts($posts);
    $this->seedProducts($author);
}
```

The `authors` arrays in the entry definitions (`'authors' => [$author->id]`) remain unchanged
in shape — they pass user IDs, and `syncAuthors()` handles the resolution to `EntryAuthor` IDs.
No definition-level changes are needed beyond the guard at the top of `run()`.

### `SandboxedEntryTreeSeeder`

This seeder creates its own dedicated user (`sandbox.entry.tree@example.test`) via
`seedAuthor()`. That method must also promote the user to author after creation:

```php
protected function seedAuthor(): User
{
    $user = User::query()->updateOrCreate(
        ['email' => self::USER_EMAIL],
        [
            'name'              => 'Sandbox Tree Author',
            'email_verified_at' => now(),
            'password'          => Hash::make('password'),
            'remember_token'    => Str::random(10),
        ]
    );

    // Ensure the sandbox author has an active eligibility record.
    app(EntryAuthorService::class)->promote($user);

    return $user;
}
```

Note that `SandboxedEntryTreeSeeder` currently only passes `created_by_user_id` into its entry
definitions — it does not use an `authors` array at all. That means it does not go through
`syncAuthors()` and requires no further changes beyond the `seedAuthor()` promotion above. If
author assignment is added to sandbox entries in the future, the same pattern as `EntrySeeder`
applies.

### `FakeDataSeeder`

`FakeDataSeeder` resolves its operator user (`User::role('super admin')->first()`) and sets it
as the authenticated user but does not pass authors into entry data. No author-related change is
required. If it is extended in the future to assign authors, the same guard pattern as
`EntrySeeder` should be applied.

### Seeder run order (no changes required)

The existing order in `DatabaseSeeder` already satisfies the dependency:

```
RolesPermissionsSeeder   → creates roles
UsersSeeder              → creates eric@mithra62.com AND promotes to author  ← updated
...
EntrySeeder              → seeds entries using eric@mithra62.com as author   ← updated
```

No reordering is needed.

---

## New `AuthorEligibilityObserver` (optional but recommended)

An Eloquent observer on `User` can handle edge cases automatically:

- `deleted` — demote the author record (or cascade, if DB constraints handle it).
- Nothing else is needed; creation of the author record is intentional admin action only.

---

## Registration

New bindings to add in `AppServiceProvider` (or a dedicated `AuthorServiceProvider`):

```php
$this->app->singleton(EntryAuthorService::class);
```

Add to `config/app.php` aliases or the facades map:

```php
'EntryAuthors' => App\Facades\EntryAuthors::class,
```

Add to `Relation::morphMap()` if `EntryAuthor` is used in any polymorphic context (none planned
for now, but register it preemptively to match the existing pattern).

---

## Permissions

No new permissions are needed in this first pass. The existing `edit user` permission gates the
user edit form where the author checkbox lives. The existing `create entry` / `edit entry`
permissions gate entry forms. Author eligibility management is an implicit part of user
management.

If finer-grained control is needed later (e.g., a separate `manage authors` permission), that
can be added in a follow-up.

---

## File Inventory

### New files

| Path | Purpose |
|---|---|
| `app/Models/EntryAuthor.php` | Eligibility model |
| `app/Services/EntryAuthorService.php` | Promote / demote / query |
| `app/Facades/EntryAuthors.php` | Facade |
| `database/migrations/2026_04_18_000011_create_entry_author_entry_table.php` | New pivot table |

### Modified files

| Path | What changes |
|---|---|
| `database/migrations/2026_04_18_000010_create_entry_authors_table.php` | Rewritten: drops `entry_id`/`sort_order`, adds `display_name`, `status`, unique on `user_id` |
| `app/Models/Entry.php` | `authors()` relation → `EntryAuthor` via `entry_author_entry` |
| `app/Models/User.php` | Add `entryAuthor()` and `isAuthorEligible()` |
| `app/Services/UserService.php` | Handle `is_author` key in `update()` and `create()` |
| `app/Facades/Users.php` | Doc-block for new method signatures if any |
| `app/Repositories/EntryRepository.php` | `syncAuthors()` resolves user_id → entry_author_id |
| `app/Builders/EntryQueryBuilder.php` | `withAuthor()` column reference |
| `app/Http/Controllers/Admin/Entry.php` | Pass `$authors` not `$users` |
| `app/Http/Requests/Entry/StoreEntryRequest.php` | `authors.*` validation rule |
| `app/Http/Requests/Entry/EditEntryRequest.php` | `authors.*` validation rule |
| `app/Http/Requests/User/StoreUserRequest.php` | Add `is_author`, `author_display_name` |
| `app/Http/Requests/User/EditUserRequest.php` | Inherits from Store; may need override |
| `app/Http/Resources/Api/EntryResource.php` | Author shape update |
| `resources/views/admin/entries/_taxonomy.twig` | Real author loop |
| `resources/views/admin/users/edit.twig` | Author checkbox block |
| `resources/views/admin/users/create.twig` | Author checkbox block |
| `app/Providers/AppServiceProvider.php` | Service binding registration |
| `database/seeders/UsersSeeder.php` | Promote eric@mithra62.com to author after creation |
| `database/seeders/EntrySeeder.php` | Guard assertion + lookup by email instead of `User::first()` |
| `database/seeders/SandboxedEntryTreeSeeder.php` | `seedAuthor()` promotes sandbox user to author |

---

## What Is Deliberately Out of Scope

- **Author profile pages** — a public `/authors/{handle}` route is not part of this plan.
- **Per-group author restrictions** — eligibility is system-wide for now.
- **Author self-registration** — this is always an admin action.
- **Removing existing entry-author assignments when a user is demoted** — historical records are
  left intact; the demoted author simply stops appearing in pickers going forward.
