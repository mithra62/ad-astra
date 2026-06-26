<?php

namespace Tests\Unit\Models\FieldLayout;

use App\Models\Field;
use App\Models\FieldLayout\Tab;
use App\Models\FieldLayout\TabElement;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TabElementTest extends TestCase
{
    use RefreshDatabase;

    public function test_has_correct_fillable_attributes(): void
    {
        $this->assertEquals(
            ['field_layout_tab_id', 'field_id', 'required',
                'sort_order',
                'hidden',
                'readonly',
                'disabled',
                'schema_property',
                'label',
                'instructions',],
            (new TabElement)->getFillable()
        );
    }

    public function test_uses_field_layout_tab_elements_table(): void
    {
        $this->assertEquals('field_layout_tab_elements', (new TabElement)->getTable());
    }

    public function test_casts_required_to_boolean(): void
    {
        $element = TabElement::factory()->create(['required' => 1]);

        $this->assertIsBool($element->required);
        $this->assertTrue($element->required);
    }

    public function test_casts_sort_order_to_integer(): void
    {
        $element = TabElement::factory()->create(['sort_order' => '6']);

        $this->assertIsInt($element->sort_order);
        $this->assertEquals(6, $element->sort_order);
    }

    public function test_tab_relationship_is_belongs_to(): void
    {
        $tab = Tab::factory()->create();
        $element = TabElement::factory()->for($tab, 'tab')->create();

        $this->assertInstanceOf(BelongsTo::class, $element->tab());
        $this->assertEquals($tab->id, $element->tab->id);
    }

    public function test_field_relationship_is_belongs_to(): void
    {
        $field = Field::factory()->create();
        $element = TabElement::factory()->create(['field_id' => $field->id]);

        $this->assertInstanceOf(BelongsTo::class, $element->field());
        $this->assertEquals($field->id, $element->field->id);
    }
}
