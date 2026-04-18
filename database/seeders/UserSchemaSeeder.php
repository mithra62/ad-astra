<?php

namespace Database\Seeders;

use App\Models\Field;
use App\Models\Field\Group as FieldGroup;
use App\Models\FieldLayout;
use App\Models\FieldLayout\Tab;
use App\Models\FieldLayout\TabElement;
use App\Models\Field\Type as FieldType;
use App\Models\UserSchema;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSchemaSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $text = FieldType::where('object', \App\Field\Types\Text::class)->firstOrFail();

        // Create user-specific fields
        $bio = Field::firstOrCreate(
            ['slug' => 'bio'],
            [
                'field_type_id' => $text->id,
                'name'          => 'Bio',
                'label'         => 'Biography',
                'instructions'  => 'A short biography for the user profile.',
            ]
        );

        $website = Field::firstOrCreate(
            ['slug' => 'website'],
            [
                'field_type_id' => $text->id,
                'name'          => 'Website',
                'label'         => 'Website URL',
                'instructions'  => 'The user\'s personal or professional website.',
            ]
        );

        // Create a field group for user fields
        $userFieldGroup = FieldGroup::firstOrCreate(
            ['slug' => 'user-fields'],
            ['name' => 'User Fields', 'description' => 'Extended profile fields for users.']
        );

        $userFieldGroup->fields()->syncWithoutDetaching([$bio->id, $website->id]);

        // Build a layout for the user schema
        $layout = FieldLayout::create(['name' => 'User Profile Layout']);

        $tab = Tab::create([
            'field_layout_id' => $layout->id,
            'name'            => 'Profile',
            'sort_order'      => 1,
        ]);

        foreach ([$bio, $website] as $order => $field) {
            TabElement::create([
                'field_layout_tab_id' => $tab->id,
                'field_id'            => $field->id,
                'required'            => false,
                'sort_order'          => $order + 1,
            ]);
        }

        // Initialise the singleton and wire the layout and field group
        $schema = UserSchema::instance();
        $schema->field_layout_id = $layout->id;
        $schema->save();

        $schema->fieldGroups()->syncWithoutDetaching([$userFieldGroup->id]);
    }
}
