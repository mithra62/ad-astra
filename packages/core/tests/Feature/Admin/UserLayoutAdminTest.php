<?php

namespace Tests\Feature\Admin;

use AdAstra\Models\FieldLayout;
use AdAstra\Models\User;
use AdAstra\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Feature coverage for the Admin\User\Layout controller. Only show() is routed
 * (GET users/layouts); it 404s when no user field layout is configured, which
 * is the default state in a fresh install.
 */
class UserLayoutAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::query()->firstOrCreate(['name' => 'super admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole($role);
    }

    public function test_show_redirects_guests_to_login(): void
    {
        $this->get(route('users.layouts.show'))->assertRedirect(route('login'));
    }

    public function test_show_forbids_non_admin_user(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('users.layouts.show'))
            ->assertForbidden();
    }

    public function test_show_returns_404_when_no_layout_configured(): void
    {
        $this->actingAs($this->admin)
            ->get(route('users.layouts.show'))
            ->assertNotFound();
    }

    public function test_show_returns_404_when_configured_layout_no_longer_exists(): void
    {
        $layout = FieldLayout::factory()->create();
        app(Settings::class)->set('users', 'user_field_layout_id', $layout->id);
        $layout->delete();

        $this->actingAs($this->admin)
            ->get(route('users.layouts.show'))
            ->assertNotFound();
    }

    public function test_show_renders_the_configured_user_field_layout(): void
    {
        $layout = FieldLayout::factory()->create(['name' => 'Member Profile Layout']);
        app(Settings::class)->set('users', 'user_field_layout_id', $layout->id);

        $response = $this->actingAs($this->admin)->get(route('users.layouts.show'));

        $response->assertOk();
        $this->assertSame($layout->id, $response->viewData('layout')->id);
        // sidebarData() feeds the layout picker in the sidebar
        $this->assertTrue(
            $response->viewData('layouts')->contains('id', $layout->id)
        );
    }
}
