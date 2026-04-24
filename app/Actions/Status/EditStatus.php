<?php

namespace App\Actions\Status;

use App\Actions\AbstractAction;
use App\Models\Status;

class EditStatus extends AbstractAction
{
    public function edit(Status $status, array $input): bool
    {
        $input['is_default'] = ! empty($input['is_default']);

        if ($input['is_default']) {
            Status::where('status_group_id', $status->status_group_id)
                ->where('id', '!=', $status->getKey())
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        return $status->update($input);
    }
}
