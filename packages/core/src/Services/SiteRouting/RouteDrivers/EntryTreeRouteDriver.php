<?php

namespace AdAstra\Services\SiteRouting\RouteDrivers;

use AdAstra\Models\EntryTree;
use AdAstra\Services\SiteRouting\RouteResult;
use AdAstra\Services\EntryService;

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
                $query->published(); //we only want published entries for tree pages
            })
            ->first();

        if (!$node) {
            return null;
        }

        if (filled($node->redirect_url) && self::isSafeRedirect($node->redirect_url)) {
            return new RouteResult(
                type: 'entry_tree_redirect',
                template: '',
                data: [
                    'url' => $node->redirect_url,
                    'status' => $node->redirect_status ?: 302,
                ],
                resource: $node,
            );
        }

        $entry_service = app(EntryService::class);
        $entry = $entry_service->find($node->entry->id);

        if ($entry instanceof Entry) {

        }
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

    /**
     * Public so diagnostics (entry-tree.integrity doctor check) can apply the
     * exact same redirect gate the router does, without duplicating it.
     */
    public static function isSafeRedirect(?string $url): bool
    {
        if (!$url) {
            return false;
        }

        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            return true;  // relative
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        return in_array(strtolower((string)$scheme), ['http', 'https'], true);
    }
}
