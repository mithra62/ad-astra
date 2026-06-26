<?php

namespace Tests\Unit\Actions\Entry;

use App\Actions\Entry\CreateNewEntry;
use App\Actions\Entry\RecordEntryMetric;
use App\Actions\Entry\UpdateEntry;
use App\Facades\Content;
use App\Facades\Entries;
use App\Models\Entry;
use App\Models\EntryMetric;
use App\Models\EntryType;
use Carbon\Carbon;
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
        $typeRecord = EntryType::factory()->create(['handle' => 'blog-post']);
        $entry = Entry::factory()->create();
        Content::shouldReceive('create')
            ->once()
            ->with('blog-post', ['type_handle' => 'blog-post', 'title' => 'Hello', 'entry_group_id' => $typeRecord->entry_group_id])
            ->andReturn($entry);

        $action = app(CreateNewEntry::class);
        $result = $action->create(['type_handle' => 'blog-post', 'title' => 'Hello', 'entry_group_id' => $typeRecord->entry_group_id]);

        $this->assertSame($entry, $result);
    }

    public function test_create_returns_entry_instance(): void
    {
        $typeRecord = EntryType::factory()->create(['handle' => 'blog-post']);
        $entry = Entry::factory()->create();
        Content::shouldReceive('create')->once()->andReturn($entry);

        $action = app(CreateNewEntry::class);
        $result = $action->create(['type_handle' => 'blog-post', 'entry_group_id' => $typeRecord->entry_group_id]);

        $this->assertInstanceOf(Entry::class, $result);
    }

    public function test_create_passes_type_handle_extracted_from_input(): void
    {
        $typeRecord = EntryType::factory()->create(['handle' => 'news-article']);
        $entry = Entry::factory()->create();
        Content::shouldReceive('create')
            ->once()
            ->with('news-article', \Mockery::any())
            ->andReturn($entry);

        $action = app(CreateNewEntry::class);
        $action->create(['type_handle' => 'news-article', 'title' => 'Big News', 'entry_group_id' => $typeRecord->entry_group_id]);
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

    // -------------------------------------------------------------------------
    // RecordEntryMetric
    // -------------------------------------------------------------------------

    public function test_record_delegates_to_entries_facade(): void
    {
        $entry = Entry::factory()->create();
        $metric = EntryMetric::factory()->create(['entry_id' => $entry->id]);

        Entries::shouldReceive('recordMetric')
            ->once()
            ->with($entry, 'views', 1, null)
            ->andReturn($metric);

        $action = app(RecordEntryMetric::class);
        $result = $action->record($entry, 'views');

        $this->assertSame($metric, $result);
    }

    public function test_record_returns_entry_metric_instance(): void
    {
        $entry = Entry::factory()->create();
        $metric = EntryMetric::factory()->create(['entry_id' => $entry->id]);

        Entries::shouldReceive('recordMetric')->once()->andReturn($metric);

        $action = app(RecordEntryMetric::class);
        $result = $action->record($entry, 'views');

        $this->assertInstanceOf(EntryMetric::class, $result);
    }

    public function test_record_passes_custom_value_to_facade(): void
    {
        $entry = Entry::factory()->create();
        $metric = EntryMetric::factory()->create(['entry_id' => $entry->id]);

        Entries::shouldReceive('recordMetric')
            ->once()
            ->with($entry, 'downloads', 5, null)
            ->andReturn($metric);

        $action = app(RecordEntryMetric::class);
        $action->record($entry, 'downloads', 5);
    }

    public function test_record_passes_custom_date_to_facade(): void
    {
        $entry = Entry::factory()->create();
        $metric = EntryMetric::factory()->create(['entry_id' => $entry->id]);
        $date = Carbon::parse('2025-01-15');

        Entries::shouldReceive('recordMetric')
            ->once()
            ->with($entry, 'views', 1, $date)
            ->andReturn($metric);

        $action = app(RecordEntryMetric::class);
        $action->record($entry, 'views', 1, $date);
    }
}
