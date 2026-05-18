<?php

namespace App\Observers;

use App\Models\EntryTree;
use App\Services\EntryService;

class EntryTreeObserver
{
    /**
     * Direct child IDs collected in `deleting()`, keyed by the deleted node's ID.
     *
     * Must be static because Laravel resolves the observer from the container on
     * every event dispatch — `deleting` and `deleted` each receive a fresh
     * instance, so an instance property would be empty by the time `deleted` runs.
     *
     * @var array<int, int[]>
     */
    private static array $pendingReroot = [];

    public function __construct(private readonly EntryService $entryService) {}

    /**
     * Before the node is deleted — snapshot IDs of all direct children while
     * the FK still resolves and the parent row still exists.
     */
    public function deleting(EntryTree $node): void
    {
        static::$pendingReroot[$node->id] = EntryTree::where('parent_id', $node->id)
            ->pluck('id')
            ->all();
    }

    /**
     * After the node is deleted — `nullOnDelete` has already set every child's
     * `parent_id` to NULL at the DB level.  Re-fetch each promoted child with
     * fresh state and rebuild its URI and depth recursively.
     *
     * Children that were cascade-deleted (e.g. because the entry itself was
     * deleted and `entry_id` has `cascadeOnDelete`) are simply skipped.
     */
    public function deleted(EntryTree $node): void
    {
        $childIds = static::$pendingReroot[$node->id] ?? [];
        unset(static::$pendingReroot[$node->id]);

        foreach ($childIds as $childId) {
            $child = EntryTree::find($childId);

            if (! $child) {
                // Row was removed by a cascade; nothing to rebuild.
                continue;
            }

            // Unset all cached relations so that rebuildTreeUri() reads the
            // current DB state (parent = null, children = live subtree).
            $child->unsetRelations();

            $this->entryService->rebuildTreeUri($child);
        }
    }
}
