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

    public function test_index_redirects_guests_to_login(): void
    {
        $response = $this->get(route('settings.show', 'general'));

        $response->assertRedirect(route('login'));
    }

    private function makeSuperAdmin(): User
    {
        $role = Role::firstOrCreate(['name' => 'super admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);
        return $user;
    }

    // -------------------------------------------------------------------------
    // index
    // -------------------------------------------------------------------------

    /**
     * Register a domain in config and create its DB row.
     * Defines a single text field so the form has something to render and submit.
     */
    private function makeDomain(string $handle = 'td_test'): SettingDomain
    {
        config(["settings.{$handle}" => [
            'name' => 'Test Domain',
            'description' => 'Feature test domain.',
            'icon' => null,
            'sort_order' => 99,
            'fields' => [
                [
                    'handle' => "{$handle}_site_name",
                    'label' => 'Site Name',
                    'type' => 'text',
                    'default' => '',
                    'instructions' => null,
                    'group' => null,
                    'hidden' => false,
                    'user_overridable' => false,
                ],
            ],
        ]]);

        return SettingDomain::create([
            'name' => 'Test Domain',
            'handle' => $handle,
            'description' => 'Feature test domain.',
            'sort_order' => 99,
        ]);
    }

    public function test_show_redirects_guests_to_login(): void
    {
        $this->makeDomain();

        $response = $this->get(route('settings.show', 'td_test'));

        $response->assertRedirect(route('login'));
    }

    // -------------------------------------------------------------------------
    // show
    // -------------------------------------------------------------------------

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
            'domain' => 'td2',
            'field_handle' => 'td2_site_name',
            'user_id' => null,
            'value_text' => 'My Existing Site',
        ]);

        $response = $this->actingAs($user)->get(route('settings.show', 'td2'));

        $response->assertOk();
        $response->assertSee('My Existing Site');
    }

    public function test_update_redirects_guests_to_login(): void
    {
        $this->makeDomain();

        $response = $this->put(route('settings.update', 'td_test'), [
            'td_test_site_name' => 'New Value',
        ]);

        $response->assertRedirect(route('login'));
    }

    // -------------------------------------------------------------------------
    // update
    // -------------------------------------------------------------------------

    public function test_update_persists_system_setting_to_typed_column(): void
    {
        $user = $this->makeSuperAdmin();
        $this->makeDomain('td3');

        $this->actingAs($user)->put(route('settings.update', 'td3'), [
            'td3_site_name' => 'Hello World',
        ]);

        $this->assertDatabaseHas('setting_values', [
            'domain' => 'td3',
            'field_handle' => 'td3_site_name',
            'user_id' => null,
            'value_text' => 'Hello World',
        ]);
    }

    public function test_update_redirects_back_to_domain_show_with_success(): void
    {
        $user = $this->makeSuperAdmin();
        $this->makeDomain('td4');

        $response = $this->actingAs($user)->put(route('settings.update', 'td4'), [
            'td4_site_name' => 'Redirect Test',
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
            'td5_site_name' => 'New Value',
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
            'td6_site_name' => 'Fresh Value',
        ]);

        $this->assertNull(Cache::get('settings.system.td6'));
    }

    public function test_update_returns_404_for_missing_domain(): void
    {
        $user = $this->makeSuperAdmin();

        $response = $this->actingAs($user)->put(route('settings.update', 'no-such-domain'), [
            'foo' => 'bar',
        ]);

        $response->assertNotFound();
    }

    public function test_update_fails_validation_when_required_field_is_missing(): void
    {
        $user = $this->makeSuperAdmin();

        config(['settings.vd1' => [
            'name' => 'VD1',
            'fields' => [
                [
                    'handle' => 'vd1_name',
                    'label' => 'Name',
                    'type' => 'text',
                    'default' => '',
                    'rules' => ['required', 'string'],
                    'hidden' => false,
                    'user_overridable' => false,
                ],
            ],
        ]]);
        SettingDomain::create(['name' => 'VD1', 'handle' => 'vd1', 'sort_order' => 99]);

        $response = $this->actingAs($user)->put(route('settings.update', 'vd1'), [
            'vd1_name' => '',
        ]);

        $response->assertSessionHasErrors('vd1_name');
        $this->assertDatabaseMissing('setting_values', ['domain' => 'vd1']);
    }

    // -------------------------------------------------------------------------
    // Validation
    // -------------------------------------------------------------------------

    public function test_update_fails_validation_when_integer_field_receives_string(): void
    {
        $user = $this->makeSuperAdmin();

        config(['settings.vd2' => [
            'name' => 'VD2',
            'fields' => [
                [
                    'handle' => 'vd2_count',
                    'label' => 'Count',
                    'type' => 'integer',
                    'default' => 10,
                    'rules' => ['required', 'integer', 'min:1'],
                    'hidden' => false,
                    'user_overridable' => false,
                ],
            ],
        ]]);
        SettingDomain::create(['name' => 'VD2', 'handle' => 'vd2', 'sort_order' => 99]);

        $response = $this->actingAs($user)->put(route('settings.update', 'vd2'), [
            'vd2_count' => 'not-a-number',
        ]);

        $response->assertSessionHasErrors('vd2_count');
        $this->assertDatabaseMissing('setting_values', ['domain' => 'vd2']);
    }

    public function test_update_passes_validation_when_nullable_field_is_empty(): void
    {
        $user = $this->makeSuperAdmin();

        config(['settings.vd3' => [
            'name' => 'VD3',
            'fields' => [
                [
                    'handle' => 'vd3_email',
                    'label' => 'Email',
                    'type' => 'text',
                    'default' => '',
                    'rules' => ['email'],   // no 'required' — nullable prepended
                    'hidden' => false,
                    'user_overridable' => false,
                ],
            ],
        ]]);
        SettingDomain::create(['name' => 'VD3', 'handle' => 'vd3', 'sort_order' => 99]);

        $response = $this->actingAs($user)->put(route('settings.update', 'vd3'), [
            'vd3_email' => '',
        ]);

        $response->assertRedirect(route('settings.show', 'vd3'));
        $response->assertSessionHasNoErrors();
    }

    public function test_update_fails_validation_when_nullable_field_has_invalid_value(): void
    {
        $user = $this->makeSuperAdmin();

        config(['settings.vd4' => [
            'name' => 'VD4',
            'fields' => [
                [
                    'handle' => 'vd4_email',
                    'label' => 'Email',
                    'type' => 'text',
                    'default' => '',
                    'rules' => ['email'],
                    'hidden' => false,
                    'user_overridable' => false,
                ],
            ],
        ]]);
        SettingDomain::create(['name' => 'VD4', 'handle' => 'vd4', 'sort_order' => 99]);

        $response = $this->actingAs($user)->put(route('settings.update', 'vd4'), [
            'vd4_email' => 'not-an-email',
        ]);

        $response->assertSessionHasErrors('vd4_email');
    }

    public function test_validation_error_messages_use_field_label(): void
    {
        $user = $this->makeSuperAdmin();

        config(['settings.vd5' => [
            'name' => 'VD5',
            'fields' => [
                [
                    'handle' => 'vd5_title',
                    'label' => 'Page Title',
                    'type' => 'text',
                    'default' => '',
                    'rules' => ['required', 'string'],
                    'hidden' => false,
                    'user_overridable' => false,
                ],
            ],
        ]]);
        SettingDomain::create(['name' => 'VD5', 'handle' => 'vd5', 'sort_order' => 99]);

        $response = $this->actingAs($user)->put(route('settings.update', 'vd5'), [
            'vd5_title' => '',
        ]);

        // Error message should reference the label, not the handle
        $response->assertSessionHasErrors('vd5_title');
        $errors = session('errors')->getBag('default');
        $this->assertStringContainsString('Page Title', $errors->first('vd5_title'));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
        Cache::flush();
    }
}
