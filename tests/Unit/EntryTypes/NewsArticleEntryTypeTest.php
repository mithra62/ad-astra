<?php

namespace Tests\Unit\EntryTypes;

use App\EntryTypes\NewsArticleEntryType;
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
