<?php

namespace Database\Seeders;

use AdAstra\Models\Field\Type;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FieldTypeSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $types = [
            ['name' => 'Text', 'object' => \AdAstra\Field\Types\Text::class],
            ['name' => 'Textarea', 'object' => \AdAstra\Field\Types\Textarea::class],
            ['name' => 'Number', 'object' => \AdAstra\Field\Types\Number::class],
            ['name' => 'Date', 'object' => \AdAstra\Field\Types\Date::class],
            ['name' => 'Email Address', 'object' => \AdAstra\Field\Types\EmailAddress::class],
            ['name' => 'URL', 'object' => \AdAstra\Field\Types\Url::class],
            ['name' => 'Telephone', 'object' => \AdAstra\Field\Types\Telephone::class],
            ['name' => 'Color Picker', 'object' => \AdAstra\Field\Types\ColorPicker::class],
            ['name' => 'Relationship', 'object' => \AdAstra\Field\Types\Relationship::class],
            ['name' => 'Boolean', 'object' => \AdAstra\Field\Types\Boolean::class],
            ['name' => 'File Upload', 'object' => \AdAstra\Field\Types\FileUpload::class],
            ['name' => 'Media', 'object' => \AdAstra\Field\Types\Media::class],
            ['name' => 'Select', 'object' => \AdAstra\Field\Types\Select::class],
            ['name' => 'Multi Select', 'object' => \AdAstra\Field\Types\MultiSelect::class],
            ['name' => 'Radio Group', 'object' => \AdAstra\Field\Types\RadioGroup::class],
            ['name' => 'Slider', 'object' => \AdAstra\Field\Types\Slider::class],
            ['name' => 'Users', 'object' => \AdAstra\Field\Types\Users::class],
            ['name' => 'Structured Rows', 'object' => \AdAstra\Field\Types\StructuredRows::class],
            ['name' => 'Money', 'object' => \AdAstra\Field\Types\Money::class],
            ['name' => 'Country', 'object' => \AdAstra\Field\Types\Country::class],
            ['name' => 'State/Province', 'object' => \AdAstra\Field\Types\StateProvince::class],
            ['name' => 'Time', 'object' => \AdAstra\Field\Types\Time::class],
            ['name' => 'Html', 'object' => \AdAstra\Field\Types\Html::class],
        ];

        foreach ($types as $type) {
            Type::firstOrCreate(['object' => $type['object']], $type);
        }
    }
}
