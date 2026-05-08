<?php

namespace Database\Seeders;

use App\Models\Field\Type;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FieldTypeSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $types = [
            ['name' => 'Text', 'object' => \App\Field\Types\Text::class],
            ['name' => 'Textarea', 'object' => \App\Field\Types\Textarea::class],
            ['name' => 'Number', 'object' => \App\Field\Types\Number::class],
            ['name' => 'Date', 'object' => \App\Field\Types\Date::class],
            ['name' => 'Email Address', 'object' => \App\Field\Types\EmailAddress::class],
            ['name' => 'URL', 'object' => \App\Field\Types\Url::class],
            ['name' => 'Telephone', 'object' => \App\Field\Types\Telephone::class],
            ['name' => 'Color Picker', 'object' => \App\Field\Types\ColorPicker::class],
            ['name' => 'Relationship', 'object' => \App\Field\Types\Relationship::class],
            ['name' => 'Boolean', 'object' => \App\Field\Types\Boolean::class],
            ['name' => 'File Upload', 'object' => \App\Field\Types\FileUpload::class],
        ];

        foreach ($types as $type) {
            Type::firstOrCreate(['object' => $type['object']], $type);
        }
    }
}
