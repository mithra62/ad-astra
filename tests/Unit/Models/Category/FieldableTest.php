<?php

namespace Tests\Unit\Models\Category;

use App\Models\Category;
use App\Models\Category\Group as CategoryGroup;
use App\Models\Field;
use App\Models\Field\Type as FieldType;
use App\Models\FieldValue;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FieldableTest extends TestCase
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

    private function makeField(FieldType $type, string $slug = 'test_field'): Field
    {
        return Field::create([
            'field_type_id' => $type->id,
            'name'          => $slug,
            'handle'          => $slug,
            'label'         => $slug,
        ]);
    }

    private function makeCategory(): Category
    {
        $group = CategoryGroup::factory()->create();

        return Category::factory()->create(['group_id' => $group->id]);
    }

    private function writeValue(Category $category, Field $field, string $value): void
    {
        $column = $field->fieldType->instance()->storageColumn();

        FieldValue::updateOrCreate(
            [
                'field_id'       => $field->id,
                'fieldable_id'   => $category->id,
                'fieldable_type' => Category::class,
            ],
            [$column => $value]
        );
    }

    // -------------------------------------------------------------------------
    // Relationship
    // -------------------------------------------------------------------------

    public function test_category_has_field_values_morph_many_relationship(): void
    {
        $category = $this->makeCategory();

        $this->assertInstanceOf(MorphMany::class, $category->fieldValues());
    }

    // -------------------------------------------------------------------------
    // Writing
    // -------------------------------------------------------------------------

    public function test_field_value_can_be_written_to_category(): void
    {
        $type     = $this->makeFieldType();
        $field    = $this->makeField($type);
        $category = $this->makeCategory();

        $this->writeValue($category, $field, 'Hello world');

        $this->assertDatabaseHas('field_values', [
            'field_id'       => $field->id,
            'fieldable_id'   => $category->id,
            'fieldable_type' => Category::class,
            'value_text'     => 'Hello world',
        ]);
    }

    public function test_writing_a_field_value_twice_updates_not_duplicates(): void
    {
        $type     = $this->makeFieldType();
        $field    = $this->makeField($type);
        $category = $this->makeCategory();

        $this->writeValue($category, $field, 'First value');
        $this->writeValue($category, $field, 'Second value');

        $this->assertDatabaseCount('field_values', 1);
        $this->assertDatabaseHas('field_values', [
            'field_id'   => $field->id,
            'value_text' => 'Second value',
        ]);
    }

    public function test_multiple_fields_can_be_written_to_one_category(): void
    {
        $type      = $this->makeFieldType();
        $fieldA    = $this->makeField($type, 'cat_title');
        $fieldB    = $this->makeField($type, 'cat_description');
        $category  = $this->makeCategory();

        $this->writeValue($category, $fieldA, 'My Title');
        $this->writeValue($category, $fieldB, 'My Description');

        $this->assertDatabaseCount('field_values', 2);
        $this->assertDatabaseHas('field_values', ['field_id' => $fieldA->id, 'value_text' => 'My Title']);
        $this->assertDatabaseHas('field_values', ['field_id' => $fieldB->id, 'value_text' => 'My Description']);
    }

    public function test_same_field_on_different_categories_stores_separate_rows(): void
    {
        $type   = $this->makeFieldType();
        $field  = $this->makeField($type);
        $catA   = $this->makeCategory();
        $catB   = $this->makeCategory();

        $this->writeValue($catA, $field, 'Value A');
        $this->writeValue($catB, $field, 'Value B');

        $this->assertDatabaseCount('field_values', 2);
        $this->assertDatabaseHas('field_values', ['fieldable_id' => $catA->id, 'value_text' => 'Value A']);
        $this->assertDatabaseHas('field_values', ['fieldable_id' => $catB->id, 'value_text' => 'Value B']);
    }

    // -------------------------------------------------------------------------
    // Reading via field() helper
    // -------------------------------------------------------------------------

    public function test_field_method_reads_value_by_slug(): void
    {
        $type     = $this->makeFieldType();
        $field    = $this->makeField($type, 'cat_description');
        $category = $this->makeCategory();

        $this->writeValue($category, $field, 'Electronics and gadgets.');

        $category->load('fieldValues.field.fieldType');

        $this->assertEquals('Electronics and gadgets.', $category->field('cat_description'));
    }

    public function test_field_method_returns_null_for_unknown_slug(): void
    {
        $category = $this->makeCategory();
        $category->load('fieldValues.field.fieldType');

        $this->assertNull($category->field('does_not_exist'));
    }

    public function test_field_method_returns_updated_value_after_overwrite(): void
    {
        $type     = $this->makeFieldType();
        $field    = $this->makeField($type, 'cat_label');
        $category = $this->makeCategory();

        $this->writeValue($category, $field, 'Original');
        $this->writeValue($category, $field, 'Updated');

        $category->load('fieldValues.field.fieldType');

        $this->assertEquals('Updated', $category->field('cat_label'));
    }

    // -------------------------------------------------------------------------
    // Bulk write pattern (pre-load fields to avoid N+1)
    // -------------------------------------------------------------------------

    public function test_bulk_write_via_field_models_keyed_by_slug(): void
    {
        $type     = $this->makeFieldType();
        $fieldA   = $this->makeField($type, 'bulk_a');
        $fieldB   = $this->makeField($type, 'bulk_b');
        $category = $this->makeCategory();

        $fieldData = [
            'bulk_a' => 'Alpha',
            'bulk_b' => 'Beta',
        ];

        $fieldModels = Field::whereIn('slug', array_keys($fieldData))
            ->with('fieldType')
            ->get()
            ->keyBy('slug');

        foreach ($fieldData as $slug => $value) {
            $f      = $fieldModels->get($slug);
            $column = $f->fieldType->instance()->storageColumn();

            FieldValue::updateOrCreate(
                [
                    'field_id'       => $f->id,
                    'fieldable_id'   => $category->id,
                    'fieldable_type' => Category::class,
                ],
                [$column => $value]
            );
        }

        $category->load('fieldValues.field.fieldType');

        $this->assertEquals('Alpha', $category->field('bulk_a'));
        $this->assertEquals('Beta',  $category->field('bulk_b'));
        $this->assertDatabaseCount('field_values', 2);
    }

    // -------------------------------------------------------------------------
    // Isolation — values are scoped to their category
    // -------------------------------------------------------------------------

    public function test_field_values_are_polymorphically_scoped_to_category(): void
    {
        $type     = $this->makeFieldType();
        $field    = $this->makeField($type, 'scoped_field');
        $catA     = $this->makeCategory();
        $catB     = $this->makeCategory();

        $this->writeValue($catA, $field, 'For A');
        $this->writeValue($catB, $field, 'For B');

        $catA->load('fieldValues.field.fieldType');
        $catB->load('fieldValues.field.fieldType');

        $this->assertEquals('For A', $catA->field('scoped_field'));
        $this->assertEquals('For B', $catB->field('scoped_field'));
    }
}
