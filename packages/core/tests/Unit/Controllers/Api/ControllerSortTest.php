<?php

namespace Tests\Unit\Controllers\Api;

use AdAstra\Http\Controllers\Api\Controller;
use Illuminate\Http\Request;
use Tests\TestCase;

class ControllerSortTest extends TestCase
{
    private Controller $controller;

    public function test_sort_returns_id_when_no_param_provided(): void
    {
        $result = $this->controller->sort($this->requestWith([]));

        $this->assertSame('id', $result);
    }

    private function requestWith(array $params): Request
    {
        return Request::create('/', 'GET', $params);
    }

    // -------------------------------------------------------------------------
    // sort()
    // -------------------------------------------------------------------------

    public function test_sort_returns_requested_column_when_in_allowlist(): void
    {
        $result = $this->controller->sort(
            $this->requestWith(['sort' => 'created_at']),
            ['id', 'created_at', 'updated_at'],
        );

        $this->assertSame('created_at', $result);
    }

    public function test_sort_falls_back_to_id_when_column_not_in_allowlist(): void
    {
        $result = $this->controller->sort(
            $this->requestWith(['sort' => 'password']),
            ['id', 'name', 'created_at'],
        );

        $this->assertSame('id', $result);
    }

    public function test_sort_falls_back_to_id_for_unlisted_sensitive_column(): void
    {
        $result = $this->controller->sort(
            $this->requestWith(['sort' => 'two_factor_secret']),
            ['id', 'name'],
        );

        $this->assertSame('id', $result);
    }

    public function test_sort_uses_default_allowlist_when_none_provided(): void
    {
        $result = $this->controller->sort(
            $this->requestWith(['sort' => 'name']), // not in the default list
        );

        $this->assertSame('id', $result);
    }

    public function test_sort_returns_updated_at_when_in_default_allowlist(): void
    {
        $result = $this->controller->sort(
            $this->requestWith(['sort' => 'updated_at']),
        );

        $this->assertSame('updated_at', $result);
    }

    public function test_sort_uses_strict_comparison_rejecting_numeric_coercions(): void
    {
        $result = $this->controller->sort(
            $this->requestWith(['sort' => '0']),
            ['id', 'name'],
        );

        $this->assertSame('id', $result);
    }

    public function test_sort_dir_defaults_to_asc(): void
    {
        $result = $this->controller->sortDir($this->requestWith([]));

        $this->assertSame('asc', $result);
    }

    // -------------------------------------------------------------------------
    // sortDir()
    // -------------------------------------------------------------------------

    public function test_sort_dir_returns_desc_when_requested(): void
    {
        $result = $this->controller->sortDir($this->requestWith(['direction' => 'desc']));

        $this->assertSame('desc', $result);
    }

    public function test_sort_dir_returns_asc_when_requested(): void
    {
        $result = $this->controller->sortDir($this->requestWith(['direction' => 'asc']));

        $this->assertSame('asc', $result);
    }

    public function test_sort_dir_falls_back_to_asc_for_invalid_value(): void
    {
        $result = $this->controller->sortDir($this->requestWith(['direction' => 'FOOBAR']));

        $this->assertSame('asc', $result);
    }

    public function test_sort_dir_is_case_insensitive(): void
    {
        $result = $this->controller->sortDir($this->requestWith(['direction' => 'DESC']));

        $this->assertSame('desc', $result);
    }

    public function test_sort_dir_rejects_sql_injection_attempt(): void
    {
        $result = $this->controller->sortDir($this->requestWith(['direction' => 'asc; DROP TABLE users']));

        $this->assertSame('asc', $result);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new class extends Controller {
            public function sort(Request $request, array $allowed = ['id', 'created_at', 'updated_at']): string
            {
                return parent::sort($request, $allowed);
            }

            public function sortDir(Request $request): string
            {
                return parent::sortDir($request);
            }
        };
    }
}
