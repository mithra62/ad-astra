<?php

namespace Tests\Unit\EntryTypes;

use AdAstra\EntryTypes\EventEntryType;
use AdAstra\Models\EntryBehavior;
use AdAstra\Models\EntryType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventEntryTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_validate_returns_error_when_end_date_is_before_start_date(): void
    {
        $type = $this->makeType();

        $errors = $type->validate([
            'fields' => [
                'start_date' => '2026-06-10',
                'end_date' => '2026-06-01',
            ],
        ]);

        $this->assertArrayHasKey('end_date', $errors);
        $this->assertStringContainsString('earlier than start_date', $errors['end_date']);
    }

    // -------------------------------------------------------------------------
    // validate() — date range guard
    // -------------------------------------------------------------------------

    private function makeType(): EventEntryType
    {
        $record = EntryType::factory()->create(['entry_behavior_id' => EntryBehavior::where('handle', 'event')->value('id')]);
        return new EventEntryType($record);
    }

    public function test_validate_passes_when_end_date_equals_start_date(): void
    {
        $type = $this->makeType();

        $errors = $type->validate([
            'fields' => [
                'start_date' => '2026-06-10',
                'end_date' => '2026-06-10',
            ],
        ]);

        $this->assertEmpty($errors);
    }

    public function test_validate_passes_when_end_date_is_after_start_date(): void
    {
        $type = $this->makeType();

        $errors = $type->validate([
            'fields' => [
                'start_date' => '2026-06-01',
                'end_date' => '2026-06-10',
            ],
        ]);

        $this->assertEmpty($errors);
    }

    public function test_validate_skips_check_when_end_date_absent(): void
    {
        $type = $this->makeType();

        $errors = $type->validate(['fields' => ['start_date' => '2026-06-01']]);

        $this->assertEmpty($errors);
    }

    public function test_validate_skips_check_when_both_dates_absent(): void
    {
        $type = $this->makeType();

        $errors = $type->validate(['title' => 'Event Update']);

        $this->assertEmpty($errors);
    }

    public function test_validate_skips_check_when_end_date_present_but_no_start_date_resolvable(): void
    {
        $type = $this->makeType();

        $errors = $type->validate(['fields' => ['end_date' => '2026-06-10']], null);

        $this->assertEmpty($errors);
    }
}
