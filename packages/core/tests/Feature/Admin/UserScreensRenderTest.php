<?php

namespace Tests\Feature\Admin;

use AdAstra\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Smoke tests that the user admin screens render (HTTP 200) after the UI-kit
 * component refactor. Exercises the _ui macros and the _card / _page-header /
 * _table embeds end-to-end via the real Twig render path.
 */
class UserScreensRenderTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'access admin']);
        Permission::firstOrCreate(['name' => 'manage user status']);

        $this->admin = User::factory()->active()->create();
        $this->admin->givePermissionTo(['access admin', 'manage user status']);
    }

    public function test_index_renders(): void
    {
        $target = User::factory()->active()->create();

        $this->actingAs($this->admin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee('All Users')      // card title var
            ->assertSee('Add User')       // ui.button in page-header embed block
            ->assertSee($target->email);  // table rows block rendered
    }

    public function test_create_renders(): void
    {
        $this->actingAs($this->admin)
            ->get(route('users.create'))
            ->assertOk();
    }

    public function test_show_renders(): void
    {
        $target = User::factory()->active()->create();

        $this->actingAs($this->admin)
            ->get(route('users.show', $target))
            ->assertOk()
            ->assertSee($target->name);
    }

    public function test_edit_renders(): void
    {
        $target = User::factory()->active()->create();

        $this->actingAs($this->admin)
            ->get(route('users.edit', $target))
            ->assertOk();
    }

    public function test_delete_confirm_renders(): void
    {
        $target = User::factory()->active()->create();

        $this->actingAs($this->admin)
            ->get(route('users.confirm', $target))
            ->assertOk();
    }

    public function test_change_password_renders(): void
    {
        $target = User::factory()->active()->create();

        $this->actingAs($this->admin)
            ->get(route('users.change_password', $target))
            ->assertOk();
    }

    public function test_tokens_index_renders(): void
    {
        $target = User::factory()->active()->create();

        $this->actingAs($this->admin)
            ->get(route('users.token.index', $target))
            ->assertOk()
            ->assertSee('No Tokens found'); // ui.empty_state rendered
    }
}
