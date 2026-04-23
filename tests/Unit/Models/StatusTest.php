<?php

namespace Tests\Unit\Models;

use App\Models\Status;
use App\Models\StatusGroup;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_has_correct_fillable_attributes(): void
    {
        $model = new Status;

        $this->assertEquals(
            ['status_group_id', 'name', 'handle', 'color', 'is_default', 'sort_order'],
            $model->getFillable()
        );
    }

    public function test_casts_is_default_to_boolean(): void
    {
        $status = Status::factory()->create(['is_default' => 1]);

        $this->assertIsBool($status->is_default);
        $this->assertTrue($status->is_default);
    }

    public function test_casts_sort_order_to_integer(): void
    {
        $status = Status::factory()->create(['sort_order' => '3']);

        $this->assertIsInt($status->sort_order);
        $this->assertEquals(3, $status->sort_order);
    }

    public function test_group_relationship_is_belongs_to_status_group(): void
    {
        $group = StatusGroup::factory()->create();
        $status = Status::factory()->for($group, 'group')->create();

        $this->assertInstanceOf(BelongsTo::class, $status->group());
        $this->assertEquals($group->id, $status->group->id);
    }

    public function test_scope_default_returns_only_default_statuses(): void
    {
        $default = Status::factory()->create(['is_default' => true]);
        Status::factory()->create(['is_default' => false]);

        $results = Status::query()->default()->get();

        $this->assertTrue($results->contains($default));
        $this->assertCount(1, $results);
    }

    public function test_scope_default_excludes_non_default_statuses(): void
    {
        $nonDefault = Status::factory()->create(['is_default' => false]);

        $results = Status::query()->default()->get();

        $this->assertFalse($results->contains($nonDefault));
    }

    public function test_scope_in_group_filters_by_status_group_model(): void
    {
        $group1 = StatusGroup::factory()->create();
        $group2 = StatusGroup::factory()->create();
        $status1 = Status::factory()->for($group1, 'group')->create();
        $status2 = Status::factory()->for($group2, 'group')->create();

        $results = Status::query()->inGroup($group1)->get();

        $this->assertTrue($results->contains($status1));
        $this->assertFalse($results->contains($status2));
    }

    public function test_scope_in_group_filters_by_status_group_id_integer(): void
    {
        $group1 = StatusGroup::factory()->create();
        $group2 = StatusGroup::factory()->create();
        $status1 = Status::factory()->for($group1, 'group')->create();
        $status2 = Status::factory()->for($group2, 'group')->create();

        $results = Status::query()->inGroup($group1->id)->get();

        $this->assertTrue($results->contains($status1));
        $this->assertFalse($results->contains($status2));
    }
}
