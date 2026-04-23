<?php

namespace App\Actions\Field;

use App\Models\Field;
use App\Models\Field\Type as FieldType;

class EditField
{
    public function edit(Field $field, array $input): bool
    {
        // The form submits a type handle (e.g. 'text'); resolve it to field_type_id.
        if (isset($input['type']) && ! isset($input['field_type_id'])) {
            $resolved = FieldType::all()->first(
                fn (FieldType $ft) => $ft->instance()->handle() === $input['type']
            );

            if ($resolved) {
                $input['field_type_id'] = $resolved->getKey();
            }

            unset($input['type']);
        }

        if (
            isset($input['field_type_id']) &&
            $field->field_type_id !== null &&
            (int) $input['field_type_id'] !== (int) $field->field_type_id &&
            $field->fieldValues()->exists()
        ) {
            throw new \RuntimeException(
                "Cannot change the type of field [{$field->handle}] — it has existing values. Migrate or clear the values first."
            );
        }

        return $field->update($input);
    }
}
