<?php

namespace App\Actions\Field;

use App\Actions\Field\Concerns\FiltersFieldSettings;
use App\Models\Field;
use App\Models\Field\Type as FieldType;
use Illuminate\Support\Facades\Log;

class EditField
{
    use FiltersFieldSettings;

    public function edit(Field $field, array $input): bool
    {
        // Resolve type handle to field_type_id if submitted as a handle string.
        if (isset($input['type']) && !isset($input['field_type_id'])) {
            $resolved = FieldType::all()->first(
                fn(FieldType $ft) => $ft->instance()->handle() === $input['type']
            );

            if ($resolved) {
                $input['field_type_id'] = $resolved->getKey();
            }

            unset($input['type']);
        }

        $typeChanged = isset($input['field_type_id'])
            && $field->field_type_id !== null
            && (int) $input['field_type_id'] !== (int) $field->field_type_id;

        if ($typeChanged && $field->fieldValues()->exists()) {
            throw new \RuntimeException(
                "Cannot change the type of field [{$field->handle}] — it has existing values. Migrate or clear the values first."
            );
        }

        $newTypeId = isset($input['field_type_id']) ? (int) $input['field_type_id'] : (int) $field->field_type_id;
        $newType   = FieldType::find($newTypeId);

        if ($newType) {
            $newInstance = $newType->instance();

            if ($typeChanged) {
                $input['settings'] = $newInstance->settingsDefaults();
            } else {
                $input['settings'] = $this->filterSettings($input['settings'] ?? [], $newInstance);

                $form = $newInstance->settingsForm();
                if (
                    isset($form['strict_options']) &&
                    !empty($input['settings']['strict_options']) &&
                    $field->fieldValues()->exists()
                ) {
                    Log::warning(
                        "Field [{$field->handle}] has strict_options enabled with existing values. " .
                        'Orphaned values will be rejected on next edit.'
                    );
                }
            }
        }

        return $field->update($input);
    }
}
