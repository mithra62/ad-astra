<?php

namespace AdAstra\Actions\Entry\Tree;

use AdAstra\Actions\AbstractAction;
use AdAstra\Models\Entry;
use AdAstra\Models\EntryTree;
use AdAstra\Services\EntryService;

/**
 * @deprecated Delegate to EntryService::createTreeNode() directly.
 *             Does not forward the redirectUrl / redirectStatus parameters.
 */
class CreateEntryTreeNode extends AbstractAction
{
    public function __construct(private readonly EntryService $entryService)
    {
    }

    public function create(Entry $entry, string $handle, ?EntryTree $parent = null, ?string $template = null, bool $isHome = false): EntryTree
    {
        return $this->entryService->createTreeNode($entry, $handle, $parent, $template, $isHome);
    }
}
