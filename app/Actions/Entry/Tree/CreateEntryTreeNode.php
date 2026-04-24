<?php

namespace App\Actions\Entry\Tree;

use App\Actions\AbstractAction;
use App\Models\Entry;
use App\Models\EntryTree;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreateEntryTreeNode extends AbstractAction
{
    public function create(Entry $entry, string $slug, ?EntryTree $parent = null, ?string $template = null, bool $isHome = false): EntryTree
    {
        return DB::transaction(function () use ($entry, $slug, $parent, $template, $isHome) {
            $entry->loadMissing('type');

            if (! $entry->type->has_entry_tree) {
                throw new InvalidArgumentException('This entry type does not support Entry Tree routing.');
            }

            $node = EntryTree::create([
                'entry_id' => $entry->id,
                'parent_id' => $parent?->id,
                'slug' => $isHome ? 'home' : EntryTree::normalizeSlug($slug),
                'uri' => '__pending__' . uniqid(),
                'depth' => $parent ? $parent->depth + 1 : 0,
                'sort_order' => $this->nextSortOrder($parent),
                'template' => $template,
                'is_home' => $isHome,
            ]);

            $node->uri = $this->buildUri($node);
            $node->save();

            return $node->fresh(['entry.type', 'parent']);
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
                array_unshift($segments, $current->slug);
            }

            $current = $current->parent;
        }

        return implode('/', array_filter($segments)) ?: '/';
    }
}
