<?php

namespace Tests\Unit\Actions\FieldLayout\Tab\Element;

use App\Actions\FieldLayout\Tab\Element\CreateTabElement;
use App\Actions\FieldLayout\Tab\Element\DeleteTabElement;
use App\Actions\FieldLayout\Tab\Element\EditTabElement;
use App\Models\Field;
use App\Models\Field\Type;
use App\Models\FieldLayout\Tab;
use App\Models\FieldLayout\TabElement;
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
