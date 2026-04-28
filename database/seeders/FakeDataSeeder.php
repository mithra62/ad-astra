<?php

namespace Database\Seeders;

use App\Facades\Content;
use App\Models\Category;
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
 * Stress-test seeder: 1,000 users + 10,000 entries across all entry types.
 * Only runs in local/testing environments.
 *
 * Data shape mirrors StoreUserRequest / StoreEntryRequest validation rules so
 * every record is verified against the same constraints the HTTP layer enforces.
 */
class FakeDataSeeder extends Seeder
{
    use WithoutModelEvents;

    private const USER_COUNT = 1_000;
    private const ENTRY_COUNT = 1_000;

    // Weighted role pool: ~70 % user, ~25 % admin, ~5 % super admin
    private const ROLE_POOL = [
        'user', 'user', 'user', 'user', 'user', 'user', 'user',
        'admin', 'admin', 'admin',
        'super admin',
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
        $this->seedEntries($users);

        $this->command->info('FakeDataSeeder — done');
    }

    // =========================================================================
    // Users
    // =========================================================================

    /** @return User[] */
    private function seedUsers(): array
    {
        $this->command->info('Creating ' . self::USER_COUNT . ' users...');

        $userService = app(UserService::class);
        $created = [];
        $failed = 0;

        for ($i = 0; $i < self::USER_COUNT; $i++) {
            $password = Str::random(10) . 'A1!'; // satisfies min:8 + complexity

            $data = [
                'name' => fake()->name(),
                'email' => fake()->unique()->safeEmail(),
                'password' => $password,
                'password_confirmation' => $password,
                'roles' => [fake()->randomElement(self::ROLE_POOL)],
                'fields' => $this->fakeUserFields(),
            ];

            if (!$this->validateUser($data)) {
                $failed++;
                continue;
            }

            // email_verified_at: 80 % verified, 20 % pending
            $user = $userService->create(array_merge($data, [
                'email_verified_at' => fake()->boolean(80) ? now() : null,
            ]));

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
    // Entries
    // =========================================================================

    /** @param User[] $users */
    private function seedEntries(array $users): void
    {
        $this->command->info('Creating ' . self::ENTRY_COUNT . ' entries...');

        // Pre-load everything needed so we avoid N+1 inside the loop.
        $allUsers = collect(empty($users) ? User::all() : $users);
        $entryTypes = EntryType::with('entryGroup.statusGroup.statuses')->get()->keyBy('handle');

        // Category ID pools keyed by entry-type handle
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

        return [
            'body' => fake()->paragraphs($paragraphCount, true),
            'excerpt' => fake()->paragraph(),
            'meta_title' => Str::limit(fake()->sentence(5, false), 55),
            'meta_description' => Str::limit(fake()->sentence(15, false), 155),
        ];
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
