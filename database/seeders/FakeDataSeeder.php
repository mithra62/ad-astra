<?php

namespace Database\Seeders;

use App\Enums\UserStatus;
use App\Facades\Categories;
use App\Facades\Content;
use App\Models\Category;
use App\Models\Category\Group as CategoryGroup;
use App\Models\EntryGroup;
use App\Models\EntryType;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Stress-test seeder: users, categories, and entries across all types.
 * Only runs in local/testing environments.
 *
 * Data shape mirrors StoreUserRequest / StoreEntryRequest validation rules so
 * every record is verified against the same constraints the HTTP layer enforces.
 * Categories are seeded before entries so the category ID pools used during
 * entry creation include the full synthetic dataset.
 */
class FakeDataSeeder extends Seeder
{
    use WithoutModelEvents;

    private const USER_COUNT = 1000;
    private const CATEGORY_COUNT = 10000;
    private const ENTRY_COUNT = 10000;

    // Maximum nesting depth for generated category trees (1 = root only).
    private const MAX_CATEGORY_DEPTH = 10;

    // Cap on how many category groups are used; never exceeds the seeded total.
    private const MAX_CATEGORY_GROUPS = 1000;

    // Weighted role pool: ~70 % user, ~25 % admin, ~5 % super admin
    private const ROLE_POOL = [
        'user', 'user', 'user', 'user', 'user', 'user', 'user',
        'admin', 'admin', 'admin',
        'super admin',
    ];

    // Weighted user-status pool: ~75 % active, ~10 % pending, ~8 % inactive,
    // ~5 % suspended, ~2 % banned.
    private const USER_STATUS_WEIGHTS = [
        UserStatus::ACTIVE    => 75,
        UserStatus::PENDING   => 10,
        UserStatus::INACTIVE  => 8,
        UserStatus::SUSPENDED => 5,
        UserStatus::BANNED    => 2,
    ];

    // Preferred weights per status handle. Any handle not listed defaults to 1.
    // Applied only to statuses that actually exist on the entry group's status group,
    // so entry groups with a narrower set (e.g. no 'archived') are handled safely.
    private const STATUS_WEIGHTS = [
        'published' => 6,
        'draft' => 3,
        'archived' => 1,
    ];

    public function run(): void
    {
        if (!app()->environment(['local', 'testing'])) {
            $this->command->warn('FakeDataSeeder only runs in local/testing environments. Skipped.');
            return;
        }

        $this->command->info('FakeDataSeeder — starting');

        $users = $this->seedUsers();
        $this->seedCategories();
        $this->seedEntries($users);

        $this->command->info('FakeDataSeeder — done');
    }

    // =========================================================================
    // Users
    // =========================================================================

    /**
     * Pick a weighted random user status from USER_STATUS_WEIGHTS.
     */
    private function pickUserStatus(): string
    {
        $pool = [];
        foreach (self::USER_STATUS_WEIGHTS as $status => $weight) {
            for ($w = 0; $w < $weight; $w++) {
                $pool[] = $status;
            }
        }

        return fake()->randomElement($pool);
    }

    /** @return User[] */
    private function seedUsers(): array
    {
        $this->command->info('Creating ' . self::USER_COUNT . ' users...');

        $userService = app(UserService::class);
        $created = [];
        $failed = 0;

        for ($i = 0; $i < self::USER_COUNT; $i++) {
            $password = Str::random(10) . 'A1!'; // satisfies min:8 + complexity
            $status = $this->pickUserStatus();

            $data = [
                'name'     => fake()->name(),
                'email'    => fake()->unique()->safeEmail(),
                'password' => $password,
                'password_confirmation' => $password,
                'status'   => $status,
                'roles'    => [fake()->randomElement(self::ROLE_POOL)],
                'fields'   => $this->fakeUserFields(),
            ];

            if (!$this->validateUser($data)) {
                $failed++;
                continue;
            }

            // email_verified_at: 80 % verified, 20 % pending
            $extra = ['email_verified_at' => fake()->boolean(80) ? now() : null];

            // Suspended accounts need a suspended_until date.
            if ($status === UserStatus::SUSPENDED) {
                $extra['suspended_until'] = now()->addDays(fake()->numberBetween(1, 30));
            }

            // Banned accounts record their banned_at timestamp.
            if ($status === UserStatus::BANNED) {
                $extra['banned_at'] = now()->subDays(fake()->numberBetween(1, 90));
            }

            $user = $userService->create(array_merge($data, $extra));

            $created[] = $user;
        }

        $this->command->info(sprintf('Users: %d created, %d failed.', count($created), $failed));

        return $created;
    }

