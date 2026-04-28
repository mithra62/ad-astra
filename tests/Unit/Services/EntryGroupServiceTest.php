<?php

namespace Tests\Unit\Services;

use App\Models\EntryGroup;
use App\Services\EntryGroupService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntryGroupServiceTest extends TestCase
{
    use RefreshDatabase;

    private EntryGroupService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EntryGroupService::class);
    }

    // -------------------------------------------------------------------------
    // find()
    // -------------------------------------------------------------------------

    public function test_find_returns_entry_group_when_it_exists(): void
    {
        $group = EntryGroup::factory()->create();

        $result = $this->service->find($group->id);

        $this->assertInstanceOf(EntryGroup::class, $result);
        $this->assertEquals($group->id, $result->id);
    }

    public function test_find_returns_null_when_group_does_not_exist(): void
    {
        $result = $this->service->find(999999);

        $this->assertNull($result);
    }

    // -------------------------------------------------------------------------
    // get()
    // -------------------------------------------------------------------------

    public function test_get_returns_entry_group_when_it_exists(): void
    {
        $group = EntryGroup::factory()->create();

        $result = $this->service->get($group->id);

        $this->assertInstanceOf(EntryGroup::class, $result);
        $this->assertEquals($group->id, $result->id);
    }

    public function test_get_throws_when_group_does_not_exist(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->service->get(999999);
    }

    // -------------------------------------------------------------------------
    // delete()
    // -------------------------------------------------------------------------

    public function test_delete_removes_group_from_database(): void
    {
        $group = EntryGroup::factory()->create();

        $this->service->delete($group);

        $this->assertDatabaseMissing('entry_groups', ['id' => $group->id]);
    }

    public function test_delete_returns_true_on_success(): void
    {
        $group = EntryGroup::factory()->create();

        $result = $this->service->delete($group);

        $this->assertTrue($result);
    }
}
