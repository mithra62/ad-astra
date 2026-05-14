<?php

namespace Tests\Unit\Actions\Entry;

use App\Actions\Entry\RecordEntryMetric;
use App\Models\Entry;
use App\Models\EntryMetric;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Integration tests for RecordEntryMetric.
 *
 * These exercise the real upsert / accumulation logic in EntryService::recordMetric()
 * rather than mocking the facade.  The unit-mock tests live in EntryActionsTest.
 */
class RecordEntryMetricTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Return type and basic persistence
    // -------------------------------------------------------------------------

    public function test_record_returns_entry_metric_instance(): void
    {
        $entry = Entry::factory()->create();
        $action = app(RecordEntryMetric::class);

        $result = $action->record($entry, 'views');

        $this->assertInstanceOf(EntryMetric::class, $result);
    }

    public function test_record_persists_metric_row_to_database(): void
    {
        $entry = Entry::factory()->create();
        $action = app(RecordEntryMetric::class);

        $action->record($entry, 'views');

        $this->assertDatabaseHas('entry_metrics', [
            'entry_id' => $entry->id,
            'metric' => 'views',
        ]);
    }

    public function test_record_associates_metric_with_correct_entry(): void
    {
        $entry = Entry::factory()->create();
        $action = app(RecordEntryMetric::class);

        $result = $action->record($entry, 'views');

        $this->assertSame($entry->id, $result->entry_id);
    }

    // -------------------------------------------------------------------------
    // Default value and custom value
    // -------------------------------------------------------------------------

    public function test_record_defaults_to_value_of_one(): void
    {
        $entry = Entry::factory()->create();
        $action = app(RecordEntryMetric::class);

        $result = $action->record($entry, 'views');

        $this->assertSame(1, $result->value);
    }

    public function test_record_stores_custom_value(): void
    {
        $entry = Entry::factory()->create();
        $action = app(RecordEntryMetric::class);

        $result = $action->record($entry, 'downloads', 42);

        $this->assertSame(42, $result->value);
    }

    // -------------------------------------------------------------------------
    // Date handling
    // -------------------------------------------------------------------------

    public function test_record_defaults_to_todays_date(): void
    {
        $entry = Entry::factory()->create();
        $action = app(RecordEntryMetric::class);

        $result = $action->record($entry, 'views');

        $this->assertTrue($result->recorded_date->isSameDay(today()));
    }

    public function test_record_uses_provided_date(): void
    {
        $entry = Entry::factory()->create();
        $action = app(RecordEntryMetric::class);
        $date = Carbon::parse('2024-06-15');

        $result = $action->record($entry, 'views', 1, $date);

        $this->assertSame('2024-06-15', $result->recorded_date->toDateString());
    }

    // -------------------------------------------------------------------------
    // Accumulation (upsert behaviour)
    // -------------------------------------------------------------------------

    public function test_repeated_calls_accumulate_value_for_same_entry_metric_and_date(): void
    {
        $entry = Entry::factory()->create();
        $action = app(RecordEntryMetric::class);
        $date = Carbon::parse('2024-03-01');

        $action->record($entry, 'views', 3, $date);
        $action->record($entry, 'views', 5, $date);
        $result = $action->record($entry, 'views', 2, $date);

        $this->assertSame(10, $result->value);
        $this->assertDatabaseCount('entry_metrics', 1);
    }

    public function test_different_dates_produce_separate_rows(): void
    {
        $entry = Entry::factory()->create();
        $action = app(RecordEntryMetric::class);

        $action->record($entry, 'views', 1, Carbon::parse('2024-01-01'));
        $action->record($entry, 'views', 1, Carbon::parse('2024-01-02'));

        $this->assertDatabaseCount('entry_metrics', 2);
    }

    // -------------------------------------------------------------------------
    // Metric name scoping
    // -------------------------------------------------------------------------

    public function test_different_metric_names_produce_separate_rows(): void
    {
        $entry = Entry::factory()->create();
        $action = app(RecordEntryMetric::class);

        $action->record($entry, 'views');
        $action->record($entry, 'downloads');

        $this->assertDatabaseCount('entry_metrics', 2);
    }

    public function test_accumulation_is_scoped_to_metric_name(): void
    {
        $entry = Entry::factory()->create();
        $action = app(RecordEntryMetric::class);
        $date = Carbon::parse('2024-03-01');

        $action->record($entry, 'views', 10, $date);
        $action->record($entry, 'downloads', 3, $date);

        $views = EntryMetric::where('entry_id', $entry->id)->where('metric', 'views')->first();
        $downloads = EntryMetric::where('entry_id', $entry->id)->where('metric', 'downloads')->first();

        $this->assertSame(10, $views->value);
        $this->assertSame(3, $downloads->value);
    }

    // -------------------------------------------------------------------------
    // Entry scoping
    // -------------------------------------------------------------------------

    public function test_metrics_are_scoped_per_entry(): void
    {
        $entryA = Entry::factory()->create();
        $entryB = Entry::factory()->create();
        $action = app(RecordEntryMetric::class);
        $date = Carbon::parse('2024-03-01');

        $action->record($entryA, 'views', 7, $date);
        $action->record($entryB, 'views', 3, $date);

        $this->assertDatabaseCount('entry_metrics', 2);

        $a = EntryMetric::where('entry_id', $entryA->id)->first();
        $b = EntryMetric::where('entry_id', $entryB->id)->first();

        $this->assertSame(7, $a->value);
        $this->assertSame(3, $b->value);
    }

    public function test_accumulation_on_one_entry_does_not_affect_another(): void
    {
        $entryA = Entry::factory()->create();
        $entryB = Entry::factory()->create();
        $action = app(RecordEntryMetric::class);
        $date = Carbon::parse('2024-03-01');

        $action->record($entryA, 'views', 5, $date);
        $action->record($entryA, 'views', 5, $date);
        $action->record($entryB, 'views', 1, $date);

        $b = EntryMetric::where('entry_id', $entryB->id)->first();
        $this->assertSame(1, $b->value);
    }
}
