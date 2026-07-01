<?php

namespace Tests\Unit\Actions\Settings;

use AdAstra\Actions\Settings\UpdateDomainSettings;
use AdAstra\Models\SettingDomain;
use AdAstra\Models\SettingValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class UpdateDomainSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_persists_text_value_to_value_text_column(): void
    {
        $this->makeDomain('ds1');

        $this->action()->execute('ds1', ['ds1_name' => 'Hello World']);

        $this->assertDatabaseHas('setting_values', [
            'domain' => 'ds1',
            'field_handle' => 'ds1_name',
            'user_id' => null,
            'value_text' => 'Hello World',
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Register a domain in config with a text, integer, and boolean field,
     * then create the matching DB row.
     */
    private function makeDomain(string $handle): SettingDomain
    {
        config(["settings.{$handle}" => [
            'name' => strtoupper($handle),
            'fields' => [
                [
                    'handle' => "{$handle}_name",
                    'label' => 'Name',
                    'type' => 'text',
                    'default' => '',
                    'user_overridable' => false,
                    'hidden' => false,
                ],
                [
                    'handle' => "{$handle}_count",
                    'label' => 'Count',
                    'type' => 'integer',
                    'default' => 0,
                    'user_overridable' => false,
                    'hidden' => false,
                ],
                [
                    'handle' => "{$handle}_enabled",
                    'label' => 'Enabled',
                    'type' => 'boolean',
                    'default' => false,
                    'user_overridable' => false,
                    'hidden' => false,
                ],
            ],
        ]]);

        return SettingDomain::create(['name' => strtoupper($handle), 'handle' => $handle]);
    }

    private function action(): UpdateDomainSettings
    {
        return app(UpdateDomainSettings::class);
    }

    // -------------------------------------------------------------------------
    // Persistence — typed columns
    // -------------------------------------------------------------------------

    public function test_execute_persists_integer_value_to_value_integer_column(): void
    {
        $this->makeDomain('ds2');

        $this->action()->execute('ds2', ['ds2_count' => 42]);

        $this->assertDatabaseHas('setting_values', [
            'domain' => 'ds2',
            'field_handle' => 'ds2_count',
            'user_id' => null,
            'value_integer' => 42,
        ]);
    }

    public function test_execute_persists_boolean_value_to_value_boolean_column(): void
    {
        $this->makeDomain('ds3');

        $this->action()->execute('ds3', ['ds3_enabled' => true]);

        $this->assertDatabaseHas('setting_values', [
            'domain' => 'ds3',
            'field_handle' => 'ds3_enabled',
            'user_id' => null,
            'value_boolean' => true,
        ]);
    }

    public function test_execute_writes_as_system_value_with_null_user_id(): void
    {
        $this->makeDomain('ds4');

        $this->action()->execute('ds4', ['ds4_name' => 'System']);

        $this->assertDatabaseHas('setting_values', [
            'field_handle' => 'ds4_name',
            'user_id' => null,
        ]);

        $this->assertDatabaseMissing('setting_values', [
            'field_handle' => 'ds4_name',
            'user_id' => 1,
        ]);
    }

    // -------------------------------------------------------------------------
    // System scope — user_id must be null
    // -------------------------------------------------------------------------

    public function test_execute_overwrites_existing_system_value(): void
    {
        $this->makeDomain('ds5');

        SettingValue::create([
            'domain' => 'ds5',
            'field_handle' => 'ds5_name',
            'user_id' => null,
            'value_text' => 'Old Value',
        ]);

        $this->action()->execute('ds5', ['ds5_name' => 'New Value']);

        $this->assertDatabaseCount('setting_values', 1);
        $this->assertDatabaseHas('setting_values', ['value_text' => 'New Value']);
    }

    // -------------------------------------------------------------------------
    // Upsert behaviour
    // -------------------------------------------------------------------------

    public function test_execute_writes_multiple_fields_in_one_call(): void
    {
        $this->makeDomain('ds6');

        $this->action()->execute('ds6', [
            'ds6_name' => 'Acme',
            'ds6_count' => 10,
            'ds6_enabled' => true,
        ]);

        $this->assertDatabaseCount('setting_values', 3);
        $this->assertDatabaseHas('setting_values', ['field_handle' => 'ds6_name', 'value_text' => 'Acme']);
        $this->assertDatabaseHas('setting_values', ['field_handle' => 'ds6_count', 'value_integer' => 10]);
        $this->assertDatabaseHas('setting_values', ['field_handle' => 'ds6_enabled', 'value_boolean' => true]);
    }

    // -------------------------------------------------------------------------
    // Multiple fields
    // -------------------------------------------------------------------------

    public function test_execute_busts_system_domain_cache(): void
    {
        $this->makeDomain('ds7');
        Cache::put('settings.system.ds7', ['ds7_name' => 'cached'], 3600);

        $this->action()->execute('ds7', ['ds7_name' => 'fresh']);

        $this->assertNull(Cache::get('settings.system.ds7'));
    }

    // -------------------------------------------------------------------------
    // Cache invalidation
    // -------------------------------------------------------------------------

    public function test_execute_does_not_bust_cache_of_other_domains(): void
    {
        $this->makeDomain('ds8');
        Cache::put('settings.system.other_domain', ['key' => 'val'], 3600);

        $this->action()->execute('ds8', ['ds8_name' => 'value']);

        $this->assertNotNull(Cache::get('settings.system.other_domain'));
    }

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }
}
