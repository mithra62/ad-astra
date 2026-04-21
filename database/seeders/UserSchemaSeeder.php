<?php

namespace Database\Seeders;

use App\Models\Field;
use App\Models\Field\Group as FieldGroup;
use App\Models\Field\Type as FieldType;
use App\Models\FieldLayout;
use App\Models\FieldLayout\Tab;
use App\Models\FieldLayout\TabElement;
use App\Models\UserSchema;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSchemaSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $text     = FieldType::where('object', \App\Field\Types\Text::class)->firstOrFail();
        $textarea = FieldType::where('object', \App\Field\Types\Textarea::class)->firstOrFail();

        $profileGroup = $this->seedProfileFields($text);
        $bioGroup     = $this->seedBioFields($text, $textarea);

        $layout = $this->buildLayout([
            'Profile' => ['first_name', 'last_name', 'gender', 'date_of_birth', 'website'],
            'Bio'     => ['bio', 'social_twitter', 'social_linkedin'],
        ]);

        $schema = UserSchema::instance();
        $schema->field_layout_id = $layout->id;
        $schema->save();

        $schema->fieldGroups()->syncWithoutDetaching([$profileGroup->id, $bioGroup->id]);
    }

    private function seedProfileFields(FieldType $text): FieldGroup
    {
        $group = FieldGroup::firstOrCreate(
            ['slug' => 'user-profile'],
            ['name' => 'User Profile', 'description' => 'Core identity fields for all users.']
        );

        $fields = [
            [
                'field_type_id' => $text->id,
                'name'          => 'First Name',
                'slug'          => 'first_name',
                'label'         => 'First Name',
                'instructions'  => "The user's first name.",
            ],
            [
                'field_type_id' => $text->id,
                'name'          => 'Last Name',
                'slug'          => 'last_name',
                'label'         => 'Last Name',
                'instructions'  => "The user's last name.",
            ],
            [
                'field_type_id' => $text->id,
                'name'          => 'Gender',
                'slug'          => 'gender',
                'label'         => 'Gender',
                'instructions'  => "The user's gender identity.",
            ],
            [
                'field_type_id' => $text->id,
                'name'          => 'Date of Birth',
                'slug'          => 'date_of_birth',
                'label'         => 'Date of Birth',
                'instructions'  => 'Format: YYYY-MM-DD.',
            ],
            [
                'field_type_id' => $text->id,
                'name'          => 'Website',
                'slug'          => 'website',
                'label'         => 'Website URL',
                'instructions'  => "The user's personal or professional website.",
            ],
        ];

        foreach ($fields as $fieldData) {
            $field = Field::firstOrCreate(['slug' => $fieldData['slug']], $fieldData);
            $group->fields()->syncWithoutDetaching([$field->id]);
        }

        return $group;
    }

    private function seedBioFields(FieldType $text, FieldType $textarea): FieldGroup
    {
        $group = FieldGroup::firstOrCreate(
            ['slug' => 'user-bio'],
            ['name' => 'User Bio', 'description' => 'Biography and social presence fields.']
        );

        $fields = [
            [
                'field_type_id' => $textarea->id,
                'name'          => 'Bio',
                'slug'          => 'bio',
                'label'         => 'Biography',
                'instructions'  => 'A short biography displayed on the user profile.',
            ],
            [
                'field_type_id' => $text->id,
                'name'          => 'Twitter',
                'slug'          => 'social_twitter',
                'label'         => 'Twitter / X Handle',
                'instructions'  => 'Without the @ symbol.',
            ],
            [
                'field_type_id' => $text->id,
                'name'          => 'LinkedIn',
                'slug'          => 'social_linkedin',
                'label'         => 'LinkedIn Profile URL',
                'instructions'  => 'Full URL to the LinkedIn profile.',
            ],
        ];

        foreach ($fields as $fieldData) {
            $field = Field::firstOrCreate(['slug' => $fieldData['slug']], $fieldData);
            $group->fields()->syncWithoutDetaching([$field->id]);
        }

        return $group;
    }

    /**
     * Build a FieldLayout with named tabs, each containing field slugs.
     *
     * @param array<string, string[]> $tabs
     */
    private function buildLayout(array $tabs): FieldLayout
    {
        $layout   = FieldLayout::create(['name' => 'User Profile Layout']);
        $tabOrder = 1;

        foreach ($tabs as $tabName => $slugs) {
            $tab = Tab::create([
                'field_layout_id' => $layout->id,
                'name'            => $tabName,
                'sort_order'      => $tabOrder++,
            ]);

            $elementOrder = 1;
            foreach ($slugs as $slug) {
                $field = Field::where('slug', $slug)->first();
                if (! $field) {
                    continue;
                }

                TabElement::create([
                    'field_layout_tab_id' => $tab->id,
                    'field_id'            => $field->id,
                    'required'            => false,
                    'sort_order'          => $elementOrder++,
                ]);
            }
        }

        return $layout;
    }
}
