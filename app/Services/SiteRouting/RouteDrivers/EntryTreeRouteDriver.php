<?php

namespace App\Services\SiteRouting\RouteDrivers;

use App\Models\EntryTree;
use App\Services\SiteRouting\RouteResult;

class EntryTreeRouteDriver implements RouteDriverInterface
{
    public function resolve(?string $uri): ?RouteResult
    {
        $uri = EntryTree::normalizeUri($uri);
        $node = EntryTree::query()
            ->with([
                'entry.entryType',
                'parent.entry',
                'children.entry.entryType',
            ])
            ->where('uri', $uri)
            ->whereHas('entry', function ($query) {
                $query->published();
            })
            ->first();

        if (!$node) {
            return null;
        }

        if (filled($node->redirect_url)) {
            return new RouteResult(
                type: 'entry_tree_redirect',
                template: '',
                data: [
                    'url' => $node->redirect_url,
                    'status' => 302,
                ],
                resource: $node,
            );
        }

        $entry = $node->entry;

        $template = $node->template
            ?? $entry->entryType?->default_template
            ?? 'entries.show';

        return new RouteResult(
            type: 'entry_tree',
            template: 'templates::' . $template,
            data: [
                'entry' => $entry,
                'entryType' => $entry->entryType,
                'node' => $node,
            ],
            resource: $node,
        );
    }
}
