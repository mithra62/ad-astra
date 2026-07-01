<?php

namespace AdAstra\Actions\Entry\Tree;

use AdAstra\Models\EntryTree;
use AdAstra\Services\EntryService;

/**
 * @deprecated Delegate to EntryService::rebuildTreeUri() directly.
 */
class RebuildEntryTreeUri
{
    public function __construct(private readonly EntryService $entryService)
    {
    }

    public function handle(EntryTree $node): void
    {
        $this->entryService->rebuildTreeUri($node);
    }
}
