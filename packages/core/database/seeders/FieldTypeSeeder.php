<?php

namespace Database\Seeders;

use AdAstra\Field\Types\Boolean;
use AdAstra\Field\Types\ColorPicker;
use AdAstra\Field\Types\Country;
use AdAstra\Field\Types\Date;
use AdAstra\Field\Types\EmailAddress;
use AdAstra\Field\Types\FileUpload;
use AdAstra\Field\Types\Html;
use AdAstra\Field\Types\Media;
use AdAstra\Field\Types\Money;
use AdAstra\Field\Types\MultiSelect;
use AdAstra\Field\Types\Number;
use AdAstra\Field\Types\RadioGroup;
use AdAstra\Field\Types\Relationship;
use AdAstra\Field\Types\Select;
use AdAstra\Field\Types\Slider;
use AdAstra\Field\Types\StateProvince;
use AdAstra\Field\Types\StructuredRows;
use AdAstra\Field\Types\Telephone;
use AdAstra\Field\Types\Text;
use AdAstra\Field\Types\Textarea;
use AdAstra\Field\Types\Time;
use AdAstra\Field\Types\Url;
use AdAstra\Field\Types\Users;
use AdAstra\Models\Field\Type;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FieldTypeSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $types = [
            ['name' => 'Text', 'object' => Text::class],
            ['name' => 'Textarea', 'object' => Textarea::class],
            ['name' => 'Number', 'object' => Number::class],
            ['name' => 'Date', 'object' => Date::class],
            ['name' => 'Email Address', 'object' => EmailAddress::class],
            ['name' => 'URL', 'object' => Url::class],
            ['name' => 'Telephone', 'object' => Telephone::class],
            ['name' => 'Color Picker', 'object' => ColorPicker::class],
            ['name' => 'Relationship', 'object' => Relationship::class],
            ['name' => 'Boolean', 'object' => Boolean::class],
            ['name' => 'File Upload', 'object' => FileUpload::class],
            ['name' => 'Media', 'object' => Media::class],
            ['name' => 'Select', 'object' => Select::class],
            ['name' => 'Multi Select', 'object' => MultiSelect::class],
            ['name' => 'Radio Group', 'object' => RadioGroup::class],
            ['name' => 'Slider', 'object' => Slider::class],
            ['name' => 'Users', 'object' => Users::class],
            ['name' => 'Structured Rows', 'object' => StructuredRows::class],
            ['name' => 'Money', 'object' => Money::class],
            ['name' => 'Country', 'object' => Country::class],
            ['name' => 'State/Province', 'object' => StateProvince::class],
            ['name' => 'Time', 'object' => Time::class],
            ['name' => 'Html', 'object' => Html::class],
        ];

        foreach ($types as $type) {
            Type::firstOrCreate(['object' => $type['object']], $type);
        }
    }
}
