<?php

namespace Tests\Unit\Actions\Entry\Tree;

use AdAstra\Actions\Entry\Tree\CreateEntryTreeNode;
use AdAstra\Actions\Entry\Tree\MoveEntryTreeNode;
use AdAstra\Actions\Entry\Tree\RebuildEntryTreeUri;
use AdAstra\Models\Entry;
use AdAstra\Models\EntryGroup;
use AdAstra\Models\EntryTree;
use AdAstra\Models\EntryType;
use AdAstra\Services\EntryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Delegation coverage for the deprecated Actions\Entry\Tree\* wrappers, which
 * forward to EntryService. Tree behavior itself is covered in
 * Tests\Unit\Services\EntryTreeServiceTest.
 */
class EntryTreeActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function makeTreeEntry(array $entryOverrides = [], array $typeOverrides = []): Entry
    {
        $group = EntryGroup::factory()->create();
        $type = EntryType::factory()->for($group)->create(array_merge([
            'has_entry_tree' => true,
            'default_template' => 'entries.page',
        ], $typeOverrides));

        return Entry::factory()
            ->for($group)
            ->for($type)
            ->create($entryOverrides);
    }

    // -------------------------------------------------------------------------
    // CreateEntryTreeNode action wrapper — delegation
    // -------------------------------------------------------------------------

    public function test_create_tree_node_action_delegates_to_service(): void
    {
        $entry = $this->makeTreeEntry();
        $node = EntryTree::factory()->create(['entry_id' => $entry->id]);
        $service = $this->mock(EntryService::class);
        $service->shouldReceive('createTreeNode')
            ->once()
            ->with($entry, 'About Us', null, null, false)
            ->andReturn($node);

        $result = app(CreateEntryTreeNode::class)->create($entry, 'About Us');

        $this->assertSame($node, $result);
    }

    public function test_create_tree_node_action_passes_parent_template_and_home_flag(): void
    {
        $entry = $this->makeTreeEntry();
        $parent = EntryTree::factory()->create(['entry_id' => $this->makeTreeEntry()->id]);
        $node = EntryTree::factory()->create(['entry_id' => $entry->id]);
        $service = $this->mock(EntryService::class);
        $service->shouldReceive('createTreeNode')
            ->once()
            ->with($entry, 'Child', $parent, 'entries.show', false)
            ->andReturn($node);

        $result = app(CreateEntryTreeNode::class)->create($entry, 'Child', $parent, 'entries.show', false);

        $this->assertSame($node, $result);
    }

    public function test_create_tree_node_action_returns_entry_tree_instance(): void
    {
        $entry = $this->makeTreeEntry();
        $node = EntryTree::factory()->create(['entry_id' => $entry->id]);
        $service = $this->mock(EntryService::class);
        $service->shouldReceive('createTreeNode')->once()->andReturn($node);

        $result = app(CreateEntryTreeNode::class)->create($entry, 'Page');

        $this->assertInstanceOf(EntryTree::class, $result);
    }

    // -------------------------------------------------------------------------
    // MoveEntryTreeNode action wrapper — delegation
    // -------------------------------------------------------------------------

    public function test_move_tree_node_action_delegates_to_service(): void
    {
        $node = EntryTree::factory()->create(['entry_id' => $this->makeTreeEntry()->id]);
        $parent = EntryTree::factory()->create(['entry_id' => $this->makeTreeEntry()->id]);
        $moved = EntryTree::factory()->create(['entry_id' => $this->makeTreeEntry()->id]);
        $service = $this->mock(EntryService::class);
        $service->shouldReceive('moveTreeNode')
            ->once()
            ->with($node, $parent, 2)
            ->andReturn($moved);

        $result = app(MoveEntryTreeNode::class)->handle($node, $parent, 2);

        $this->assertSame($moved, $result);
    }

    public function test_move_tree_node_action_returns_entry_tree_instance(): void
    {
        $node = EntryTree::factory()->create(['entry_id' => $this->makeTreeEntry()->id]);
        $moved = EntryTree::factory()->create(['entry_id' => $this->makeTreeEntry()->id]);
        $service = $this->mock(EntryService::class);
        $service->shouldReceive('moveTreeNode')->once()->andReturn($moved);

        $result = app(MoveEntryTreeNode::class)->handle($node, null, 0);

        $this->assertInstanceOf(EntryTree::class, $result);
    }

    // -------------------------------------------------------------------------
    // RebuildEntryTreeUri action wrapper — delegation
    // -------------------------------------------------------------------------

    public function test_rebuild_tree_uri_action_delegates_to_service(): void
    {
        $node = EntryTree::factory()->create(['entry_id' => $this->makeTreeEntry()->id]);
        $service = $this->mock(EntryService::class);
        $service->shouldReceive('rebuildTreeUri')
            ->once()
            ->with($node);

        app(RebuildEntryTreeUri::class)->handle($node);
    }

    public function test_rebuild_tree_uri_action_returns_void(): void
    {
        $node = EntryTree::factory()->create(['entry_id' => $this->makeTreeEntry()->id]);
        $service = $this->mock(EntryService::class);
        $service->shouldReceive('rebuildTreeUri')->once();

        $result = app(RebuildEntryTreeUri::class)->handle($node);

        $this->assertNull($result);
    }
}
