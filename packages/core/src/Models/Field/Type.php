<?php

namespace AdAstra\Models\Field;

use AdAstra\Field\AbstractField;
use AdAstra\Models\Field;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class Type extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'object',
        'settings'
    ];

    protected $casts = [
        'settings' => 'array'
    ];

    protected $table = 'field_types';

    /**
     * Creates an instance of the specified field type class.
     *
     * @return AbstractField An instance of the field type class.
     *
     * @throws RuntimeException If the specified class does not extend AbstractField.
     * @throws RuntimeException If the specified class does not exist.
     */
    public function instance(array $fieldSettings = [], ?Field $field = null): AbstractField
    {
        $class = $this->object;

        if (!class_exists($class)) {
            throw new RuntimeException("FieldType class [{$class}] does not exist.");
        }

        if (!is_subclass_of($class, AbstractField::class)) {
            throw new RuntimeException("FieldType class [{$class}] must extend AbstractField.");
        }

        $merged = array_merge($this->settings ?? [], $fieldSettings);

        $instance = new $class($merged);

        // When the caller has the owning Field in scope, attach it so the
        // field type can read $this->field->handle for things like input_name
        // defaulting and old() lookups in render().
        if ($field) {
            $instance->setField($field);
        }

        return $instance;
    }
}
