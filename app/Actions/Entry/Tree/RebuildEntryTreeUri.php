<?php

namespace App\Actions\Entry\Tree;

use App\Models\EntryTree;
use App\Services\EntryService;

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
