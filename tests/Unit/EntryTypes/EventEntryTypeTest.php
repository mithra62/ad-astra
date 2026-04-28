<?php

namespace Tests\Unit\EntryTypes;

use App\EntryTypes\EventEntryType;
use App\Models\Entry;
use App\Models\EntryType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
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
        $type    = $this->makeType();
        $existingDate = now()->subWeek();
        $entry   = Entry::factory()->create(['published_at' => $existingDate]);

        $result = $type->beforeUpdate($entry, ['status' => 'published']);

        $this->assertArrayNotHasKey('published_at', $result);
    }

    // -------------------------------------------------------------------------
    // beforeUpdate — date range validation
    // -------------------------------------------------------------------------

    public function test_before_update_throws_when_end_date_is_before_start_date(): void
    {
        $type  = $this->makeType();
        $entry = Entry::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('end_date cannot be earlier than start_date');

        $type->beforeUpdate($entry, [
            'fields' => [
                'start_date' => '2026-06-10',
                'end_date'   => '2026-06-01',
            ],
        ]);
    }

    public function test_before_update_passes_when_end_date_equals_start_date(): void
    {
        $type  = $this->makeType();
        $entry = Entry::factory()->create();

        // Should not throw.
        $result = $type->beforeUpdate($entry, [
            'fields' => [
                'start_date' => '2026-06-10',
                'end_date'   => '2026-06-10',
            ],
        ]);

        $this->assertIsArray($result);
    }

    public function test_before_update_passes_when_end_date_is_after_start_date(): void
    {
        $type  = $this->makeType();
        $entry = Entry::factory()->create();

        $result = $type->beforeUpdate($entry, [
            'fields' => [
                'start_date' => '2026-06-01',
                'end_date'   => '2026-06-10',
            ],
        ]);

        $this->assertIsArray($result);
    }

    public function test_before_update_skips_date_validation_when_end_date_absent(): void
    {
        $type  = $this->makeType();
        $entry = Entry::factory()->create();

        // No exception — end_date not in payload, start_date alone is fine.
        $result = $type->beforeUpdate($entry, ['fields' => ['start_date' => '2026-06-01']]);

        $this->assertIsArray($result);
    }

    public function test_before_update_skips_date_validation_when_both_dates_absent(): void
    {
        $type  = $this->makeType();
        $entry = Entry::factory()->create();

        $result = $type->beforeUpdate($entry, ['title' => 'Event Update']);

        $this->assertIsArray($result);
    }
}
