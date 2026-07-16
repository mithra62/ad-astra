<?php

namespace AdAstra\Doctor\Checks\EntryTree;

use AdAstra\Doctor\AbstractDoctorCheck;
use AdAstra\Doctor\DoctorResult;
use AdAstra\Models\Entry;
use AdAstra\Models\EntryTree;
use AdAstra\Services\SiteRouting\RouteDrivers\EntryTreeRouteDriver;
use Illuminate\Support\Collection;

/**
 * Validates stored entry tree data against the invariants the EntryTreeService
 * write path maintains: parent chains, stored uri/depth, root-level handle
 * uniqueness, the home-node singleton, entry linkage, and redirect targets.
 * Diagnostic only — reports drift, never repairs it.
 *
 * Deliberately not covered here: template existence (covered by
 * templates.entry-templates), duplicate URIs and duplicate entry_ids
 * (enforced by real unique indexes), sort_order gaps (cosmetic; the
 * sibling rebalancing in EntryTreeService tolerates them), and tree handles
 * that differ from their entry's handle (a supported state —
 * createTreeNode() accepts any handle, and syncForEntry() preserves
 * custom handles on save).
 */
class EntryTreeIntegrityCheck extends AbstractDoctorCheck
{
    protected string $id = 'entry-tree.integrity';
    protected string $name = 'Entry tree integrity';

    /** @var Collection<int, EntryTree> */
    private Collection $nodes;

    /**
     * Chain intactness per node id: true when the node's parent chain reaches
     * a root without cycles or dangling references. Nodes on broken chains
     * are excluded from the uri/depth checks so one root cause doesn't
     * cascade into a wall of derived failures.
     *
     * @var array<int, bool>
     */
    private array $chainIntact = [];

    public function dependsOn(): array
    {
        return ['database.connection', 'database.required-tables'];
    }

    public function run(): iterable
    {
        $this->nodes = EntryTree::query()->with('entry.entryType')->get()->keyBy('id');
        $this->chainIntact = [];

        $results = [
            ...$this->checkParentChains(),
            ...$this->checkStoredUrisAndDepths(),
            ...$this->checkRootHandles(),
            ...$this->checkHandleNormalization(),
            ...$this->checkHomeNode(),
            ...$this->checkEntryLinkage(),
            ...$this->checkRedirects(),
        ];

        if ($results !== []) {
            yield from $results;

            return;
        }

        if ($this->nodes->isEmpty()) {
            yield $this->pass('No entry tree nodes to check');

            return;
        }

        $count = $this->nodes->count();

        yield $this->pass(sprintf(
            'Entry tree healthy — %d %s, max depth %d, all URIs consistent',
            $count,
            $count === 1 ? 'node' : 'nodes',
            (int) $this->nodes->max('depth'),
        ));
    }

    /**
     * Walk every node's parent chain with a visited set. The parent_id FK
     * does not prevent cycles (A→B→A), and a cycle makes
     * EntryTreeService::rebuildTreeUri() recurse forever. Dangling parent
     * references are impossible while FK enforcement is on but can arrive
     * via imports run with constraints disabled.
     *
     * @return list<DoctorResult>
     */
    private function checkParentChains(): array
    {
        $results = [];

        foreach ($this->nodes as $id => $node) {
            if (isset($this->chainIntact[$id])) {
                continue;
            }

            $path = [];
            $current = $node;
            $intact = true;

            while (true) {
                if (isset($this->chainIntact[$current->id])) {
                    $intact = $this->chainIntact[$current->id];
                    break;
                }

                if (isset($path[$current->id])) {
                    // Cycle: everything from the first visit of this node onward loops.
                    $cycleIds = array_slice(array_keys($path), array_search($current->id, array_keys($path), true));
                    $results[] = $this->fail(
                        'Entry tree nodes #' . implode(', #', $cycleIds) . ' form a parent cycle — URI rebuilds would recurse forever',
                        fixCommand: 'fix the parent_id chain directly in the database',
                    );
                    $intact = false;
                    break;
                }

                $path[$current->id] = true;

                if ($current->parent_id === null) {
                    break;
                }

                $parent = $this->nodes->get($current->parent_id);

                if (!$parent) {
                    $results[] = $this->fail(
                        "Entry tree node #{$current->id} [{$current->handle}] references missing parent #{$current->parent_id}",
                        fixCommand: 'fix the parent_id reference directly in the database',
                    );
                    $intact = false;
                    break;
                }

                $current = $parent;
            }

            foreach (array_keys($path) as $pathId) {
                $this->chainIntact[$pathId] = $intact;
            }
        }

        return $results;
    }

