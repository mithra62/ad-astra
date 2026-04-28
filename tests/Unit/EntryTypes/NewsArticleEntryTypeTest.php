<?php

namespace Tests\Unit\EntryTypes;

use App\EntryTypes\NewsArticleEntryType;
use App\Models\Entry;
use App\Models\EntryType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsArticleEntryTypeTest extends TestCase
{
    use RefreshDatabase;

    private function makeType(): NewsArticleEntryType
    {
        $record = EntryType::factory()->create(['class' => NewsArticleEntryType::class]);
        return new NewsArticleEntryType($record);
    }

    // -------------------------------------------------------------------------
    // beforeCreate
    // -------------------------------------------------------------------------

    public function test_before_create_stamps_published_at_when_published(): void
    {
        $type = $this->makeType();

        $result = $type->beforeCreate(['status' => 'published']);

        $this->assertNotNull($result['published_at']);
    }

    public function test_before_create_does_not_stamp_when_draft(): void
    {
        $type = $this->makeType();

        $result = $type->beforeCreate(['status' => 'draft']);

        $this->assertArrayNotHasKey('published_at', $result);
    }

    public function test_before_create_does_not_overwrite_explicit_published_at(): void
    {
        $type = $this->makeType();
        $date = now()->subDay()->toDateTimeString();

        $result = $type->beforeCreate(['status' => 'published', 'published_at' => $date]);

        $this->assertSame($date, $result['published_at']);
    }

    // -------------------------------------------------------------------------
    // beforeUpdate
    // -------------------------------------------------------------------------

    public function test_before_update_stamps_published_at_on_first_publish(): void
    {
        $type  = $this->makeType();
        $entry = Entry::factory()->create(['published_at' => null]);

        $result = $type->beforeUpdate($entry, ['status' => 'published']);

        $this->assertNotNull($result['published_at']);
    }

    public function test_before_update_does_not_overwrite_existing_published_at(): void
    {
        $type  = $this->makeType();
        $entry = Entry::factory()->published()->create();

        $result = $type->beforeUpdate($entry, ['status' => 'published']);

        $this->assertArrayNotHasKey('published_at', $result);
    }

    // -------------------------------------------------------------------------
    // validate()
    // -------------------------------------------------------------------------

    public function test_validate_returns_error_when_source_url_set_without_source(): void
    {
        $type = $this->makeType();

        $errors = $type->validate([
            'fields' => [
                'source_url' => 'https://reuters.com/article/123',
                'source'     => '',
            ],
        ]);

        $this->assertArrayHasKey('source', $errors);
    }

    public function test_validate_passes_when_both_source_and_url_provided(): void
    {
        $type = $this->makeType();

        $errors = $type->validate([
            'fields' => [
                'source_url' => 'https://reuters.com/article/123',
                'source'     => 'Reuters',
            ],
        ]);

        $this->assertEmpty($errors);
    }

    public function test_validate_passes_when_neither_source_nor_url_provided(): void
    {
        $type = $this->makeType();

        $errors = $type->validate(['fields' => []]);

        $this->assertEmpty($errors);
    }

    public function test_validate_passes_when_source_set_without_url(): void
    {
        $type = $this->makeType();

        $errors = $type->validate(['fields' => ['source' => 'Reuters']]);

        $this->assertEmpty($errors);
    }
}
