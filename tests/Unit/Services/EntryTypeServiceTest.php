<?php

namespace Tests\Unit\Services;

use App\Models\EntryGroup;
use App\Models\EntryType;
use App\Services\EntryTypeService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntryTypeServiceTest extends TestCase
{
    use RefreshDatabase;

    private EntryTypeService $service;

    public function test_find_returns_entry_type_when_it_exists(): void
    {
        $type = EntryType::factory()->create();

        $result = $this->service->find($type->id);

        $this->assertInstanceOf(EntryType::class, $result);
        $this->assertEquals($type->id, $result->id);
    }

    // -------------------------------------------------------------------------
    // find()
    // -------------------------------------------------------------------------

    public function test_find_returns_null_when_type_does_not_exist(): void
    {
        $result = $this->service->find(999999);

        $this->assertNull($result);
    }

    public function test_get_returns_entry_type_when_it_exists(): void
    {
        $type = EntryType::factory()->create();

        $result = $this->service->get($type->id);

        $this->assertInstanceOf(EntryType::class, $result);
        $this->assertEquals($type->id, $result->id);
    }

    // -------------------------------------------------------------------------
    // get()
    // -------------------------------------------------------------------------

    public function test_get_throws_when_type_does_not_exist(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->service->get(999999);
    }

    public function test_delete_removes_type_from_database(): void
    {
        $type = EntryType::factory()->create();

        $this->service->delete($type);

        $this->assertDatabaseMissing('entry_types', ['id' => $type->id]);
    }

    // -------------------------------------------------------------------------
    // delete()
    // -------------------------------------------------------------------------

    public function test_delete_returns_true_on_success(): void
    {
        $type = EntryType::factory()->create();

        $result = $this->service->delete($type);

        $this->assertTrue($result);
    }

    public function test_create_accepts_entry_group_model_or_int_equivalently(): void
    {
        $groupA = EntryGroup::factory()->create();
        $groupB = EntryGroup::factory()->create();

        $byModel = $this->service->create($groupA, [
            'name' => 'By Model', 'handle' => 'by-model', 'class' => 'App\\EntryTypes\\PageEntryType',
        ]);

        $byInt = $this->service->create($groupB->id, [
            'name' => 'By Int', 'handle' => 'by-int', 'class' => 'App\\EntryTypes\\PageEntryType',
        ]);

        $this->assertEquals($groupA->id, $byModel->entry_group_id);
        $this->assertEquals($groupB->id, $byInt->entry_group_id);
    }

    // -------------------------------------------------------------------------
    // create() with EntryGroup model (not just int)
    // -------------------------------------------------------------------------

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EntryTypeService::class);
    }
}
