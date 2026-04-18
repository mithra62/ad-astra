<?php

namespace Database\Seeders;

use App\Models\Field;
use App\Models\Field\Group as FieldGroup;
use App\Models\Field\Type as FieldType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FieldGroupSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $text     = FieldType::where('object', \App\Field\Types\Text::class)->firstOrFail();
        $textarea = FieldType::where('object', \App\Field\Types\Textarea::class)->firstOrFail();

        $this->seedContentFields($text, $textarea);
        $this->seedSeoFields($text, $textarea);
    }

    private function seedContentFields(FieldType $text, FieldType $textarea): void
    {
        $group = FieldGroup::firstOrCreate(
            ['slug' => 'content-fields'],
            ['name' => 'Content Fields', 'description' => 'Core content fields for entries.']
        );

        $fields = [
            [
                'field_type_id' => $textarea->id,
                'name'          => 'Body',
                'slug'          => 'body',
                'label'         => 'Body',
                'instructions'  => 'The main content of the entry.',
            ],
            [
                'field_type_id' => $textarea->id,
                'name'          => 'Excerpt',
                'slug'          => 'excerpt',
                'label'         => 'Excerpt',
                'instructions'  => 'A short summary of the entry.',
            ],
        ];

        foreach ($fields as $fieldData) {
            $field = Field::firstOrCreate(['slug' => $fieldData['slug']], $fieldData);
            $group->fields()->syncWithoutDetaching([$field->id]);
        }
    }

    private function seedSeoFields(FieldType $text, FieldType $textarea): void
    {
        $group = FieldGroup::firstOrCreate(
            ['slug' => 'seo-fields'],
            ['name' => 'SEO Fields', 'description' => 'Search engine optimisation metadata.']
        );

        $fields = [
            [
                'field_type_id' => $text->id,
                'name'          => 'Meta Title',
                'slug'          => 'meta_title',
                'label'         => 'Meta Title',
                'instructions'  => 'Override the page title for search engines (max 60 chars).',
                'settings'      => ['maxLength' => 60],
            ],
            [
                'field_type_id' => $textarea->id,
                'name'          => 'Meta Description',
                'slug'          => 'meta_description',
                'label'         => 'Meta Description',
                'instructions'  => 'Summary for search engine result pages (max 160 chars).',
                'settings'      => ['maxLength' => 160],
            ],
        ];

        foreach ($fields as $fieldData) {
            $field = Field::firstOrCreate(['slug' => $fieldData['slug']], $fieldData);
            $group->fields()->syncWithoutDetaching([$field->id]);
        }
    }
}
