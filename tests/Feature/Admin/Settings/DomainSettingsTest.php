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

class DomainSettingsTest extends TestCase
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
     * Register a domain in config and create its DB row.
     * Defines a single text field so the form has something to render and submit.
     */
    private function makeDomain(string $handle = 'td_test'): SettingDomain
    {
        config(["settings.{$handle}" => [
            'name'        => 'Test Domain',
            'description' => 'Feature test domain.',
            'icon'        => null,
            'sort_order'  => 99,
            'fields'      => [
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
            'name'        => 'Test Domain',
            'handle'      => $handle,
            'description' => 'Feature test domain.',
            'sort_order'  => 99,
        ]);
    }

    // -------------------------------------------------------------------------
    // index
    // -------------------------------------------------------------------------

    public function test_index_redirects_guests_to_login(): void
    {
        $response = $this->get(route('settings'));

        $response->assertRedirect(route('login'));
    }

    public function test_index_renders_for_authenticated_user(): void
    {
        $user = $this->makeSuperAdmin();
        $this->makeDomain();

        $response = $this->actingAs($user)->get(route('settings'));

        $response->assertOk();
        $response->assertSee('Settings');
        $response->assertSee('Test Domain');
    }

    // -------------------------------------------------------------------------
    // show
    // -------------------------------------------------------------------------

    public function test_show_redirects_guests_to_login(): void
    {
        $this->makeDomain();

        $response = $this->get(route('settings.show', 'td_test'));

        $response->assertRedirect(route('login'));
    }

    public function test_show_renders_domain_form(): void
    {
        $user = $this->makeSuperAdmin();
        $this->makeDomain();

        $response = $this->actingAs($user)->get(route('settings.show', 'td_test'));

        $response->assertOk();
        $response->assertSee('Test Domain');
        $response->assertSee('Site Name');
    }

    public function test_show_returns_404_for_missing_domain(): void
    {
        $user = $this->makeSuperAdmin();

        $response = $this->actingAs($user)->get(route('settings.show', 'no-such-domain'));

        $response->assertNotFound();
    }

    public function test_show_pre_populates_existing_system_value(): void
    {
        $user = $this->makeSuperAdmin();
        $this->makeDomain('td2');

        SettingValue::create([
            'domain'       => 'td2',
            'field_handle' => 'td2_site_name',
            'user_id'      => null,
            'value_text'   => 'My Existing Site',
        ]);

        $response = $this->actingAs($user)->get(route('settings.show', 'td2'));

        $response->assertOk();
        $response->assertSee('My Existing Site');
    }

    // -------------------------------------------------------------------------
    // update
    // -------------------------------------------------------------------------

    public function test_update_redirects_guests_to_login(): void
    {
        $this->makeDomain();

        $response = $this->put(route('settings.update', 'td_test'), [
            'fields' => ['td_test_site_name' => 'New Value'],
        ]);

        $response->assertRedirect(route('login'));
    }

    public function test_update_persists_system_setting_to_typed_column(): void
    {
        $user = $this->makeSuperAdmin();
        $this->makeDomain('td3');

        $this->actingAs($user)->put(route('settings.update', 'td3'), [
            'fields' => ['td3_site_name' => 'Hello World'],
        ]);

        $this->assertDatabaseHas('setting_values', [
            'domain'       => 'td3',
            'field_handle' => 'td3_site_name',
            'user_id'      => null,
            'value_text'   => 'Hello World',
        ]);
    }

    public function test_update_redirects_back_to_domain_show_with_success(): void
    {
        $user = $this->makeSuperAdmin();
        $this->makeDomain('td4');

        $response = $this->actingAs($user)->put(route('settings.update', 'td4'), [
            'fields' => ['td4_site_name' => 'Redirect Test'],
        ]);

        $response->assertRedirect(route('settings.show', 'td4'));
        $response->assertSessionHas('success');
    }

    public function test_update_overwrites_existing_system_value(): void
    {
        $user = $this->makeSuperAdmin();
        $this->makeDomain('td5');

        SettingValue::create([
            'domain' => 'td5', 'field_handle' => 'td5_site_name',
            'user_id' => null, 'value_text' => 'Old Value',
        ]);

        $this->actingAs($user)->put(route('settings.update', 'td5'), [
            'fields' => ['td5_site_name' => 'New Value'],
        ]);

        $this->assertDatabaseCount('setting_values', 1);
        $this->assertDatabaseHas('setting_values', ['value_text' => 'New Value']);
    }

    public function test_update_busts_system_cache(): void
    {
        $user = $this->makeSuperAdmin();
        $this->makeDomain('td6');

        Cache::put('settings.system.td6', ['td6_site_name' => 'cached'], 3600);

        $this->actingAs($user)->put(route('settings.update', 'td6'), [
            'fields' => ['td6_site_name' => 'Fresh Value'],
        ]);

        $this->assertNull(Cache::get('settings.system.td6'));
    }

    public function test_update_returns_404_for_missing_domain(): void
    {
        $user = $this->makeSuperAdmin();

        $response = $this->actingAs($user)->put(route('settings.update', 'no-such-domain'), [
            'fields' => ['foo' => 'bar'],
        ]);

        $response->assertNotFound();
    }
}