    private function fakeUserFields(): array
    {
        // All fields are optional in UserSchema; randomise presence to exercise
        // both sparse and fully-populated profiles.
        return array_filter([
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'gender' => fake()->optional(0.65)->randomElement([
                'male', 'female', 'non-binary', 'prefer not to say',
            ]),
            'date_of_birth' => fake()->optional(0.60)
                ->dateTimeBetween('-70 years', '-18 years')
                ?->format('Y-m-d'),
            'website' => fake()->optional(0.35)->url(),
            'bio' => fake()->optional(0.50)->paragraphs(
                fake()->numberBetween(1, 3), true
            ),
            'social_twitter' => fake()->optional(0.30)->userName(),
            'social_linkedin' => fake()->optional(0.25)->url(),
        ], fn($v) => $v !== null);
    }

    /**
     * Mirror StoreUserRequest rules, including the DB uniqueness check so
     * re-running the seeder against a populated database fails gracefully
     * rather than throwing a constraint violation.
     */
    private function validateUser(array $data): bool
    {
        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required'],
            'roles' => ['required', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
            'fields' => ['nullable', 'array'],
        ]);

        if ($validator->fails()) {
            $this->command->warn('User validation failed: ' . implode(', ', $validator->errors()->all()));
            return false;
        }

        return true;
    }

    // =========================================================================
    // Categories
    // =========================================================================

    private function seedCategories(): void
    {
        $this->command->info('Creating up to ' . self::CATEGORY_COUNT . ' categories...');

        // Eager-load the full field layout chain so resolveGroupFieldDefs()
        // can walk the tree without additional queries per group.
        $groups = CategoryGroup::with('fieldLayout.tabs.elements.field.fieldType')
            ->orderBy('sort_order')
            ->limit(self::MAX_CATEGORY_GROUPS)
            ->get();

        if ($groups->isEmpty()) {
            $this->command->warn('No category groups found; skipping category seeding.');
            return;
        }

        // Distribute the total budget roughly evenly across all groups.
        $perGroup = (int)ceil(self::CATEGORY_COUNT / $groups->count())*5;
        $created = 0;
        $failed = 0;

        foreach ($groups as $group) {
            $budget = $perGroup;
            $fieldDefs = $this->resolveGroupFieldDefs($group);

            // Each group gets several root categories; the recursive builder
            // then branches out into subtrees, consuming from $budget.
            $rootCount = fake()->numberBetween(30, 80);

            for ($i = 0; $i < $rootCount && $budget > 0; $i++) {
                $this->createCategoryNode($group, $fieldDefs, null, 1, $budget, $created, $failed);
            }
        }

        $this->command->info(sprintf('Categories: %d created, %d failed.', $created, $failed));
    }

    /**
     * Recursively create a single category node and its subtree.
     *
     * Child probability and maximum fanout both taper with depth so the tree
     * is bushy near the roots and sparse near the leaf limit.
     *
     * @param array<int, array{handle: string, type: string}> $fieldDefs
     */
    private function createCategoryNode(
        CategoryGroup $group,
        array         $fieldDefs,
        ?int          $parentId,
        int           $depth,
        int           &$budget,
        int           &$created,
        int           &$failed,
    ): void
    {
        if ($budget <= 0 || $depth > self::MAX_CATEGORY_DEPTH) {
            return;
        }

        try {
            $category = Categories::create($group, [
                'name' => ucwords(fake()->words(fake()->numberBetween(1, 3), true)),
                'handle' => Str::slug(fake()->word()) . '-' . Str::random(6),
                'sort_order' => fake()->numberBetween(1, 99),
                'parent_id' => $parentId,
                'fields' => $this->resolveFakeFieldValues($fieldDefs),
            ]);
            $created++;
            $budget--;
        } catch (\Throwable $e) {
            $this->command->warn("Category creation failed ({$group->handle}): " . $e->getMessage());
            $failed++;
            $budget--;
            return;
        }

        // Child probability drops by ~12 pp per depth level (75 % at depth 1,
        // ~3 % at depth 6, never below 5 %).
        $childProbability = max(5, 75 - ($depth * 12));

        if (!fake()->boolean($childProbability) || $budget <= 0) {
            return;
        }

        // Fanout also narrows with depth: up to 5 children at depth 1, 1 at depth 5+.
        $maxChildren = max(1, 6 - $depth);
        $childCount = fake()->numberBetween(1, $maxChildren);

        for ($i = 0; $i < $childCount && $budget > 0; $i++) {
            $this->createCategoryNode(
                $group, $fieldDefs, $category->id,
                $depth + 1, $budget, $created, $failed,
            );
        }
    }

    /**
     * Pre-resolve the field definitions for a group's layout into a flat array
     * of [handle, type] pairs so the recursive tree builder can generate fake
     * values without re-querying the DB on every node.
     *
     * @return array<int, array{handle: string, type: string}>
     */
    private function resolveGroupFieldDefs(CategoryGroup $group): array
    {
        $layout = $group->fieldLayout;

        if (!$layout) {
            return [];
        }

        $defs = [];

        foreach ($layout->tabs as $tab) {
            foreach ($tab->elements as $element) {
                $field = $element->field;

                if (!$field || !$field->fieldType) {
                    continue;
                }

                // Relational fields write to a separate table and require real
                // related IDs — skip them in the fake data generator.
                if ($field->fieldType->instance()->isRelational()) {
                    continue;
                }

                $defs[] = ['handle' => $field->handle, 'type' => $field->fieldType->object];
            }
        }

        return $defs;
    }

    /**
     * Generate fake field values for a category, randomly omitting ~30 % of
     * fields to produce realistic sparse records alongside fully-populated ones.
     *
     * @param array<int, array{handle: string, type: string}> $fieldDefs
     * @return array<string, mixed>
     */
    private function resolveFakeFieldValues(array $fieldDefs): array
    {
        if (empty($fieldDefs)) {
            return [];
        }

        $values = [];

        foreach ($fieldDefs as $def) {
            // Skip ~30 % of fields on any given category for sparse realism.
            if (fake()->boolean(30)) {
                continue;
            }

            $values[$def['handle']] = $this->fakeFieldValue($def['type']);
        }

        return $values;
    }

    /**
     * Generate a single plausible fake value for a given field type class.
     * Matched on the short class name so new types in App\Field\Types are
     * covered by the default arm without requiring changes here.
     */
    private function fakeFieldValue(string $typeClass): mixed
    {
        return match (true) {
            str_ends_with($typeClass, 'Textarea'),
            str_ends_with($typeClass, 'Html') => fake()->paragraph(),
            str_ends_with($typeClass, 'Text') => ucfirst(fake()->words(fake()->numberBetween(2, 4), true)),
            str_ends_with($typeClass, 'Number') => fake()->numberBetween(1, 1000),
            str_ends_with($typeClass, 'Boolean') => fake()->boolean(),
            str_ends_with($typeClass, 'Date') => fake()->date(),
            str_ends_with($typeClass, 'Url') => fake()->url(),
            str_ends_with($typeClass, 'EmailAddress') => fake()->safeEmail(),
            str_ends_with($typeClass, 'ColorPicker') => fake()->hexColor(),
            str_ends_with($typeClass, 'Telephone') => fake()->phoneNumber(),
            default => ucfirst(fake()->words(3, true)),
        };
    }

    // =========================================================================
    // Entries
    // =========================================================================

    /** @param User[] $users */
    private function seedEntries(array $users): void
    {
        $this->command->info('Creating ' . self::ENTRY_COUNT . ' entries...');

        // Pre-load everything needed so we avoid N+1 inside the loop.
        $allUsers = collect(empty($users) ? User::all() : $users);
        $entryTypes = EntryType::with('entryGroup.statusGroup.statuses')->get()->keyBy('handle');

        // Category ID pools keyed by entry-type handle — built after seedCategories()
        // so the pools include all synthetic categories created above.
        $categoryPools = $this->buildCategoryPools();

        // Auth must be set for EntryRepository::create() to capture created_by_user_id.
        // Use the first available super-admin so the author is always valid.
        $operator = User::role('super admin')->first() ?? $allUsers->first() ?? User::first();
        Auth::setUser($operator);

        $created = 0;
        $failed = 0;

        $typeHandles = $entryTypes->keys()->all();

        for ($i = 0; $i < self::ENTRY_COUNT; $i++) {
            $typeHandle = fake()->randomElement($typeHandles);

            /** @var \App\Models\EntryType|null $entryType */
            $entryType = $entryTypes->get($typeHandle);
            if (!$entryType) {
                $failed++;
                continue;
            }

            $entryGroup = $entryType->entryGroup;
            $status = $this->pickStatus($entryGroup);

            $data = [
                'title' => $this->fakeTitle($typeHandle),
                'handle' => $this->uniqueHandle(),
                'status' => $status,
                'published_at' => $this->fakePublishedAt($status),
                'authors' => $this->pickAuthorIds($allUsers),
                'categories' => $this->pickCategoryIds($typeHandle, $categoryPools),
                'fields' => $this->fakeEntryFields($typeHandle),
            ];

            if (!$this->validateEntry($data, $entryGroup)) {
                $failed++;
                continue;
            }

            try {
                Content::create($typeHandle, $data);
                $created++;
            } catch (\Throwable $e) {
                $this->command->warn("Entry creation failed ({$typeHandle}): " . $e->getMessage());
                $failed++;
            }
        }

        $this->command->info(sprintf('Entries: %d created, %d failed.', $created, $failed));
    }

    /**
     * Build category-ID pools keyed by entry-type handle.
     *
     * Each entry type is assigned the union of all category IDs that belong to
     * the category groups attached to its entry group.  Types whose entry group
     * has no category groups get an empty pool (no categories assigned).
     *
     * @return array<string, int[]>  typeHandle => [category IDs]
     */
    private function buildCategoryPools(): array
    {
        // Load every category ID, grouped by their category-group ID.
        $categoryIdsByGroup = Category::all()
            ->groupBy('group_id')
            ->map(fn($cats) => $cats->pluck('id')->all());

        // Load every entry type with its entry group and that group's category groups.
        $entryTypes = EntryType::with('entryGroup.categoryGroups')->get();

        $pools = [];

        foreach ($entryTypes as $type) {
            $ids = [];

            foreach ($type->entryGroup?->categoryGroups ?? [] as $catGroup) {
                $ids = array_merge($ids, $categoryIdsByGroup->get($catGroup->id, []));
            }

            $pools[$type->handle] = array_values(array_unique($ids));
        }

        return $pools;
    }

    /**
     * Pick a random status handle from the statuses that actually belong to the
     * entry group's status group, weighted by STATUS_WEIGHTS.
     */
    private function pickStatus(EntryGroup $entryGroup): string
    {
        $statuses = $entryGroup->statusGroup?->statuses ?? collect();

        $pool = [];
        foreach ($statuses as $status) {
            $weight = self::STATUS_WEIGHTS[$status->handle] ?? 1;
            for ($w = 0; $w < $weight; $w++) {
                $pool[] = $status->handle;
            }
        }

        if (empty($pool)) {
            return 'draft'; // safe fallback if the group has no statuses configured
        }

        return fake()->randomElement($pool);
    }

    private function fakeTitle(string $typeHandle): string
    {
        return match ($typeHandle) {
            'blog_post' => Str::title(fake()->sentence(fake()->numberBetween(4, 8), false)),
            'product' => Str::title(fake()->words(fake()->numberBetween(2, 4), true)),
            'event' => Str::title(fake()->sentence(3, false)) . ' ' . fake()->year(),
            'news_article' => Str::title(fake()->sentence(fake()->numberBetween(5, 10), false)),
            'page' => Str::title(fake()->words(fake()->numberBetween(2, 5), true)),
            'job_listing' => fake()->jobTitle() . ' — ' . fake()->city(),
            'podcast_episode' => sprintf(
                'Ep. %d: %s',
                fake()->numberBetween(1, 300),
                Str::title(fake()->sentence(4, false))
            ),
            'portfolio_item' => fake()->catchPhrase(),
            'video' => Str::title(fake()->sentence(fake()->numberBetween(3, 7), false)),
            'recipe' => Str::title(implode(' ', fake()->words(3))) . ' Recipe',
            default => Str::title(fake()->sentence(5, false)),
        };
    }

    private function uniqueHandle(): string
    {
        // Slug-style handle guaranteed unique by combining a short word and a
        // random suffix — avoids DB constraint violations at scale.
        return Str::slug(fake()->word()) . '-' . Str::random(8);
    }

    private function fakePublishedAt(string $status): ?\DateTimeInterface
    {
        return match ($status) {
            'published' => fake()->dateTimeBetween('-2 years', 'now'),
            'archived' => fake()->dateTimeBetween('-3 years', '-6 months'),
            default => null,
        };
    }

    /** @return int[] */
    private function pickAuthorIds(\Illuminate\Support\Collection $users): array
    {
        if ($users->isEmpty()) {
            return [];
        }

        $count = min(fake()->numberBetween(1, 3), $users->count());
        return $users->random($count)->pluck('id')->all();
    }

    /**
     * @param array<string, int[]> $pools
     * @return int[]
     */
    private function pickCategoryIds(string $typeHandle, array $pools): array
    {
        $pool = $pools[$typeHandle] ?? [];
        if (empty($pool)) {
            return [];
        }

        $count = min(fake()->numberBetween(0, 2), count($pool));
        if ($count === 0) {
            return [];
        }

        return (array)fake()->randomElements($pool, $count);
    }

    private function fakeEntryFields(string $typeHandle): array
    {
        // All active entry types share the same content/SEO layout; body and
        // excerpt lengths vary by type to reflect realistic content diversity.
        $paragraphCount = match ($typeHandle) {
            'page', 'portfolio_item' => fake()->numberBetween(4, 10),
            'recipe' => fake()->numberBetween(3, 6),
            'podcast_episode' => fake()->numberBetween(2, 5),
            default => fake()->numberBetween(2, 6),
        };

        $fields = [
            'body' => fake()->paragraphs($paragraphCount, true),
            'excerpt' => fake()->paragraph(),
            'meta_title' => Str::limit(fake()->sentence(5, false), 55),
            'meta_description' => Str::limit(fake()->sentence(15, false), 155),
        ];

        // Supply the fields required by each EntryType's validate() hook so
        // that published entries always pass type-level validation.
        $fields += match ($typeHandle) {
            'product' => [
                // ProductEntryType::validate() requires a SKU when published.
                'sku' => strtoupper(Str::random(4)) . '-' . fake()->numberBetween(1000, 9999),
                'price' => fake()->randomFloat(2, 1, 500),
                'stock_quantity' => fake()->numberBetween(0, 200),
            ],
            'video' => [
                // VideoEntryType::validate() requires platform_id OR video_url when published.
                'video_url' => fake()->url(),
                'video_duration' => fake()->numberBetween(60, 7200),
                'video_platform' => fake()->randomElement(['youtube', 'vimeo', 'other']),
            ],
            'job_listing' => [
                // JobListingEntryType::validate() requires application_url OR application_email when published.
                'application_url' => fake()->url(),
                'department' => fake()->randomElement([
                    'Engineering', 'Marketing', 'Sales', 'Design', 'Operations', 'HR',
                ]),
                'job_location' => fake()->city() . ', ' . fake()->stateAbbr(),
            ],
            default => [],
        };

        return $fields;
    }

    /**
     * Mirror StoreEntryRequest rules.
     * Resolves valid status handles from the entry group's status group so the
     * check is identical to the Rule::exists(...)->where(...) the request uses.
     */
    private function validateEntry(array $data, EntryGroup $entryGroup): bool
    {
        $validStatuses = $entryGroup->statusGroup?->statuses->pluck('handle')->all() ?? [];

        $validator = Validator::make($data, [
            'title' => ['required', 'string', 'max:255'],
            'handle' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:100', Rule::in($validStatuses)],
            'published_at' => ['nullable', 'date'],
            'authors' => ['nullable', 'array'],
            'authors.*' => ['integer', 'exists:users,id'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['integer', 'exists:categories,id'],
            'fields' => ['nullable', 'array'],
        ]);

        if ($validator->fails()) {
            $this->command->warn(
                "Entry validation failed: " . implode(', ', $validator->errors()->all())
            );
            return false;
        }

        return true;
    }
}
