<?php

namespace Database\Seeders;

use App\Models\Field\Type;

class FieldTypes
{
    public function run(): void
    {
        Type::create(['name' => 'text', 'object' => 'App\\Fieldtypes\\Text']);
        Type::create(['name' => 'textarea', 'object' => 'App\\Fieldtypes\\Textarea']);
        Type::create(['name' => 'text', 'object' => 'App\\Fieldtypes\\Text']);
        Type::create(['name' => 'text', 'object' => 'App\\Fieldtypes\\Text']);
        Type::create(['name' => 'text', 'object' => 'App\\Fieldtypes\\Text']);
    }
}
