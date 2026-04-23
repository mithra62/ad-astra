<?php

namespace Tests\Unit\Models\Category;

use App\Models\Category;
use App\Models\Category\Group as CategoryGroup;
use App\Models\Field;
use App\Models\Field\Group as FieldGroup;
use App\Models\Field\Type as FieldType;
use App\Models\FieldLayout;
use App\Models\FieldLayout\Tab;
use App\Models\FieldLayout\TabElement;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupFieldSchemaTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeFieldType(): FieldType
    {
        return FieldType::create([
            'name'   => 'Text',
            'object' => \App\Field\Types\Text::class,
        ]);
    }

    private function makeField(FieldType $type, string $slug): Field
    {
        return Field::create([
            'field_type_id' => $type->id,
            'name'          => $slug,
            'handle'          => $slug,
            'label'         => $slug,
        ]);
    }

    // -------------------------------------------------------------------------
    // Relationships on CategoryGroup
    // -------------------------------------------------------------------------

    public function test_category_group_has_field_groups_morph_to_many_relationship(): void
    {
        $group = CategoryGroup::factory()->create();

        $this->assertInstanceOf(MorphToMany::class, $group->fieldGroups());
    }

    public function test_category_group_has_field_layout_belongs_to_relationship(): void
    {
        $group = CategoryGroup::factory()->create();

        $this->assertInstanceOf(BelongsTo::class, $group->fieldLayout());
    }

    // -------------------------------------------------------------------------
    // FieldGroup attachment
    // -------------------------------------------------------------------------

    public function test_field_group_can_be_attached_to_category_group(): void
    {
        $group      = CategoryGroup::factory()->create();
        $fieldGroup = FieldGroup::create(['name' => 'Test Fields', 'slug' => 'test-fields']);

        $group->fieldGroups()->syncWithoutDetaching([$fieldGroup->id]);

        $this->assertCount(1, $group->fieldGroups);
        $this->assertEquals($fieldGroup->id, $group->fieldGroups->first()->id);
    }

    public function test_multiple_field_groups_can_be_attached_to_category_group(): void
    {
        $group  = CategoryGroup::factory()->create();
        $groupA = FieldGroup::create(['name' => 'Group A', 'slug' => 'group-a']);
        $groupB = FieldGroup::create(['name' => 'Group B', 'slug' => 'group-b']);

        $group->fieldGroups()->syncWithoutDetaching([$groupA->id, $groupB->id]);

        $this->assertCount(2, $group->fresh()->fieldGroups);
    }

    public function test_field_group_attachment_is_idempotent(): void
    {
        $group      = CategoryGroup::factory()->create();
        $fieldGroup = FieldGroup::create(['name' => 'SEO Fields', 'slug' => 'seo-fields']);

        $group->fieldGroups()->syncWithoutDetaching([$fieldGroup->id]);
        $group->fieldGroups()->syncWithoutDetaching([$fieldGroup->id]);

        $this->assertCount(1, $group->fresh()->fieldGroups);
    }

    public function test_fields_are_reachable_through_field_group_on_category_group(): void
    {
        $type       = $this->makeFieldType();
        $field      = $this->makeField($type, 'cat_bio');
        $fieldGroup = FieldGroup::create(['name' => 'Details', 'slug' => 'details']);
        $catGroup   = CategoryGroup::factory()->create();

        $fieldGroup->fields()->syncWithoutDetaching([$field->id]);
        $catGroup->fieldGroups()->syncWithoutDetaching([$fieldGroup->id]);

        $catGroup->load('fieldGroups.fields');
        $fields = $catGroup->fieldGroups->flatMap->fields;

        $this->assertCount(1, $fields);
        $this->assertEquals('cat_bio', $fields->first()->slug);
    }

    // -------------------------------------------------------------------------
    // FieldLayout assignment
    // -------------------------------------------------------------------------

    public function test_field_layout_can_be_assigned_to_category_group(): void
    {
        $catGroup = CategoryGroup::factory()->create();
        $layout   = FieldLayout::create(['name' => 'Topics Layout']);

        $catGroup->update(['field_layout_id' => $layout->id]);

        $this->assertEquals($layout->id, $catGroup->fresh()->field_layout_id);
        $this->assertInstanceOf(FieldLayout::class, $catGroup->fresh()->fieldLayout);
    }

    public function test_field_layout_with_tab_and_elements_is_reachable_from_category_group(): void
    {
        $type     = $this->makeFieldType();
        $fieldA   = $this->makeField($type, 'layout_title');
        $fieldB   = $this->makeField($type, 'layout_body');
        $catGroup = CategoryGroup::factory()->create();
        $layout   = FieldLayout::create(['name' => 'Full Layout']);

        $tab = Tab::create([
            'field_layout_id' => $layout->id,
            'name'            => 'Content',
            'sort_order'      => 1,
        ]);

        TabElement::create(['field_layout_tab_id' => $tab->id, 'field_id' => $fieldA->id, 'sort_order' => 1, 'required' => false]);
        TabElement::create(['field_layout_tab_id' => $tab->id, 'field_id' => $fieldB->id, 'sort_order' => 2, 'required' => false]);

        $catGroup->update(['field_layout_id' => $layout->id]);

        $catGroup->load('fieldLayout.tabs.elements.field');

        $fields = $catGroup->fieldLayout->fields();

        $this->assertCount(2, $fields);
        $this->assertEquals('layout_title', $fields[0]->handle);
        $this->assertEquals('layout_body',  $fields[1]->handle);
    }

    public function test_category_group_without_layout_has_null_field_layout(): void
    {
        $catGroup = CategoryGroup::factory()->create(['field_layout_id' => null]);

        $this->assertNull($catGroup->fieldLayout);
    }

    // -------------------------------------------------------------------------
    // Full schema + value flow
    // -------------------------------------------------------------------------

    public function test_full_flow_schema_to_category_field_value(): void
    {
        // 1. Schema: field type, field, field group, category group, layout
        $type       = $this->makeFieldType();
        $field      = $this->makeField($type, 'region_description');
        $fieldGroup = FieldGroup::create(['name' => 'Region Fields', 'handle' => 'region-fields']);
        $catGroup   = CategoryGroup::factory()->create();
        $layout     = FieldLayout::create(['name' => 'Regions Layout']);

        $fieldGroup->fields()->syncWithoutDetaching([$field->id]);
        $catGroup->fieldGroups()->syncWithoutDetaching([$fieldGroup->id]);

        $tab = Tab::create(['field_layout_id' => $layout->id, 'name' => 'Details', 'sort_order' => 1]);
        TabElement::create(['field_layout_tab_id' => $tab->id, 'field_id' => $field->id, 'sort_order' => 1, 'required' => false]);
        $catGroup->update(['field_layout_id' => $layout->id]);

        // 2. Category record
        $category = Category::factory()->create(['group_id' => $catGroup->id]);

        // 3. Write field value
        $column = $field->fieldType->instance()->storageColumn();
        \App\Models\FieldValue::updateOrCreate(
            [
                'field_id'       => $field->id,
                'fieldable_id'   => $category->id,
                'fieldable_type' => Category::class,
            ],
            [$column => 'All regions of Europe.']
        );

        // 4. Read back via Fieldable helper
        $category->load('fieldValues.field.fieldType');

        $this->assertEquals('All regions of Europe.', $category->field('region_description'));

        // 5. Schema is accessible from the group
        $catGroup->load('fieldLayout.tabs.elements.field');
        $layoutFields = $catGroup->fieldLayout->fields();
        $this->assertTrue($layoutFields->contains('handle', 'region_description'));
    }

    // -------------------------------------------------------------------------
    // Shared field groups across multiple category groups
    // -------------------------------------------------------------------------

    public function test_field_group_can_be_shared_across_multiple_category_groups(): void
    {
        $sharedGroup = FieldGroup::create(['name' => 'SEO Fields', 'handle' => 'seo-shared']);
        $catGroupA   = CategoryGroup::factory()->create();
        $catGroupB   = CategoryGroup::factory()->create();

        $catGroupA->fieldGroups()->syncWithoutDetaching([$sharedGroup->id]);
        $catGroupB->fieldGroups()->syncWithoutDetaching([$sharedGroup->id]);

        $this->assertCount(1, $catGroupA->fresh()->fieldGroups);
        $this->assertCount(1, $catGroupB->fresh()->fieldGroups);
        $this->assertEquals($sharedGroup->id, $catGroupA->fresh()->fieldGroups->first()->id);
        $this->assertEquals($sharedGroup->id, $catGroupB->fresh()->fieldGroups->first()->id);
    }
}
