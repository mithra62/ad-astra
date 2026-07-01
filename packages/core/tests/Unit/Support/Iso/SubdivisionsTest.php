<?php

namespace Tests\Unit\Support\Iso;

use AdAstra\Support\Iso\Subdivisions;
use PHPUnit\Framework\TestCase;

class SubdivisionsTest extends TestCase
{
    public function test_for_country_returns_us_states(): void
    {
        $list = Subdivisions::forCountry('US');
        $this->assertNotEmpty($list);

        $codes = array_column($list, 'code');
        $this->assertContains('US-CA', $codes);
        $this->assertContains('US-NY', $codes);
        $this->assertContains('US-DC', $codes);
    }

    public function test_for_country_returns_ca_provinces(): void
    {
        $codes = array_column(Subdivisions::forCountry('CA'), 'code');
        $this->assertContains('CA-ON', $codes);
        $this->assertContains('CA-QC', $codes);
    }

    public function test_for_country_case_insensitive(): void
    {
        $this->assertSame(Subdivisions::forCountry('US'), Subdivisions::forCountry('us'));
    }

    public function test_has_data(): void
    {
        $this->assertTrue(Subdivisions::hasData('US'));
        $this->assertTrue(Subdivisions::hasData('us'));
        $this->assertTrue(Subdivisions::hasData('CA'));
        $this->assertTrue(Subdivisions::hasData('JP'));
        $this->assertFalse(Subdivisions::hasData('XX'));
        $this->assertFalse(Subdivisions::hasData('FI')); // Finland not in v1 subset
    }

    public function test_exists(): void
    {
        $this->assertTrue(Subdivisions::exists('US', 'US-CA'));
        $this->assertTrue(Subdivisions::exists('us', 'us-ca'));
        $this->assertFalse(Subdivisions::exists('US', 'US-XX'));
        $this->assertFalse(Subdivisions::exists('XX', 'XX-YY'));
    }

    public function test_name_lookup(): void
    {
        $this->assertSame('California', Subdivisions::name('US', 'US-CA'));
        $this->assertSame('Ontario', Subdivisions::name('CA', 'CA-ON'));
    }

    public function test_name_unknown_returns_code(): void
    {
        $this->assertSame('US-XX', Subdivisions::name('US', 'US-XX'));
    }
}
