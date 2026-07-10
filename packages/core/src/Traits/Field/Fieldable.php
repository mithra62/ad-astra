<?php

namespace AdAstra\Traits\Field;

use AdAstra\Models\FieldValue;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

trait Fieldable
{
    public function fieldValues(): MorphMany
    {
        return $this->morphMany(FieldValue::class, 'fieldable');
    }

    public function field(string $handle): mixed
    {
        return $this->fieldValues
            ->first(fn ($v) => $v->field?->handle === $handle)
            ?->resolvedValue();
    }

    /**
     * The Field models making up this record's intended schema, in layout order.
     *
     * Default empty — models with no field layout keep the stored-only fieldArray()
     * output. Fieldable models override this with their own layout accessor (User via
     * UserFieldLayout, Entry via getFieldLayout(), Category/Media via their owner).
     */
    public function fieldSchema(): Collection
    {
        return collect();
    }

    /**
     * Field values keyed by handle.
     *
     * When the model exposes a schema via fieldSchema(), every non-relational
     * schema handle is present — unset fields resolve to null — so the shape is
     * stable across records. Relational field types are excluded (they store in
     * entry_relationships, not field_values, and are resolved elsewhere). When no
     * schema is configured, only stored values are returned.
     */
    public function fieldArray(): array
    {
        $stored = $this->fieldValues
            ->filter(fn ($v) => $v->field !== null)
            ->mapWithKeys(fn ($v) => [$v->field->handle => $v->resolvedValue()])
            ->all();

        $schema = $this->fieldSchema();

        if ($schema->isEmpty()) {
            return $stored;
        }

        $template = [];
        foreach ($schema as $field) {
            $type = $field->fieldType ? $field->typeInstance() : null;

            if ($type && $type->isRelational()) {
                continue;
            }

            $template[$field->handle] = null;
        }

        // Stored values overlay the null template; any stored handle no longer in
        // the layout (e.g. a removed field) is preserved at the end.
        return array_merge($template, $stored);
    }
}
