<?php

namespace App\Actions\Entry\Tree;

use App\Models\EntryTree;

class RebuildEntryTreeUri
{
    public function handle(EntryTree $node): void
    {
        $node->loadMissing(['parent', 'children']);

        $node->depth = $node->parent
            ? $node->parent->depth + 1
            : 0;

        $node->uri = $this->buildUri($node);
        $node->save();

        foreach ($node->children as $child) {
            $this->handle($child);
        }
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
}
