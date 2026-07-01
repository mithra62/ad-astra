<?php

namespace Database\Seeders;

use AdAstra\Field\Types\Text;
use AdAstra\Field\Types\Url;
use AdAstra\Field\Types\Date;
use AdAstra\Field\Types\Textarea;
use AdAstra\Models\Field;
use AdAstra\Models\Field\Group as FieldGroup;
use AdAstra\Models\Field\Type as FieldType;
use AdAstra\Models\FieldLayout;
use AdAstra\Models\FieldLayout\Tab;
use AdAstra\Models\FieldLayout\TabElement;
use AdAstra\Settings;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UserSchemaSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $text = FieldType::where('object', Text::class)->firstOrFail();
        $textarea = FieldType::where('object', Textarea::class)->firstOrFail();
        $url = FieldType::where('object', Url::class)->firstOrFail();
        $date = FieldType::where('object', Date::class)->firstOrFail();

        $profileGroup = $this->seedProfileFields($text, $url, $date);
        $bioGroup = $this->seedBioFields($text, $textarea, $url);

        $layout = $this->buildLayout([
            'Profile' => ['first_name', 'last_name', 'gender', 'date_of_birth', 'website'],
            'Bio' => ['bio', 'social_twitter', 'social_linkedin'],
        ]);

        app(Settings::class)->set('users', 'user_field_layout_id', $layout->id);
    }

    private function seedProfileFields(FieldType $text, FieldType $url, FieldType $date): FieldGroup
    {
        $group = FieldGroup::firstOrCreate(
            ['handle' => 'user-profile'],
            ['name' => 'User Profile', 'description' => 'Core identity fields for all users.']
        );

        $fields = [
            [
                'field_type_id' => $text->id,
                'name' => 'First Name',
                'handle' => 'first_name',
                'label' => 'First Name',
                'instructions' => "The user's first name.",
            ],
            [
                'field_type_id' => $text->id,
                'name' => 'Last Name',
                'handle' => 'last_name',
                'label' => 'Last Name',
                'instructions' => "The user's last name.",
            ],
            [
                'field_type_id' => $text->id,
                'name' => 'Gender',
                'handle' => 'gender',
                'label' => 'Gender',
                'instructions' => "The user's gender identity.",
            ],
            [
                'field_type_id' => $date->id,
                'name' => 'Date of Birth',
                'handle' => 'date_of_birth',
                'label' => 'Date of Birth',
                'instructions' => 'Format: YYYY-MM-DD.',
            ],
            [
                'field_type_id' => $url->id,
                'name' => 'Website',
                'handle' => 'website',
                'label' => 'Website URL',
                'instructions' => "The user's personal or professional website.",
            ],
        ];

        foreach ($fields as $fieldData) {
            $field = Field::firstOrCreate(['handle' => $fieldData['handle']], $fieldData);
            $group->fields()->syncWithoutDetaching([$field->id]);
        }

        return $group;
    }

    private function seedBioFields(FieldType $text, FieldType $textarea, FieldType $url): FieldGroup
    {
        $group = FieldGroup::firstOrCreate(
            ['handle' => 'user-bio'],
            ['name' => 'User Bio', 'description' => 'Biography and social presence fields.']
        );

        $fields = [
            [
                'field_type_id' => $textarea->id,
                'name' => 'Bio',
                'handle' => 'bio',
                'label' => 'Biography',
                'instructions' => 'A short biography displayed on the user profile.',
            ],
            [
                'field_type_id' => $text->id,
                'name' => 'Twitter',
                'handle' => 'social_twitter',
                'label' => 'Twitter / X Handle',
                'instructions' => 'Without the @ symbol.',
            ],
            [
                'field_type_id' => $url->id,
                'name' => 'LinkedIn',
                'handle' => 'social_linkedin',
                'label' => 'LinkedIn Profile URL',
                'instructions' => 'Full URL to the LinkedIn profile.',
            ],
        ];

        foreach ($fields as $fieldData) {
            $field = Field::firstOrCreate(['handle' => $fieldData['handle']], $fieldData);
            $group->fields()->syncWithoutDetaching([$field->id]);
        }

        return $group;
    }

    /**
     * Build a FieldLayout with named tabs, each containing field handles.
     *
     * @param  array<string, string[]>  $tabs
     */
    private function buildLayout(array $tabs): FieldLayout
    {
        $layout = FieldLayout::create(['name' => 'User Profile Layout', 'handle' => 'user-profile-layout']);
        $tabOrder = 1;

        foreach ($tabs as $tabName => $handles) {
            $tab = Tab::create([
                'field_layout_id' => $layout->id,
                'name' => $tabName,
                'handle' => Str::slug($tabName),
                'sort_order' => $tabOrder++,
            ]);

            $elementOrder = 1;
            foreach ($handles as $handle) {
                $field = Field::where('handle', $handle)->first();
                if (! $field) {
                    continue;
                }

                TabElement::create([
                    'field_layout_tab_id' => $tab->id,
                    'field_id' => $field->id,
                    'required' => true,
                    'sort_order' => $elementOrder++,
                ]);
            }
        }

        return $layout;
    }
}
