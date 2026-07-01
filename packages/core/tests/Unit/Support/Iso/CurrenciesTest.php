<?php

namespace Tests\Unit\Support\Iso;

use AdAstra\Support\Iso\Currencies;
use PHPUnit\Framework\TestCase;

class CurrenciesTest extends TestCase
{
    public function test_decimals_for_common_currencies(): void
    {
        $this->assertSame(2, Currencies::decimals('USD'));
        $this->assertSame(0, Currencies::decimals('JPY'));
        $this->assertSame(3, Currencies::decimals('BHD'));
        $this->assertSame(4, Currencies::decimals('CLF'));
    }

    public function test_decimals_for_lowercase_input(): void
    {
        $this->assertSame(2, Currencies::decimals('usd'));
    }

    public function test_decimals_unknown_currency_falls_back_to_two(): void
    {
        // ZZZ is not assigned in ISO 4217. (XXX would be a poor choice — it's
        // the official "No currency" placeholder and is in moneyphp's dataset
        // with 0 minor units.)
        $this->assertSame(2, Currencies::decimals('ZZZ'));
    }

    public function test_exists(): void
    {
        $this->assertTrue(Currencies::exists('USD'));
        $this->assertTrue(Currencies::exists('jpy'));
        $this->assertFalse(Currencies::exists('ZZZ'));
    }

    public function test_symbol_for_known_currency(): void
    {
        $this->assertSame('$', Currencies::symbol('USD'));
        $this->assertSame('€', Currencies::symbol('EUR'));
    }

    public function test_name_for_known_currency(): void
    {
        $this->assertSame('US Dollar', Currencies::name('USD'));
    }

    public function test_all_has_complete_shape(): void
    {
        $list = Currencies::all();
        $this->assertNotEmpty($list);
        $this->assertGreaterThan(140, count($list), 'Expected a substantial currency list.');

        foreach ($list as $entry) {
            $this->assertArrayHasKey('code', $entry);
            $this->assertArrayHasKey('name', $entry);
            $this->assertArrayHasKey('symbol', $entry);
            $this->assertArrayHasKey('decimals', $entry);
            $this->assertIsInt($entry['decimals']);
            $this->assertGreaterThanOrEqual(0, $entry['decimals']);
            $this->assertSame(strtoupper($entry['code']), $entry['code']);
            $this->assertSame(3, strlen($entry['code']));
        }
    }
}
