<?php

namespace Tests\Unit\Models;

use AdAstra\Models\Entry;
use AdAstra\Models\EntryMetric;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntryMetricTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Model basics
    // -------------------------------------------------------------------------

    public function test_entry_metric_can_be_created(): void
    {
        $entry = Entry::factory()->create();
        $metric = EntryMetric::create([
            'entry_id' => $entry->id,
            'metric' => 'views',
            'value' => 100,
            'recorded_date' => today()->toDateString(),
        ]);

        $this->assertDatabaseHas('entry_metrics', [
            'entry_id' => $entry->id,
            'metric' => 'views',
            'value' => 100,
        ]);

        $this->assertInstanceOf(EntryMetric::class, $metric);
    }

    public function test_entry_metric_belongs_to_entry(): void
    {
        $entry = Entry::factory()->create();
        $metric = EntryMetric::factory()->create(['entry_id' => $entry->id]);

        $this->assertEquals($entry->id, $metric->entry->id);
    }

    public function test_value_is_cast_to_integer(): void
    {
        $metric = EntryMetric::factory()->make(['value' => '42']);

        $this->assertSame(42, $metric->value);
    }

    public function test_recorded_date_is_cast_to_date(): void
    {
        $metric = EntryMetric::factory()->make(['recorded_date' => '2026-01-15']);

        $this->assertInstanceOf(Carbon::class, $metric->recorded_date);
        $this->assertSame('2026-01-15', $metric->recorded_date->toDateString());
    }

    // -------------------------------------------------------------------------
    // Entry relationship
    // -------------------------------------------------------------------------

    public function test_entry_has_metrics_relationship(): void
    {
        $entry = Entry::factory()->create();
        EntryMetric::factory()->count(3)->sequence(
            ['recorded_date' => '2026-01-01'],
            ['recorded_date' => '2026-01-02'],
            ['recorded_date' => '2026-01-03'],
        )->create(['entry_id' => $entry->id, 'metric' => 'views']);

        $this->assertCount(3, $entry->metrics);
    }

    public function test_entry_metrics_are_deleted_when_entry_is_deleted(): void
    {
        $entry = Entry::factory()->create();
        EntryMetric::factory()->count(2)->sequence(
            ['metric' => 'views', 'recorded_date' => '2026-01-01'],
            ['metric' => 'downloads', 'recorded_date' => '2026-01-01'],
        )->create(['entry_id' => $entry->id]);

        $entry->delete();

        $this->assertDatabaseMissing('entry_metrics', ['entry_id' => $entry->id]);
    }

    // -------------------------------------------------------------------------
    // Entry::metricTotal()
    // -------------------------------------------------------------------------

    public function test_metric_total_sums_values_for_named_metric(): void
    {
        $entry = Entry::factory()->create();
        EntryMetric::factory()->create(['entry_id' => $entry->id, 'metric' => 'views', 'value' => 100, 'recorded_date' => '2026-01-01']);
        EntryMetric::factory()->create(['entry_id' => $entry->id, 'metric' => 'views', 'value' => 200, 'recorded_date' => '2026-01-02']);
        EntryMetric::factory()->create(['entry_id' => $entry->id, 'metric' => 'downloads', 'value' => 50, 'recorded_date' => '2026-01-01']);

        $this->assertSame(300, $entry->metricTotal('views'));
        $this->assertSame(50, $entry->metricTotal('downloads'));
    }

    public function test_metric_total_returns_zero_when_no_records(): void
    {
        $entry = Entry::factory()->create();

        $this->assertSame(0, $entry->metricTotal('views'));
    }

    public function test_metric_total_filters_by_from_date(): void
    {
        $entry = Entry::factory()->create();
        EntryMetric::factory()->create(['entry_id' => $entry->id, 'metric' => 'views', 'value' => 100, 'recorded_date' => '2026-01-01']);
        EntryMetric::factory()->create(['entry_id' => $entry->id, 'metric' => 'views', 'value' => 200, 'recorded_date' => '2026-02-01']);
        EntryMetric::factory()->create(['entry_id' => $entry->id, 'metric' => 'views', 'value' => 300, 'recorded_date' => '2026-03-01']);

        $from = Carbon::parse('2026-02-01');

        $this->assertSame(500, $entry->metricTotal('views', $from));
    }

    public function test_metric_total_does_not_mix_metrics_across_different_entries(): void
    {
        $entryA = Entry::factory()->create();
        $entryB = Entry::factory()->create();

        EntryMetric::factory()->create(['entry_id' => $entryA->id, 'metric' => 'views', 'value' => 999, 'recorded_date' => today()]);
        EntryMetric::factory()->create(['entry_id' => $entryB->id, 'metric' => 'views', 'value' => 1, 'recorded_date' => today()]);

        $this->assertSame(999, $entryA->metricTotal('views'));
        $this->assertSame(1, $entryB->metricTotal('views'));
    }

    // -------------------------------------------------------------------------
    // Unique constraint
    // -------------------------------------------------------------------------

    public function test_unique_constraint_prevents_duplicate_metric_per_day(): void
    {
        $entry = Entry::factory()->create();

        EntryMetric::create([
            'entry_id' => $entry->id,
            'metric' => 'views',
            'value' => 10,
            'recorded_date' => '2026-01-01',
        ]);

        $this->expectException(QueryException::class);

        EntryMetric::create([
            'entry_id' => $entry->id,
            'metric' => 'views',
            'value' => 20,
            'recorded_date' => '2026-01-01',
        ]);
    }
}
