<?php

namespace Tests\Unit\Services;

use AdAstra\Models\Entry;
use AdAstra\Models\EntryMetric;
use AdAstra\Services\EntryService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntryServiceMetricTest extends TestCase
{
    use RefreshDatabase;

    private EntryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EntryService::class);
    }

    // -------------------------------------------------------------------------
    // recordMetric() — insert
    // -------------------------------------------------------------------------

    public function test_record_metric_creates_row_on_first_call(): void
    {
        $entry = Entry::factory()->create();

        $metric = $this->service->recordMetric($entry, 'views');

        $this->assertInstanceOf(EntryMetric::class, $metric);
        $this->assertDatabaseHas('entry_metrics', [
            'entry_id' => $entry->id,
            'metric' => 'views',
            'value' => 1,
        ]);
    }

    public function test_record_metric_uses_custom_value(): void
    {
        $entry = Entry::factory()->create();

        $this->service->recordMetric($entry, 'downloads', 50);

        $this->assertDatabaseHas('entry_metrics', [
            'entry_id' => $entry->id,
            'metric' => 'downloads',
            'value' => 50,
        ]);
    }

    public function test_record_metric_defaults_to_today(): void
    {
        $entry = Entry::factory()->create();

        $metric = $this->service->recordMetric($entry, 'views');

        $this->assertSame(today()->toDateString(), $metric->recorded_date->toDateString());
    }

    // -------------------------------------------------------------------------
    // recordMetric() — increment on subsequent calls (same day)
    // -------------------------------------------------------------------------

    public function test_record_metric_increments_on_second_call_same_day(): void
    {
        $entry = Entry::factory()->create();

        $this->service->recordMetric($entry, 'views', 10);
        $metric = $this->service->recordMetric($entry, 'views', 5);

        $this->assertSame(15, $metric->value);
        $this->assertDatabaseCount('entry_metrics', 1);
    }

    public function test_record_metric_increments_by_correct_amount(): void
    {
        $entry = Entry::factory()->create();

        $this->service->recordMetric($entry, 'plays', 100);
        $this->service->recordMetric($entry, 'plays', 200);
        $metric = $this->service->recordMetric($entry, 'plays', 300);

        $this->assertSame(600, $metric->value);
        $this->assertDatabaseCount('entry_metrics', 1);
    }

    // -------------------------------------------------------------------------
    // recordMetric() — backdated writes
    // -------------------------------------------------------------------------

    public function test_record_metric_accepts_custom_date(): void
    {
        $entry = Entry::factory()->create();
        $date = Carbon::parse('2025-01-15');

        $metric = $this->service->recordMetric($entry, 'views', 1, $date);

        $this->assertSame('2025-01-15', $metric->recorded_date->toDateString());
    }

    public function test_record_metric_creates_separate_rows_for_different_dates(): void
    {
        $entry = Entry::factory()->create();

        $this->service->recordMetric($entry, 'views', 10, Carbon::parse('2025-01-01'));
        $this->service->recordMetric($entry, 'views', 20, Carbon::parse('2025-01-02'));

        $this->assertDatabaseCount('entry_metrics', 2);
        // metricTotal($metric, $from) sums all rows where recorded_date >= $from.
        $this->assertSame(30, $entry->metricTotal('views', Carbon::parse('2025-01-01')));
        $this->assertSame(20, $entry->metricTotal('views', Carbon::parse('2025-01-02')));
    }

    public function test_record_metric_increments_correctly_on_backdated_duplicate(): void
    {
        $entry = Entry::factory()->create();
        $date = Carbon::parse('2025-03-01');

        $this->service->recordMetric($entry, 'views', 5, $date);
        $metric = $this->service->recordMetric($entry, 'views', 3, $date);

        $this->assertSame(8, $metric->value);
        $this->assertDatabaseCount('entry_metrics', 1);
    }

    // -------------------------------------------------------------------------
    // recordMetric() — isolation between entries and metrics
    // -------------------------------------------------------------------------

    public function test_record_metric_does_not_affect_other_entries(): void
    {
        $entryA = Entry::factory()->create();
        $entryB = Entry::factory()->create();

        $this->service->recordMetric($entryA, 'views', 100);
        $this->service->recordMetric($entryB, 'views', 1);

        $this->assertSame(100, $entryA->metricTotal('views'));
        $this->assertSame(1, $entryB->metricTotal('views'));
    }

    public function test_record_metric_does_not_affect_other_metric_names(): void
    {
        $entry = Entry::factory()->create();

        $this->service->recordMetric($entry, 'views', 10);
        $this->service->recordMetric($entry, 'downloads', 5);

        $this->assertSame(10, $entry->metricTotal('views'));
        $this->assertSame(5, $entry->metricTotal('downloads'));
        $this->assertDatabaseCount('entry_metrics', 2);
    }

    // -------------------------------------------------------------------------
    // recordMetric() — return value
    // -------------------------------------------------------------------------

    public function test_record_metric_returns_entry_metric_instance(): void
    {
        $entry = Entry::factory()->create();

        $result = $this->service->recordMetric($entry, 'views');

        $this->assertInstanceOf(EntryMetric::class, $result);
        $this->assertSame($entry->id, $result->entry_id);
        $this->assertSame('views', $result->metric);
    }

    public function test_record_metric_returns_fresh_model_after_increment(): void
    {
        $entry = Entry::factory()->create();

        $this->service->recordMetric($entry, 'views', 10);
        $result = $this->service->recordMetric($entry, 'views', 10);

        // The returned model reflects the post-increment value, not the stale one.
        $this->assertSame(20, $result->value);
    }
}
