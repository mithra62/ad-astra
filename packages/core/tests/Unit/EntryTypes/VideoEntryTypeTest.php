<?php

namespace Tests\Unit\EntryTypes;

use AdAstra\EntryTypes\VideoEntryType;
use AdAstra\Models\EntryBehavior;
use AdAstra\Models\EntryType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VideoEntryTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_validate_returns_error_when_publishing_with_no_platform_id_or_video_url(): void
    {
        $type = $this->makeType();

        $errors = $type->validate([
            'status' => 'published',
            'fields' => [],
        ]);

        $this->assertArrayHasKey('platform_id', $errors);
    }

    // -------------------------------------------------------------------------
    // validate()
    // -------------------------------------------------------------------------

    private function makeType(): VideoEntryType
    {
        $record = EntryType::factory()->create(['entry_behavior_id' => EntryBehavior::where('handle', 'video')->value('id')]);
        return new VideoEntryType($record);
    }

    public function test_validate_passes_when_platform_id_is_provided(): void
    {
        $type = $this->makeType();

        $errors = $type->validate([
            'status' => 'published',
            'fields' => ['platform_id' => 'dQw4w9WgXcQ'],
        ]);

        $this->assertEmpty($errors);
    }

    public function test_validate_passes_when_video_url_is_provided(): void
    {
        $type = $this->makeType();

        $errors = $type->validate([
            'status' => 'published',
            'fields' => ['video_url' => 'https://example.com/video.mp4'],
        ]);

        $this->assertEmpty($errors);
    }

    public function test_validate_passes_when_status_is_draft_with_no_video(): void
    {
        $type = $this->makeType();

        $errors = $type->validate(['status' => 'draft', 'fields' => []]);

        $this->assertEmpty($errors);
    }
}
