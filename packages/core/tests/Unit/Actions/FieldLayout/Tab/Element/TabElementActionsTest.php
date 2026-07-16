<?php

namespace Tests\Unit\Actions\FieldLayout\Tab\Element;

use AdAstra\Actions\FieldLayout\Tab\Element\BulkUpdateTabElements;
use AdAstra\Actions\FieldLayout\Tab\Element\CreateTabElement;
use AdAstra\Actions\FieldLayout\Tab\Element\DeleteTabElement;
use AdAstra\Actions\FieldLayout\Tab\Element\EditTabElement;
use AdAstra\Models\Field;
use AdAstra\Models\Field\Group as FieldGroup;
use AdAstra\Models\Field\Type;
use AdAstra\Models\FieldLayout;
use AdAstra\Models\FieldLayout\Tab;
use AdAstra\Models\FieldLayout\TabElement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TabElementActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_tab_element_creates_and_returns_element(): void
    {
        $tab = Tab::factory()->create();
        $field = Field::factory()->create();
        $action = app(CreateTabElement::class);

        $element = $action->create($tab, ['field_id' => $field->id, 'required' => false, 'sort_order' => 1]);

        $this->assertInstanceOf(TabElement::class, $element);
        $this->assertDatabaseHas('field_layout_tab_elements', [
            'field_layout_tab_id' => $tab->id,
            'field_id' => $field->id,
            'required' => false,
            'sort_order' => 1,
        ]);
    }

    public function test_create_tab_element_auto_calculates_next_sort_order(): void
    {
        $tab = Tab::factory()->create();
        $type = Type::factory()->create();
        $field1 = Field::factory()->create(['field_type_id' => $type->id]);
        $field2 = Field::factory()->create(['field_type_id' => $type->id]);

        TabElement::factory()->for($tab, 'tab')->create(['field_id' => $field1->id, 'sort_order' => 5]);

        $action = app(CreateTabElement::class);
        $element = $action->create($tab, ['field_id' => $field2->id]);

        $this->assertEquals(6, $element->sort_order);
    }

    public function test_create_tab_element_defaults_sort_order_to_one_when_no_elements_exist(): void
    {
        $tab = Tab::factory()->create();
        $field = Field::factory()->create();
        $action = app(CreateTabElement::class);

        $element = $action->create($tab, ['field_id' => $field->id]);

        $this->assertEquals(1, $element->sort_order);
    }

    public function test_create_tab_element_defaults_required_to_false(): void
    {
        $tab = Tab::factory()->create();
        $field = Field::factory()->create();
        $action = app(CreateTabElement::class);

        $element = $action->create($tab, ['field_id' => $field->id]);

        $this->assertFalse($element->required);
    }

    public function test_create_tab_element_throws_validation_exception_for_duplicate_field(): void
    {
        $tab = Tab::factory()->create();
        $field = Field::factory()->create();
        TabElement::factory()->for($tab, 'tab')->create(['field_id' => $field->id]);

        $action = app(CreateTabElement::class);

        $this->expectException(ValidationException::class);

        $action->create($tab, ['field_id' => $field->id]);
    }

    public function test_create_tab_element_duplicate_exception_contains_field_error(): void
    {
        $tab = Tab::factory()->create();
        $field = Field::factory()->create();
        TabElement::factory()->for($tab, 'tab')->create(['field_id' => $field->id]);

        $action = app(CreateTabElement::class);

        try {
            $action->create($tab, ['field_id' => $field->id]);
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('field_id', $e->errors());
        }
    }

    public function test_create_tab_element_throws_when_field_not_in_layout_field_groups(): void
    {
        $layout = FieldLayout::factory()->create();
        $tab = Tab::factory()->for($layout, 'layout')->create();
        $type = Type::factory()->create();
        $groupField = Field::factory()->create(['field_type_id' => $type->id]);
        $outsideField = Field::factory()->create(['field_type_id' => $type->id]);

        $group = FieldGroup::factory()->create();
        $group->fields()->attach($groupField);
        $layout->fieldGroups()->attach($group);

        $action = app(CreateTabElement::class);

        try {
            $action->create($tab, ['field_id' => $outsideField->id]);
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('field_id', $e->errors());
        }
    }

    public function test_create_tab_element_allows_field_inside_layout_field_groups(): void
    {
        $layout = FieldLayout::factory()->create();
        $tab = Tab::factory()->for($layout, 'layout')->create();
        $field = Field::factory()->create();

        $group = FieldGroup::factory()->create();
        $group->fields()->attach($field);
        $layout->fieldGroups()->attach($group);

        $element = app(CreateTabElement::class)->create($tab, ['field_id' => $field->id]);

        $this->assertDatabaseHas('field_layout_tab_elements', [
            'id' => $element->id,
            'field_layout_tab_id' => $tab->id,
            'field_id' => $field->id,
        ]);
    }

    public function test_create_tab_element_throws_when_field_assigned_to_another_tab_in_layout(): void
    {
        $layout = FieldLayout::factory()->create();
        $tab1 = Tab::factory()->for($layout, 'layout')->create();
        $tab2 = Tab::factory()->for($layout, 'layout')->create();
        $field = Field::factory()->create();

        TabElement::factory()->for($tab1, 'tab')->create(['field_id' => $field->id]);

        $action = app(CreateTabElement::class);

        $this->expectException(ValidationException::class);

        $action->create($tab2, ['field_id' => $field->id]);
    }

    public function test_create_tab_element_allows_same_field_on_tabs_of_different_layouts(): void
    {
        $field = Field::factory()->create();
        $tab1 = Tab::factory()->create();
        $tab2 = Tab::factory()->create();

        TabElement::factory()->for($tab1, 'tab')->create(['field_id' => $field->id]);

        $element = app(CreateTabElement::class)->create($tab2, ['field_id' => $field->id]);

        $this->assertDatabaseHas('field_layout_tab_elements', [
            'id' => $element->id,
            'field_layout_tab_id' => $tab2->id,
            'field_id' => $field->id,
        ]);
    }

    public function test_bulk_update_throws_when_new_field_assigned_to_another_tab_in_layout(): void
    {
        $layout = FieldLayout::factory()->create();
        $tab1 = Tab::factory()->for($layout, 'layout')->create();
        $tab2 = Tab::factory()->for($layout, 'layout')->create();
        $field = Field::factory()->create();

        TabElement::factory()->for($tab1, 'tab')->create(['field_id' => $field->id]);

        $action = app(BulkUpdateTabElements::class);

        try {
            $action->update($tab2, ['new_fields' => [['field_id' => $field->id]]]);
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('new_fields', $e->errors());
        }
    }

    public function test_edit_tab_element_updates_required_and_sort_order(): void
    {
        $element = TabElement::factory()->create(['required' => false, 'sort_order' => 1]);
        $action = app(EditTabElement::class);

        $result = $action->edit($element, ['required' => true, 'sort_order' => 10]);

        $this->assertTrue($result->required);
        $this->assertEquals(10, $result->sort_order);
    }

    public function test_edit_tab_element_preserves_sort_order_when_omitted(): void
    {
        $element = TabElement::factory()->create(['sort_order' => 7]);
        $action = app(EditTabElement::class);

        $result = $action->edit($element, ['required' => false]);

        $this->assertEquals(7, $result->sort_order);
    }

    public function test_edit_tab_element_returns_fresh_model(): void
    {
        $element = TabElement::factory()->create(['required' => false]);
        $action = app(EditTabElement::class);

        $result = $action->edit($element, ['required' => true]);

        $this->assertNotSame($element, $result);
        $this->assertTrue($result->required);
    }

    public function test_delete_tab_element_removes_record(): void
    {
        $element = TabElement::factory()->create();
        $action = app(DeleteTabElement::class);

        $action->delete($element);

        $this->assertDatabaseMissing('field_layout_tab_elements', ['id' => $element->id]);
    }

    public function test_delete_tab_element_returns_true(): void
    {
        $element = TabElement::factory()->create();
        $action = app(DeleteTabElement::class);

        $result = $action->delete($element);

        $this->assertTrue($result);
    }
}
