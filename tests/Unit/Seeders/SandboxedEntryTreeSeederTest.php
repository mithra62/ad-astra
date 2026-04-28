<?php

namespace Tests\Unit\Seeders;

use App\Models\Entry;
use App\Models\EntryGroup;
use App\Models\EntryTree;
use App\Models\EntryType;
use App\Models\Status;
use App\Models\StatusGroup;
use App\Models\User;
use Database\Seeders\SandboxedEntryTreeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SandboxedEntryTreeSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_sandboxed_entry_tree_seeder_creates_isolated_dependencies_and_tree(): void
    {
        $this->seed(SandboxedEntryTreeSeeder::class);

        $statusGroup = StatusGroup::query()->where('handle', 'sandbox-entry-tree-statuses')->first();
        $entryGroup = EntryGroup::query()->where('handle', 'sandbox-entry-tree')->first();
        $entryType = EntryType::query()->where('handle', 'sandbox-tree-page')->first();
        $author = User::query()->where('email', 'sandbox.entry.tree@example.test')->first();

        $this->assertNotNull($statusGroup);
        $this->assertNotNull($entryGroup);
        $this->assertNotNull($entryType);
        $this->assertNotNull($author);

        $this->assertSame($statusGroup->id, $entryGroup->status_group_id);
        $this->assertSame($entryGroup->id, $entryType->entry_group_id);
        $this->assertTrue($entryType->has_entry_tree);

        $this->assertDatabaseHas('statuses', [
            'status_group_id' => $statusGroup->id,
            'handle' => 'published',
            'is_default' => true,
            'is_public' => true,
        ]);
        $this->assertDatabaseHas('statuses', [
            'status_group_id' => $statusGroup->id,
            'handle' => 'draft',
            'is_public' => false,
        ]);

        $entries = Entry::query()
            ->where('entry_group_id', $entryGroup->id)
            ->orderBy('handle')
            ->get();

        $this->assertCount(6, $entries);
        $this->assertCount(6, EntryTree::query()->get());
        $this->assertTrue($entries->every(fn(Entry $entry) => $entry->created_by_user_id === $author->id));

        $this->assertDatabaseHas('entry_trees', ['handle' => 'site', 'uri' => 'site', 'depth' => 0]);
        $this->assertDatabaseHas('entry_trees', ['handle' => 'about', 'uri' => 'site/about', 'depth' => 1]);
        $this->assertDatabaseHas('entry_trees', ['handle' => 'services', 'uri' => 'site/services', 'depth' => 1]);
        $this->assertDatabaseHas('entry_trees', ['handle' => 'consulting', 'uri' => 'site/services/consulting', 'depth' => 2]);
        $this->assertDatabaseHas('entry_trees', ['handle' => 'team', 'uri' => 'site/about/team', 'depth' => 2]);
        $this->assertDatabaseHas('entry_trees', ['handle' => 'contact', 'uri' => 'site/contact', 'depth' => 1]);
    }

    public function test_sandboxed_entry_tree_seeder_is_idempotent(): void
    {
        $this->seed(SandboxedEntryTreeSeeder::class);
        $this->seed(SandboxedEntryTreeSeeder::class);

        $entryGroup = EntryGroup::query()->where('handle', 'sandbox-entry-tree')->firstOrFail();
        $statusGroup = StatusGroup::query()->where('handle', 'sandbox-entry-tree-statuses')->firstOrFail();

        $this->assertSame(1, EntryGroup::query()->where('handle', 'sandbox-entry-tree')->count());
        $this->assertSame(1, StatusGroup::query()->where('handle', 'sandbox-entry-tree-statuses')->count());
        $this->assertSame(2, Status::query()->where('status_group_id', $statusGroup->id)->count());
        $this->assertSame(6, Entry::query()->where('entry_group_id', $entryGroup->id)->count());
        $this->assertSame(6, EntryTree::query()->count());
    }
}
