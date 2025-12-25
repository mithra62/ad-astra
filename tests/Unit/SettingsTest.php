<?php

namespace Tests\Unit;

use App\Models\Settings as SettingsModel;
use App\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_constructor_loads_settings_from_database(): void
    {
        SettingsModel::factory()->create([
            'key' => 'site_name',
            'value' => 'Laravel Base',
        ]);

        $settings = new Settings();

        $this->assertEquals('Laravel Base', $settings->get('site_name'));
    }

    public function test_get_returns_default_when_key_does_not_exist(): void
    {
        $settings = new Settings();

        $this->assertEquals('default_value', $settings->get('non_existent', 'default_value'));
        $this->assertNull($settings->get('non_existent'));
    }

    public function test_set_updates_internal_settings_and_returns_self(): void
    {
        $settings = new Settings();
        $result = $settings->set('new_key', 'new_value');

        $this->assertInstanceOf(Settings::class, $result);
        $this->assertEquals('new_value', $settings->get('new_key'));
    }

    public function test_save_persists_new_settings_to_database(): void
    {
        $settings = new Settings();
        $settings->set('brand_color', '#ff0000');

        $this->assertDatabaseMissing('settings', ['key' => 'brand_color']);

        $settings->save();

        $this->assertDatabaseHas('settings', [
            'key' => 'brand_color',
            'value' => '#ff0000',
        ]);
    }

    public function test_save_updates_existing_settings_in_database(): void
    {
        SettingsModel::factory()->create([
            'key' => 'timezone',
            'value' => 'UTC',
        ]);

        $settings = new Settings();
        $settings->set('timezone', 'Europe/Paris');
        $settings->save();

        $this->assertDatabaseHas('settings', [
            'key' => 'timezone',
            'value' => 'Europe/Paris',
        ]);

        $this->assertEquals(1, SettingsModel::where('key', 'timezone')->count());
    }

    public function test_save_only_persists_changed_settings(): void
    {
        SettingsModel::factory()->create([
            'key' => 'keep_me',
            'value' => 'original',
        ]);

        $settings = new Settings();
        $settings->set('change_me', 'new_value');

        // Manually update the database behind the scenes to see if save() overwrites it if not "changed"
        SettingsModel::where('key', 'keep_me')->update(['value' => 'tampered']);

        $settings->save();

        // should still be tampered because it wasn't in $this->changed
        $this->assertDatabaseHas('settings', [
            'key' => 'keep_me',
            'value' => 'tampered',
        ]);

        $this->assertDatabaseHas('settings', [
            'key' => 'change_me',
            'value' => 'new_value',
        ]);
    }
}
