<?php

namespace Tests\Unit\Models;

use App\Models\Status;
use App\Models\StatusGroup;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatusGroupTest extends TestCase
{
    use RefreshDatabase;

    public function test_has_correct_fillable_attributes(): void
    {
        $model = new StatusGroup;

        $this->assertEquals(['name', 'handle', 'sort_order'], $model->getFillable());
    }

    public function test_casts_sort_order_to_integer(): void
    {
        $group = StatusGroup::factory()->create(['sort_order' => '5']);

        $this->assertIsInt($group->sort_order);
        $this->assertEquals(5, $group->sort_order);
    }

    public function test_statuses_relationship_is_has_many(): void
    {
        $group = StatusGroup::factory()->create();

        $this->assertInstanceOf(HasMany::class, $group->statuses());
    }

    public function test_statuses_are_ordered_by_sort_order(): void
    {
        $group = StatusGroup::factory()->create();
        Status::factory()->for($group, 'group')->create(['sort_order' => 3]);
        Status::factory()->for($group, 'group')->create(['sort_order' => 1]);
        Status::factory()->for($group, 'group')->create(['sort_order' => 2]);

        $statuses = $group->statuses()->get();

        $this->assertEquals(1, $statuses->first()->sort_order);
        $this->assertEquals(3, $statuses->last()->sort_order);
    }

    public function test_default_status_relationship_is_has_one(): void
    {
        $group = StatusGroup::factory()->create();

        $this->assertInstanceOf(HasOne::class, $group->defaultStatus());
    }

    public function test_default_status_returns_the_default_status(): void
    {
        $group = StatusGroup::factory()->create();
        Status::factory()->for($group, 'group')->create(['is_default' => false]);
        $default = Status::factory()->for($group, 'group')->create(['is_default' => true]);

        $this->assertEquals($default->id, $group->defaultStatus->id);
    }

    public function test_default_status_returns_null_when_none_set(): void
    {
        $group = StatusGroup::factory()->create();
        Status::factory()->for($group, 'group')->create(['is_default' => false]);

        $this->assertNull($group->defaultStatus);
    }

    public function test_scope_ordered_sorts_by_sort_order_then_name(): void
    {
        StatusGroup::factory()->create(['name' => 'Zebra', 'sort_order' => 1]);
        StatusGroup::factory()->create(['name' => 'Alpha', 'sort_order' => 1]);
        StatusGroup::factory()->create(['name' => 'Middle', 'sort_order' => 0]);

        $groups = StatusGroup::query()->ordered()->get();

        $this->assertEquals('Middle', $groups->first()->name);
        $this->assertEquals('Alpha', $groups->get(1)->name);
        $this->assertEquals('Zebra', $groups->last()->name);
    }
}
