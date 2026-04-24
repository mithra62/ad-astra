<?php

namespace Tests\Unit\Models;

use App\Models\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_has_correct_fillable_attributes(): void
    {
        $model = new Settings;

        $this->assertEquals(['key', 'value'], $model->getFillable());
    }

    public function test_uses_settings_table(): void
    {
        $model = new Settings;

        $this->assertEquals('settings', $model->getTable());
    }

    public function test_primary_key_is_key(): void
    {
        $model = new Settings;

        $this->assertEquals('key', $model->getKeyName());
    }

    public function test_is_not_auto_incrementing(): void
    {
        $model = new Settings;

        $this->assertFalse($model->getIncrementing());
    }

    public function test_can_create_and_retrieve_by_string_key(): void
    {
        Settings::factory()->create(['key' => 'site_name', 'value' => 'My App']);

        $setting = Settings::find('site_name');

        $this->assertNotNull($setting);
        $this->assertEquals('My App', $setting->value);
    }

    public function test_can_update_setting_value(): void
    {
        Settings::factory()->create(['key' => 'theme', 'value' => 'light']);

        Settings::where('key', 'theme')->update(['value' => 'dark']);

        $this->assertEquals('dark', Settings::find('theme')->value);
    }
}
