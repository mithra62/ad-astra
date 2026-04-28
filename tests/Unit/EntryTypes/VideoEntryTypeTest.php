<?php

namespace Tests\Unit\EntryTypes;

use App\EntryTypes\VideoEntryType;
use App\Models\EntryType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VideoEntryTypeTest extends TestCase
{
    use RefreshDatabase;

    private function makeType(): VideoEntryType
    {
        $record = EntryType::factory()->create(['class' => VideoEntryType::class]);
        return new VideoEntryType($record);
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
    // validate()
    // -------------------------------------------------------------------------

    public function test_validate_returns_error_when_publishing_with_no_platform_id_or_video_url(): void
    {
        $type = $this->makeType();

        $errors = $type->validate([
            'status' => 'published',
            'fields' => [],
        ]);

        $this->assertArrayHasKey('platform_id', $errors);
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
