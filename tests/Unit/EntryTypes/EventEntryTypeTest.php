<?php

namespace Tests\Unit\EntryTypes;

use App\EntryTypes\EventEntryType;
use App\Models\Entry;
use App\Models\EntryType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventEntryTypeTest extends TestCase
{
    use RefreshDatabase;

    private function makeType(): EventEntryType
    {
        $record = EntryType::factory()->create(['class' => EventEntryType::class]);
        return new EventEntryType($record);
    }

    // -------------------------------------------------------------------------
    // beforeCreate — published_at
    // -------------------------------------------------------------------------

    public function test_before_create_defaults_published_at_to_now(): void
    {
        $type = $this->makeType();

        $result = $type->beforeCreate([]);

        $this->assertNotNull($result['published_at']);
    }

    public function test_before_create_does_not_overwrite_explicit_published_at(): void
    {
        $type = $this->makeType();
        $date = now()->addDay()->toDateTimeString();

        $result = $type->beforeCreate(['published_at' => $date]);

        $this->assertSame($date, $result['published_at']);
    }

    // -------------------------------------------------------------------------
    // beforeUpdate — published_at stamping
    // -------------------------------------------------------------------------

    public function test_before_update_stamps_published_at_on_publish_when_none_exists(): void
    {
        $type  = $this->makeType();
        $entry = Entry::factory()->create(['published_at' => null]);

        $result = $type->beforeUpdate($entry, ['status' => 'published']);

        $this->assertNotNull($result['published_at']);
    }

    public function test_before_update_does_not_overwrite_existing_published_at(): void
    {
        $type  = $this->makeType();
        $entry = Entry::factory()->create(['published_at' => now()->subWeek()]);

        $result = $type->beforeUpdate($entry, ['status' => 'published']);

        $this->assertArrayNotHasKey('published_at', $result);
    }

    public function test_before_update_does_not_throw_on_invalid_date_range(): void
    {
        // Date range validation has moved to validate(); beforeUpdate must not throw.
        $type  = $this->makeType();
        $entry = Entry::factory()->create();

        $result = $type->beforeUpdate($entry, [
            'fields' => [
                'start_date' => '2026-06-10',
                'end_date'   => '2026-06-01',
            ],
        ]);

        $this->assertIsArray($result);
    }

    // -------------------------------------------------------------------------
    // validate() — date range guard
    // -------------------------------------------------------------------------

    public function test_validate_returns_error_when_end_date_is_before_start_date(): void
    {
        $type = $this->makeType();

        $errors = $type->validate([
            'fields' => [
                'start_date' => '2026-06-10',
                'end_date'   => '2026-06-01',
            ],
        ]);

        $this->assertArrayHasKey('end_date', $errors);
        $this->assertStringContainsString('earlier than start_date', $errors['end_date']);
    }

    public function test_validate_passes_when_end_date_equals_start_date(): void
    {
        $type = $this->makeType();

        $errors = $type->validate([
            'fields' => [
                'start_date' => '2026-06-10',
                'end_date'   => '2026-06-10',
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
                'end_date'   => '2026-06-10',
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
        // No start_date in payload and no entry to read from — should not error.
        $type = $this->makeType();

        $errors = $type->validate(['fields' => ['end_date' => '2026-06-10']], null);

        $this->assertEmpty($errors);
    }
}
