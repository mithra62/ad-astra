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
            ->first(fn($v) => $v->field->slug === $handle)
            ?->resolvedValue();
    }
}
