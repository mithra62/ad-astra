<?php

namespace Tests\Unit\Services\Settings;

use AdAstra\Models\SettingDomain;
use AdAstra\Models\SettingValue;
use AdAstra\Models\User;
use AdAstra\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SettingsResolverTest extends TestCase
{
    use RefreshDatabase;

    private Settings $settings;

    public function test_get_returns_null_when_no_value_and_no_default(): void
    {
        config(['settings.g1' => [
            'name' => 'G1',
            'fields' => [
                // No 'default' key
                ['handle' => 'g1_count', 'label' => 'Count', 'type' => 'integer', 'user_overridable' => false, 'hidden' => false],
            ],
        ]]);
        SettingDomain::create(['name' => 'G1', 'handle' => 'g1']);

        $result = $this->settings->get('g1', 'g1_count');

        $this->assertNull($result);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function test_get_returns_config_default_when_no_db_value(): void
    {
        $this->makeDomain('g2');

        $result = $this->settings->get('g2', 'g2_timezone');

        $this->assertSame('UTC', $result);
    }

    // -------------------------------------------------------------------------
    // get()
    // -------------------------------------------------------------------------

    /**
     * Register a domain in config with a text "timezone" and integer "count" field,
     * then create the matching SettingDomain DB row.
     */
    private function makeDomain(string $handle = 'test'): SettingDomain
    {
        config(["settings.{$handle}" => [
            'name' => ucfirst($handle),
            'fields' => [
                [
                    'handle' => "{$handle}_timezone",
                    'label' => 'Timezone',
                    'type' => 'text',
                    'default' => 'UTC',
                    'user_overridable' => true,
                    'hidden' => false,
                ],
                [
                    'handle' => "{$handle}_count",
                    'label' => 'Count',
                    'type' => 'integer',
                    'default' => 10,
                    'user_overridable' => false,
                    'hidden' => false,
                ],
            ],
        ]]);

        return SettingDomain::create(['name' => ucfirst($handle), 'handle' => $handle]);
    }

    public function test_get_returns_system_value_over_config_default(): void
    {
        $this->makeDomain('g3');

        SettingValue::create([
            'domain' => 'g3', 'field_handle' => 'g3_timezone',
            'user_id' => null, 'value_text' => 'Europe/London',
        ]);

        $result = $this->settings->get('g3', 'g3_timezone');

        $this->assertSame('Europe/London', $result);
    }

    public function test_get_returns_provided_default_when_domain_config_missing(): void
    {
        $result = $this->settings->get('nonexistent', 'some_key', 'fallback');

        $this->assertSame('fallback', $result);
    }

    public function test_set_creates_system_value_in_correct_typed_column(): void
    {
        $this->makeDomain('s1');

        $this->settings->set('s1', 's1_timezone', 'Asia/Tokyo');

        $this->assertDatabaseHas('setting_values', [
            'domain' => 's1',
            'field_handle' => 's1_timezone',
            'user_id' => null,
            'value_text' => 'Asia/Tokyo',
        ]);
    }

    // -------------------------------------------------------------------------
    // set()
    // -------------------------------------------------------------------------

    public function test_set_writes_integer_to_value_integer_column(): void
    {
        $this->makeDomain('s2');

        $this->settings->set('s2', 's2_count', 42);

        $this->assertDatabaseHas('setting_values', [
            'domain' => 's2',
            'field_handle' => 's2_count',
            'user_id' => null,
            'value_integer' => 42,
        ]);
    }

    public function test_set_creates_user_override(): void
    {
        $this->makeDomain('s3');
        $user = User::factory()->create();

        $this->settings->set('s3', 's3_timezone', 'America/Chicago', $user);

        $this->assertDatabaseHas('setting_values', [
            'domain' => 's3',
            'field_handle' => 's3_timezone',
            'user_id' => $user->id,
            'value_text' => 'America/Chicago',
        ]);
    }

    public function test_set_updates_existing_system_value(): void
    {
        $this->makeDomain('s4');

        SettingValue::create(['domain' => 's4', 'field_handle' => 's4_timezone', 'user_id' => null, 'value_text' => 'UTC']);

        $this->settings->set('s4', 's4_timezone', 'Pacific/Auckland');

        $this->assertDatabaseCount('setting_values', 1);
        $this->assertDatabaseHas('setting_values', ['value_text' => 'Pacific/Auckland']);
    }

    public function test_set_busts_system_cache(): void
    {
        $this->makeDomain('s5');
        Cache::put('settings.system.s5', ['s5_timezone' => 'UTC'], 3600);

        $this->settings->set('s5', 's5_timezone', 'America/Denver');

        $this->assertNull(Cache::get('settings.system.s5'));
    }

    public function test_set_busts_user_cache(): void
    {
        $this->makeDomain('s6');
        $user = User::factory()->create();

        Cache::put("settings.user.{$user->id}.s6", ['s6_timezone' => 'UTC'], 3600);

        $this->settings->set('s6', 's6_timezone', 'America/Denver', $user);

        $this->assertNull(Cache::get("settings.user.{$user->id}.s6"));
    }

    public function test_system_returns_config_defaults_when_no_db_rows(): void
    {
        $this->makeDomain('a1');

        $result = $this->settings->system('a1');

        $this->assertSame('UTC', $result['a1_timezone']);
        $this->assertSame(10, $result['a1_count']);
    }

    // -------------------------------------------------------------------------
    // all() / system() — resolution order
    // -------------------------------------------------------------------------

    public function test_all_user_override_takes_precedence_over_system(): void
    {
        $this->makeDomain('a2');
        $user = User::factory()->create();

        SettingValue::create(['domain' => 'a2', 'field_handle' => 'a2_timezone', 'user_id' => null, 'value_text' => 'UTC']);
        SettingValue::create(['domain' => 'a2', 'field_handle' => 'a2_timezone', 'user_id' => $user->id, 'value_text' => 'America/New_York']);

        $result = $this->settings->all('a2', $user);

        $this->assertSame('America/New_York', $result['a2_timezone']);
    }

    public function test_all_falls_back_to_system_when_no_user_override(): void
    {
        $this->makeDomain('a3');
        $user = User::factory()->create();

        SettingValue::create(['domain' => 'a3', 'field_handle' => 'a3_timezone', 'user_id' => null, 'value_text' => 'Europe/Berlin']);

        $result = $this->settings->all('a3', $user);

        $this->assertSame('Europe/Berlin', $result['a3_timezone']);
    }

    public function test_integer_value_is_returned_as_native_int(): void
    {
        $this->makeDomain('a4');

        SettingValue::create(['domain' => 'a4', 'field_handle' => 'a4_count', 'user_id' => null, 'value_integer' => 42]);

        $result = $this->settings->system('a4');

        $this->assertIsInt($result['a4_count']);
        $this->assertSame(42, $result['a4_count']);
    }

    public function test_column_for_maps_all_types_correctly(): void
    {
        $this->assertSame('value_text', $this->settings->columnFor('text'));
        $this->assertSame('value_text', $this->settings->columnFor('email'));
        $this->assertSame('value_text', $this->settings->columnFor('textarea'));
        $this->assertSame('value_text', $this->settings->columnFor('unknown_type'));
        $this->assertSame('value_integer', $this->settings->columnFor('integer'));
        $this->assertSame('value_float', $this->settings->columnFor('float'));
        $this->assertSame('value_boolean', $this->settings->columnFor('boolean'));
        $this->assertSame('value_json', $this->settings->columnFor('json'));
    }

    // -------------------------------------------------------------------------
    // columnFor()
    // -------------------------------------------------------------------------

    public function test_system_values_are_cached_after_first_access(): void
    {
        $this->makeDomain('c1');
        SettingValue::create(['domain' => 'c1', 'field_handle' => 'c1_timezone', 'user_id' => null, 'value_text' => 'UTC']);

        $this->settings->system('c1');

        $this->assertNotNull(Cache::get('settings.system.c1'));
    }

    // -------------------------------------------------------------------------
    // Caching
    // -------------------------------------------------------------------------

    public function test_user_values_are_cached_after_first_access(): void
    {
        $this->makeDomain('c2');
        $user = User::factory()->create();

        SettingValue::create(['domain' => 'c2', 'field_handle' => 'c2_timezone', 'user_id' => $user->id, 'value_text' => 'UTC']);

        $this->settings->all('c2', $user);

        $this->assertNotNull(Cache::get("settings.user.{$user->id}.c2"));
    }

    public function test_bust_clears_system_cache(): void
    {
        Cache::put('settings.system.bx', ['k' => 'v'], 3600);

        $this->settings->bust('bx');

        $this->assertNull(Cache::get('settings.system.bx'));
    }

    // -------------------------------------------------------------------------
    // Cache invalidation — read → write → read (the real-world sequence)
    //
    // These tests verify the end-to-end contract: a get() call that populates
    // the cache followed by a set()/setMany() write must return the new value
    // on the next get(), not the stale cached one.
    // -------------------------------------------------------------------------

    public function test_get_returns_new_system_value_after_set(): void
    {
        $this->makeDomain('rw1');
        SettingValue::create(['domain' => 'rw1', 'field_handle' => 'rw1_timezone', 'user_id' => null, 'value_text' => 'UTC']);

        // Prime the cache.
        $this->assertSame('UTC', $this->settings->get('rw1', 'rw1_timezone'));

        // Write a new value — must bust the cache.
        $this->settings->set('rw1', 'rw1_timezone', 'Europe/Paris');

        // Next read must reflect the DB update, not the stale cached entry.
        $this->assertSame('Europe/Paris', $this->settings->get('rw1', 'rw1_timezone'));
    }

    public function test_get_returns_new_user_value_after_set(): void
    {
        $this->makeDomain('rw2');
        $user = User::factory()->create();

        SettingValue::create(['domain' => 'rw2', 'field_handle' => 'rw2_timezone', 'user_id' => $user->id, 'value_text' => 'UTC']);

        // Prime the cache with the old user override.
        $this->assertSame('UTC', $this->settings->get('rw2', 'rw2_timezone', null, $user));

        // Write a new user override.
        $this->settings->set('rw2', 'rw2_timezone', 'Asia/Tokyo', $user);

        // Must not serve the stale cache.
        $this->assertSame('Asia/Tokyo', $this->settings->get('rw2', 'rw2_timezone', null, $user));
    }

    public function test_get_returns_new_system_values_after_set_many(): void
    {
        $this->makeDomain('rw3');
        SettingValue::create(['domain' => 'rw3', 'field_handle' => 'rw3_timezone', 'user_id' => null, 'value_text' => 'UTC']);
        SettingValue::create(['domain' => 'rw3', 'field_handle' => 'rw3_count', 'user_id' => null, 'value_integer' => 5]);

        // Prime the cache.
        $this->settings->system('rw3');

        // Bulk write.
        $this->settings->setMany('rw3', ['rw3_timezone' => 'America/Chicago', 'rw3_count' => 99]);

        $result = $this->settings->system('rw3');
        $this->assertSame('America/Chicago', $result['rw3_timezone']);
        $this->assertSame(99, $result['rw3_count']);
    }

    public function test_user_override_does_not_poison_system_cache(): void
    {
        $this->makeDomain('rw4');
        $user = User::factory()->create();

        SettingValue::create(['domain' => 'rw4', 'field_handle' => 'rw4_timezone', 'user_id' => null, 'value_text' => 'UTC']);

        // Prime both system and user caches.
        $this->settings->system('rw4');
        $this->settings->all('rw4', $user);

        // Write a user override — must only bust the user cache, not the system cache.
        $this->settings->set('rw4', 'rw4_timezone', 'Pacific/Auckland', $user);

        // System value must remain unchanged.
        $this->assertSame('UTC', $this->settings->system('rw4')['rw4_timezone']);

        // User read must reflect the override.
        $this->assertSame('Pacific/Auckland', $this->settings->get('rw4', 'rw4_timezone', null, $user));
    }

    // -------------------------------------------------------------------------
    // bust() and bustDomain()
    // -------------------------------------------------------------------------

    public function test_bust_clears_user_cache(): void
    {
        $user = User::factory()->create();
        Cache::put("settings.user.{$user->id}.bx", ['k' => 'v'], 3600);

        $this->settings->bust('bx', $user);

        $this->assertNull(Cache::get("settings.user.{$user->id}.bx"));
    }

    public function test_bust_domain_clears_system_and_all_user_caches(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $this->makeDomain('bd');

        SettingValue::create(['domain' => 'bd', 'field_handle' => 'bd_timezone', 'user_id' => $userA->id, 'value_text' => 'UTC']);
        SettingValue::create(['domain' => 'bd', 'field_handle' => 'bd_timezone', 'user_id' => $userB->id, 'value_text' => 'UTC']);

        Cache::put('settings.system.bd', [], 3600);
        Cache::put("settings.user.{$userA->id}.bd", [], 3600);
        Cache::put("settings.user.{$userB->id}.bd", [], 3600);

        $this->settings->bustDomain('bd');

        $this->assertNull(Cache::get('settings.system.bd'));
        $this->assertNull(Cache::get("settings.user.{$userA->id}.bd"));
        $this->assertNull(Cache::get("settings.user.{$userB->id}.bd"));
    }

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->settings = new Settings;
    }
}
