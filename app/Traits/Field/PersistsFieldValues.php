<?php

namespace App\Traits\Field;

use App\Models\Field;
use App\Models\FieldValue;
use Illuminate\Database\Eloquent\Model;

trait PersistsFieldValues
{
    public function setField(Model $model, string $handle, mixed $value): void
    {
        $field = Field::where('handle', $handle)->firstOrFail();
        $instance = $field->typeInstance();

        FieldValue::updateOrCreate(
            [
                'field_id' => $field->id,
                'fieldable_id' => $model->getKey(),
                'fieldable_type' => $model->getMorphClass(),
            ],
            [$instance->storageColumn() => $instance->prepareForStorage($value)]
        );
    }

    /**
     * @param array<string, mixed> $fields ['field_handle' => value]
     */
    public function setFields(Model $model, array $fields): void
    {
        if (empty($fields)) {
            return;
        }

        $fieldModels = Field::whereIn('handle', array_keys($fields))
            ->get()
            ->keyBy('handle');

        foreach ($fields as $handle => $value) {
            $field = $fieldModels->get($handle);

            if (!$field || !$field->fieldType) {
                continue;
            }

            $instance = $field->typeInstance();

            FieldValue::updateOrCreate(
                [
                    'field_id' => $field->id,
                    'fieldable_id' => $model->getKey(),
                    'fieldable_type' => $model->getMorphClass(),
                ],
                [$instance->storageColumn() => $instance->prepareForStorage($value)]
            );
        }
    }
}
