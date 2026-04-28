<?php

namespace Tests\Unit\EntryTypes;

use App\EntryTypes\AbstractEntryType;
use App\EntryTypes\EntryTypeRegistry;
use App\EntryTypes\GeneralEntryType;
use App\Models\EntryType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

class EntryTypeRegistryTest extends TestCase
{
    use RefreshDatabase;

    private EntryTypeRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = app(EntryTypeRegistry::class);
    }

    // -------------------------------------------------------------------------
    // instantiate() — null / missing class fallback (Option B)
    // -------------------------------------------------------------------------

    public function test_resolves_general_entry_type_when_class_is_null(): void
    {
        $record = EntryType::factory()->create(['class' => null]);

        $instance = $this->registry->resolveByRecord($record);

        $this->assertInstanceOf(GeneralEntryType::class, $instance);
    }

    public function test_resolves_general_entry_type_when_class_does_not_exist(): void
    {
        $record = EntryType::factory()->create(['class' => 'App\\EntryTypes\\DoesNotExistEntryType']);

        $instance = $this->registry->resolveByRecord($record);

        $this->assertInstanceOf(GeneralEntryType::class, $instance);
    }

    public function test_logs_warning_when_class_is_null(): void
    {
        Log::shouldReceive('warning')->once()->withArgs(function (string $msg) {
            return str_contains($msg, 'no class assigned') && str_contains($msg, 'GeneralEntryType');
        });

        $record = EntryType::factory()->create(['class' => null]);

        $this->registry->resolveByRecord($record);
    }

    public function test_logs_warning_when_class_does_not_exist(): void
    {
        Log::shouldReceive('warning')->once()->withArgs(function (string $msg) {
            return str_contains($msg, 'does not exist') && str_contains($msg, 'GeneralEntryType');
        });

        $record = EntryType::factory()->create(['class' => 'App\\EntryTypes\\DoesNotExistEntryType']);

        $this->registry->resolveByRecord($record);
    }

    // -------------------------------------------------------------------------
    // instantiate() — invalid class (not AbstractEntryType subclass) still throws
    // -------------------------------------------------------------------------

    public function test_throws_runtime_exception_when_class_does_not_extend_abstract_entry_type(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/must extend AbstractEntryType/');

        $record = EntryType::factory()->create(['class' => \stdClass::class]);

        $this->registry->resolveByRecord($record);
    }

    // -------------------------------------------------------------------------
    // resolveByRecord() — caching
    // -------------------------------------------------------------------------

    public function test_resolves_same_instance_for_same_record(): void
    {
        $record = EntryType::factory()->create(['class' => GeneralEntryType::class]);

        $first  = $this->registry->resolveByRecord($record);
        $second = $this->registry->resolveByRecord($record);

        $this->assertSame($first, $second);
    }

    // -------------------------------------------------------------------------
    // resolveByHandle() — normal path
    // -------------------------------------------------------------------------

    public function test_resolves_by_handle(): void
    {
        $record = EntryType::factory()->create(['class' => GeneralEntryType::class]);

        $instance = $this->registry->resolveByHandle($record->handle);

        $this->assertInstanceOf(AbstractEntryType::class, $instance);
    }
}
