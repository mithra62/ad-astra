<?php

namespace Tests\Unit\Settings;

use App\Models\User;
use App\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Covers the `general.appearance` setting that backs dark mode. Asserts the
 * real config wiring: it resolves light → system → user override, and persists
 * as a text token (the field is typed 'text', not 'select', so it must NOT land
 * in value_integer).
 */
class AppearanceSettingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function settings(): Settings
    {
        return app(Settings::class);
    }

    public function test_defaults_to_light_when_nothing_stored(): void
    {
        $user = User::factory()->create();

        $this->assertSame('light', $this->settings()->get('general', 'appearance', 'fallback', $user));
    }

    public function test_system_value_used_when_user_has_no_override(): void
    {
        $user = User::factory()->create();

        $this->settings()->set('general', 'appearance', 'dark', null);

        $this->assertSame('dark', $this->settings()->get('general', 'appearance', 'light', $user));
    }

    public function test_user_override_wins_over_system_value(): void
    {
        $user = User::factory()->create();

        $this->settings()->set('general', 'appearance', 'dark', null);
        $this->settings()->set('general', 'appearance', 'system', $user);

        $this->assertSame('system', $this->settings()->get('general', 'appearance', 'light', $user));
    }

    public function test_override_is_stored_in_text_column_not_integer(): void
    {
        $user = User::factory()->create();

        $this->settings()->set('general', 'appearance', 'dark', $user);

        $this->assertDatabaseHas('setting_values', [
            'domain' => 'general',
            'field_handle' => 'appearance',
            'user_id' => $user->id,
            'value_text' => 'dark',
            'value_integer' => null,
        ]);
    }
}
