<?php
namespace App\Actions\Media\Library;

use App\Models\Media\Library;
use App\Actions\AbstractAction;

class DeleteMediaLibrary extends AbstractAction
{
    /**
     * @todo add to job queue
     */
    public function delete(Library $library): bool
    {
        return $library->delete();
    }
}
