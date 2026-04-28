<?php

namespace Tests\Unit\Services\SiteRouting\RouteDrivers;

use App\Models\Entry;
use App\Models\EntryGroup;
use App\Models\EntryTree;
use App\Models\EntryType;
use App\Services\SiteRouting\RouteDrivers\EntryTreeRouteDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntryTreeRouteDriverTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolve_returns_route_result_for_published_entry_tree_node(): void
    {
        $entry = $this->makePublishedTreeEntry([
            'title' => 'About',
        ], [
            'default_template' => 'entries.page',
        ]);

        $node = EntryTree::create([
            'entry_id' => $entry->id,
            'parent_id' => null,
            'handle' => 'about',
            'uri' => 'about',
            'depth' => 0,
            'sort_order' => 1,
            'template' => null,
            'is_home' => false,
        ]);

        $result = app(EntryTreeRouteDriver::class)->resolve('/about/');

        $this->assertNotNull($result);
        $this->assertSame('entry_tree', $result->type);
        $this->assertSame('entries.page', $result->template);
        $this->assertSame($node->id, $result->resource->id);
        $this->assertSame($entry->id, $result->data['entry']->id);
        $this->assertSame($entry->entryType->id, $result->data['entryType']->id);
    }

    protected function makePublishedTreeEntry(array $entryOverrides = [], array $typeOverrides = []): Entry
    {
        $group = EntryGroup::factory()->create();
        $type = EntryType::factory()->for($group)->create(array_merge([
            'has_entry_tree' => true,
            'default_template' => 'entries.show',
        ], $typeOverrides));

        return Entry::factory()
            ->for($group)
            ->for($type)
            ->create(array_merge([
                'status_handle' => 'published',
                'status_is_public' => true,
                'published_at' => now()->subHour(),
            ], $entryOverrides));
    }

    public function test_resolve_does_not_return_scheduled_entries(): void
    {
        $entry = $this->makePublishedTreeEntry([
            'status_handle' => 'published',
            'status_is_public' => true,
            'published_at' => now()->addDay(),
        ]);

        EntryTree::create([
            'entry_id' => $entry->id,
            'parent_id' => null,
            'handle' => 'future-entry',
            'uri' => 'future-entry',
            'depth' => 0,
            'sort_order' => 1,
            'template' => null,
            'is_home' => false,
        ]);

        $result = app(EntryTreeRouteDriver::class)->resolve('future-entry');

        $this->assertNull($result);
    }
}
