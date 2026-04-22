<?php

namespace App\Concerns;

use App\Models\Field;
use App\Models\FieldValue;
use Illuminate\Database\Eloquent\Model;

trait PersistsFieldValues
{
    public function setField(Model $model, string $slug, mixed $value): void
    {
        $field  = Field::where('slug', $slug)->firstOrFail();
        $column = $field->fieldType->instance()->storageColumn();

        FieldValue::updateOrCreate(
            [
                'field_id'       => $field->id,
                'fieldable_id'   => $model->getKey(),
                'fieldable_type' => $model->getMorphClass(),
            ],
            [$column => $value]
        );
    }

    /**
     * @param array<string, mixed> $fields  ['field_slug' => value]
     */
    public function setFields(Model $model, array $fields): void
    {
        if (empty($fields)) {
            return;
        }

        $fieldModels = Field::whereIn('slug', array_keys($fields))
            ->get()
            ->keyBy('slug');

        foreach ($fields as $slug => $value) {
            $field = $fieldModels->get($slug);

            if (! $field || ! $field->fieldType) {
                continue;
            }

            $column = $field->fieldType->instance()->storageColumn();

            FieldValue::updateOrCreate(
                [
                    'field_id'       => $field->id,
                    'fieldable_id'   => $model->getKey(),
                    'fieldable_type' => $model->getMorphClass(),
                ],
                [$column => $value]
            );
        }
    }
}
