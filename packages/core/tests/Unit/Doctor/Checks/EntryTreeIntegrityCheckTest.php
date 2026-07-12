<?php

namespace Tests\Unit\Doctor\Checks;

use AdAstra\Doctor\Checks\EntryTree\EntryTreeIntegrityCheck;
use AdAstra\Doctor\DoctorStatus;
use AdAstra\Models\Entry;
use AdAstra\Models\EntryTree;
use AdAstra\Models\EntryType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Corrupt states are seeded with factories and raw DB writes on purpose —
 * EntryService refuses to create them, and that refusal is exactly why the
 * doctor check exists (drift arrives via crashed syncs, imports, or direct
 * DB edits).
 */
class EntryTreeIntegrityCheckTest extends TestCase
{
    use RefreshDatabase;

    private ?EntryType $treeType = null;

    /** @return \AdAstra\Doctor\DoctorResult[] */
    private function runCheck(): array
    {
        return iterator_to_array((new EntryTreeIntegrityCheck())->run(), false);
    }

    private function treeType(): EntryType
    {
        return $this->treeType ??= EntryType::factory()->create(['has_entry_tree' => true]);
    }

    /**
     * A node whose entry, handle, uri, and depth are mutually consistent —
     * overrides then introduce one specific corruption per test.
     */
    private function makeNode(array $overrides = [], bool $published = true): EntryTree
    {
        $factory = Entry::factory();

        if ($published) {
            $factory = $factory->published();
        }

        $entry = $factory->create(['entry_type_id' => $this->treeType()->id]);
        $handle = EntryTree::normalizeHandle($entry->handle);

        return EntryTree::factory()->create(array_merge([
            'entry_id' => $entry->id,
            'handle' => $handle,
            'uri' => $handle,
            'depth' => 0,
            'sort_order' => 1,
        ], $overrides));
    }

    private function makeHome(bool $published = true): EntryTree
    {
        return $this->makeNode(['handle' => 'home', 'uri' => '/', 'is_home' => true], $published);
    }

    private function makeChild(EntryTree $parent): EntryTree
    {
        $entry = Entry::factory()->published()->create(['entry_type_id' => $this->treeType()->id]);
        $handle = EntryTree::normalizeHandle($entry->handle);

        return EntryTree::factory()->create([
            'entry_id' => $entry->id,
            'parent_id' => $parent->id,
            'handle' => $handle,
            'uri' => $parent->uri . '/' . $handle,
            'depth' => $parent->depth + 1,
            'sort_order' => 1,
        ]);
    }

    private function assertHasResult(array $results, DoctorStatus $status, string $needle): void
    {
        foreach ($results as $result) {
            if ($result->status === $status && str_contains($result->message, $needle)) {
                $this->addToAssertionCount(1);

                return;
            }
        }

        $this->fail(
            "No {$status->value} result containing [{$needle}]. Got: "
            . json_encode(array_map(fn ($r) => "{$r->status->value}: {$r->message}", $results))
        );
    }

    private function assertNoResult(array $results, string $needle): void
    {
        foreach ($results as $result) {
            $this->assertStringNotContainsString($needle, $result->message);
        }
    }

    public function test_clean_tree_passes_with_stats(): void
    {
        $this->makeHome();
        $root = $this->makeNode();
        $this->makeChild($root);

        $results = $this->runCheck();

        $this->assertCount(1, $results);
        $this->assertSame(DoctorStatus::Pass, $results[0]->status);
        $this->assertStringContainsString('3 nodes', $results[0]->message);
        $this->assertStringContainsString('max depth 1', $results[0]->message);
    }

    public function test_empty_tree_passes(): void
    {
        $results = $this->runCheck();

        $this->assertCount(1, $results);
        $this->assertSame(DoctorStatus::Pass, $results[0]->status);
        $this->assertStringContainsString('No entry tree nodes', $results[0]->message);
    }

    public function test_parent_cycle_fails_without_cascading_uri_failures(): void
    {
        $this->makeHome();
        $root = $this->makeNode();
        $child = $this->makeChild($root);

        // A→B→A: impossible through EntryService, insertable under the FK.
        DB::table('entry_trees')->where('id', $root->id)->update(['parent_id' => $child->id]);

        $results = $this->runCheck();

        $this->assertHasResult($results, DoctorStatus::Fail, 'parent cycle');
        $this->assertSame(
            1,
            count(array_filter($results, fn ($r) => str_contains($r->message, 'parent cycle'))),
            'The cycle should be reported exactly once',
        );
        // Nodes on the broken chain are excluded from derived checks.
        $this->assertNoResult($results, 'stored URI');
        $this->assertNoResult($results, 'stored depth');
    }

    public function test_stale_uri_fails(): void
    {
        $this->makeHome();
        $this->makeNode(['uri' => 'stale-old-uri']);

        $results = $this->runCheck();

        $this->assertHasResult($results, DoctorStatus::Fail, 'stored URI [stale-old-uri]');
    }

    public function test_wrong_depth_fails(): void
    {
        $this->makeHome();
        $this->makeNode(['depth' => 5]);

        $results = $this->runCheck();

        $this->assertHasResult($results, DoctorStatus::Fail, 'stored depth 5');
    }

