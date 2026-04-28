<?php

namespace Tests\Unit\EntryTypes;

use App\EntryTypes\GeneralEntryType;
use App\Models\Entry;
use App\Models\EntryType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeneralEntryTypeTest extends TestCase
{
    use RefreshDatabase;

    private function makeType(): GeneralEntryType
    {
        $record = EntryType::factory()->create(['class' => GeneralEntryType::class]);
        return new GeneralEntryType($record);
    }

    // -------------------------------------------------------------------------
    // beforeCreate
    // -------------------------------------------------------------------------

    public function test_before_create_defaults_published_at_to_now(): void
    {
        $type = $this->makeType();

        $result = $type->beforeCreate([]);

        $this->assertNotNull($result['published_at']);
    }

    public function test_before_create_respects_explicit_published_at(): void
    {
        $type = $this->makeType();
        $date = now()->addWeek()->toDateTimeString();

        $result = $type->beforeCreate(['published_at' => $date]);

        $this->assertSame($date, $result['published_at']);
    }

    // -------------------------------------------------------------------------
    // beforeUpdate
    // -------------------------------------------------------------------------

    public function test_before_update_stamps_published_at_on_publish_transition(): void
    {
        $type  = $this->makeType();
        $entry = Entry::factory()->create(['published_at' => null]);

        $result = $type->beforeUpdate($entry, ['status' => 'published']);

        $this->assertNotNull($result['published_at']);
    }

    public function test_before_update_does_not_overwrite_existing_published_at(): void
    {
        $type  = $this->makeType();
        $date  = now()->subDays(5);
        $entry = Entry::factory()->create(['published_at' => $date]);

        $result = $type->beforeUpdate($entry, ['status' => 'published']);

        $this->assertArrayNotHasKey('published_at', $result);
    }

    public function test_before_update_does_not_stamp_when_status_is_not_published(): void
    {
        $type  = $this->makeType();
        $entry = Entry::factory()->create(['published_at' => null]);

        $result = $type->beforeUpdate($entry, ['status' => 'draft']);

        $this->assertArrayNotHasKey('published_at', $result);
    }

    public function test_before_update_respects_explicit_published_at_in_payload(): void
    {
        $type  = $this->makeType();
        $date  = now()->addDays(3)->toDateTimeString();
        $entry = Entry::factory()->create(['published_at' => null]);

        $result = $type->beforeUpdate($entry, ['status' => 'published', 'published_at' => $date]);

        $this->assertSame($date, $result['published_at']);
    }
}