    /**
     * Recompute uri and depth for every node with an intact chain and compare
     * to the stored columns. Routing matches the stored uri string verbatim,
     * so a stale uri means the node is unreachable. Home nodes are skipped
     * for the uri comparison — checkHomeNode() covers them fully.
     *
     * @return list<DoctorResult>
     */
    private function checkStoredUrisAndDepths(): array
    {
        $results = [];

        foreach ($this->nodes as $id => $node) {
            if (!$this->chainIntact[$id]) {
                continue;
            }

            if (!$node->is_home && $node->uri !== $this->expectedUri($node)) {
                $results[] = $this->fail(
                    "Entry tree node #{$id} [{$node->handle}] has stored URI [{$node->uri}] but its handle chain resolves to [{$this->expectedUri($node)}] — the node is unreachable at its stored URI",
                    fixCommand: 're-save the entry in the admin to rebuild the subtree URIs',
                );
            }

            if ((int) $node->depth !== $this->expectedDepth($node)) {
                $results[] = $this->fail(
                    "Entry tree node #{$id} [{$node->handle}] has stored depth {$node->depth} but is actually {$this->expectedDepth($node)} levels deep",
                    fixCommand: 're-save the entry in the admin to rebuild the subtree URIs',
                );
            }
        }

        return $results;
    }

    /**
     * The unique(parent_id, handle) index does not fire for root nodes:
     * SQL composite uniques allow repeated NULLs, so root-level handle
     * collisions can exist despite the schema.
     *
     * @return list<DoctorResult>
     */
    private function checkRootHandles(): array
    {
        return $this->nodes
            ->whereNull('parent_id')
            ->groupBy('handle')
            ->filter(fn (Collection $group) => $group->count() > 1)
            ->map(fn (Collection $group, $handle) => $this->fail(
                "Root-level entry tree handle [{$handle}] is used by nodes #" . $group->pluck('id')->implode(', #') . ' — the unique index does not cover NULL parents',
                fixCommand: 'rename the colliding entries in the admin',
            ))
            ->values()
            ->all();
    }

    /**
     * A handle that isn't already slug-normalized produces a stored URI the
     * router can never match, since inbound URIs are normalized before lookup.
     *
     * @return list<DoctorResult>
     */
    private function checkHandleNormalization(): array
    {
        $results = [];

        foreach ($this->nodes as $id => $node) {
            if ($node->handle !== EntryTree::normalizeHandle($node->handle)) {
                $results[] = $this->fail(
                    "Entry tree node #{$id} has non-URL-safe handle [{$node->handle}] — URIs built from it can never match a request",
                    fixCommand: 're-save the entry in the admin to re-slug the handle',
                );
            }
        }

        return $results;
    }

    /**
     * @return list<DoctorResult>
     */
    private function checkHomeNode(): array
    {
        $results = [];
        $homes = $this->nodes->where('is_home', true);

        if ($homes->isEmpty()) {
            if ($this->nodes->isNotEmpty()) {
                $results[] = $this->warn(
                    'No entry tree node is marked as home — the site root only resolves if another route driver serves /',
                    fixCommand: 'mark a top-level entry as the home entry in the admin',
                );
            }

            return $results;
        }

        if ($homes->count() > 1) {
            $results[] = $this->fail(
                'Multiple entry tree nodes are marked as home (#' . $homes->keys()->implode(', #') . ') — routing for / becomes arbitrary',
                fixCommand: 're-save the intended home entry in the admin; taking the home flag demotes the other node',
            );
        }

        foreach ($homes as $id => $home) {
            if ($home->parent_id !== null) {
                $results[] = $this->fail(
                    "Home node #{$id} has a parent — the home node must be a root node",
                    fixCommand: 'move the home entry to the top level in the admin',
                );
            }

            if ($home->handle !== 'home') {
                $results[] = $this->fail(
                    "Home node #{$id} has handle [{$home->handle}] instead of the literal [home]",
                    fixCommand: 're-save the home entry in the admin',
                );
            }

            if ($home->uri !== '/') {
                $results[] = $this->fail(
                    "Home node #{$id} has stored URI [{$home->uri}] instead of [/] — the site root will not resolve",
                    fixCommand: 're-save the home entry in the admin',
                );
            }

            // Reuse Entry::scopePublished — the exact visibility gate
            // EntryTreeRouteDriver applies — instead of re-deriving it.
            $published = Entry::query()->whereKey($home->entry_id)->published()->exists();

            if (!$published) {
                $results[] = $this->warn(
                    "Home node #{$id}'s entry is not published — the site root will 404",
                    fixCommand: 'publish the home entry in the admin',
                );
            }
        }

        return $results;
    }