    public function test_duplicate_root_handles_fail(): void
    {
        $this->makeHome();
        $this->makeNode(['handle' => 'dupe', 'uri' => 'dupe']);
        // The uri unique index forces a distinct (stale) uri on the twin;
        // the composite unique(parent_id, handle) never fires for NULL parents.
        $this->makeNode(['handle' => 'dupe', 'uri' => 'dupe-stale']);

        $results = $this->runCheck();

        $this->assertHasResult($results, DoctorStatus::Fail, 'Root-level entry tree handle [dupe]');
    }

    public function test_non_normalized_handle_fails(): void
    {
        $this->makeHome();
        $this->makeNode(['handle' => 'Bad Handle!', 'uri' => 'Bad Handle!']);

        $results = $this->runCheck();

        $this->assertHasResult($results, DoctorStatus::Fail, 'non-URL-safe handle [Bad Handle!]');
    }

    public function test_multiple_home_nodes_fail(): void
    {
        $this->makeHome();
        // Second home needs a distinct uri (unique index), which also trips
        // the home-uri invariant — assert both findings.
        $second = $this->makeNode(['handle' => 'home', 'uri' => 'stale-home', 'is_home' => true]);

        $results = $this->runCheck();

        $this->assertHasResult($results, DoctorStatus::Fail, 'Multiple entry tree nodes are marked as home');
        $this->assertHasResult($results, DoctorStatus::Fail, "Home node #{$second->id} has stored URI [stale-home]");
    }

    public function test_home_below_root_fails(): void
    {
        $root = $this->makeNode();
        $this->makeNode(['handle' => 'home', 'uri' => '/', 'is_home' => true, 'parent_id' => $root->id, 'depth' => 1]);

        $results = $this->runCheck();

        $this->assertHasResult($results, DoctorStatus::Fail, 'must be a root node');
    }

    public function test_home_with_wrong_handle_fails(): void
    {
        $this->makeNode(['handle' => 'homepage', 'uri' => '/', 'is_home' => true]);

        $results = $this->runCheck();

        $this->assertHasResult($results, DoctorStatus::Fail, 'handle [homepage] instead of the literal [home]');
    }

    public function test_missing_home_warns(): void
    {
        $this->makeNode();

        $results = $this->runCheck();

        $this->assertHasResult($results, DoctorStatus::Warn, 'No entry tree node is marked as home');
    }

    public function test_unpublished_home_entry_warns(): void
    {
        $this->makeHome(published: false);

        $results = $this->runCheck();

        $this->assertHasResult($results, DoctorStatus::Warn, 'not published — the site root will 404');
    }

    public function test_tree_typed_entry_without_node_warns(): void
    {
        $entry = Entry::factory()->published()->create(['entry_type_id' => $this->treeType()->id]);

        $results = $this->runCheck();

        $this->assertHasResult($results, DoctorStatus::Warn, "Entry #{$entry->id} [{$entry->handle}] has a tree-enabled type but no tree node");
    }

    public function test_node_on_non_tree_type_warns(): void
    {
        $this->makeHome();
        $entry = Entry::factory()->published()->create([
            'entry_type_id' => EntryType::factory()->create(['has_entry_tree' => false])->id,
        ]);
        $handle = EntryTree::normalizeHandle($entry->handle);
        EntryTree::factory()->create(['entry_id' => $entry->id, 'handle' => $handle, 'uri' => $handle, 'depth' => 0]);

        $results = $this->runCheck();

        $this->assertHasResult($results, DoctorStatus::Warn, 'no longer has the entry tree enabled');
    }

    public function test_custom_tree_handle_diverging_from_entry_handle_passes(): void
    {
        $this->makeHome();
        // A supported state: createTreeNode() accepts any handle, and
        // syncTreeNode() preserves custom handles on save.
        $this->makeNode(['handle' => 'custom-handle', 'uri' => 'custom-handle']);

        $results = $this->runCheck();

        $this->assertCount(1, $results);
        $this->assertSame(DoctorStatus::Pass, $results[0]->status);
    }

    public function test_unsafe_redirect_url_warns(): void
    {
        $this->makeHome();
        $this->makeNode(['redirect_url' => 'javascript:alert(1)']);

        $results = $this->runCheck();

        $this->assertHasResult($results, DoctorStatus::Warn, 'redirect URL the router will refuse to follow');
    }

    public function test_protocol_relative_redirect_url_warns(): void
    {
        $this->makeHome();
        $this->makeNode(['redirect_url' => '//evil.example.com/path']);

        $results = $this->runCheck();

        $this->assertHasResult($results, DoctorStatus::Warn, 'redirect URL the router will refuse to follow');
    }

    public function test_non_3xx_redirect_status_warns(): void
    {
        $this->makeHome();
        $this->makeNode(['redirect_url' => '/target', 'redirect_status' => 200]);

        $results = $this->runCheck();

        $this->assertHasResult($results, DoctorStatus::Warn, 'non-3xx status 200');
    }
}
