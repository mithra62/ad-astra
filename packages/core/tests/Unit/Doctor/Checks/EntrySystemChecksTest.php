<?php

namespace Tests\Unit\Doctor\Checks;

use AdAstra\Doctor\Checks\EntrySystem\DuplicateTypeHandlesCheck;
use AdAstra\Doctor\Checks\EntrySystem\SilentBehaviorFallbackCheck;
use AdAstra\Doctor\DoctorStatus;
use AdAstra\Models\EntryBehavior;
use AdAstra\Models\EntryType;
use Database\Seeders\EntryBehaviorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntrySystemChecksTest extends TestCase
{
    use RefreshDatabase;

    private function behaviorId(): int
    {
        $this->seed(EntryBehaviorSeeder::class);

        return EntryBehavior::where('class', 'behavior.general')->firstOrFail()->id;
    }

    public function test_unique_handles_pass(): void
    {
        $behaviorId = $this->behaviorId();
        EntryType::create(['name' => 'One', 'handle' => 'doctor_one', 'entry_behavior_id' => $behaviorId]);
        EntryType::create(['name' => 'Two', 'handle' => 'doctor_two', 'entry_behavior_id' => $behaviorId]);

        $results = iterator_to_array((new DuplicateTypeHandlesCheck())->run(), false);

        $this->assertCount(1, $results);
        $this->assertSame(DoctorStatus::Pass, $results[0]->status);
    }

    public function test_duplicate_handles_fail(): void
    {
        $behaviorId = $this->behaviorId();
        EntryType::create(['name' => 'One', 'handle' => 'doctor_dupe', 'entry_behavior_id' => $behaviorId]);
        EntryType::create(['name' => 'Two', 'handle' => 'doctor_dupe', 'entry_behavior_id' => $behaviorId]);

        $results = iterator_to_array((new DuplicateTypeHandlesCheck())->run(), false);

        $this->assertCount(1, $results);
        $this->assertSame(DoctorStatus::Fail, $results[0]->status);
        $this->assertStringContainsString('doctor_dupe', $results[0]->message);
    }

    public function test_types_with_behaviors_pass_fallback_check(): void
    {
        EntryType::create(['name' => 'One', 'handle' => 'doctor_one', 'entry_behavior_id' => $this->behaviorId()]);

        $results = iterator_to_array((new SilentBehaviorFallbackCheck())->run(), false);

        $this->assertCount(1, $results);
        $this->assertSame(DoctorStatus::Pass, $results[0]->status);
    }

    public function test_type_without_behavior_warns(): void
    {
        EntryType::create(['name' => 'Orphan', 'handle' => 'doctor_orphan', 'entry_behavior_id' => null]);

        $results = iterator_to_array((new SilentBehaviorFallbackCheck())->run(), false);

        $this->assertCount(1, $results);
        $this->assertSame(DoctorStatus::Warn, $results[0]->status);
        $this->assertStringContainsString('doctor_orphan', $results[0]->message);
        $this->assertStringContainsString('GeneralEntryType', $results[0]->message);
    }
}
