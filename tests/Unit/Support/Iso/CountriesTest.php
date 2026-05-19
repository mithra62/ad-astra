<?php

namespace Tests\Unit\Support\Iso;

use App\Support\Iso\Countries;
use PHPUnit\Framework\TestCase;

class CountriesTest extends TestCase
{
    public function test_exists_for_known_codes(): void
    {
        $this->assertTrue(Countries::exists('US'));
        $this->assertTrue(Countries::exists('us'));
        $this->assertTrue(Countries::exists('GB'));
        $this->assertTrue(Countries::exists('JP'));
    }

    public function test_exists_for_unknown_codes(): void
    {
        $this->assertFalse(Countries::exists('XX'));
        $this->assertFalse(Countries::exists(''));
        $this->assertFalse(Countries::exists('USA'));
    }

    public function test_name_for_known_codes(): void
    {
        $this->assertSame('United States', Countries::name('US'));
        $this->assertSame('United Kingdom', Countries::name('GB'));
    }

    public function test_name_unknown_returns_code(): void
    {
        $this->assertSame('XX', Countries::name('XX'));
    }

    public function test_all_has_complete_shape(): void
    {
        $list = Countries::all();
        $this->assertNotEmpty($list);
        $this->assertGreaterThan(240, count($list), 'Expected the full ISO 3166-1 list.');

        foreach ($list as $entry) {
            $this->assertArrayHasKey('code', $entry);
            $this->assertArrayHasKey('name', $entry);
            $this->assertSame(2, strlen($entry['code']));
            $this->assertSame(strtoupper($entry['code']), $entry['code']);
            $this->assertNotEmpty($entry['name']);
        }
    }
}
