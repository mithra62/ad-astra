<?php

namespace Database\Seeders;

use AdAstra\Models\Field\Type;

class FieldTypes
{
    public function run(): void
    {
        Type::create(['name' => 'text', 'object' => 'AdAstra\\Fieldtypes\\Text']);
        Type::create(['name' => 'textarea', 'object' => 'AdAstra\\Fieldtypes\\Textarea']);
        Type::create(['name' => 'text', 'object' => 'AdAstra\\Fieldtypes\\Text']);
        Type::create(['name' => 'text', 'object' => 'AdAstra\\Fieldtypes\\Text']);
        Type::create(['name' => 'text', 'object' => 'AdAstra\\Fieldtypes\\Text']);
    }
}
