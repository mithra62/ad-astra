<?php

namespace App\Actions\Media\Library;

use App\Actions\AbstractAction;
use App\Jobs\ProcessMediaLibraryRemoval;
use App\Models\Media\Library;

class DeleteMediaLibrary extends AbstractAction
{
    public function delete(Library $library): bool
    {
        $libraryId = $library->id;
        $deleted   = $library->delete();

        if ($deleted) {
            ProcessMediaLibraryRemoval::dispatch($libraryId);
        }

        return (bool) $deleted;
    }
}
