<?php

namespace App\Actions\Status;

use App\Actions\AbstractAction;
use App\Models\Status;

class EditStatus extends AbstractAction
{
    public function edit(Status $status, array $input): bool
    {
        $input['is_default'] = ! empty($input['is_default']);

        return $status->update($input);
    }
}
