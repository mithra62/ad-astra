<?php

namespace App\Actions\Field;

use App\Models\Field;

class EditField
{
    public function edit(Field $field, array $input): bool
    {
        return $field->update($input);
    }
}
