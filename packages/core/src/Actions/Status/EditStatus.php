<?php

namespace AdAstra\Actions\Status;

use AdAstra\Actions\AbstractAction;
use AdAstra\Models\Status;
use AdAstra\Models\StatusGroup;
use Illuminate\Support\Facades\DB;

class EditStatus extends AbstractAction
{
    public function edit(Status $status, array $input): bool
    {
        $input['is_default'] = !empty($input['is_default']);

        return DB::transaction(function () use ($status, $input) {
            // Lock the status group row to serialise concurrent default changes.
            StatusGroup::lockForUpdate()->findOrFail($status->status_group_id);

            if ($input['is_default']) {
                Status::where('status_group_id', $status->status_group_id)
                    ->where('id', '!=', $status->getKey())
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            return $status->update($input);
        });
    }
}
