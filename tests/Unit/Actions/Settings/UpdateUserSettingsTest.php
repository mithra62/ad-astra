<?php

namespace Tests\Unit\Actions\Settings;

use App\Actions\Settings\UpdateUserSettings;
use App\Models\SettingDomain;
use App\Models\SettingValue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class UpdateUserSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_saves_value_with_correct_user_id(): void
    {
        $this->makeDomain('us1');
        $user = User::factory()->create();

        $this->action()->execute($user, ['us1_tz' => 'Asia/Tokyo']);

        $this->assertDatabaseHas('setting_values', [
            'domain' => 'us1',
            'field_handle' => 'us1_tz',
            'user_id' => $user->id,
            'value_text' => 'Asia/Tokyo',
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Register a domain with two fields — one overridable, one not — and create
     * the matching DB row. Returns the domain model.
     */
    private function makeDomain(string $handle): SettingDomain
    {
        config(["settings.{$handle}" => [
            'name' => strtoupper($handle),
            'fields' => [
                [
                    'handle' => "{$handle}_tz",
                    'label' => 'Timezone',
                    'type' => 'text',
                    'default' => 'UTC',
                    'user_overridable' => true,
                    'hidden' => false,
                ],
                [
                    'handle' => "{$handle}_site",
                    'label' => 'Site Name',
                    'type' => 'text',
                    'default' => '',
                    'user_overridable' => false,
                    'hidden' => false,
                ],
            ],
        ]]);

        return SettingDomain::create(['name' => strtoupper($handle), 'handle' => $handle]);
    }

    private function action(): UpdateUserSettings
    {
        return app(UpdateUserSettings::class);
    }

    public function test_execute_does_not_create_system_value(): void
    {
        $this->makeDomain('us2');
        $user = User::factory()->create();

        $this->action()->execute($user, ['us2_tz' => 'Europe/Paris']);

        $this->assertDatabaseMissing('setting_values', [
            'field_handle' => 'us2_tz',
            'user_id' => null,
        ]);
    }

    public function test_execute_persists_boolean_override(): void
    {
        $this->makeBooleanDomain('us3');
        $user = User::factory()->create();

        $this->action()->execute($user, ['us3_flag' => true]);

        $this->assertDatabaseHas('setting_values', [
            'domain' => 'us3',
            'field_handle' => 'us3_flag',
            'user_id' => $user->id,
            'value_boolean' => true,
        ]);
    }

    // -------------------------------------------------------------------------
    // Persistence — user scope
    // -------------------------------------------------------------------------

    /**
     * Register a domain whose sole field is a boolean toggle, user-overridable.
     */
    private function makeBooleanDomain(string $handle): SettingDomain
    {
        config(["settings.{$handle}" => [
            'name' => strtoupper($handle),
            'fields' => [
                [
                    'handle' => "{$handle}_flag",
                    'label' => 'Flag',
                    'type' => 'boolean',
                    'default' => false,
                    'user_overridable' => true,
                    'hidden' => false,
                ],
            ],
        ]]);

        return SettingDomain::create(['name' => strtoupper($handle), 'handle' => $handle]);
    }

    public function test_execute_ignores_non_overridable_handles_in_data(): void
    {
        $this->makeDomain('us4');
        $user = User::factory()->create();

        // us4_site is not overridable — it shouldn't be written even if present
        $this->action()->execute($user, [
            'us4_tz' => 'UTC',
            'us4_site' => 'Should be ignored',
        ]);

        $this->assertDatabaseMissing('setting_values', [
            'field_handle' => 'us4_site',
            'user_id' => $user->id,
        ]);
    }

    public function test_execute_writes_nothing_for_domain_with_no_overridable_fields(): void
    {
        $this->makeNonOverridableDomain('us5');
        $user = User::factory()->create();

        $this->action()->execute($user, ['us5_admin_only' => 'attempt']);

        $this->assertDatabaseMissing('setting_values', [
            'domain' => 'us5',
            'user_id' => $user->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // Only overridable fields are written
    // -------------------------------------------------------------------------

    /**
     * Register a domain with no user-overridable fields.
     */
    private function makeNonOverridableDomain(string $handle): SettingDomain
    {
        config(["settings.{$handle}" => [
            'name' => strtoupper($handle),
            'fields' => [
                [
                    'handle' => "{$handle}_admin_only",
                    'label' => 'Admin Only',
                    'type' => 'text',
                    'default' => '',
                    'user_overridable' => false,
                    'hidden' => false,
                ],
            ],
        ]]);

        return SettingDomain::create(['name' => strtoupper($handle), 'handle' => $handle]);
    }

    public function test_execute_distributes_payload_across_multiple_domains(): void
    {
        $this->makeDomain('us6a');
        $this->makeDomain('us6b');
        $user = User::factory()->create();

        $this->action()->execute($user, [
            'us6a_tz' => 'America/Denver',
            'us6b_tz' => 'Pacific/Auckland',
        ]);

        $this->assertDatabaseHas('setting_values', [
            'domain' => 'us6a',
            'field_handle' => 'us6a_tz',
            'user_id' => $user->id,
            'value_text' => 'America/Denver',
        ]);
        $this->assertDatabaseHas('setting_values', [
            'domain' => 'us6b',
            'field_handle' => 'us6b_tz',
            'user_id' => $user->id,
            'value_text' => 'Pacific/Auckland',
        ]);
    }

    // -------------------------------------------------------------------------
    // Multi-domain distribution
    // -------------------------------------------------------------------------

    public function test_execute_skips_domain_entirely_when_no_matching_handles_in_data(): void
    {
        $this->makeDomain('us7a');
        $this->makeDomain('us7b');
        $user = User::factory()->create();

        // Only submit data for us7a — us7b should produce no DB rows
        $this->action()->execute($user, ['us7a_tz' => 'UTC']);

        $this->assertDatabaseMissing('setting_values', [
            'domain' => 'us7b',
            'user_id' => $user->id,
        ]);
    }

    public function test_execute_overwrites_existing_user_override(): void
    {
        $this->makeDomain('us8');
        $user = User::factory()->create();

        SettingValue::create([
            'domain' => 'us8',
            'field_handle' => 'us8_tz',
            'user_id' => $user->id,
            'value_text' => 'UTC',
        ]);

        $this->action()->execute($user, ['us8_tz' => 'Asia/Seoul']);

        $this->assertDatabaseCount('setting_values', 1);
        $this->assertDatabaseHas('setting_values', ['value_text' => 'Asia/Seoul']);
    }

    // -------------------------------------------------------------------------
    // Upsert behaviour
    // -------------------------------------------------------------------------

    public function test_execute_does_not_affect_another_users_settings(): void
    {
        $this->makeDomain('us9');
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        SettingValue::create([
            'domain' => 'us9',
            'field_handle' => 'us9_tz',
            'user_id' => $userB->id,
            'value_text' => 'Europe/London',
        ]);

        $this->action()->execute($userA, ['us9_tz' => 'America/New_York']);

        $this->assertDatabaseHas('setting_values', [
            'field_handle' => 'us9_tz',
            'user_id' => $userB->id,
            'value_text' => 'Europe/London',
        ]);
    }

    // -------------------------------------------------------------------------
    // Isolation between users
    // -------------------------------------------------------------------------

    public function test_execute_busts_user_cache_for_affected_domain(): void
    {
        $this->makeDomain('us10');
        $user = User::factory()->create();

        Cache::put("settings.user.{$user->id}.us10", ['us10_tz' => 'UTC'], 3600);

        $this->action()->execute($user, ['us10_tz' => 'Asia/Kolkata']);

        $this->assertNull(Cache::get("settings.user.{$user->id}.us10"));
    }

    // -------------------------------------------------------------------------
    // Cache invalidation
    // -------------------------------------------------------------------------

    public function test_execute_does_not_bust_another_users_cache(): void
    {
        $this->makeDomain('us11');
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        Cache::put("settings.user.{$userA->id}.us11", ['us11_tz' => 'UTC'], 3600);
        Cache::put("settings.user.{$userB->id}.us11", ['us11_tz' => 'UTC'], 3600);

        $this->action()->execute($userA, ['us11_tz' => 'Asia/Kolkata']);

        $this->assertNull(Cache::get("settings.user.{$userA->id}.us11"));
        $this->assertNotNull(Cache::get("settings.user.{$userB->id}.us11"));
    }

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }
}
