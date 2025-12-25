<?php

namespace Tests\Unit\Models;

use App\Models\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_has_fillable_attributes(): void
    {
        $settings = new Settings();
        $fillable = ['key', 'value'];
        $this->assertEquals($fillable, $settings->getFillable());
    }

    public function test_settings_has_correct_table_name(): void
    {
        $settings = new Settings();
        $this->assertEquals('settings', $settings->getTable());
    }

    public function test_settings_has_correct_primary_key(): void
    {
        $settings = new Settings();
        $this->assertEquals('key', $settings->getKeyName());
    }

    public function test_settings_is_not_incrementing(): void
    {
        $settings = new Settings();
        $this->assertFalse($settings->getIncrementing());
    }

    public function test_settings_can_be_created_via_factory(): void
    {
        $settings = Settings::factory()->create([
            'key' => 'site_name',
            'value' => 'My Awesome Site',
        ]);

        $this->assertDatabaseHas('settings', [
            'key' => 'site_name',
            'value' => 'My Awesome Site',
        ]);

        $this->assertEquals('site_name', $settings->key);
        $this->assertEquals('My Awesome Site', $settings->value);
    }
}
