<?php

namespace Tests\Feature\Admin;

use App\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * End-to-end check that the resolved appearance preference reaches the layout's
 * no-FOUC inline theme script. Uses the guest login page (auth::_layout) so no
 * admin permissions are required. Proves the '*' View composer fires for a Twig
 * top-level view and the value is inherited by the extended layout.
 */
class DarkModeAppearanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_login_page_renders_resolved_system_appearance(): void
    {
        app(Settings::class)->set('general', 'appearance', 'dark', null);

        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee("var pref = 'dark'", false);
    }

    public function test_login_page_defaults_to_light(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee("var pref = 'light'", false);
    }
}
