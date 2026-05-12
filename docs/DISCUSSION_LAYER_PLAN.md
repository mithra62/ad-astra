# Discussion Layer — Design Plan

A polymorphic comment/review/discussion system that can be attached to any model
(Entry, Media, or future models) by adding a single trait. The design follows every
existing paradigm in the codebase: the `Traits/Category/` namespace convention,
`AbstractService` inheritance, repository-based persistence with transactional writes,
and the `morphMany` / `morphTo` patterns used throughout.

---

## Goals

- One trait to attach discussions to any model — no schema changes to that model.
- Threaded replies (one level of nesting is enforced at the DB layer; deeper nesting
  is resolved in the service).
- Optional rating (1–5) so the same system covers both plain comments and reviews.
- Lightweight moderation: `status` enum with `pending / approved / flagged / spam`.
- Reactions (likes, upvotes, etc.) as a child model, following the
  `Models/Media/Library` sub-model pattern.
- Everything wired through a `DiscussionService` + `DiscussionRepository` pair so
  consumers never touch Eloquent directly.

---

## File Layout

All new code lives in **one new file per concern**. No existing files are modified.

```
app/
  Models/
    Discussion.php                       ← core model
    Discussion/
      Reaction.php                       ← sub-model (like Media/Library)
  Traits/
    Discussion/
      HasDiscussions.php                 ← attach to Entry, Media, etc.
  Repositories/
    DiscussionRepository.php
  Services/
    DiscussionService.php
database/
  migrations/
    xxxx_create_discussions_table.php
    xxxx_create_discussion_reactions_table.php
```

---

## Database Schema

### `discussions`

```
id                    bigint unsigned  PK auto-increment
discussable_id        bigint unsigned  NOT NULL   ─┐ polymorphic morph
discussable_type      varchar(255)     NOT NULL   ─┘
user_id               bigint unsigned  NOT NULL   FK → users.id  (author)
parent_id             bigint unsigned  NULL       FK → discussions.id  (threading)
body                  text             NOT NULL
status                enum(pending, approved, flagged, spam)  NOT NULL  default: pending
rating                tinyint unsigned NULL       range 1–5 (null = plain comment)
is_pinned             boolean          NOT NULL   default: false
edited_at             timestamp        NULL
approved_at           timestamp        NULL
approved_by_user_id   bigint unsigned  NULL       FK → users.id
created_at / updated_at
```

**Indexes:**
- `(discussable_id, discussable_type)` — standard morph index for loading all
  discussions belonging to a model.
- `(discussable_id, discussable_type, status)` — scoped listing (approved only).
- `(parent_id)` — reply lookups.
- `(user_id)` — "all comments by this user" queries.

**Constraints:**
- `parent_id` may not point to a row that itself has a non-null `parent_id`
  (one-level nesting enforced in `DiscussionRepository::create()`; the DB
  constraint is a CHECK or validated in the service).

---

### `discussion_reactions`

```
id               bigint unsigned  PK auto-increment
discussion_id    bigint unsigned  NOT NULL  FK → discussions.id  (cascade delete)
user_id          bigint unsigned  NOT NULL  FK → users.id
type             varchar(64)      NOT NULL  e.g. 'like', 'upvote', 'helpful'
created_at / updated_at

UNIQUE (discussion_id, user_id, type)   ← one reaction type per user per discussion
```

---

## Models

### `App\Models\Discussion`

Mirrors `Entry` in structure: explicit `$fillable`, typed casts, named relationship
methods, and scopes for common queries.

