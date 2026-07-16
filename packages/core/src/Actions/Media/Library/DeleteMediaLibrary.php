<?php

namespace AdAstra\Actions\Media\Library;

use AdAstra\Actions\AbstractAction;
use AdAstra\Jobs\ProcessMediaLibraryRemoval;
use AdAstra\Models\Media\Library;

class DeleteMediaLibrary extends AbstractAction
{
    public function delete(Library $library): bool
    {
        $libraryId = $library->id;
        $deleted = $library->delete();

        if ($deleted) {
            ProcessMediaLibraryRemoval::dispatch($libraryId);
        }

        return (bool)$deleted;
    }
}
