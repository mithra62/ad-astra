<?php

namespace App\Traits;

use App\Models\FieldValue;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait Fieldable
{
    public function fieldValues(): MorphMany
    {
        return $this->morphMany(FieldValue::class, 'fieldable');
    }

    public function field(string $handle): mixed
    {
        return $this->fieldValues
            ->first(fn ($v) => $v->field->handle === $handle)
            ?->resolvedValue();
    }

    public function fieldArray(): array
    {
        return $this->fieldValues
            ->filter(fn ($v) => $v->field !== null)
            ->mapWithKeys(fn ($v) => [$v->field->handle => $v->resolvedValue()])
            ->all();
    }
}
