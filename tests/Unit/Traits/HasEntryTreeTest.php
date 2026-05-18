<?php

namespace Tests\Unit\Traits;

use App\Models\Entry;
use App\Models\EntryGroup;
use App\Models\EntryTree;
use App\Models\EntryType;
use App\Services\EntryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HasEntryTreeTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // treeUrl()
    // -------------------------------------------------------------------------

    public function test_tree_url_returns_null_when_entry_has_no_tree_node(): void
    {
        $entry = $this->makeTreeEntry();

        $this->assertNull($entry->treeUrl());
    }

    public function test_tree_url_returns_full_url_for_root_node(): void
    {
        $service = app(EntryService::class);
        $entry = $this->makeTreeEntry(['handle' => 'about']);
        $service->createTreeNode($entry, 'about');

        $url = $entry->fresh()->treeUrl();

        $this->assertNotNull($url);
        $this->assertStringEndsWith('/about', $url);
    }

    public function test_tree_url_returns_full_url_for_nested_node(): void
    {
        $service = app(EntryService::class);
        $parent = $this->makeTreeEntry(['handle' => 'about']);
        $child = $this->makeTreeEntry(['handle' => 'team']);

        $parentNode = $service->createTreeNode($parent, 'about');
        $service->createTreeNode($child, 'team', $parentNode);

        $url = $child->fresh()->treeUrl();

        $this->assertNotNull($url);
        $this->assertStringEndsWith('/about/team', $url);
    }

    // -------------------------------------------------------------------------
    // treeParent()
    // -------------------------------------------------------------------------

    public function test_tree_parent_returns_null_when_entry_has_no_tree_node(): void
    {
        $entry = $this->makeTreeEntry();

        $this->assertNull($entry->treeParent());
    }

    public function test_tree_parent_returns_null_for_root_node(): void
    {
        $service = app(EntryService::class);
        $entry = $this->makeTreeEntry(['handle' => 'root']);
        $service->createTreeNode($entry, 'root');

        $this->assertNull($entry->fresh()->treeParent());
    }

    public function test_tree_parent_returns_parent_entry(): void
    {
        $service = app(EntryService::class);
        $parent = $this->makeTreeEntry(['handle' => 'parent']);
        $child = $this->makeTreeEntry(['handle' => 'child']);

        $parentNode = $service->createTreeNode($parent, 'parent');
        $service->createTreeNode($child, 'child', $parentNode);

        $result = $child->fresh()->treeParent();

        $this->assertInstanceOf(Entry::class, $result);
        $this->assertSame($parent->id, $result->id);
    }

    // -------------------------------------------------------------------------
    // treeChildren()
    // -------------------------------------------------------------------------

    public function test_tree_children_returns_empty_collection_when_no_tree_node(): void
    {
        $entry = $this->makeTreeEntry();

        $this->assertCount(0, $entry->treeChildren());
    }

    public function test_tree_children_returns_empty_collection_for_leaf_node(): void
    {
        $service = app(EntryService::class);
        $entry = $this->makeTreeEntry(['handle' => 'leaf']);
        $service->createTreeNode($entry, 'leaf');

        $this->assertCount(0, $entry->fresh()->treeChildren());
    }

    public function test_tree_children_returns_direct_children_only(): void
    {
        $service = app(EntryService::class);
        $parent = $this->makeTreeEntry(['handle' => 'parent']);
        $child1 = $this->makeTreeEntry(['handle' => 'child-one']);
        $child2 = $this->makeTreeEntry(['handle' => 'child-two']);
        $grandchild = $this->makeTreeEntry(['handle' => 'grandchild']);

        $parentNode = $service->createTreeNode($parent, 'parent');
        $c1Node = $service->createTreeNode($child1, 'child-one', $parentNode);
        $service->createTreeNode($child2, 'child-two', $parentNode);
        $service->createTreeNode($grandchild, 'grandchild', $c1Node);

        $children = $parent->fresh()->treeChildren();

        $this->assertCount(2, $children);
        $childIds = $children->pluck('id')->sort()->values()->toArray();
        $this->assertSame(
            collect([$child1->id, $child2->id])->sort()->values()->toArray(),
            $childIds
        );
    }

    // -------------------------------------------------------------------------
    // treeAncestors()
    // -------------------------------------------------------------------------

    public function test_tree_ancestors_returns_empty_collection_when_no_tree_node(): void
    {
        $entry = $this->makeTreeEntry();

        $this->assertCount(0, $entry->treeAncestors());
    }

    public function test_tree_ancestors_returns_empty_collection_for_root_node(): void
    {
        $service = app(EntryService::class);
        $entry = $this->makeTreeEntry(['handle' => 'root']);
        $service->createTreeNode($entry, 'root');

        $this->assertCount(0, $entry->fresh()->treeAncestors());
    }

    public function test_tree_ancestors_returns_chain_root_first(): void
    {
        $service = app(EntryService::class);
        $root = $this->makeTreeEntry(['handle' => 'root']);
        $mid = $this->makeTreeEntry(['handle' => 'mid']);
        $leaf = $this->makeTreeEntry(['handle' => 'leaf']);

        $rootNode = $service->createTreeNode($root, 'root');
        $midNode = $service->createTreeNode($mid, 'mid', $rootNode);
        $service->createTreeNode($leaf, 'leaf', $midNode);

        $ancestors = $leaf->fresh()->treeAncestors();

        $this->assertCount(2, $ancestors);
        $this->assertSame($root->id, $ancestors->get(0)->id);
        $this->assertSame($mid->id, $ancestors->get(1)->id);
    }

    // -------------------------------------------------------------------------
    // treeDescendants()
    // -------------------------------------------------------------------------

    public function test_tree_descendants_returns_empty_collection_when_no_tree_node(): void
    {
        $entry = $this->makeTreeEntry();

        $this->assertCount(0, $entry->treeDescendants());
    }

    public function test_tree_descendants_returns_empty_collection_for_leaf_node(): void
    {
        $service = app(EntryService::class);
        $entry = $this->makeTreeEntry(['handle' => 'leaf']);
        $service->createTreeNode($entry, 'leaf');

        $this->assertCount(0, $entry->fresh()->treeDescendants());
    }

    public function test_tree_descendants_returns_all_levels(): void
    {
        $service = app(EntryService::class);
        $root = $this->makeTreeEntry(['handle' => 'root']);
        $child = $this->makeTreeEntry(['handle' => 'child']);
        $grandchild = $this->makeTreeEntry(['handle' => 'grandchild']);

        $rootNode = $service->createTreeNode($root, 'root');
        $childNode = $service->createTreeNode($child, 'child', $rootNode);
        $service->createTreeNode($grandchild, 'grandchild', $childNode);

        $descendants = $root->fresh()->treeDescendants();

        $this->assertCount(2, $descendants);
        $ids = $descendants->pluck('id')->toArray();
        $this->assertContains($child->id, $ids);
        $this->assertContains($grandchild->id, $ids);
    }

    public function test_tree_descendants_respects_max_depth(): void
    {
        $service = app(EntryService::class);
        $root = $this->makeTreeEntry(['handle' => 'root']);
        $child = $this->makeTreeEntry(['handle' => 'child']);
        $grandchild = $this->makeTreeEntry(['handle' => 'grandchild']);

        $rootNode = $service->createTreeNode($root, 'root');
        $childNode = $service->createTreeNode($child, 'child', $rootNode);
        $service->createTreeNode($grandchild, 'grandchild', $childNode);

        // maxDepth=1 should return only the direct child, not the grandchild
        $descendants = $root->fresh()->treeDescendants(1);

        $this->assertCount(1, $descendants);
        $this->assertSame($child->id, $descendants->first()->id);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeTreeEntry(array $entryOverrides = [], array $typeOverrides = []): Entry
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
}
