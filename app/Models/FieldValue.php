<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class FieldValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'field_id',
        'fieldable_id',
        'fieldable_type',
        'value_text',
        'value_integer',
        'value_float',
        'value_date',
        'value_boolean',
        'value_json',
    ];

    protected $with = ['field'];

    protected $casts = [
        'value_integer' => 'integer',
        'value_float' => 'float',
        'value_date' => 'datetime',
        'value_boolean' => 'boolean',
        'value_json' => 'array',
    ];

    public function fieldable(): MorphTo
    {
        return $this->morphTo();
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(Field::class);
    }

    /**
     * @return mixed
     */
    public function resolvedValue(): mixed
    {
        $fieldType = $this->field?->fieldType;

        if (!$fieldType) {
            return $this->value_text;
        }

        $column = $fieldType->instance()->storageColumn();

        return $this->field->fieldType
            ->instance()->value($this->{$column});
        //return $this->{$column};
    }
}
