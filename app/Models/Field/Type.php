<?php

namespace App\Models\Field;

use App\Field\AbstractField;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class Type extends Model
{
    protected $fillable = ['name', 'object', 'settings'];

    protected $casts = ['settings' => 'array'];

    protected $table = 'field_types';

    public function instance(): AbstractField
    {
        $class = $this->object;

        if (! class_exists($class)) {
            throw new RuntimeException("FieldType class [{$class}] does not exist.");
        }

        if (! is_subclass_of($class, AbstractField::class)) {
            throw new RuntimeException("FieldType class [{$class}] must extend AbstractField.");
        }

        return new $class($this->settings ?? []);
    }
}
