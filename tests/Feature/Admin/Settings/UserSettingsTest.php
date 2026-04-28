<?php

namespace Tests\Feature\Admin\Settings;

use App\Models\Role;
use App\Models\SettingDomain;
use App\Models\SettingValue;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class UserSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
        Cache::flush();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeSuperAdmin(): User
    {
        $role = Role::firstOrCreate(['name' => 'super admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);
        return $user;
    }

    /**
     * Register a domain in config with two fields:
     *   - {handle}_tz        (text, user_overridable = true)
     *   - {handle}_site_name (text, user_overridable = false)
     *
     * Only the tz field should appear on the user preferences page.
     */
    private function makeDomainWithMixedFields(string $handle = 'ud_test'): SettingDomain
    {
        config(["settings.{$handle}" => [
            'name'        => strtoupper($handle),
            'description' => null,
            'icon'        => null,
            'sort_order'  => 99,
            'fields'      => [
                [
                    'handle'          => "{$handle}_tz",
                    'label'           => 'Timezone',
                    'type'            => 'text',
                    'default'         => 'UTC',
                    'instructions'    => null,
                    'group'           => null,
                    'hidden'          => false,
                    'user_overridable' => true,
                ],
                [
                    'handle'          => "{$handle}_site_name",
                    'label'           => 'Site Name',
                    'type'            => 'text',
                    'default'         => '',
                    'instructions'    => null,
                    'group'           => null,
                    'hidden'          => false,
                    'user_overridable' => false,
                ],
            ],
        ]]);

        return SettingDomain::create([
            'name'       => strtoupper($handle),
            'handle'     => $handle,
            'sort_order' => 99,
        ]);
    }

    // -------------------------------------------------------------------------
    // show
    // -------------------------------------------------------------------------

    public function test_show_redirects_guests_to_login(): void
    {
        $response = $this->get(route('settings.user'));

        $response->assertRedirect(route('login'));
    }

    public function test_show_renders_for_authenticated_user(): void
    {
        $user = $this->makeSuperAdmin();
        $this->makeDomainWithMixedFields();

        $response = $this->actingAs($user)->get(route('settings.user'));

        $response->assertOk();
        $response->assertSee('My Preferences');
    }

    public function test_show_includes_overridable_fields(): void
    {
        $user = $this->makeSuperAdmin();
        $this->makeDomainWithMixedFields('ud2');

        $response = $this->actingAs($user)->get(route('settings.user'));

        $response->assertOk();
        $response->assertSee('Timezone');
    }

    public function test_show_excludes_non_overridable_fields(): void
    {
        $user = $this->makeSuperAdmin();
        $this->makeDomainWithMixedFields('ud3');

        $response = $this->actingAs($user)->get(route('settings.user'));

        $response->assertOk();
        $response->assertDontSee('Site Name');
    }

    public function test_show_indicates_active_user_override(): void
    {
        $user = $this->makeSuperAdmin();
        $this->makeDomainWithMixedFields('ud4');

        SettingValue::create([
            'domain'       => 'ud4',
            'field_handle' => 'ud4_tz',
            'user_id'      => $user->id,
            'value_text'   => 'America/Chicago',
        ]);

        $response = $this->actingAs($user)->get(route('settings.user'));

        $response->assertOk();
        $response->assertSee('your override is active');
    }

    public function test_show_indicates_system_default_when_no_override(): void
    {
        $user = $this->makeSuperAdmin();
        $this->makeDomainWithMixedFields('ud5');

        $response = $this->actingAs($user)->get(route('settings.user'));

        $response->assertOk();
        $response->assertSee('system default');
    }

    // -------------------------------------------------------------------------
    // update
    // -------------------------------------------------------------------------

    public function test_update_redirects_guests_to_login(): void
    {
        $response = $this->put(route('settings.user.update'), [
            'fields' => ['timezone' => 'UTC'],
        ]);

        $response->assertRedirect(route('login'));
    }

    public function test_update_saves_user_override_for_overridable_field(): void
    {
        $user = $this->makeSuperAdmin();
        $this->makeDomainWithMixedFields('ud6');

        $this->actingAs($user)->put(route('settings.user.update'), [
            'fields' => ['ud6_tz' => 'Asia/Tokyo'],
        ]);

        $this->assertDatabaseHas('setting_values', [
            'domain'       => 'ud6',
            'field_handle' => 'ud6_tz',
            'user_id'      => $user->id,
            'value_text'   => 'Asia/Tokyo',
        ]);
    }

    public function test_update_does_not_create_system_value(): void
    {
        $user = $this->makeSuperAdmin();
        $this->makeDomainWithMixedFields('ud7');

        $this->actingAs($user)->put(route('settings.user.update'), [
            'fields' => ['ud7_tz' => 'Europe/Paris'],
        ]);

        $this->assertDatabaseMissing('setting_values', [
            'domain'       => 'ud7',
            'field_handle' => 'ud7_tz',
            'user_id'      => null,
        ]);
    }

    public function test_update_ignores_non_overridable_fields(): void
    {
        $user = $this->makeSuperAdmin();
        $this->makeDomainWithMixedFields('ud8');

        $this->actingAs($user)->put(route('settings.user.update'), [
            'fields' => [
                'ud8_tz'        => 'UTC',
                'ud8_site_name' => 'Should Be Ignored',
            ],
        ]);

        $this->assertDatabaseMissing('setting_values', [
            'field_handle' => 'ud8_site_name',
            'user_id'      => $user->id,
        ]);
    }

    public function test_update_redirects_back_to_user_settings_with_success(): void
    {
        $user = $this->makeSuperAdmin();
        $this->makeDomainWithMixedFields('ud9');

        $response = $this->actingAs($user)->put(route('settings.user.update'), [
            'fields' => ['ud9_tz' => 'UTC'],
        ]);

        $response->assertRedirect(route('settings.user'));
        $response->assertSessionHas('success');
    }

    public function test_update_does_not_affect_another_users_settings(): void
    {
        $userA = $this->makeSuperAdmin();
        $userB = User::factory()->create();
        $this->makeDomainWithMixedFields('ud10');

        SettingValue::create([
            'domain'       => 'ud10',
            'field_handle' => 'ud10_tz',
            'user_id'      => $userB->id,
            'value_text'   => 'Europe/London',
        ]);

        $this->actingAs($userA)->put(route('settings.user.update'), [
            'fields' => ['ud10_tz' => 'America/New_York'],
        ]);

        $this->assertDatabaseHas('setting_values', [
            'field_handle' => 'ud10_tz',
            'user_id'      => $userB->id,
            'value_text'   => 'Europe/London',
        ]);
    }

    public function test_update_busts_only_current_users_cache(): void
    {
        $userA = $this->makeSuperAdmin();
        $userB = User::factory()->create();
        $this->makeDomainWithMixedFields('ud11');

        Cache::put("settings.user.{$userA->id}.ud11", ['ud11_tz' => 'UTC'], 3600);
        Cache::put("settings.user.{$userB->id}.ud11", ['ud11_tz' => 'UTC'], 3600);

        $this->actingAs($userA)->put(route('settings.user.update'), [
            'fields' => ['ud11_tz' => 'Asia/Seoul'],
        ]);

        $this->assertNull(Cache::get("settings.user.{$userA->id}.ud11"));
        $this->assertNotNull(Cache::get("settings.user.{$userB->id}.ud11"));
    }
}
