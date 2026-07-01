<?php

namespace Tests\Unit\Models;

use AdAstra\Models\Field;
use AdAstra\Models\Field\Group as FieldGroup;
use AdAstra\Models\Field\Type;
use AdAstra\Models\FieldLayout;
use AdAstra\Models\FieldLayout\Tab;
use AdAstra\Models\FieldLayout\TabElement;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FieldLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_has_correct_fillable_attributes(): void
    {
        $this->assertEquals(['name', 'handle'], (new FieldLayout())->getFillable());
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
        $layout = new FieldLayout();
        $layout->setRelation('tabs', collect([]));

        $this->assertCount(0, $layout->fields());
    }

    public function test_fields_returns_flattened_collection_across_all_tabs(): void
    {
        $field1 = new Field(['name' => 'Title']);
        $field2 = new Field(['name' => 'Body']);
        $field3 = new Field(['name' => 'Summary']);

        $el1 = new TabElement();
        $el1->setRelation('field', $field1);
        $el2 = new TabElement();
        $el2->setRelation('field', $field2);
        $el3 = new TabElement();
        $el3->setRelation('field', $field3);

        $tab1 = new Tab();
        $tab1->setRelation('elements', collect([$el1, $el2]));
        $tab2 = new Tab();
        $tab2->setRelation('elements', collect([$el3]));

        $layout = new FieldLayout();
        $layout->setRelation('tabs', collect([$tab1, $tab2]));

        $fields = $layout->fields();

        $this->assertCount(3, $fields);
        $this->assertSame($field1, $fields->get(0));
        $this->assertSame($field2, $fields->get(1));
        $this->assertSame($field3, $fields->get(2));
    }

    public function test_available_fields_returns_all_fields_sorted_by_name_when_no_groups_assigned(): void
    {
        $layout = FieldLayout::factory()->create();
        $type = Type::factory()->create();
        Field::factory()->create(['name' => 'Zebra', 'field_type_id' => $type->id]);
        Field::factory()->create(['name' => 'Alpha', 'field_type_id' => $type->id]);

        $fields = $layout->availableFields();

        $this->assertCount(2, $fields);
        $this->assertEquals(['Alpha', 'Zebra'], $fields->pluck('name')->all());
    }

    public function test_available_fields_returns_only_fields_from_assigned_groups(): void
    {
        $layout = FieldLayout::factory()->create();
        $type = Type::factory()->create();
        $inGroup = Field::factory()->create(['name' => 'In Group', 'field_type_id' => $type->id]);
        $outside = Field::factory()->create(['name' => 'Outside', 'field_type_id' => $type->id]);

        $group = FieldGroup::factory()->create();
        $group->fields()->attach($inGroup);
        $layout->fieldGroups()->attach($group);

        $fields = $layout->availableFields();

        $this->assertCount(1, $fields);
        $this->assertTrue($fields->pluck('id')->contains($inGroup->id));
        $this->assertFalse($fields->pluck('id')->contains($outside->id));
    }

    public function test_available_fields_deduplicates_fields_in_multiple_groups(): void
    {
        $layout = FieldLayout::factory()->create();
        $field = Field::factory()->create();

        $group1 = FieldGroup::factory()->create();
        $group2 = FieldGroup::factory()->create();
        $group1->fields()->attach($field);
        $group2->fields()->attach($field);
        $layout->fieldGroups()->attach([$group1->id, $group2->id]);

        $this->assertCount(1, $layout->availableFields());
    }

    public function test_available_fields_from_groups_are_sorted_by_name(): void
    {
        $layout = FieldLayout::factory()->create();
        $type = Type::factory()->create();
        $zebra = Field::factory()->create(['name' => 'Zebra', 'field_type_id' => $type->id]);
        $alpha = Field::factory()->create(['name' => 'Alpha', 'field_type_id' => $type->id]);

        $group = FieldGroup::factory()->create();
        $group->fields()->attach([$zebra->id, $alpha->id]);
        $layout->fieldGroups()->attach($group);

        $this->assertEquals(['Alpha', 'Zebra'], $layout->availableFields()->pluck('name')->all());
    }
}
