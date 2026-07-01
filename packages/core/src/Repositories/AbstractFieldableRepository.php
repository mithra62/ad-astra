<?php

namespace AdAstra\Repositories;

use AdAstra\Models\FieldValue;
use AdAstra\Repositories\Contracts\RepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;

/**
 * Base class for repositories that persist field values on Fieldable models.
 *
 * Shares the race-safe upsertFieldValue pattern (SQLSTATE-23000 retry) and the
 * generic applyFieldValues loop so that CategoryRepository, MediaRepository,
 * and any future Fieldable-backed repos do not duplicate this logic.
 *
 * Subclasses must implement resolveLayoutFields() to return the set of Field
 * models that are valid for the given model instance.  The method accepts the
 * broad Model type so the abstract declaration matches in every subclass
 * without violating PHP's parameter-type contravariance rules; in practice
 * each subclass only ever receives its specific model type at runtime.
 */
abstract class AbstractFieldableRepository implements RepositoryInterface
{
    /**
     * Persist field values for the given handles.
     *
     * Skips handles not present in the model's resolved layout and silently
     * ignores handles whose FieldType cannot be resolved.
     *
     * @param array<string, mixed> $fields ['field_handle' => value, ...]
     */
    protected function applyFieldValues(Model $model, array $fields): void
    {
        if (empty($fields)) {
            return;
        }

        $layoutFields = $this->resolveLayoutFields($model);

        foreach ($fields as $handle => $value) {
            $field = $layoutFields->firstWhere('handle', $handle);

            if (!$field || !$field->fieldType) {
                continue;
            }

            $instance = $field->typeInstance();

            $this->upsertFieldValue(
                $field->getKey(),
                $model->getKey(),
                $model->getMorphClass(),
                $instance->storageColumn(),
                $instance->prepareForStorage($value),
            );
        }
    }

    /**
     * Return the Field models reachable through this model's field layout.
     *
     * Implementations should eager-load the full layout chain and return an
     * empty Collection when no layout is configured.
     */
    abstract public function resolveLayoutFields(Model $model): Collection;

    /**
     * Race-safe field value upsert.
     *
     * Two concurrent requests can both find no existing row and race to INSERT.
     * The second INSERT hits a unique-constraint violation (SQLSTATE 23000).
     * Retrying once guarantees the second caller updates the row committed by
     * the first rather than failing with a duplicate-key exception.
     */
    protected function upsertFieldValue(
        int    $fieldId,
        int    $fieldableId,
        string $fieldableType,
        string $column,
        mixed  $value,
    ): void
    {
        $key = [
            'field_id' => $fieldId,
            'fieldable_id' => $fieldableId,
            'fieldable_type' => $fieldableType,
        ];

        try {
            FieldValue::updateOrCreate($key, [$column => $value]);
        } catch (QueryException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }

            FieldValue::updateOrCreate($key, [$column => $value]);
        }
    }
}
