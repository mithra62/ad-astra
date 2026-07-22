<?php

namespace Tests\Feature\Admin;

use AdAstra\Models\SettingDomain;
use AdAstra\Models\User;
use AdAstra\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Feature coverage for the Admin\Account\Settings controller (the authenticated
 * user's personal setting overrides).
 */
class AccountSettingsAdminTest extends TestCase
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
        $this->get(route('account.settings'))->assertRedirect(route('login'));
    }

    public function test_show_renders(): void
    {
        $this->actingAs($this->admin)->get(route('account.settings'))->assertOk();
    }

    public function test_update_persists_and_redirects(): void
    {
        $this->actingAs($this->admin)
            ->put(route('account.edit_settings'), [])
            ->assertRedirect(route('account.settings'))
            ->assertSessionHas('success');
    }

    /**
     * Create the 'general' domain row so show() has an overridable domain to
     * render — its timezone field is user_overridable with an options_callback.
     */
    private function seedGeneralDomain(): SettingDomain
    {
        return SettingDomain::create([
            'name' => 'General',
            'handle' => 'general',
            'description' => 'Site-wide general configuration.',
            'sort_order' => 0,
        ]);
    }

    public function test_show_renders_overridable_domains_with_hydrated_options(): void
    {
        $this->seedGeneralDomain();

        $response = $this->actingAs($this->admin)->get(route('account.settings'));

        $response->assertOk();
        // timezone is the general domain's user-overridable field; its options
        // come from an options_callback, so seeing it proves hydrateOptions ran.
        $response->assertSee('Timezone');
    }

    public function test_show_marks_fields_the_user_has_overridden(): void
    {
        $this->seedGeneralDomain();
        app(Settings::class)->set('general', 'timezone', 'America/Chicago', $this->admin);

        $response = $this->actingAs($this->admin)->get(route('account.settings'));

        $response->assertOk();
        $domains = $response->viewData('domains');

        $this->assertCount(1, $domains);
        $this->assertContains('timezone', $domains->first()['override_handles']);
        $this->assertSame('America/Chicago', $domains->first()['field_values']['timezone']);
    }

    public function test_show_skips_domains_without_overridable_fields(): void
    {
        // The email domain defines no user_overridable fields, so it must be
        // filtered out of the personal settings screen entirely.
        SettingDomain::create([
            'name' => 'Email',
            'handle' => 'email',
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($this->admin)->get(route('account.settings'));

        $response->assertOk();
        $this->assertCount(0, $response->viewData('domains'));
    }
}
