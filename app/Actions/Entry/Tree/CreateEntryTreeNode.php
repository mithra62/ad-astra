<?php

namespace App\Actions\Entry\Tree;

use App\Actions\AbstractAction;
use App\Models\Entry;
use App\Models\EntryTree;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreateEntryTreeNode extends AbstractAction
{
    public function create(Entry $entry, string $handle, ?EntryTree $parent = null, ?string $template = null, bool $isHome = false): EntryTree
    {
        return DB::transaction(function () use ($entry, $handle, $parent, $template, $isHome) {
            $entry->loadMissing('entryType');

            if (! $entry->entryType?->has_entry_tree) {
                throw new InvalidArgumentException('This entry type does not support Entry Tree routing.');
            }

            $normalizedHandle = $isHome ? 'home' : EntryTree::validatedHandle($handle);

            $this->assertValidPlacement($parent, $isHome);
            $this->assertUniqueHandleWithinParent($normalizedHandle, $parent);

            $node = EntryTree::create([
                'entry_id' => $entry->id,
                'parent_id' => $parent?->id,
                'handle' => $normalizedHandle,
                'uri' => '__pending__' . uniqid(),
                'depth' => $parent ? $parent->depth + 1 : 0,
                'sort_order' => $this->nextSortOrder($parent),
                'template' => $template,
                'is_home' => $isHome,
            ]);

            $node->uri = $this->buildUri($node);
            $node->save();

            return $node->fresh(['entry.entryType', 'parent']);
        });
    }

    protected function nextSortOrder(?EntryTree $parent): int
    {
        return ((int) EntryTree::query()
                ->where('parent_id', $parent?->id)
                ->max('sort_order')) + 1;
    }

    protected function buildUri(EntryTree $node): string
    {
        if ($node->is_home) {
            return '/';
        }

        $segments = [];
        $current = $node;

        while ($current) {
            if (! $current->is_home) {
                array_unshift($segments, $current->handle);
            }

            $current = $current->parent;
        }

        return implode('/', array_filter($segments)) ?: '/';
    }

    protected function assertValidPlacement(?EntryTree $parent, bool $isHome): void
    {
        if ($isHome) {
            if ($parent) {
                throw new InvalidArgumentException('The Entry Tree home node must be a root node.');
            }

            if (EntryTree::query()->where('is_home', true)->exists()) {
                throw new InvalidArgumentException('Only one Entry Tree home node may exist.');
            }
        }
    }

    protected function assertUniqueHandleWithinParent(string $handle, ?EntryTree $parent): void
    {
        $query = EntryTree::query()->where('handle', $handle);

        if ($parent) {
            $query->where('parent_id', $parent->id);
        } else {
            $query->whereNull('parent_id');
        }

        if ($query->exists()) {
            throw new InvalidArgumentException("An Entry Tree node with handle [{$handle}] already exists at this level.");
        }
    }
}
