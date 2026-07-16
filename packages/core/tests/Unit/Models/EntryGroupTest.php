<?php

namespace Tests\Unit\Models;

use AdAstra\Models\Entry;
use AdAstra\Models\EntryGroup;
use AdAstra\Models\EntryType;
use AdAstra\Models\StatusGroup;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntryGroupTest extends TestCase
{
    use RefreshDatabase;

    public function test_has_correct_fillable_attributes(): void
    {
        $this->assertEquals(
            ['field_layout_id', 'status_group_id', 'name', 'handle', 'description', 'sort_order'],
            (new EntryGroup)->getFillable()
        );
    }

    public function test_casts_sort_order_to_integer(): void
    {
        $group = EntryGroup::factory()->create(['sort_order' => '5']);

        $this->assertIsInt($group->sort_order);
        $this->assertEquals(5, $group->sort_order);
    }

    public function test_status_group_relationship_is_belongs_to(): void
    {
        $statusGroup = StatusGroup::factory()->create();
        $group = EntryGroup::factory()->create(['status_group_id' => $statusGroup->id]);

        $this->assertInstanceOf(BelongsTo::class, $group->statusGroup());
        $this->assertEquals($statusGroup->id, $group->statusGroup->id);
    }

    public function test_statuses_relationship_is_has_many_through(): void
    {
        $group = EntryGroup::factory()->create();

        $this->assertInstanceOf(HasManyThrough::class, $group->statuses());
    }

    public function test_entry_types_relationship_is_has_many(): void
    {
        $group = EntryGroup::factory()->create();

        $this->assertInstanceOf(HasMany::class, $group->entryTypes());
    }

    public function test_entry_types_are_ordered_by_sort_order(): void
    {
        $group = EntryGroup::factory()->create();
        EntryType::factory()->for($group)->create(['sort_order' => 2, 'name' => 'Second']);
        EntryType::factory()->for($group)->create(['sort_order' => 1, 'name' => 'First']);

        $types = $group->entryTypes()->get();

        $this->assertEquals('First', $types->first()->name);
        $this->assertEquals('Second', $types->last()->name);
    }

    public function test_entries_relationship_is_has_many(): void
    {
        $group = EntryGroup::factory()->create();

        $this->assertInstanceOf(HasMany::class, $group->entries());
    }

    public function test_entries_returns_all_entries_in_group(): void
    {
        $group = EntryGroup::factory()->create();
        Entry::factory()->for($group)->create();
        Entry::factory()->for($group)->create();

        $this->assertCount(2, $group->entries);
    }

    public function test_scope_ordered_sorts_by_sort_order_then_name(): void
    {
        EntryGroup::factory()->create(['name' => 'Zed', 'sort_order' => 1]);
        EntryGroup::factory()->create(['name' => 'Alpha', 'sort_order' => 1]);
        EntryGroup::factory()->create(['name' => 'First', 'sort_order' => 0]);

        $groups = EntryGroup::query()->ordered()->get();

        $this->assertEquals('First', $groups->first()->name);
        $this->assertEquals('Alpha', $groups->get(1)->name);
        $this->assertEquals('Zed', $groups->last()->name);
    }

    public function test_resolved_returns_entry_group_by_id(): void
    {
        $group = EntryGroup::factory()->create();

        $resolved = EntryGroup::resolvedFields($group->id);

        $this->assertEquals($group->id, $resolved->id);
    }
}