    /**
     * Coverage and linkage between entries and their tree nodes.
     *
     * @return list<DoctorResult>
     */
    private function checkEntryLinkage(): array
    {
        $results = [];

        // Entries whose type expects a tree node but have none. Known write
        // path hazard: createTreeNode() runs after the entry transaction
        // commits, so a tree-create failure orphans the entry.
        $missing = Entry::query()
            ->whereHas('entryType', fn ($query) => $query->where('has_entry_tree', true))
            ->whereDoesntHave('entryTree')
            ->get(['id', 'handle']);

        foreach ($missing as $entry) {
            $results[] = $this->warn(
                "Entry #{$entry->id} [{$entry->handle}] has a tree-enabled type but no tree node — it is unreachable via entry-tree routing",
                fixCommand: 're-save the entry in the admin to create its tree node',
            );
        }

        foreach ($this->nodes as $id => $node) {
            $entry = $node->entry;

            if (!$entry) {
                $results[] = $this->fail(
                    "Entry tree node #{$id} [{$node->handle}] references a missing entry (#{$node->entry_id})",
                    fixCommand: 'delete the orphaned node directly in the database',
                );
                continue;
            }

            if (!$entry->entryType?->has_entry_tree) {
                $results[] = $this->warn(
                    "Entry tree node #{$id} [{$node->handle}] belongs to entry #{$entry->id} whose type no longer has the entry tree enabled — the node routes but cannot be managed in the admin",
                    fixCommand: 're-enable the entry tree on the type, or delete the node',
                );
            }
        }

        return $results;
    }

    /**
     * A redirect_url the route driver deems unsafe is silently ignored — the
     * node renders its template instead of redirecting.
     *
     * @return list<DoctorResult>
     */
    private function checkRedirects(): array
    {
        $results = [];

        foreach ($this->nodes as $id => $node) {
            if (!filled($node->redirect_url)) {
                continue;
            }

            if (!EntryTreeRouteDriver::isSafeRedirect($node->redirect_url)) {
                $results[] = $this->warn(
                    "Entry tree node #{$id} [{$node->handle}] has a redirect URL the router will refuse to follow — the node renders instead of redirecting",
                    details: 'Redirect URLs must be site-relative or http(s) absolute',
                    fixCommand: 'fix the redirect URL on the entry in the admin',
                );
            }

            $status = (int) $node->redirect_status;

            if ($status < 300 || $status > 399) {
                $results[] = $this->warn(
                    "Entry tree node #{$id} [{$node->handle}] redirects with non-3xx status {$status}",
                    fixCommand: 'set a 3xx redirect status on the entry in the admin',
                );
            }
        }

        return $results;
    }

    /**
     * Mirrors EntryService::treeBuildUri() against the in-memory node map:
     * home contributes no segment, empty handles are dropped, root is "/".
     */
    private function expectedUri(EntryTree $node): string
    {
        if ($node->is_home) {
            return '/';
        }

        $segments = [];
        $current = $node;

        while ($current) {
            if (!$current->is_home) {
                array_unshift($segments, $current->handle);
            }

            $current = $current->parent_id !== null ? $this->nodes->get($current->parent_id) : null;
        }

        return implode('/', array_filter($segments)) ?: '/';
    }

    private function expectedDepth(EntryTree $node): int
    {
        $depth = 0;
        $current = $node;

        while ($current->parent_id !== null) {
            $current = $this->nodes->get($current->parent_id);
            $depth++;
        }

        return $depth;
    }
}
