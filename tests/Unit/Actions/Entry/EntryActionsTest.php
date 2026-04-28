<?php

namespace Tests\Unit\Actions\Entry;

use App\Actions\Entry\CreateNewEntry;
use App\Actions\Entry\UpdateEntry;
use App\Facades\Content;
use App\Models\Entry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntryActionsTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // CreateNewEntry
    // -------------------------------------------------------------------------

    public function test_create_delegates_to_content_facade(): void
    {
        $entry = Entry::factory()->create();
        Content::shouldReceive('create')
            ->once()
            ->with('blog-post', ['type_handle' => 'blog-post', 'title' => 'Hello'])
            ->andReturn($entry);

        $action = app(CreateNewEntry::class);
        $result = $action->create(['type_handle' => 'blog-post', 'title' => 'Hello']);

        $this->assertSame($entry, $result);
    }

    public function test_create_returns_entry_instance(): void
    {
        $entry = Entry::factory()->create();
        Content::shouldReceive('create')->once()->andReturn($entry);

        $action = app(CreateNewEntry::class);
        $result = $action->create(['type_handle' => 'blog-post']);

        $this->assertInstanceOf(Entry::class, $result);
    }

    public function test_create_passes_type_handle_extracted_from_input(): void
    {
        $entry = Entry::factory()->create();
        Content::shouldReceive('create')
            ->once()
            ->with('news-article', \Mockery::any())
            ->andReturn($entry);

        $action = app(CreateNewEntry::class);
        $action->create(['type_handle' => 'news-article', 'title' => 'Big News']);
    }

    // -------------------------------------------------------------------------
    // UpdateEntry
    // -------------------------------------------------------------------------

    public function test_update_delegates_to_content_facade(): void
    {
        $entry = Entry::factory()->create();
        $updated = Entry::factory()->create();
        Content::shouldReceive('update')
            ->once()
            ->with($entry, ['title' => 'New Title'])
            ->andReturn($updated);

        $action = app(UpdateEntry::class);
        $result = $action->update($entry, ['title' => 'New Title']);

        $this->assertSame($updated, $result);
    }

    public function test_update_returns_entry_instance(): void
    {
        $entry = Entry::factory()->create();
        $updated = Entry::factory()->create();
        Content::shouldReceive('update')->once()->andReturn($updated);

        $action = app(UpdateEntry::class);
        $result = $action->update($entry, []);

        $this->assertInstanceOf(Entry::class, $result);
    }

    public function test_update_passes_entry_and_input_to_facade(): void
    {
        $entry = Entry::factory()->create();
        Content::shouldReceive('update')
            ->once()
            ->with($entry, ['title' => 'Updated', 'status' => 'published'])
            ->andReturn($entry);

        $action = app(UpdateEntry::class);
        $action->update($entry, ['title' => 'Updated', 'status' => 'published']);
    }
}
