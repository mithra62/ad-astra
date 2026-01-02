<?php

namespace App\Actions\Media\Library;

use App\Models\Media\Library;
use App\Actions\AbstractAction;

class EditMediaLibrary extends AbstractAction
{
    public function edit(Library $library, array $input): bool
    {
        return $library->update($input);
    }
}
