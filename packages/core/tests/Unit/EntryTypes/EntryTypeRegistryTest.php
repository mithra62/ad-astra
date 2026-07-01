<?php

namespace Tests\Unit\EntryTypes;

use AdAstra\EntryTypes\AbstractEntryType;
use AdAstra\EntryTypes\EntryTypeRegistry;
use AdAstra\EntryTypes\GeneralEntryType;
use AdAstra\Models\EntryBehavior;
use AdAstra\Models\EntryType;
use Database\Seeders\EntryBehaviorSeeder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use stdClass;
use Tests\TestCase;

class EntryTypeRegistryTest extends TestCase
{
    use RefreshDatabase;

    private EntryTypeRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EntryBehaviorSeeder::class);
        $this->registry = app(EntryTypeRegistry::class);
    }

    // -------------------------------------------------------------------------
    // instantiate() — null behavior fallback
    // -------------------------------------------------------------------------

    public function test_resolves_general_entry_type_when_behavior_is_null(): void
    {
        $record = EntryType::factory()->create(['entry_behavior_id' => null]);

        $instance = $this->registry->resolveByRecord($record);

        $this->assertInstanceOf(GeneralEntryType::class, $instance);
    }

    // -------------------------------------------------------------------------
    // instantiate() — invalid class (not AbstractEntryType subclass) still throws
    // -------------------------------------------------------------------------

    public function test_throws_runtime_exception_when_class_does_not_extend_abstract_entry_type(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/must extend AbstractEntryType/');

        $morphKey = 'behavior.bad-' . uniqid();
        Relation::morphMap([$morphKey => stdClass::class]);

        $badBehavior = EntryBehavior::create([
            'name' => 'Bad',
            'handle' => 'bad-class-' . uniqid(),
            'class' => $morphKey,
        ]);

        $record = EntryType::factory()->create(['entry_behavior_id' => $badBehavior->id]);

        $this->registry->resolveByRecord($record);
    }

    // -------------------------------------------------------------------------
    // resolveByRecord() — caching
    // -------------------------------------------------------------------------

    public function test_resolves_same_instance_for_same_record(): void
    {
        $record = EntryType::factory()->create([
            'entry_behavior_id' => EntryBehavior::where('handle', 'general')->value('id'),
        ]);

        $first = $this->registry->resolveByRecord($record);
        $second = $this->registry->resolveByRecord($record);

        $this->assertSame($first, $second);
    }

    // -------------------------------------------------------------------------
    // resolveByHandle() — normal path
    // -------------------------------------------------------------------------

    public function test_resolves_by_handle(): void
    {
        $record = EntryType::factory()->create([
            'entry_behavior_id' => EntryBehavior::where('handle', 'general')->value('id'),
        ]);

        $instance = $this->registry->resolveByHandle($record->handle);

        $this->assertInstanceOf(AbstractEntryType::class, $instance);
    }

    // -------------------------------------------------------------------------
    // Cache convergence — resolveByHandle and resolveByRecord return same instance
    // -------------------------------------------------------------------------

    public function test_resolve_by_handle_and_resolve_by_record_return_same_instance(): void
    {
        $record = EntryType::factory()->create([
            'entry_behavior_id' => EntryBehavior::where('handle', 'general')->value('id'),
        ]);

        $byHandle = $this->registry->resolveByHandle($record->handle);
        $byRecord = $this->registry->resolveByRecord($record);

        $this->assertSame($byHandle, $byRecord);
    }

    public function test_resolve_by_record_then_by_handle_return_same_instance(): void
    {
        $record = EntryType::factory()->create([
            'entry_behavior_id' => EntryBehavior::where('handle', 'general')->value('id'),
        ]);

        $byRecord = $this->registry->resolveByRecord($record);
        $byHandle = $this->registry->resolveByHandle($record->handle);

        $this->assertSame($byRecord, $byHandle);
    }
}
