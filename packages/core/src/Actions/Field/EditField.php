<?php

namespace AdAstra\Actions\Field;

use AdAstra\Actions\Field\Concerns\FiltersFieldSettings;
use AdAstra\Models\EntryRelationship;
use AdAstra\Models\Field;
use AdAstra\Models\Field\Type as FieldType;
use Illuminate\Support\Facades\Log;

class EditField
{
    use FiltersFieldSettings;

    public ?string $warning = null;

    public function edit(Field $field, array $input): bool
    {
        // Resolve type handle to field_type_id if submitted as a handle string.
        if (isset($input['type']) && ! isset($input['field_type_id'])) {
            $resolved = FieldType::where('handle', $input['type'])->first();

            if ($resolved) {
                $input['field_type_id'] = $resolved->getKey();
            }

            unset($input['type']);
        }

        $typeChanged = isset($input['field_type_id'])
            && $field->field_type_id !== null
            && (int) $input['field_type_id'] !== (int) $field->field_type_id;

        if ($typeChanged && $this->fieldHasValues($field)) {
            throw new \RuntimeException(
                "Cannot change the type of field [{$field->handle}] — it has existing values. Migrate or clear the values first."
            );
        }

        $newTypeId = isset($input['field_type_id']) ? (int) $input['field_type_id'] : (int) $field->field_type_id;
        $newType = FieldType::find($newTypeId);

        if ($newType) {
            $newInstance = $newType->instance([], $field);

            if ($typeChanged) {
                $input['settings'] = $newInstance->settingsDefaults();
            } else {
                $input['settings'] = $this->filterSettings($input['settings'] ?? [], $newInstance);

                $form = $newInstance->settingsForm();
                if (
                    isset($form['strict_options']) &&
                    ! empty($input['settings']['strict_options']) &&
                    $this->fieldHasValues($field)
                ) {
                    $message = "Field [{$field->handle}] has strict_options enabled with existing values. ".
                        'Orphaned values will be rejected on next edit.';
                    Log::warning($message);
                    $this->warning = 'Strict options is enabled and this field has existing values. '.
                        'Any stored values that are no longer valid options will be rejected on next entry edit.';
                }
            }
        }

        return $field->update($input);
    }

    private function fieldHasValues(Field $field): bool
    {
        // Null-safe: orphaned Fields without a FieldType return null and the
        // outer null-safe check below treats them as non-relational.
        $currentInstance = $field->fieldType ? $field->typeInstance() : null;
        if ($currentInstance?->isRelational()) {
            return EntryRelationship::where('field_id', $field->getKey())->exists();
        }

        return $field->fieldValues()->exists();
    }
}
