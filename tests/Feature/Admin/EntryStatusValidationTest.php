<?php

namespace Tests\Feature\Admin;

use App\EntryTypes\PageEntryType;
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

class EntryStatusValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_accepts_status_from_entry_groups_status_group(): void
    {
        $user = $this->makeSuperAdmin();
        [$group, $type] = $this->makeEntryGroupAndTypeWithStatuses();
        Status::factory()->create([
            'status_group_id' => $group->status_group_id,
            'handle' => 'published',
            'name' => 'Published',
            'is_default' => false,
        ]);

        $response = $this->actingAs($user)->post(route('entries.store', ['group_id' => $group->id]), [
            'type_handle' => $type->handle,
            'title' => 'Valid Entry',
            'status' => 'published',
        ]);

        $entry = Entry::query()->where('handle', 'valid-entry')->first();

        $response->assertRedirect(route('entries.groups.show', $group->id));
        $this->assertNotNull($entry);
        $this->assertSame('published', $entry->status_handle);
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
    protected function makeEntryGroupAndTypeWithStatuses(): array
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
            'class' => PageEntryType::class,
        ]);

        return [$group, $type];
    }

    public function test_store_rejects_status_from_another_status_group(): void
    {
        $user = $this->makeSuperAdmin();
        [$group, $type] = $this->makeEntryGroupAndTypeWithStatuses();

        $otherStatusGroup = StatusGroup::factory()->create();
        Status::factory()->create([
            'status_group_id' => $otherStatusGroup->id,
            'handle' => 'published',
            'name' => 'Published',
            'is_default' => false,
        ]);

        $response = $this->actingAs($user)
            ->from(route('entries.create', ['group_id' => $group->id]))
            ->post(route('entries.store', ['group_id' => $group->id]), [
                'type_handle' => $type->handle,
                'title' => 'Invalid Entry',
                'status' => 'published',
            ]);

        $response->assertRedirect(route('entries.create', ['group_id' => $group->id]));
        $response->assertSessionHasErrors('status');
        $this->assertDatabaseMissing('entries', ['title' => 'Invalid Entry']);
    }

    public function test_update_accepts_status_from_entries_status_group(): void
    {
        $user = $this->makeSuperAdmin();
        [$group, $type] = $this->makeEntryGroupAndTypeWithStatuses();
        Status::factory()->create([
            'status_group_id' => $group->status_group_id,
            'handle' => 'published',
            'name' => 'Published',
            'is_default' => false,
        ]);

        $entry = Entry::factory()->create([
            'entry_group_id' => $group->id,
            'entry_type_id' => $type->id,
            'created_by_user_id' => $user->id,
            'title' => 'Draft Entry',
            'handle' => 'draft-entry',
            'status_handle' => 'draft',
        ]);

        $response = $this->actingAs($user)->put(route('entries.update', $entry), [
            'title' => 'Draft Entry',
            'handle' => 'draft-entry',
            'status' => 'published',
        ]);

        $response->assertRedirect(route('entries.edit', $entry));
        $this->assertSame('published', $entry->fresh()->status_handle);
    }

    public function test_update_rejects_status_from_another_status_group(): void
    {
        $user = $this->makeSuperAdmin();
        [$group, $type] = $this->makeEntryGroupAndTypeWithStatuses();

        $otherStatusGroup = StatusGroup::factory()->create();
        Status::factory()->create([
            'status_group_id' => $otherStatusGroup->id,
            'handle' => 'published',
            'name' => 'Published',
            'is_default' => false,
        ]);

        $entry = Entry::factory()->create([
            'entry_group_id' => $group->id,
            'entry_type_id' => $type->id,
            'created_by_user_id' => $user->id,
            'title' => 'Draft Entry',
            'handle' => 'draft-entry',
            'status_handle' => 'draft',
        ]);

        $response = $this->actingAs($user)
            ->from(route('entries.edit', $entry))
            ->put(route('entries.update', $entry), [
                'title' => 'Draft Entry',
                'handle' => 'draft-entry',
                'status' => 'published',
            ]);

        $response->assertRedirect(route('entries.edit', $entry));
        $response->assertSessionHasErrors('status');
        $this->assertSame('draft', $entry->fresh()->status_handle);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(VerifyCsrfToken::class);
    }
}
