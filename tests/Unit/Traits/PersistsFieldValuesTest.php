<?php

namespace Tests\Unit\Traits;

use AdAstra\Field\Types\Number;
use AdAstra\Field\Types\Text;
use AdAstra\Models\Category;
use AdAstra\Models\Field;
use AdAstra\Models\Field\Type as FieldType;
use AdAstra\Models\FieldValue;
use AdAstra\Traits\Field\PersistsFieldValues;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersistsFieldValuesTest extends TestCase
{
    use RefreshDatabase;

    /** Anonymous host class — gives us the trait with no extra baggage. */
    private object $subject;

    public function test_set_field_creates_a_field_value_record(): void
    {
        $field = $this->makeTextField('bio');
        $category = $this->makeCategory();

        $this->subject->setField($category, 'bio', 'Hello world');

        $this->assertDatabaseHas('field_values', [
            'field_id' => $field->id,
            'fieldable_id' => $category->id,
            'value_text' => 'Hello world',
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** Create a text-type Field with the given handle. */
    private function makeTextField(string $handle): Field
    {
        $type = FieldType::firstOrCreate(
            ['object' => Text::class],
            ['name' => 'Text', 'settings' => []],
        );

        return Field::factory()->create([
            'field_type_id' => $type->id,
            'handle' => $handle,
        ]);
    }

    private function makeCategory(): Category
    {
        return Category::factory()->create();
    }

    public function test_set_field_writes_to_value_text_for_text_type(): void
    {
        $this->makeTextField('tagline');
        $category = $this->makeCategory();

        $this->subject->setField($category, 'tagline', 'A tagline');

        $this->assertDatabaseHas('field_values', [
            'fieldable_id' => $category->id,
            'value_text' => 'A tagline',
        ]);
    }

    public function test_set_field_writes_to_value_integer_for_number_type(): void
    {
        $this->makeIntegerField('priority');
        $category = $this->makeCategory();

        $this->subject->setField($category, 'priority', 42);

        $this->assertDatabaseHas('field_values', [
            'fieldable_id' => $category->id,
            'value_integer' => 42,
        ]);
    }

    // -------------------------------------------------------------------------
    // setField() — basic persistence
    // -------------------------------------------------------------------------

    /** Create an integer number-type Field with the given handle. */
    private function makeIntegerField(string $handle): Field
    {
        $type = FieldType::firstOrCreate(
            ['object' => Number::class],
            ['name' => 'Number', 'settings' => ['decimals' => 0]],
        );

        return Field::factory()->create([
            'field_type_id' => $type->id,
            'handle' => $handle,
        ]);
    }

    public function test_set_field_stores_correct_field_id(): void
    {
        $field = $this->makeTextField('notes');
        $category = $this->makeCategory();

        $this->subject->setField($category, 'notes', 'some note');

        $this->assertDatabaseHas('field_values', [
            'field_id' => $field->id,
            'fieldable_id' => $category->id,
        ]);
    }

    public function test_set_field_stores_correct_fieldable_id(): void
    {
        $this->makeTextField('desc');
        $category = $this->makeCategory();

        $this->subject->setField($category, 'desc', 'text');

        $this->assertDatabaseHas('field_values', ['fieldable_id' => $category->id]);
    }

    public function test_set_field_stores_morph_type_from_morph_map(): void
    {
        $this->makeTextField('slug');
        $category = $this->makeCategory();

        $this->subject->setField($category, 'slug', 'my-slug');

        // AppServiceProvider registers 'category' in the morph map.
        $this->assertDatabaseHas('field_values', [
            'fieldable_id' => $category->id,
            'fieldable_type' => 'category',
        ]);
    }

    public function test_set_field_does_not_create_duplicate_on_second_call(): void
    {
        $this->makeTextField('title');
        $category = $this->makeCategory();

        $this->subject->setField($category, 'title', 'First');
        $this->subject->setField($category, 'title', 'Second');

        $this->assertDatabaseCount('field_values', 1);
    }

    public function test_set_field_updates_value_when_called_again(): void
    {
        $this->makeTextField('summary');
        $category = $this->makeCategory();

        $this->subject->setField($category, 'summary', 'Original');
        $this->subject->setField($category, 'summary', 'Updated');

        $this->assertDatabaseHas('field_values', [
            'fieldable_id' => $category->id,
            'value_text' => 'Updated',
        ]);
        $this->assertDatabaseMissing('field_values', ['value_text' => 'Original']);
    }

    // -------------------------------------------------------------------------
    // setField() — updateOrCreate semantics
    // -------------------------------------------------------------------------

    public function test_set_field_throws_model_not_found_for_unknown_handle(): void
    {
        $category = $this->makeCategory();

        $this->expectException(ModelNotFoundException::class);

        $this->subject->setField($category, 'does-not-exist', 'value');
    }

    public function test_set_fields_does_nothing_for_empty_array(): void
    {
        $category = $this->makeCategory();

        $this->subject->setFields($category, []);

        $this->assertDatabaseCount('field_values', 0);
    }

    // -------------------------------------------------------------------------
    // setField() — error path
    // -------------------------------------------------------------------------

    public function test_set_fields_creates_a_record_for_each_handle(): void
    {
        $this->makeTextField('alpha');
        $this->makeTextField('beta');
        $category = $this->makeCategory();

        $this->subject->setFields($category, ['alpha' => 'A', 'beta' => 'B']);

        $this->assertDatabaseCount('field_values', 2);
    }

    // -------------------------------------------------------------------------
    // setFields() — empty input
    // -------------------------------------------------------------------------

    public function test_set_fields_persists_correct_values_per_handle(): void
    {
        $fieldA = $this->makeTextField('color');
        $fieldB = $this->makeTextField('flavor');
        $category = $this->makeCategory();

        $this->subject->setFields($category, ['color' => 'red', 'flavor' => 'sweet']);

        $this->assertDatabaseHas('field_values', ['field_id' => $fieldA->id, 'value_text' => 'red']);
        $this->assertDatabaseHas('field_values', ['field_id' => $fieldB->id, 'value_text' => 'sweet']);
    }

    // -------------------------------------------------------------------------
    // setFields() — bulk persistence
    // -------------------------------------------------------------------------

    public function test_set_fields_uses_correct_storage_column_per_field_type(): void
    {
        $this->makeTextField('label');
        $this->makeIntegerField('rank');
        $category = $this->makeCategory();

        $this->subject->setFields($category, ['label' => 'My Label', 'rank' => 7]);

        $this->assertDatabaseHas('field_values', [
            'fieldable_id' => $category->id,
            'value_text' => 'My Label',
        ]);
        $this->assertDatabaseHas('field_values', [
            'fieldable_id' => $category->id,
            'value_integer' => 7,
        ]);
    }

    public function test_set_fields_stores_correct_morph_type_for_each_record(): void
    {
        $this->makeTextField('x');
        $this->makeTextField('y');
        $category = $this->makeCategory();

        $this->subject->setFields($category, ['x' => '1', 'y' => '2']);

        FieldValue::where('fieldable_id', $category->id)->each(function (FieldValue $fv) {
            $this->assertEquals('category', $fv->fieldable_type);
        });
    }

    public function test_set_fields_updates_existing_records_on_second_call(): void
    {
        $this->makeTextField('page');
        $category = $this->makeCategory();

        $this->subject->setFields($category, ['page' => 'Before']);
        $this->subject->setFields($category, ['page' => 'After']);

        $this->assertDatabaseCount('field_values', 1);
        $this->assertDatabaseHas('field_values', ['value_text' => 'After']);
        $this->assertDatabaseMissing('field_values', ['value_text' => 'Before']);
    }

    public function test_set_fields_silently_skips_handles_not_in_database(): void
    {
        $category = $this->makeCategory();

        // No exception; no records written.
        $this->subject->setFields($category, ['ghost-handle' => 'value']);

        $this->assertDatabaseCount('field_values', 0);
    }

    // -------------------------------------------------------------------------
    // setFields() — updateOrCreate semantics
    // -------------------------------------------------------------------------

    public function test_set_fields_creates_only_known_handles_when_mixed_with_unknown(): void
    {
        $this->makeTextField('real');
        $category = $this->makeCategory();

        $this->subject->setFields($category, ['real' => 'yes', 'fake' => 'no']);

        $this->assertDatabaseCount('field_values', 1);
        $this->assertDatabaseHas('field_values', ['value_text' => 'yes']);
    }

    // -------------------------------------------------------------------------
    // setFields() — skip paths
    // -------------------------------------------------------------------------

    public function test_set_fields_silently_skips_field_with_null_field_type(): void
    {
        // field_type_id is nullable (nullOnDelete); trait guards with `! $field->fieldType`.
        $this->makeTypelessField('orphaned');
        $category = $this->makeCategory();

        $this->subject->setFields($category, ['orphaned' => 'ignored']);

        $this->assertDatabaseCount('field_values', 0);
    }

    /** Create a Field whose field_type_id is NULL (orphaned / type was deleted). */
    private function makeTypelessField(string $handle): Field
    {
        return Field::factory()->create([
            'field_type_id' => null,
            'handle' => $handle,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new class {
            use PersistsFieldValues;
        };
    }
}
