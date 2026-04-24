<?php

namespace Tests\Unit\Models\FieldLayout;

use App\Models\FieldLayout;
use App\Models\FieldLayout\Tab;
use App\Models\FieldLayout\TabElement;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TabTest extends TestCase
{
    use RefreshDatabase;

    public function test_has_correct_fillable_attributes(): void
    {
        $this->assertEquals(['field_layout_id', 'name', 'sort_order'], (new Tab)->getFillable());
    }

    public function test_uses_field_layout_tabs_table(): void
    {
        $this->assertEquals('field_layout_tabs', (new Tab)->getTable());
    }

    public function test_casts_sort_order_to_integer(): void
    {
        $tab = Tab::factory()->create(['sort_order' => '4']);

        $this->assertIsInt($tab->sort_order);
        $this->assertEquals(4, $tab->sort_order);
    }

    public function test_layout_relationship_is_belongs_to(): void
    {
        $layout = FieldLayout::factory()->create();
        $tab = Tab::factory()->for($layout, 'layout')->create();

        $this->assertInstanceOf(BelongsTo::class, $tab->layout());
        $this->assertEquals($layout->id, $tab->layout->id);
    }

    public function test_elements_relationship_is_has_many(): void
    {
        $tab = Tab::factory()->create();

        $this->assertInstanceOf(HasMany::class, $tab->elements());
    }

    public function test_elements_are_ordered_by_sort_order(): void
    {
        $tab = Tab::factory()->create();
        TabElement::factory()->for($tab, 'tab')->create(['sort_order' => 3]);
        TabElement::factory()->for($tab, 'tab')->create(['sort_order' => 1]);

        $elements = $tab->elements()->get();

        $this->assertEquals(1, $elements->first()->sort_order);
        $this->assertEquals(3, $elements->last()->sort_order);
    }
}
