<?php

namespace Tests\Feature\Admin;

use AdAstra\Models\Entry;
use AdAstra\Models\EntryAuthor;
use AdAstra\Models\EntryGroup;
use AdAstra\Models\EntryType;
use AdAstra\Models\Status;
use AdAstra\Models\StatusGroup;
use AdAstra\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EntryGroupViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_displays_entry_author_display_names(): void
    {
        $user = $this->makeSuperAdmin();
        $statusGroup = StatusGroup::factory()->create();
        $status = Status::factory()->create([
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
        $entry = Entry::factory()->create([
            'entry_group_id' => $group->id,
            'entry_type_id' => $type->id,
            'status_id' => $status->id,
            'status_handle' => $status->handle,
        ]);
        $author = EntryAuthor::factory()->create([
            'display_name' => 'Primary Author',
            'status' => 'active',
        ]);

        $entry->authors()->attach($author->id);

        $this->actingAs($user)
            ->get(route('entries.groups.show', $group))
            ->assertOk()
            ->assertSee('Primary Author')
            ->assertDontSee('<span></span>', false);
    }

    public function test_show_displays_entry_author_user_name_when_display_name_is_empty(): void
    {
        $user = $this->makeSuperAdmin();
        $authorUser = User::factory()->create(['name' => 'Fallback Author']);
        $statusGroup = StatusGroup::factory()->create();
        $status = Status::factory()->create([
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
        $entry = Entry::factory()->create([
            'entry_group_id' => $group->id,
            'entry_type_id' => $type->id,
            'status_id' => $status->id,
            'status_handle' => $status->handle,
        ]);
        $author = EntryAuthor::factory()->create([
            'user_id' => $authorUser->id,
            'display_name' => null,
            'status' => 'active',
        ]);

        $entry->authors()->attach($author->id);

        $this->actingAs($user)
            ->get(route('entries.groups.show', $group))
            ->assertOk()
            ->assertSee('Fallback Author');
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
}
