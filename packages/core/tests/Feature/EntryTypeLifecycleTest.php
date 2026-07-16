<?php

namespace Tests\Feature;

use AdAstra\Models\Entry;
use AdAstra\Models\EntryBehavior;
use AdAstra\Models\EntryGroup;
use AdAstra\Models\EntryType;
use AdAstra\Models\Status;
use AdAstra\Models\StatusGroup;
use AdAstra\Models\User;
use AdAstra\Services\EntryService;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Stubs\SpyEntryType;
use Tests\TestCase;

/**
 * End-to-end tests for the EntryType lifecycle hook contract.
 *
 * Each test drives through EntryService → EntryRepository so the full
 * call stack (registry resolution, transaction wrapping, hook ordering)
 * is exercised rather than just the hook stubs in isolation.
 */
class EntryTypeLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private EntryService $service;
    private EntryType $type;

    // -------------------------------------------------------------------------
    // beforeCreate
    // -------------------------------------------------------------------------

    public function test_before_create_is_invoked(): void
    {
        $this->service->create($this->type->handle, ['title' => 'Hello', 'handle' => 'hello']);

        $this->assertSame(1, SpyEntryType::$beforeCreateCalls);
    }

    public function test_before_create_mutation_is_persisted(): void
    {
        // SpyEntryType::beforeCreate appends ' [bc]' to the title.
        $this->service->create($this->type->handle, ['title' => 'Hello', 'handle' => 'hello']);

        $this->assertDatabaseHas('entries', ['title' => 'Hello [bc]']);
    }

    // -------------------------------------------------------------------------
    // afterCreate
    // -------------------------------------------------------------------------

    public function test_after_create_is_invoked(): void
    {
        $this->service->create($this->type->handle, ['title' => 'Hello', 'handle' => 'hello']);

        $this->assertSame(1, SpyEntryType::$afterCreateCalls);
    }

    public function test_after_create_receives_a_persisted_entry(): void
    {
        // Verify the entry passed to afterCreate already exists in the DB
        // by checking that the returned entry has a non-zero primary key.
        $entry = $this->service->create($this->type->handle, ['title' => 'Hello', 'handle' => 'hello']);

        $this->assertTrue($entry->exists);
        $this->assertGreaterThan(0, $entry->id);
    }

    // -------------------------------------------------------------------------
    // beforeUpdate
    // -------------------------------------------------------------------------

    public function test_before_update_is_invoked(): void
    {
        $entry = $this->makeEntry();

        $this->service->update($entry, ['title' => 'Updated']);

        $this->assertSame(1, SpyEntryType::$beforeUpdateCalls);
    }

    /**
     * Create a bare entry in the DB using the spy type, bypassing the service
     * (so setUp hooks don't inflate the create counters for update tests).
     */
    private function makeEntry(): Entry
    {
        // Direct factory create — skips EntryService and its lifecycle hooks.
        $entry = Entry::factory()->create([
            'entry_group_id' => $this->type->entry_group_id,
            'entry_type_id' => $this->type->id,
            'title' => 'Original',
        ]);

        // Reset counters so update tests start from zero.
        SpyEntryType::reset();

        return $entry;
    }

    // -------------------------------------------------------------------------
    // afterUpdate
    // -------------------------------------------------------------------------

    public function test_before_update_mutation_is_persisted(): void
    {
        $entry = $this->makeEntry();

        // SpyEntryType::beforeUpdate appends ' [bu]' to the title.
        $this->service->update($entry, ['title' => 'Updated']);

        $this->assertDatabaseHas('entries', ['id' => $entry->id, 'title' => 'Updated [bu]']);
    }

    public function test_after_update_is_invoked(): void
    {
        $entry = $this->makeEntry();

        $this->service->update($entry, ['title' => 'Updated']);

        $this->assertSame(1, SpyEntryType::$afterUpdateCalls);
    }

    // -------------------------------------------------------------------------
    // Hook ordering — create
    // -------------------------------------------------------------------------

    public function test_after_update_receives_entry_with_new_title(): void
    {
        $entry = $this->makeEntry();

        $updated = $this->service->update($entry, ['title' => 'Updated']);

        // The returned entry should reflect the beforeUpdate mutation.
        $this->assertSame('Updated [bu]', $updated->title);
    }

    public function test_before_create_runs_before_after_create(): void
    {
        // Both hooks fire on a single create() call.
        $this->service->create($this->type->handle, ['title' => 'Hello', 'handle' => 'hello']);

        $this->assertSame(1, SpyEntryType::$beforeCreateCalls);
        $this->assertSame(1, SpyEntryType::$afterCreateCalls);
        $this->assertSame(0, SpyEntryType::$beforeUpdateCalls);
        $this->assertSame(0, SpyEntryType::$afterUpdateCalls);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function test_before_update_runs_before_after_update(): void
    {
        $entry = $this->makeEntry();

        $this->service->update($entry, ['title' => 'Updated']);

        $this->assertSame(0, SpyEntryType::$beforeCreateCalls);
        $this->assertSame(0, SpyEntryType::$afterCreateCalls);
        $this->assertSame(1, SpyEntryType::$beforeUpdateCalls);
        $this->assertSame(1, SpyEntryType::$afterUpdateCalls);
    }

    protected function setUp(): void
    {
        parent::setUp();

        SpyEntryType::reset();

        $this->service = app(EntryService::class);
        $this->actingAs(User::factory()->create());

        $statusGroup = StatusGroup::factory()->create();
        Status::factory()->create([
            'status_group_id' => $statusGroup->id,
            'handle' => 'draft',
            'is_default' => true,
            'is_public' => false,
        ]);

        $group = EntryGroup::factory()->create(['status_group_id' => $statusGroup->id]);

        $morphKey = 'behavior.spy-' . uniqid();
        Relation::morphMap([$morphKey => SpyEntryType::class]);

        $behavior = EntryBehavior::create([
            'name' => 'Spy',
            'handle' => 'spy-' . uniqid(),
            'class' => $morphKey,
        ]);

        $this->type = EntryType::factory()->create([
            'entry_group_id' => $group->id,
            'handle' => 'spy-type-' . uniqid(),
            'entry_behavior_id' => $behavior->id,
        ]);
    }
}
