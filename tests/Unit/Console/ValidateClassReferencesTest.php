<?php

namespace Tests\Unit\Console;

use App\Models\EntryBehavior;
use Database\Seeders\EntryBehaviorSeeder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ValidateClassReferencesTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Valid morphMap keys — should pass
    // -------------------------------------------------------------------------

    public function test_valid_class_passes(): void
    {
        $this->seed(EntryBehaviorSeeder::class);

        $this->artisan('app:validate-class-references')
            ->assertSuccessful();
    }

    // -------------------------------------------------------------------------
    // Unmapped morph key — should fail
    // -------------------------------------------------------------------------

    public function test_missing_class_is_reported_as_error(): void
    {
        EntryBehavior::create([
            'name'   => 'Missing',
            'handle' => 'missing-' . uniqid(),
            'class'  => 'behavior.nonexistent-' . uniqid(),
        ]);

        $this->artisan('app:validate-class-references')
            ->assertFailed();
    }

    // -------------------------------------------------------------------------
    // Mapped key whose class does not extend AbstractEntryType — should fail
    // -------------------------------------------------------------------------

    public function test_class_not_extending_abstract_entry_type_is_reported_as_error(): void
    {
        $morphKey = 'behavior.bad-' . uniqid();
        Relation::morphMap([$morphKey => \stdClass::class]);

        EntryBehavior::create([
            'name'   => 'Bad',
            'handle' => 'bad-' . uniqid(),
            'class'  => $morphKey,
        ]);

        $this->artisan('app:validate-class-references')
            ->assertFailed();
    }
}
