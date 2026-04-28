<?php

namespace Tests\Unit\Actions\Field;

use App\Actions\Field\CreateNewField;
use App\Actions\Field\EditField;
use App\Field\Types\Boolean;
use App\Field\Types\Text;
use App\Models\Field;
use App\Models\Field\Group as FieldGroup;
use App\Models\Field\Type as FieldType;
use App\Models\FieldValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FieldActionsTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function test_create_persists_field_to_database(): void
    {
        $type = $this->textType();
        $action = app(CreateNewField::class);

        $action->create([
            'field_type_id' => $type->id,
            'name' => 'Title',
            'handle' => 'title',
            'label' => 'Title',
        ]);

        $this->assertDatabaseHas('fields', [
            'name' => 'Title',
            'handle' => 'title',
        ]);
    }

    /** Create a Text FieldType, reusing the existing row if already present. */
    private function textType(): FieldType
    {
        return FieldType::firstOrCreate(
            ['object' => Text::class],
            ['name' => 'Text', 'settings' => []]
        );
    }

    // -------------------------------------------------------------------------
    // CreateNewField::create
    // -------------------------------------------------------------------------

    public function test_create_returns_a_field_model(): void
    {
        $type = $this->textType();
        $action = app(CreateNewField::class);

        $result = $action->create([
            'field_type_id' => $type->id,
            'name' => 'Body',
            'handle' => 'body',
            'label' => 'Body',
        ]);

        $this->assertInstanceOf(Field::class, $result);
    }

    public function test_create_stores_all_provided_attributes(): void
    {
        $type = $this->textType();
        $action = app(CreateNewField::class);

        $action->create([
            'field_type_id' => $type->id,
            'name' => 'Summary',
            'handle' => 'summary',
            'label' => 'Summary Label',
            'instructions' => 'Enter a short summary',
            'hidden' => false,
        ]);

        $this->assertDatabaseHas('fields', [
            'name' => 'Summary',
            'handle' => 'summary',
            'label' => 'Summary Label',
            'instructions' => 'Enter a short summary',
            'hidden' => false,
        ]);
    }

    public function test_create_by_group_attaches_field_to_group(): void
    {
        $type = $this->textType();
        $group = FieldGroup::factory()->create();
        $action = app(CreateNewField::class);

        $field = $action->createByGroup([
            'group_id' => $group->id,
            'field_type_id' => $type->id,
            'name' => 'Slug',
            'handle' => 'slug',
            'label' => 'Slug',
        ]);

        $this->assertInstanceOf(Field::class, $field);
        $this->assertTrue($group->fields()->where('fields.id', $field->id)->exists());
    }

    // -------------------------------------------------------------------------
    // CreateNewField::createByGroup
    // -------------------------------------------------------------------------

    public function test_create_by_group_persists_field_to_database(): void
    {
        $type = $this->textType();
        $group = FieldGroup::factory()->create();
        $action = app(CreateNewField::class);

        $action->createByGroup([
            'group_id' => $group->id,
            'field_type_id' => $type->id,
            'name' => 'Excerpt',
            'handle' => 'excerpt',
            'label' => 'Excerpt',
        ]);

        $this->assertDatabaseHas('fields', [
            'name' => 'Excerpt',
            'handle' => 'excerpt',
        ]);
    }

    public function test_edit_returns_true_on_success(): void
    {
        $type = $this->textType();
        $field = Field::factory()->create(['field_type_id' => $type->id, 'name' => 'Old']);
        $action = app(EditField::class);

        $result = $action->edit($field, ['name' => 'New', 'handle' => 'new', 'label' => 'New']);

        $this->assertTrue($result);
    }

    // -------------------------------------------------------------------------
    // EditField::edit
    // -------------------------------------------------------------------------

    public function test_edit_updates_field_name_and_handle(): void
    {
        $type = $this->textType();
        $field = Field::factory()->create(['field_type_id' => $type->id, 'name' => 'Old Name', 'handle' => 'old-name']);
        $action = app(EditField::class);

        $action->edit($field, ['name' => 'New Name', 'handle' => 'new-name', 'label' => 'New Name']);

        $this->assertDatabaseHas('fields', [
            'id' => $field->id,
            'name' => 'New Name',
            'handle' => 'new-name',
        ]);
    }

    public function test_edit_resolves_type_handle_to_field_type_id(): void
    {
        $type = $this->textType();
        $field = Field::factory()->create(['field_type_id' => $type->id]);
        $action = app(EditField::class);

        // Pass 'type' => 'text' (the handle of Text type); EditField resolves it to field_type_id.
        $action->edit($field, ['name' => $field->name, 'handle' => $field->handle, 'label' => $field->label, 'type' => 'text']);

        $this->assertDatabaseHas('fields', [
            'id' => $field->id,
            'field_type_id' => $type->id,
        ]);
    }

    public function test_edit_throws_when_changing_type_on_field_with_existing_values(): void
    {
        $typeA = $this->textType();
        $typeB = $this->booleanType();
        $field = Field::factory()->create(['field_type_id' => $typeA->id]);

        FieldValue::create([
            'field_id' => $field->id,
            'fieldable_id' => 1,
            'fieldable_type' => 'entry',
            'value_text' => 'some value',
        ]);

        $action = app(EditField::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot change the type of field');

        $action->edit($field, [
            'name' => $field->name,
            'handle' => $field->handle,
            'label' => $field->label,
            'field_type_id' => $typeB->id,
        ]);
    }

    /** Create a Boolean FieldType, reusing the existing row if already present. */
    private function booleanType(): FieldType
    {
        return FieldType::firstOrCreate(
            ['object' => Boolean::class],
            ['name' => 'Boolean', 'settings' => []]
        );
    }

    public function test_edit_allows_same_type_id_even_with_existing_values(): void
    {
        $type = $this->textType();
        $field = Field::factory()->create(['field_type_id' => $type->id]);

        FieldValue::create([
            'field_id' => $field->id,
            'fieldable_id' => 1,
            'fieldable_type' => 'entry',
            'value_text' => 'some value',
        ]);

        $action = app(EditField::class);

        // Same type ID — should NOT throw
        $result = $action->edit($field, [
            'name' => 'Updated',
            'handle' => $field->handle,
            'label' => $field->label,
            'field_type_id' => $type->id,
        ]);

        $this->assertTrue($result);
    }
}
