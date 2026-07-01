<?php

namespace AdAstra\Traits;

use AdAstra\Models\Status;
use AdAstra\Observers\StatusSyncRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * For models that carry a single Status instance via a denormalized triple.
 *
 * Schema contract (every consumer must declare these):
 *
 *   $table->unsignedBigInteger('status_id')->nullable()->index();
 *   $table->string('status_handle')->nullable()->index();
 *   $table->boolean('status_is_public')->default(false)->index();
 *
 * Use $table->statusColumns() (Blueprint macro) for the column trio.
 * FK to `statuses` is wired in a follow-up migration when `statuses` exists.
 *
 * Required model fields:
 *
 *   protected $fillable = [..., 'status_id', 'status_handle', 'status_is_public'];
 *   protected $casts    = [..., 'status_is_public' => 'boolean'];
 *
 * Note: this trait does NOT define scopePublished. That scope is domain-specific
 * (Entry combines status_is_public + published_at clauses; Media is a single-
 * column predicate). Define scopePublished on each model as needed, typically
 * by composing this trait's scopePublic() with whatever extra clauses apply.
 *
 * @see StatusSyncRegistry
 * @see \AdAstra\Observers\StatusObserver
 */
trait HasStatus
{
    /**
     * Registers the consuming model with StatusSyncRegistry so StatusObserver
     * can cascade is_public / handle changes here on every Status update.
     *
     * In local/testing environments, asserts that the consumer satisfies the
     * column / fillable / cast contract. Throws LogicException early instead
     * of letting drift surface as silent stale data at runtime. The contract
     * check runs BEFORE registration so failing classes never enter the
     * registry — the observer would otherwise try to query a broken consumer
     * on the next Status save.
     */
    public static function bootHasStatus(): void
    {
        if (app()->environment(['local', 'testing'])) {
            $instance = new static();

            $missing = array_diff(
                ['status_id', 'status_handle', 'status_is_public'],
                $instance->getFillable()
            );
            if (!empty($missing)) {
                throw new LogicException(sprintf(
                    '%s uses HasStatus but is missing column(s) from $fillable: %s.',
                    static::class,
                    implode(', ', $missing)
                ));
            }

            $cast = $instance->getCasts()['status_is_public'] ?? '(none)';
            if ($cast !== 'boolean' && $cast !== 'bool') {
                throw new LogicException(sprintf(
                    '%s uses HasStatus but $casts[\'status_is_public\'] is %s, expected boolean.',
                    static::class,
                    $cast
                ));
            }
        }

        StatusSyncRegistry::register(static::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    public function scopeWithStatus(Builder $query, string $handle): Builder
    {
        return $query->where('status_handle', $handle);
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('status_is_public', true);
    }
}
