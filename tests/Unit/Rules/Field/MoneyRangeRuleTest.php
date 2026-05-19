<?php

namespace Tests\Unit\Rules\Field;

use App\Rules\Field\MoneyRangeRule;
use PHPUnit\Framework\TestCase;

class MoneyRangeRuleTest extends TestCase
{
    private function runRule(MoneyRangeRule $rule, mixed $value): ?string
    {
        $error = null;
        $rule->validate('amount', $value, function (string $msg) use (&$error) {
            $error = $msg;
        });
        return $error;
    }

    public function test_passes_value_in_range(): void
    {
        $this->assertNull($this->runRule(new MoneyRangeRule('0', '100', 'USD'), '50.00'));
    }

    public function test_rejects_below_min(): void
    {
        $error = $this->runRule(new MoneyRangeRule('10', '100', 'USD'), '5.00');
        $this->assertNotNull($error);
        $this->assertStringContainsString('at least 10', $error);
    }

    public function test_rejects_above_max(): void
    {
        $error = $this->runRule(new MoneyRangeRule('0', '100', 'USD'), '101.00');
        $this->assertNotNull($error);
        $this->assertStringContainsString('at most 100', $error);
    }

    public function test_boundary_values_accepted(): void
    {
        $rule = new MoneyRangeRule('0', '100', 'USD');
        $this->assertNull($this->runRule($rule, '0.00'));
        $this->assertNull($this->runRule($rule, '100.00'));
    }

    public function test_null_passes(): void
    {
        $this->assertNull($this->runRule(new MoneyRangeRule('0', '100', 'USD'), null));
    }

    public function test_no_bounds_passes_anything_well_formed(): void
    {
        $this->assertNull($this->runRule(new MoneyRangeRule(null, null, 'USD'), '99999.99'));
    }

    public function test_negative_minimum_allows_negative_value(): void
    {
        $this->assertNull($this->runRule(new MoneyRangeRule('-100', '100', 'USD'), '-50.00'));
    }

    public function test_invalid_format_passes_silently(): void
    {
        // Format errors are MoneyDecimalFormatRule's job; range rule must not double-report.
        $this->assertNull($this->runRule(new MoneyRangeRule('0', '100', 'USD'), 'abc'));
    }

    public function test_no_float_drift_at_boundary(): void
    {
        // 0.1 + 0.2 = 0.30000000000000004 in float math; integer minor units
        // produce exactly 30 cents so this must accept "0.30" against max "0.30".
        $this->assertNull($this->runRule(new MoneyRangeRule('0', '0.30', 'USD'), '0.30'));
        $this->assertNotNull($this->runRule(new MoneyRangeRule('0', '0.30', 'USD'), '0.31'));
    }
}
