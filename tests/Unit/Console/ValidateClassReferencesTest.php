<?php

namespace Tests\Unit\Console;

use App\EntryTypes\GeneralEntryType;
use App\Models\EntryType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ValidateClassReferencesTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Null class — should be skipped, not reported as broken
    // -------------------------------------------------------------------------

    public function test_null_class_is_not_reported_as_an_error(): void
    {
        EntryType::factory()->create(['class' => null]);

        $this->artisan('app:validate-class-references')
            ->assertSuccessful();
    }

    public function test_null_class_outputs_handle_in_informational_line(): void
    {
        $type = EntryType::factory()->create(['class' => null]);

        $this->artisan('app:validate-class-references')
            ->expectsOutputToContain($type->handle)
            ->assertSuccessful();
    }

    public function test_null_class_outputs_no_class_set_message(): void
    {
        EntryType::factory()->create(['class' => null]);

        $this->artisan('app:validate-class-references')
            ->expectsOutputToContain('no class set')
            ->assertSuccessful();
    }

    // -------------------------------------------------------------------------
    // Valid class — should pass
    // -------------------------------------------------------------------------

    public function test_valid_class_passes(): void
    {
        EntryType::factory()->create(['class' => GeneralEntryType::class]);

        $this->artisan('app:validate-class-references')
            ->assertSuccessful();
    }

    // -------------------------------------------------------------------------
    // Missing class — should fail
    // -------------------------------------------------------------------------

    public function test_missing_class_is_reported_as_error(): void
    {
        EntryType::factory()->create(['class' => 'App\\EntryTypes\\DoesNotExistEntryType']);

        $this->artisan('app:validate-class-references')
            ->assertFailed();
    }

    // -------------------------------------------------------------------------
    // Invalid class — exists but does not extend AbstractEntryType
    // -------------------------------------------------------------------------

    public function test_class_not_extending_abstract_entry_type_is_reported_as_error(): void
    {
        EntryType::factory()->create(['class' => \stdClass::class]);

        $this->artisan('app:validate-class-references')
            ->assertFailed();
    }
}
