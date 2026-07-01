<?php

namespace Tests\Unit\EntryTypes;

use AdAstra\EntryTypes\JobListingEntryType;
use AdAstra\Models\Entry;
use AdAstra\Models\EntryBehavior;
use AdAstra\Models\EntryType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobListingEntryTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_before_update_returns_early_when_status_is_expired(): void
    {
        $type = $this->makeType();
        $entry = Entry::factory()->published()->create();

        // closing_date in payload would normally trigger auto-expire logic,
        // but the explicit 'expired' status should short-circuit it.
        $result = $type->beforeUpdate($entry, [
            'status' => 'expired',
            'fields' => ['closing_date' => now()->subDay()->toDateString()],
        ]);

        $this->assertSame('expired', $result['status']);
    }

    // -------------------------------------------------------------------------
    // beforeUpdate — expired/closed guard
    // -------------------------------------------------------------------------

    private function makeType(): JobListingEntryType
    {
        $record = EntryType::factory()->create(['entry_behavior_id' => EntryBehavior::where('handle', 'job-listing')->value('id')]);
        return new JobListingEntryType($record);
    }

    public function test_before_update_returns_early_when_status_is_closed(): void
    {
        $type = $this->makeType();
        $entry = Entry::factory()->published()->create();

        $result = $type->beforeUpdate($entry, ['status' => 'closed']);

        $this->assertSame('closed', $result['status']);
    }

    // -------------------------------------------------------------------------
    // beforeUpdate — auto-expire on closing_date
    // -------------------------------------------------------------------------

    public function test_before_update_auto_expires_when_closing_date_has_passed(): void
    {
        $type = $this->makeType();
        $entry = Entry::factory()->published()->create();

        $result = $type->beforeUpdate($entry, [
            'fields' => ['closing_date' => now()->subDay()->toDateString()],
        ]);

        $this->assertSame('expired', $result['status']);
    }

    public function test_before_update_does_not_expire_when_closing_date_is_future(): void
    {
        $type = $this->makeType();
        $entry = Entry::factory()->published()->create();

        $result = $type->beforeUpdate($entry, [
            'fields' => ['closing_date' => now()->addDay()->toDateString()],
        ]);

        $this->assertArrayNotHasKey('status', $result);
    }

    public function test_before_update_does_not_expire_when_no_closing_date(): void
    {
        $type = $this->makeType();
        $entry = Entry::factory()->published()->create();

        $result = $type->beforeUpdate($entry, ['title' => 'Updated Title']);

        $this->assertArrayNotHasKey('status', $result);
    }

    // -------------------------------------------------------------------------
    // validate()
    // -------------------------------------------------------------------------

    public function test_validate_returns_error_when_publishing_with_no_application_route(): void
    {
        $type = $this->makeType();

        $errors = $type->validate(['status' => 'published', 'fields' => []]);

        $this->assertArrayHasKey('application_url', $errors);
    }

    public function test_validate_passes_when_application_url_is_provided(): void
    {
        $type = $this->makeType();

        $errors = $type->validate([
            'status' => 'published',
            'fields' => ['application_url' => 'https://example.com/apply'],
        ]);

        $this->assertEmpty($errors);
    }

    public function test_validate_passes_when_application_email_is_provided(): void
    {
        $type = $this->makeType();

        $errors = $type->validate([
            'status' => 'published',
            'fields' => ['application_email' => 'hr@example.com'],
        ]);

        $this->assertEmpty($errors);
    }

    public function test_validate_passes_when_status_is_draft(): void
    {
        $type = $this->makeType();

        $errors = $type->validate(['status' => 'draft', 'fields' => []]);

        $this->assertEmpty($errors);
    }
}
