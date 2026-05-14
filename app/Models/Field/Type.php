<?php

namespace App\Models\Field;

use App\Field\AbstractField;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class Type extends Model
{
    use HasFactory;

    /**
     * @var string[]
     */
    protected $fillable = ['name', 'object', 'settings'];

    /**
     * @var string[]
     */
    protected $casts = ['settings' => 'array'];

    /**
     * @var string
     */
    protected $table = 'field_types';

    /**
     * Creates an instance of the specified field type class.
     *
     * @return AbstractField An instance of the field type class.
     *
     * @throws RuntimeException If the specified class does not extend AbstractField.
     * @throws RuntimeException If the specified class does not exist.
     */
    public function instance(array $fieldSettings = []): AbstractField
    {
        $class = $this->object;

        if (!class_exists($class)) {
            throw new RuntimeException("FieldType class [{$class}] does not exist.");
        }

        if (!is_subclass_of($class, AbstractField::class)) {
            throw new RuntimeException("FieldType class [{$class}] must extend AbstractField.");
        }

        $merged = array_merge($this->settings ?? [], $fieldSettings);

        return new $class($merged);
    }
}
