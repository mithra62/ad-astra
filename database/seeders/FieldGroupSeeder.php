<?php

namespace Database\Seeders;

use App\Field\Types\Relationship;
use App\Field\Types\Text;
use App\Field\Types\Textarea;
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
        $text = FieldType::where('object', Text::class)->firstOrFail();
        $textarea = FieldType::where('object', Textarea::class)->firstOrFail();
        $relationship = FieldType::where('object', Relationship::class)->firstOrFail();

        $this->seedContentFields($text, $textarea);
        $this->seedSeoFields($text, $textarea);
        $this->seedRelationshipFields($relationship);
    }

    private function seedContentFields(FieldType $text, FieldType $textarea): void
    {
        $group = FieldGroup::firstOrCreate(
            ['handle' => 'content-fields'],
            ['name' => 'Content Fields', 'description' => 'Core content fields for entries.']
        );

        $fields = [
            [
                'field_type_id' => $textarea->id,
                'name' => 'Body',
                'handle' => 'body',
                'label' => 'Body',
                'instructions' => 'The main content of the entry.',
            ],
            [
                'field_type_id' => $textarea->id,
                'name' => 'Excerpt',
                'handle' => 'excerpt',
                'label' => 'Excerpt',
                'instructions' => 'A short summary of the entry.',
            ],
        ];

        foreach ($fields as $fieldData) {
            $field = Field::firstOrCreate(['handle' => $fieldData['handle']], $fieldData);
            $group->fields()->syncWithoutDetaching([$field->id]);
        }
    }

    private function seedSeoFields(FieldType $text, FieldType $textarea): void
    {
        $group = FieldGroup::firstOrCreate(
            ['handle' => 'seo-fields'],
            ['name' => 'SEO Fields', 'description' => 'Search engine optimisation metadata.']
        );

        $fields = [
            [
                'field_type_id' => $text->id,
                'name' => 'Meta Title',
                'handle' => 'meta_title',
                'label' => 'Meta Title',
                'instructions' => 'Override the page title for search engines (max 60 chars).',
                'settings' => ['maxLength' => 60],
            ],
            [
                'field_type_id' => $textarea->id,
                'name' => 'Meta Description',
                'handle' => 'meta_description',
                'label' => 'Meta Description',
                'instructions' => 'Summary for search engine result pages (max 160 chars).',
                'settings' => ['maxLength' => 160],
            ],
        ];

        foreach ($fields as $fieldData) {
            $field = Field::firstOrCreate(['handle' => $fieldData['handle']], $fieldData);
            $group->fields()->syncWithoutDetaching([$field->id]);
        }
    }

    private function seedRelationshipFields(FieldType $relationship): void
    {
        $group = FieldGroup::firstOrCreate(
            ['handle' => 'relationship-fields'],
            ['name' => 'Relationship Fields', 'description' => 'Fields for linking related entries.']
        );

        $field = Field::firstOrCreate(
            ['handle' => 'related_entries'],
            [
                'field_type_id' => $relationship->id,
                'name' => 'Related Entries',
                'handle' => 'related_entries',
                'label' => 'Related Entries',
                'instructions' => 'Link related entries from the same section.',
                'settings' => ['limit' => 5],
            ]
        );

        $group->fields()->syncWithoutDetaching([$field->id]);
    }
}
