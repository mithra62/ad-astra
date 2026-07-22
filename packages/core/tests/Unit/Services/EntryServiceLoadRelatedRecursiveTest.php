<?php

namespace Tests\Unit\Services;

use AdAstra\Models\Entry;
use AdAstra\Models\EntryRelationship;
use AdAstra\Models\Field;
use AdAstra\Services\EntryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers EntryService::loadRelatedRecursive(): depth limiting, cycle
 * detection, per-field filtering, sort ordering, and deduplication.
 */
class EntryServiceLoadRelatedRecursiveTest extends TestCase
{
    use RefreshDatabase;

    private EntryService $service;

    private Field $field;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(EntryService::class);
        $this->field = Field::factory()->create(['handle' => 'related-entries']);
    }

    private function relate(Entry $from, Entry $to, int $sortOrder = 0, ?Field $field = null): void
    {
        EntryRelationship::create([
            'entry_id' => $from->id,
            'related_entry_id' => $to->id,
            'field_id' => ($field ?? $this->field)->id,
            'sort_order' => $sortOrder,
        ]);
    }

    public function test_returns_direct_relations_in_sort_order(): void
    {
        [$a, $b, $c] = Entry::factory()->count(3)->create();
        $this->relate($a, $c, sortOrder: 2);
        $this->relate($a, $b, sortOrder: 1);

        $result = $this->service->loadRelatedRecursive($a, 'related-entries');

        $this->assertSame([$b->id, $c->id], $result->pluck('id')->all());
    }

    public function test_returns_empty_collection_when_entry_has_no_relations(): void
    {
        $entry = Entry::factory()->create();

        $this->assertTrue($this->service->loadRelatedRecursive($entry, 'related-entries')->isEmpty());
    }

    public function test_recurses_through_nested_relations(): void
    {
        [$a, $b, $c] = Entry::factory()->count(3)->create();
        $this->relate($a, $b);
        $this->relate($b, $c);

        $result = $this->service->loadRelatedRecursive($a, 'related-entries');

        $this->assertSame([$b->id, $c->id], $result->pluck('id')->all());
    }

    public function test_max_depth_limits_recursion(): void
    {
        [$a, $b, $c, $d] = Entry::factory()->count(4)->create();
        $this->relate($a, $b);
        $this->relate($b, $c);
        $this->relate($c, $d);

        $result = $this->service->loadRelatedRecursive($a, 'related-entries', maxDepth: 2);

        $this->assertSame([$b->id, $c->id], $result->pluck('id')->all());
    }

    public function test_zero_max_depth_returns_nothing(): void
    {
        [$a, $b] = Entry::factory()->count(2)->create();
        $this->relate($a, $b);

        $result = $this->service->loadRelatedRecursive($a, 'related-entries', maxDepth: 0);

        $this->assertTrue($result->isEmpty());
    }

    public function test_cycles_terminate_and_do_not_include_the_root(): void
    {
        [$a, $b, $c] = Entry::factory()->count(3)->create();
        $this->relate($a, $b);
        $this->relate($b, $c);
        $this->relate($c, $a); // cycle back to the root

        $result = $this->service->loadRelatedRecursive($a, 'related-entries', maxDepth: 10);

        $this->assertSame([$b->id, $c->id], $result->pluck('id')->all());
    }

    public function test_self_referencing_entry_does_not_loop(): void
    {
        $a = Entry::factory()->create();
        $this->relate($a, $a);

        $result = $this->service->loadRelatedRecursive($a, 'related-entries', maxDepth: 10);

        $this->assertTrue($result->isEmpty());
    }

    public function test_shared_relations_are_deduplicated(): void
    {
        [$a, $b, $c] = Entry::factory()->count(3)->create();
        $this->relate($a, $b, sortOrder: 1);
        $this->relate($a, $c, sortOrder: 2);
        $this->relate($b, $c); // C reachable both directly and through B

        $result = $this->service->loadRelatedRecursive($a, 'related-entries');

        $this->assertSame([$b->id, $c->id], $result->pluck('id')->all());
    }

    public function test_only_the_requested_field_handle_is_traversed(): void
    {
        $otherField = Field::factory()->create([
            'handle' => 'see-also',
            'field_type_id' => $this->field->field_type_id,
        ]);
        [$a, $b, $c] = Entry::factory()->count(3)->create();
        $this->relate($a, $b);
        $this->relate($a, $c, field: $otherField);

        $result = $this->service->loadRelatedRecursive($a, 'related-entries');

        $this->assertSame([$b->id], $result->pluck('id')->all());
    }
}
