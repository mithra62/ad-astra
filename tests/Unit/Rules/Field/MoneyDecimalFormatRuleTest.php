<?php

namespace Tests\Unit\Rules\Field;

use App\Rules\Field\MoneyDecimalFormatRule;
use PHPUnit\Framework\TestCase;

class MoneyDecimalFormatRuleTest extends TestCase
{
    private function runRule(MoneyDecimalFormatRule $rule, mixed $value): ?string
    {
        $error = null;
        $rule->validate('amount', $value, function (string $msg) use (&$error) {
            $error = $msg;
        });
        return $error;
    }

    public function test_accepts_integer_string_usd(): void
    {
        $this->assertNull($this->runRule(new MoneyDecimalFormatRule('USD'), '42'));
    }

    public function test_accepts_two_decimal_places_usd(): void
    {
        $this->assertNull($this->runRule(new MoneyDecimalFormatRule('USD'), '42.50'));
    }

    public function test_accepts_negative(): void
    {
        $this->assertNull($this->runRule(new MoneyDecimalFormatRule('USD'), '-3.14'));
    }

    public function test_accepts_null_and_empty(): void
    {
        $this->assertNull($this->runRule(new MoneyDecimalFormatRule('USD'), null));
        $this->assertNull($this->runRule(new MoneyDecimalFormatRule('USD'), ''));
    }

    public function test_rejects_non_numeric_string(): void
    {
        $error = $this->runRule(new MoneyDecimalFormatRule('USD'), 'abc');
        $this->assertNotNull($error);
        $this->assertStringContainsString('valid decimal', $error);
    }

    public function test_rejects_double_dot(): void
    {
        $this->assertNotNull($this->runRule(new MoneyDecimalFormatRule('USD'), '4.2.0'));
    }

    public function test_rejects_array(): void
    {
        $this->assertNotNull($this->runRule(new MoneyDecimalFormatRule('USD'), [1, 2]));
    }

    public function test_rejects_too_many_decimals_for_usd(): void
    {
        $error = $this->runRule(new MoneyDecimalFormatRule('USD'), '42.555');
        $this->assertNotNull($error);
        $this->assertStringContainsString('2 decimal places', $error);
        $this->assertStringContainsString('USD', $error);
    }

    public function test_jpy_rejects_any_fractional_digit(): void
    {
        $error = $this->runRule(new MoneyDecimalFormatRule('JPY'), '100.5');
        $this->assertNotNull($error);
        $this->assertStringContainsString('0 decimal places', $error);
        $this->assertStringContainsString('JPY', $error);
    }

    public function test_bhd_accepts_three_decimal_places(): void
    {
        $this->assertNull($this->runRule(new MoneyDecimalFormatRule('BHD'), '1.234'));
    }

    public function test_bhd_rejects_four_decimal_places(): void
    {
        $this->assertNotNull($this->runRule(new MoneyDecimalFormatRule('BHD'), '1.2345'));
    }
}