```php
namespace App\Models;

use App\Models\Discussion\Reaction;
use App\Traits\Discussion\HasDiscussions;  // NOT used here — this is the target model
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Discussion extends Model
{
    protected $fillable = [
        'discussable_id',
        'discussable_type',
        'user_id',
        'parent_id',
        'body',
        'status',
        'rating',
        'is_pinned',
        'edited_at',
        'approved_at',
        'approved_by_user_id',
    ];

    protected $casts = [
        'is_pinned'   => 'boolean',
        'rating'      => 'integer',
        'edited_at'   => 'datetime',
        'approved_at' => 'datetime',
    ];

    // ── Relationships ────────────────────────────────────────────────────────

    /** The model this discussion is attached to (Entry, Media, etc.). */
    public function discussable(): MorphTo
    {
        return $this->morphTo();
    }

    /** The user who authored this discussion. */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** The moderator who approved this discussion (nullable). */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    /** Parent discussion (null for top-level). */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** Direct replies to this discussion. */
    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('created_at');
    }

    /** Reactions (likes, upvotes, etc.) on this discussion. */
    public function reactions(): HasMany
    {
        return $this->hasMany(Reaction::class);
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    /** Only approved discussions. */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    /** Only top-level discussions (no parent). */
    public function scopeTopLevel(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    /** Only discussions that include a rating (review mode). */
    public function scopeWithRating(Builder $query): Builder
    {
        return $query->whereNotNull('rating');
    }

    /** Filter by a specific status value. */
    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /** Filter by author. */
    public function scopeByUser(Builder $query, int|User $user): Builder
    {
        $id = $user instanceof User ? $user->getKey() : $user;
        return $query->where('user_id', $id);
    }

    /** Pinned discussions first, then chronological. */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderByDesc('is_pinned')->orderBy('created_at');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function isTopLevel(): bool   { return $this->parent_id === null; }
    public function isApproved(): bool   { return $this->status === 'approved'; }
    public function isReview(): bool     { return $this->rating !== null; }
    public function isPinned(): bool     { return $this->is_pinned; }
    public function wasEdited(): bool    { return $this->edited_at !== null; }
}
```

---

### `App\Models\Discussion\Reaction`

Follows the `Models/Media/Library` sub-model namespace pattern.

```php
namespace App\Models\Discussion;

use App\Models\Discussion;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reaction extends Model
{
    protected $fillable = ['discussion_id', 'user_id', 'type'];

    public function discussion(): BelongsTo
    {
        return $this->belongsTo(Discussion::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

---

## Trait

### `App\Traits\Discussion\HasDiscussions`

Mirrors `Traits/Category/HasCategories` exactly: one method, one relationship,
no business logic.

```php
namespace App\Traits\Discussion;

use App\Models\Discussion;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasDiscussions
{
    public function discussions(): MorphMany
    {
        return $this->morphMany(Discussion::class, 'discussable');
    }
}
```

**To opt a model in, add one line:**

```php
// Entry.php  (example — no change required now)
use App\Traits\Discussion\HasDiscussions;

class Entry extends Model
{
    use Fieldable, HasCategories, HasDiscussions, HasFactory;
    // ...
}
```

---

## Repository

### `App\Repositories\DiscussionRepository`

Mirrors `EntryRepository`: transactional `create()`, explicit `applyData()` for
updates, named eager-load methods, and no framework coupling beyond Eloquent.

```php
namespace App\Repositories;

