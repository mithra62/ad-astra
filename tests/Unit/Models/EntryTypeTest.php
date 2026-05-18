<?php

namespace Tests\Unit\Models;

use App\Models\Entry;
use App\Models\EntryGroup;
use App\Models\EntryType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntryTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_has_correct_fillable_attributes(): void
    {
        $this->assertEquals(
            ['entry_group_id',
                'entry_behavior_id',
                'field_layout_id',
                'name',
                'handle',
                'default_template',
                'has_entry_tree',
                'max_depth',
                'allowed_parent_types',
                'sort_order'],
            (new EntryType)->getFillable()
        );
    }

    public function test_casts_sort_order_to_integer(): void
    {
        $type = EntryType::factory()->create(['sort_order' => '3']);

        $this->assertIsInt($type->sort_order);
        $this->assertEquals(3, $type->sort_order);
    }

    public function test_entry_group_relationship_is_belongs_to(): void
    {
        $group = EntryGroup::factory()->create();
        $type = EntryType::factory()->for($group)->create();

        $this->assertInstanceOf(BelongsTo::class, $type->entryGroup());
        $this->assertEquals($group->id, $type->entryGroup->id);
    }

    public function test_entries_relationship_is_has_many(): void
    {
        $type = EntryType::factory()->create();

        $this->assertInstanceOf(HasMany::class, $type->entries());
    }

    public function test_entries_returns_entries_of_this_type(): void
    {
        $group = EntryGroup::factory()->create();
        $type = EntryType::factory()->for($group)->create();
        Entry::factory()->for($group)->for($type)->create();
        Entry::factory()->for($group)->for($type)->create();

        $this->assertCount(2, $type->entries);
    }

    public function test_scope_in_group_filters_by_entry_group_model(): void
    {
        $group1 = EntryGroup::factory()->create();
        $group2 = EntryGroup::factory()->create();
        $type1 = EntryType::factory()->for($group1)->create();
        $type2 = EntryType::factory()->for($group2)->create();

        $results = EntryType::query()->inGroup($group1)->get();

        $this->assertTrue($results->contains($type1));
        $this->assertFalse($results->contains($type2));
    }

    public function test_scope_in_group_filters_by_group_id_integer(): void
    {
        $group1 = EntryGroup::factory()->create();
        $group2 = EntryGroup::factory()->create();
        $type1 = EntryType::factory()->for($group1)->create();
        $type2 = EntryType::factory()->for($group2)->create();

        $results = EntryType::query()->inGroup($group1->id)->get();

        $this->assertTrue($results->contains($type1));
        $this->assertFalse($results->contains($type2));
    }
}
