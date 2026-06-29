<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Category\Group as CategoryGroup;
use App\Models\Entry;
use App\Models\EntryGroup;
use App\Models\EntryType;
use App\Models\Role;
use App\Models\Status;
use App\Models\StatusGroup;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntryCategoryValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_accepts_category_from_attached_category_group(): void
    {
        $user = $this->makeSuperAdmin();
        [$group, $type] = $this->makeEntryGroupAndType();
        $category = $this->makeAttachedCategory($group);

        $response = $this->actingAs($user)->post(route('entries.store', ['group_id' => $group->id]), [
            'type_handle' => $type->handle,
            'title' => 'Categorised Entry',
            'handle' => 'categorised-entry',
            'categories' => [$category->id],
        ]);

        $entry = Entry::query()->where('handle', 'categorised-entry')->first();

        $response->assertRedirect(route('entries.groups.show', $group->id));
        $this->assertNotNull($entry);
        $this->assertTrue($entry->categories()->whereKey($category->id)->exists());
    }

    public function test_store_rejects_category_from_unattached_category_group(): void
    {
        $user = $this->makeSuperAdmin();
        [$group, $type] = $this->makeEntryGroupAndType();
        $category = $this->makeUnattachedCategory();

        $response = $this->actingAs($user)
            ->from(route('entries.create', ['group_id' => $group->id]))
            ->post(route('entries.store', ['group_id' => $group->id]), [
                'type_handle' => $type->handle,
                'title' => 'Invalid Entry',
                'handle' => 'invalid-entry',
                'categories' => [$category->id],
            ]);

        $response->assertRedirect(route('entries.create', ['group_id' => $group->id]));
        $response->assertSessionHasErrors('categories.0');
        $this->assertDatabaseMissing('entries', ['title' => 'Invalid Entry']);
    }

    public function test_update_accepts_category_from_attached_category_group(): void
    {
        $user = $this->makeSuperAdmin();
        [$group, $type] = $this->makeEntryGroupAndType();
        $category = $this->makeAttachedCategory($group);

        $entry = Entry::factory()->create([
            'entry_group_id' => $group->id,
            'entry_type_id' => $type->id,
            'created_by_user_id' => $user->id,
            'title' => 'Plain Entry',
            'handle' => 'plain-entry',
        ]);

        $response = $this->actingAs($user)->put(route('entries.update', $entry), [
            'title' => 'Plain Entry',
            'handle' => 'plain-entry',
            'categories' => [$category->id],
        ]);

        $response->assertRedirect(route('entries.edit', $entry));
        $this->assertTrue($entry->fresh()->categories()->whereKey($category->id)->exists());
    }

    public function test_update_rejects_category_from_unattached_category_group(): void
    {
        $user = $this->makeSuperAdmin();
        [$group, $type] = $this->makeEntryGroupAndType();
        $category = $this->makeUnattachedCategory();

        $entry = Entry::factory()->create([
            'entry_group_id' => $group->id,
            'entry_type_id' => $type->id,
            'created_by_user_id' => $user->id,
            'title' => 'Plain Entry',
            'handle' => 'plain-entry',
        ]);

        $response = $this->actingAs($user)
            ->from(route('entries.edit', $entry))
            ->put(route('entries.update', $entry), [
                'title' => 'Plain Entry',
                'handle' => 'plain-entry',
                'categories' => [$category->id],
            ]);

        $response->assertRedirect(route('entries.edit', $entry));
        $response->assertSessionHasErrors('categories.0');
        $this->assertFalse($entry->fresh()->categories()->whereKey($category->id)->exists());
    }

    protected function makeSuperAdmin(): User
    {
        $role = Role::query()->firstOrCreate([
            'name' => 'super admin',
            'guard_name' => 'web',
        ]);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    /**
     * @return array{0: EntryGroup, 1: EntryType}
     */
    protected function makeEntryGroupAndType(): array
    {
        $statusGroup = StatusGroup::factory()->create();
        Status::factory()->default()->create([
            'status_group_id' => $statusGroup->id,
            'handle' => 'draft',
            'name' => 'Draft',
        ]);

        $group = EntryGroup::factory()->create([
            'status_group_id' => $statusGroup->id,
        ]);

        $type = EntryType::factory()->create([
            'entry_group_id' => $group->id,
        ]);

        return [$group, $type];
    }

    protected function makeAttachedCategory(EntryGroup $group): Category
    {
        $categoryGroup = CategoryGroup::factory()->create();
        $group->categoryGroups()->syncWithoutDetaching([$categoryGroup->id]);

        return Category::factory()->for($categoryGroup, 'group')->create();
    }

    protected function makeUnattachedCategory(): Category
    {
        $categoryGroup = CategoryGroup::factory()->create();

        return Category::factory()->for($categoryGroup, 'group')->create();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(VerifyCsrfToken::class);
    }
}