use App\Models\Discussion;
use App\Models\Discussion\Reaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class DiscussionRepository
{
    // ── Create ───────────────────────────────────────────────────────────────

    /**
     * Create a new top-level discussion or a reply.
     *
     * $data keys:
     *   body          string   required
     *   status        string   optional  default: 'pending'
     *   rating        int      optional  1–5, null for plain comment
     *   parent_id     int      optional  must be a top-level discussion
     *   is_pinned     bool     optional  default: false
     */
    public function create(Model $discussable, User $author, array $data): Discussion
    {
        return DB::transaction(function () use ($discussable, $author, $data) {
            // Enforce one-level threading: if a parent_id is given,
            // verify the parent is itself top-level.
            if (!empty($data['parent_id'])) {
                $parent = Discussion::findOrFail($data['parent_id']);
                if ($parent->parent_id !== null) {
                    throw new \InvalidArgumentException(
                        'Replies to replies are not supported. Use the top-level discussion id.'
                    );
                }
            }

            $discussion = new Discussion();
            $discussion->discussable_id   = $discussable->getKey();
            $discussion->discussable_type = $discussable->getMorphClass();
            $discussion->user_id          = $author->getKey();
            $discussion->status           = $data['status']    ?? 'pending';
            $discussion->is_pinned        = $data['is_pinned'] ?? false;
            $this->applyCoreAttributes($discussion, $data);
            $discussion->save();

            return $discussion->refresh();
        });
    }

    // ── Update ───────────────────────────────────────────────────────────────

    /**
     * Update body, rating, status, or pin state.
     * Sets edited_at automatically when body changes.
     */
    public function applyData(Discussion $discussion, array $data): Discussion
    {
        $bodyChanged = isset($data['body']) && $data['body'] !== $discussion->body;

        $this->applyCoreAttributes($discussion, $data);

        if (isset($data['status']))    $discussion->status    = $data['status'];
        if (isset($data['is_pinned'])) $discussion->is_pinned = $data['is_pinned'];

        if ($bodyChanged) {
            $discussion->edited_at = now();
        }

        $discussion->save();
        return $discussion->refresh();
    }

    // ── Approval ─────────────────────────────────────────────────────────────

    public function approve(Discussion $discussion, User $moderator): Discussion
    {
        $discussion->status               = 'approved';
        $discussion->approved_at          = now();
        $discussion->approved_by_user_id  = $moderator->getKey();
        $discussion->save();
        return $discussion;
    }

    // ── Delete ───────────────────────────────────────────────────────────────

    public function delete(Discussion $discussion): bool
    {
        return (bool) $discussion->delete();
    }

    // ── Reactions ────────────────────────────────────────────────────────────

    /**
     * Toggle a reaction: creates it if absent, removes it if present.
     * Returns true when the reaction now exists, false when removed.
     */
    public function toggleReaction(Discussion $discussion, User $user, string $type): bool
    {
        $existing = Reaction::where('discussion_id', $discussion->getKey())
            ->where('user_id',       $user->getKey())
            ->where('type',          $type)
            ->first();

        if ($existing) {
            $existing->delete();
            return false;
        }

        Reaction::create([
            'discussion_id' => $discussion->getKey(),
            'user_id'       => $user->getKey(),
            'type'          => $type,
        ]);

        return true;
    }

    // ── Finders ──────────────────────────────────────────────────────────────

    public function find(int $id): ?Discussion
    {
        return Discussion::with($this->defaultEagerLoad())->find($id);
    }

    public function findOrFail(int $id): Discussion
    {
        return Discussion::with($this->defaultEagerLoad())->findOrFail($id);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function applyCoreAttributes(Discussion $discussion, array $data): void
    {
        if (isset($data['body']))   $discussion->body   = $data['body'];
        if (isset($data['rating'])) $discussion->rating = $data['rating'];
        if (array_key_exists('parent_id', $data)) {
            $discussion->parent_id = $data['parent_id'];
        }
    }

    private function defaultEagerLoad(): array
    {
        return [
            'author',
            'approvedBy',
            'parent',
            'replies.author',
            'reactions',
        ];
    }
}
```

---

## Service

### `App\Services\DiscussionService`

Extends `AbstractService` (same as every other service). Houses validation and
business rules so the repository stays pure persistence.

```php
namespace App\Services;

use App\Models\Discussion;
use App\Models\User;
use App\Repositories\DiscussionRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class DiscussionService extends AbstractService
{
    public function __construct(
        $app,
        private readonly DiscussionRepository $repository,
    ) {
        parent::__construct($app);
    }

    // ── Public API ───────────────────────────────────────────────────────────

    /**
     * Post a new discussion (comment or review) on any discussable model.
     *
     * $data keys:
     *   body       string   required
     *   rating     int      optional  1–5
     *   parent_id  int      optional  reply to existing discussion
     */
    public function create(Model $discussable, User $author, array $data): Discussion
    {
        $errors = $this->validate($data);
        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        return $this->repository->create($discussable, $author, $data);
    }

    /**
     * Edit body and/or rating. Only the original author or a privileged caller
     * should be allowed by the controller/policy layer.
     */
    public function update(Discussion $discussion, array $data): Discussion
    {
        $errors = $this->validate($data, $discussion);
        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        return $this->repository->applyData($discussion, $data);
    }

    /**
     * Approve a pending discussion.
     */
    public function approve(Discussion $discussion, User $moderator): Discussion
    {
        return $this->repository->approve($discussion, $moderator);
    }

    /**
     * Flag a discussion for review.
     */
    public function flag(Discussion $discussion): Discussion
    {
        return $this->repository->applyData($discussion, ['status' => 'flagged']);
    }

    /**
     * Delete a discussion and its replies (cascade handled by DB constraint).
     */
    public function delete(Discussion $discussion): bool
    {
        return $this->repository->delete($discussion);
    }

    /**
     * Toggle a reaction ('like', 'upvote', 'helpful', etc.).
     * Returns true when the reaction now exists, false when it was removed.
     */
    public function toggleReaction(Discussion $discussion, User $user, string $type): bool
    {
        return $this->repository->toggleReaction($discussion, $user, $type);
    }

    /**
     * Compute the mean rating for a discussable model.
     * Returns null when no rated discussions exist.
     */
    public function averageRating(Model $discussable): ?float
    {
        return $discussable->discussions()
            ->approved()
            ->withRating()
            ->average('rating');
    }

    // ── Validation ───────────────────────────────────────────────────────────

    /**
     * Returns an array of field → message pairs (empty = valid).
     * Mirrors AbstractEntryType::validate() convention.
     */
    private function validate(array $data, ?Discussion $existing = null): array
    {
        $errors = [];

        // body is required on create, optional on update
        if ($existing === null && empty($data['body'])) {
            $errors['body'] = 'A discussion body is required.';
        }

        if (isset($data['body']) && mb_strlen($data['body']) > 10_000) {
            $errors['body'] = 'Discussion body may not exceed 10,000 characters.';
        }

        if (isset($data['rating'])) {
            $rating = (int) $data['rating'];
            if ($rating < 1 || $rating > 5) {
                $errors['rating'] = 'Rating must be between 1 and 5.';
            }
        }

        return $errors;
    }
}
```

---

## Migrations

### `xxxx_create_discussions_table.php`

```php
Schema::create('discussions', function (Blueprint $table) {
    $table->id();
    $table->morphs('discussable');            // discussable_id + discussable_type + index
    $table->foreignId('user_id')
          ->constrained()
          ->cascadeOnDelete();
    $table->foreignId('parent_id')
          ->nullable()
          ->constrained('discussions')
          ->nullOnDelete();
    $table->text('body');
    $table->enum('status', ['pending', 'approved', 'flagged', 'spam'])
          ->default('pending');
    $table->unsignedTinyInteger('rating')->nullable();
    $table->boolean('is_pinned')->default(false);
    $table->timestamp('edited_at')->nullable();
    $table->timestamp('approved_at')->nullable();
    $table->foreignId('approved_by_user_id')
          ->nullable()
          ->constrained('users')
          ->nullOnDelete();
    $table->timestamps();

    // Scoped listing index (discussable + status)
    $table->index(['discussable_id', 'discussable_type', 'status']);
});
```

### `xxxx_create_discussion_reactions_table.php`

```php
Schema::create('discussion_reactions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('discussion_id')
          ->constrained()
          ->cascadeOnDelete();
    $table->foreignId('user_id')
          ->constrained()
          ->cascadeOnDelete();
    $table->string('type', 64);
    $table->timestamps();

    $table->unique(['discussion_id', 'user_id', 'type']);
});
```

---

## Eager-Load Strategy

Following the `EntryRepository` pattern, `DiscussionRepository::defaultEagerLoad()`
returns:

```php
[
    'author',           // Discussion → User
    'approvedBy',       // Discussion → User (moderator)
    'parent',           // Discussion → Discussion (thread root)
    'replies.author',   // nested HasMany → User (avoids N+1 on reply lists)
    'reactions',        // HasMany → Reaction
]
```

Callers that only need metadata (e.g., a count widget) can use a lighter load:

```php
['author']   // just the author name/avatar
```

This matches the `defaultEagerLoad()` / `metaEagerLoad()` split in `EntryRepository`.

---

## Polymorphic Morph Summary

| Morph name      | Pivot / Target table    | Participating models      | Purpose                    |
|-----------------|-------------------------|---------------------------|----------------------------|
| `fieldable`     | `field_values`          | Entry, Category, User     | Custom field values        |
| `categorizable` | `categorizables`        | Entry, Media              | Category tagging           |
| `discussable`   | `discussions`           | Entry, Media, (any model) | Comments / reviews         |

---

## Open Questions / Deferred Decisions

1. **Soft deletes** — Should deleted discussions be hidden or permanently removed?
   A `deleted_at` column (SoftDeletes trait) would let moderators recover flagged
   discussions; omitted from this plan pending a decision.

2. **Nesting depth** — Currently hard-limited to one level (reply-to-discussion only).
   Deeper threading is possible by removing the repository guard and adding a `depth`
   column, similar to the `EntryTree` depth field.

3. **Auto-approval** — Trusted users (by role, using the existing Spatie `HasRoles`
   trait on `User`) could bypass `pending` and land directly in `approved`. This
   would live as a policy check inside `DiscussionService::create()`.

4. **Notifications** — Replies could dispatch a Laravel `Notification` to the parent
   author. This would go in `DiscussionService::create()` after the repository call,
   following the `afterCreate` pattern from `AbstractEntryType`.

5. **Read tracking** — An `entry_metrics`-style `discussion_reads` table could track
   view counts per discussion. Deferred until needed.

6. **API / Controller layer** — Not covered here; out of scope for the model layer plan.
