<?php

namespace Database\Seeders;

use AdAstra\Models\Entry;
use AdAstra\Models\EntryBehavior;
use AdAstra\Models\EntryGroup;
use AdAstra\Models\EntryTree;
use AdAstra\Models\EntryType;
use AdAstra\Models\Status;
use AdAstra\Models\StatusGroup;
use AdAstra\Models\User;
use AdAstra\Services\EntryAuthorService;
use AdAstra\Services\EntryTreeService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SandboxedEntryTreeSeeder extends Seeder
{
    private const USER_EMAIL = 'sandbox.entry.tree@example.test';

    private const STATUS_GROUP_HANDLE = 'sandbox-entry-tree-statuses';

    private const ENTRY_GROUP_HANDLE = 'sandbox-entry-tree';

    private const ENTRY_TYPE_HANDLE = 'sandbox-tree-page';

    public function run(): void
    {
        DB::transaction(function (): void {
            $author = $this->seedAuthor();
            $statusGroup = $this->seedStatusGroup();
            $entryGroup = $this->seedEntryGroup($statusGroup);
            $entryType = $this->seedEntryType($entryGroup);

            $definitions = $this->treeDefinitions();

            $this->pruneSandboxEntries($entryGroup, array_keys($definitions));

            $entries = $this->seedEntries($definitions, $entryGroup, $entryType, $author);
            $this->seedTreeNodes($definitions, $entries);
        });
    }

    protected function seedAuthor(): User
    {
        $user = User::query()->updateOrCreate(
            ['email' => self::USER_EMAIL],
            [
                'name' => 'Sandbox Tree Author',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'remember_token' => Str::random(10),
            ]
        );

        // Ensure the sandbox author has an active eligibility record so that
        // any future entry assignments go through the eligibility layer cleanly.
        app(EntryAuthorService::class)->promote($user);

        return $user;
    }

    protected function seedStatusGroup(): StatusGroup
    {
        $group = StatusGroup::query()->updateOrCreate(
            ['handle' => self::STATUS_GROUP_HANDLE],
            ['name' => 'Sandbox Entry Tree Statuses', 'sort_order' => 999]
        );

        Status::query()->updateOrCreate(
            ['status_group_id' => $group->id, 'handle' => 'published'],
            ['name' => 'Published', 'color' => '#22c55e', 'is_default' => true, 'is_public' => true, 'sort_order' => 1]
        );

        Status::query()->updateOrCreate(
            ['status_group_id' => $group->id, 'handle' => 'draft'],
            ['name' => 'Draft', 'color' => '#94a3b8', 'is_default' => false, 'is_public' => false, 'sort_order' => 2]
        );

        return $group;
    }

    protected function seedEntryGroup(StatusGroup $statusGroup): EntryGroup
    {
        return EntryGroup::query()->updateOrCreate(
            ['handle' => self::ENTRY_GROUP_HANDLE],
            [
                'name' => 'Sandbox Entry Tree',
                'description' => 'A self-contained demo entry tree with isolated dependencies.',
                'status_group_id' => $statusGroup->id,
                'field_layout_id' => null,
                'sort_order' => 999,
            ]
        );
    }

    protected function seedEntryType(EntryGroup $entryGroup): EntryType
    {
        return EntryType::query()->updateOrCreate(
            ['entry_group_id' => $entryGroup->id, 'handle' => self::ENTRY_TYPE_HANDLE],
            [
                'name' => 'Sandbox Tree Page',
                'entry_behavior_id' => EntryBehavior::where('handle', 'page')->value('id'),
                'sort_order' => 1,
                'default_template' => 'entries.page',
                'has_entry_tree' => true,
                'field_layout_id' => null,
            ]
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function treeDefinitions(): array
    {
        return [
            'site' => [
                'title' => 'Sandbox Site',
                'entry_handle' => 'sandbox-site',
                'tree_handle' => 'site',
                'parent' => null,
                'sort_order' => 1,
                'published_at' => now()->subDays(10),
            ],
            'about' => [
                'title' => 'About',
                'entry_handle' => 'sandbox-about',
                'tree_handle' => 'about',
                'parent' => 'site',
                'sort_order' => 1,
                'published_at' => now()->subDays(9),
            ],
            'services' => [
                'title' => 'Services',
                'entry_handle' => 'sandbox-services',
                'tree_handle' => 'services',
                'parent' => 'site',
                'sort_order' => 2,
                'published_at' => now()->subDays(8),
            ],
            'consulting' => [
                'title' => 'Consulting',
                'entry_handle' => 'sandbox-consulting',
                'tree_handle' => 'consulting',
                'parent' => 'services',
                'sort_order' => 1,
                'published_at' => now()->subDays(7),
            ],
            'team' => [
                'title' => 'Team',
                'entry_handle' => 'sandbox-team',
                'tree_handle' => 'team',
                'parent' => 'about',
                'sort_order' => 1,
                'published_at' => now()->subDays(6),
            ],
            'contact' => [
                'title' => 'Contact',
                'entry_handle' => 'sandbox-contact',
                'tree_handle' => 'contact',
                'parent' => 'site',
                'sort_order' => 3,
                'published_at' => now()->subDays(5),
            ],
        ];
    }

    /**
     * @param string[] $definitionKeys
     */
    protected function pruneSandboxEntries(EntryGroup $entryGroup, array $definitionKeys): void
    {
        $handles = collect($this->treeDefinitions())
            ->only($definitionKeys)
            ->pluck('entry_handle')
            ->all();

        Entry::query()
            ->where('entry_group_id', $entryGroup->id)
            ->whereNotIn('handle', $handles)
            ->delete();
    }

    /**
     * @param array<string, array<string, mixed>> $definitions
     * @return array<string, Entry>
     */
    protected function seedEntries(array $definitions, EntryGroup $entryGroup, EntryType $entryType, User $author): array
    {
        $publishedStatus = Status::query()
            ->where('status_group_id', $entryGroup->status_group_id)
            ->where('handle', 'published')
            ->firstOrFail();

        $entries = [];

        foreach ($definitions as $key => $definition) {
            $entries[$key] = Entry::query()->updateOrCreate(
                [
                    'entry_group_id' => $entryGroup->id,
                    'handle' => $definition['entry_handle'],
                ],
                [
                    'entry_type_id' => $entryType->id,
                    'created_by_user_id' => $author->id,
                    'title' => $definition['title'],
                    'status_id' => $publishedStatus->id,
                    'status_handle' => $publishedStatus->handle,
                    'status_is_public' => $publishedStatus->is_public,
                    'published_at' => $definition['published_at'],
                ]
            );
        }

        return $entries;
    }

    /**
     * @param array<string, array<string, mixed>> $definitions
     * @param array<string, Entry> $entries
     */
    protected function seedTreeNodes(array $definitions, array $entries): void
    {
        $nodes = [];

        foreach ($definitions as $key => $definition) {
            $parentNode = $definition['parent']
                ? $nodes[$definition['parent']] ?? null
                : null;

            $nodes[$key] = EntryTree::query()->updateOrCreate(
                ['entry_id' => $entries[$key]->id],
                [
                    'parent_id' => $parentNode?->id,
                    'handle' => EntryTree::validatedHandle($definition['tree_handle']),
                    'uri' => '__seed__-' . $definition['entry_handle'],
                    'depth' => 0,
                    'sort_order' => $definition['sort_order'],
                    'template' => 'site.tree',
                    'is_home' => false,
                ]
            );
        }

        app(EntryTreeService::class)->rebuildTreeUri($nodes['site']);
    }
}
