<?php

namespace Tests\Unit\Models;

use App\Models\Field;
use App\Models\FieldLayout;
use App\Models\FieldLayout\Tab;
use App\Models\FieldLayout\TabElement;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FieldLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_has_correct_fillable_attributes(): void
    {
        $this->assertEquals(['name', 'handle'], (new FieldLayout)->getFillable());
    }

    public function test_tabs_relationship_is_has_many(): void
    {
        $layout = FieldLayout::factory()->create();

        $this->assertInstanceOf(HasMany::class, $layout->tabs());
    }

    public function test_tabs_are_ordered_by_sort_order(): void
    {
        $layout = FieldLayout::factory()->create();
        Tab::factory()->for($layout, 'layout')->create(['sort_order' => 3, 'name' => 'Last']);
        Tab::factory()->for($layout, 'layout')->create(['sort_order' => 1, 'name' => 'First']);

        $tabs = $layout->tabs()->get();

        $this->assertEquals('First', $tabs->first()->name);
        $this->assertEquals('Last', $tabs->last()->name);
    }

    public function test_entry_groups_relationship_is_has_many(): void
    {
        $layout = FieldLayout::factory()->create();

        $this->assertInstanceOf(HasMany::class, $layout->entryGroups());
    }

    public function test_entry_types_relationship_is_has_many(): void
    {
        $layout = FieldLayout::factory()->create();

        $this->assertInstanceOf(HasMany::class, $layout->entryTypes());
    }

    public function test_fields_returns_empty_collection_when_no_tabs(): void
    {
        $layout = new FieldLayout;
        $layout->setRelation('tabs', collect([]));

        $this->assertCount(0, $layout->fields());
    }

    public function test_fields_returns_flattened_collection_across_all_tabs(): void
    {
        $field1 = new Field(['name' => 'Title']);
        $field2 = new Field(['name' => 'Body']);
        $field3 = new Field(['name' => 'Summary']);

        $el1 = new TabElement;
        $el1->setRelation('field', $field1);
        $el2 = new TabElement;
        $el2->setRelation('field', $field2);
        $el3 = new TabElement;
        $el3->setRelation('field', $field3);

        $tab1 = new Tab;
        $tab1->setRelation('elements', collect([$el1, $el2]));
        $tab2 = new Tab;
        $tab2->setRelation('elements', collect([$el3]));

        $layout = new FieldLayout;
        $layout->setRelation('tabs', collect([$tab1, $tab2]));

        $fields = $layout->fields();

        $this->assertCount(3, $fields);
        $this->assertSame($field1, $fields->get(0));
        $this->assertSame($field2, $fields->get(1));
        $this->assertSame($field3, $fields->get(2));
    }
}
