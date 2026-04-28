<?php

namespace Tests\Unit\EntryTypes;

use App\EntryTypes\JobListingEntryType;
use App\Models\Entry;
use App\Models\EntryType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobListingEntryTypeTest extends TestCase
{
    use RefreshDatabase;

    private function makeType(): JobListingEntryType
    {
        $record = EntryType::factory()->create(['class' => JobListingEntryType::class]);
        return new JobListingEntryType($record);
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
        $date = now()->addDay()->toDateTimeString();

        $result = $type->beforeCreate(['published_at' => $date]);

        $this->assertSame($date, $result['published_at']);
    }

    // -------------------------------------------------------------------------
    // beforeUpdate — expired/closed clearing
    // -------------------------------------------------------------------------

    public function test_before_update_clears_published_at_when_expired(): void
    {
        $type  = $this->makeType();
        $entry = Entry::factory()->published()->create();

        $result = $type->beforeUpdate($entry, ['status' => 'expired']);

        $this->assertNull($result['published_at']);
    }

    public function test_before_update_clears_published_at_when_closed(): void
    {
        $type  = $this->makeType();
        $entry = Entry::factory()->published()->create();

        $result = $type->beforeUpdate($entry, ['status' => 'closed']);

        $this->assertNull($result['published_at']);
    }

    // -------------------------------------------------------------------------
    // beforeUpdate — auto-expire on closing_date
    // -------------------------------------------------------------------------

    public function test_before_update_auto_expires_when_closing_date_has_passed(): void
    {
        $type  = $this->makeType();
        $entry = Entry::factory()->published()->create();

        $result = $type->beforeUpdate($entry, [
            'fields' => ['closing_date' => now()->subDay()->toDateString()],
        ]);

        $this->assertSame('expired', $result['status']);
        $this->assertNull($result['published_at']);
    }

    public function test_before_update_does_not_expire_when_closing_date_is_future(): void
    {
        $type  = $this->makeType();
        $entry = Entry::factory()->published()->create();

        $result = $type->beforeUpdate($entry, [
            'fields' => ['closing_date' => now()->addDay()->toDateString()],
        ]);

        $this->assertArrayNotHasKey('status', $result);
    }

    public function test_before_update_does_not_expire_when_no_closing_date(): void
    {
        $type  = $this->makeType();
        $entry = Entry::factory()->published()->create();

        $result = $type->beforeUpdate($entry, ['title' => 'Updated Title']);

        $this->assertArrayNotHasKey('status', $result);
    }

    public function test_before_update_explicit_status_takes_precedence_over_auto_expire(): void
    {
        // If the caller explicitly sets 'expired' or 'closed', the early return
        // fires and closing_date logic is skipped — published_at is still cleared.
        $type  = $this->makeType();
        $entry = Entry::factory()->published()->create();

        $result = $type->beforeUpdate($entry, [
            'status' => 'closed',
            'fields' => ['closing_date' => now()->subDay()->toDateString()],
        ]);

        $this->assertSame('closed', $result['status']);
        $this->assertNull($result['published_at']);
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
