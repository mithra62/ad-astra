<?php

namespace Tests\Unit\Services\SiteRouting\RouteDrivers;

use AdAstra\Services\SiteRouting\RouteDrivers\TemplateRouteDriver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use RuntimeException;
use Tests\TestCase;

/**
 * The template driver is the public catch-all: everything here is reachable by
 * unauthenticated traffic, so the reserved-group and traversal guards are
 * security boundaries, not conveniences.
 *
 * Real templates used: site/index.twig (home), about/index.twig (group index),
 * about/test.twig (action view). The tests/fixtures/templates dir supplies
 * blog/entry.twig for the entry-template fallback path.
 */
class TemplateRouteDriverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        View::prependNamespace('templates', base_path('packages/core/tests/fixtures/templates'));
    }

    private function driver(?Request $request = null): TemplateRouteDriver
    {
        return new TemplateRouteDriver($request ?? Request::create('/'));
    }

    // -------------------------------------------------------------------------
    // Home
    // -------------------------------------------------------------------------

    public function test_home_resolves_to_configured_default_template(): void
    {
        $result = $this->driver()->resolve('/');

        $this->assertNotNull($result);
        $this->assertSame('template', $result->type);
        $this->assertSame('templates::site.index', $result->template);
        $this->assertSame([], $result->data['segments']);
    }

    public function test_home_returns_null_when_default_template_does_not_exist(): void
    {
        config(['site.templates.default_template' => 'templates::site.does-not-exist']);

        $this->assertNull($this->driver()->resolve('/'));
    }

    // -------------------------------------------------------------------------
    // Reserved groups and traversal guards
    // -------------------------------------------------------------------------

    public function test_reserved_groups_are_never_resolved(): void
    {
        $driver = $this->driver();

        foreach ([
            'api', 'admin', 'login', 'logout', 'register',
            'password', 'sanctum', 'storage', 'assets', 'vendor',
        ] as $reserved) {
            $this->assertNull($driver->resolve($reserved), "'{$reserved}' should be reserved");
            $this->assertNull($driver->resolve("{$reserved}/anything"), "'{$reserved}/anything' should be reserved");
        }
    }

    public function test_reserved_group_check_is_case_insensitive(): void
    {
        $this->assertNull($this->driver()->resolve('Admin/dashboard'));
        $this->assertNull($this->driver()->resolve('API/entries'));
    }

    public function test_traversal_segments_are_rejected(): void
    {
        $driver = $this->driver();

        $this->assertNull($driver->resolve('../etc'));
        $this->assertNull($driver->resolve('about/..'));
        $this->assertNull($driver->resolve('about/../admin'));
        $this->assertNull($driver->resolve('about\\..\\admin'));
        $this->assertNull($driver->resolve('blog\\entry'));
    }

    // -------------------------------------------------------------------------
    // Group index
    // -------------------------------------------------------------------------

    public function test_single_segment_resolves_group_index_template(): void
    {
        $result = $this->driver()->resolve('/about/');

        $this->assertNotNull($result);
        $this->assertSame('templates::about.index', $result->template);
        $this->assertSame(['about'], $result->data['segments']);
        $this->assertSame('about', $result->data['segment_1']);
        $this->assertSame([], $result->data['params']);
    }

    public function test_single_segment_returns_null_when_group_index_missing(): void
    {
        $this->assertNull($this->driver()->resolve('no-such-group'));
    }

    // -------------------------------------------------------------------------
    // Second segment: action view first, entry fallback second
    // -------------------------------------------------------------------------

    public function test_matching_action_template_wins_over_entry_fallback(): void
    {
        $result = $this->driver()->resolve('about/test');

        $this->assertNotNull($result);
        $this->assertSame('templates::about.test', $result->template);
        $this->assertNull($result->data['handle']);
        $this->assertSame([], $result->data['tail']);
    }

    public function test_unmatched_second_segment_falls_back_to_entry_template(): void
    {
        $result = $this->driver()->resolve('blog/my-first-post');

        $this->assertNotNull($result);
        $this->assertSame('templates::blog.entry', $result->template);
        $this->assertSame('my-first-post', $result->data['handle']);
        $this->assertSame([], $result->data['tail']);
    }

    public function test_returns_null_when_neither_action_nor_entry_template_exists(): void
    {
        // about/ has index.twig and test.twig but no entry.twig
        $this->assertNull($this->driver()->resolve('about/no-such-entry'));
    }

    // -------------------------------------------------------------------------
    // Segment params
    // -------------------------------------------------------------------------

    public function test_trailing_segments_become_key_value_params(): void
    {
        $result = $this->driver()->resolve('blog/my-post/page/2/sort/asc');

        $this->assertNotNull($result);
        $this->assertSame(['page' => '2', 'sort' => 'asc'], $result->data['params']);
        $this->assertSame(['page', '2', 'sort', 'asc'], $result->data['tail']);
        $this->assertSame('my-post', $result->data['segment_2']);
        $this->assertSame('page', $result->data['segment_3']);
    }

    public function test_dangling_param_key_without_value_is_dropped(): void
    {
        $result = $this->driver()->resolve('blog/my-post/page/2/dangling');

        $this->assertNotNull($result);
        $this->assertSame(['page' => '2'], $result->data['params']);
    }

    public function test_request_query_string_is_exposed_as_get_data(): void
    {
        $request = Request::create('/blog/my-post', 'GET', ['preview' => '1']);

        $result = $this->driver($request)->resolve('blog/my-post');

        $this->assertNotNull($result);
        $this->assertSame(['preview' => '1'], $result->data['get']);
    }

    // -------------------------------------------------------------------------
    // Admin view leak guard
    // -------------------------------------------------------------------------

    public function test_refuses_to_render_admin_namespace_views(): void
    {
        // Even if configuration points the public router at an existing admin
        // view, the driver must refuse rather than leak it.
        config(['site.templates.default_template' => 'admin::dashboard']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('admin::dashboard');

        $this->driver()->resolve('/');
    }
}
