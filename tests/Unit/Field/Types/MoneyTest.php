<?php

namespace Tests\Unit\Field\Types;

use App\Field\Types\Money;
use App\Rules\Field\MoneyDecimalFormatRule;
use App\Rules\Field\MoneyRangeRule;
use InvalidArgumentException;
use Money\Money as PhpMoney;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    private function make(array $settings = []): Money
    {
        return new Money($settings);
    }

    public function test_storage_column_is_value_integer(): void
    {
        $this->assertSame('value_integer', $this->make()->storageColumn());
    }

    public function test_get_rules_includes_format_and_range_rules(): void
    {
        $rules = $this->make(['currency' => 'USD', 'min' => '0', 'max' => '1000'])->getRules();
        $this->assertContainsOnly('object', array_filter($rules, 'is_object'));

        $hasFormat = false;
        $hasRange = false;
        foreach ($rules as $rule) {
            if ($rule instanceof MoneyDecimalFormatRule) {
                $hasFormat = true;
            }
            if ($rule instanceof MoneyRangeRule) {
                $hasRange = true;
            }
        }
        $this->assertTrue($hasFormat, 'getRules() should include MoneyDecimalFormatRule.');
        $this->assertTrue($hasRange, 'getRules() should include MoneyRangeRule.');
    }

    public function test_prepare_for_storage_usd(): void
    {
        $this->assertSame(4250, $this->make(['currency' => 'USD'])->prepareForStorage('42.50'));
        $this->assertSame(100, $this->make(['currency' => 'USD'])->prepareForStorage('1.00'));
        $this->assertSame(100, $this->make(['currency' => 'USD'])->prepareForStorage('1'));
        $this->assertSame(-4250, $this->make(['currency' => 'USD'])->prepareForStorage('-42.50'));
    }

    public function test_prepare_for_storage_jpy(): void
    {
        $this->assertSame(100, $this->make(['currency' => 'JPY'])->prepareForStorage('100'));
        $this->assertSame(0, $this->make(['currency' => 'JPY'])->prepareForStorage('0'));
    }

    public function test_prepare_for_storage_bhd(): void
    {
        $this->assertSame(1234, $this->make(['currency' => 'BHD'])->prepareForStorage('1.234'));
        $this->assertSame(1230, $this->make(['currency' => 'BHD'])->prepareForStorage('1.23'));
    }

    public function test_prepare_for_storage_no_float_drift(): void
    {
        // 0.1 + 0.2 in float math = 0.30000000000000004. Our string-based
        // parser must produce exactly 30 cents.
        $this->assertSame(30, $this->make(['currency' => 'USD'])->prepareForStorage('0.30'));
        $this->assertSame(10, $this->make(['currency' => 'USD'])->prepareForStorage('0.10'));
        $this->assertSame(20, $this->make(['currency' => 'USD'])->prepareForStorage('0.20'));
    }

    public function test_prepare_for_storage_null_passthrough(): void
    {
        $this->assertNull($this->make()->prepareForStorage(null));
        $this->assertNull($this->make()->prepareForStorage(''));
    }

    public function test_prepare_for_storage_throws_on_garbage(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->make(['currency' => 'USD'])->prepareForStorage('garbage');
    }

    public function test_prepare_for_storage_throws_on_too_many_decimals(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->make(['currency' => 'USD'])->prepareForStorage('42.555');
    }

    public function test_prepare_for_storage_throws_on_array(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->make()->prepareForStorage([1, 2]);
    }

    public function test_cast_returns_int(): void
    {
        $result = $this->make()->cast('4250');
        $this->assertIsInt($result);
        $this->assertSame(4250, $result);
    }

    public function test_cast_passes_through_null(): void
    {
        $this->assertNull($this->make()->cast(null));
    }

    public function test_value_returns_moneyphp_money(): void
    {
        $mv = $this->make(['currency' => 'USD'])->value(4250);
        $this->assertInstanceOf(PhpMoney::class, $mv);
        $this->assertSame('4250', $mv->getAmount());
        $this->assertSame('USD', $mv->getCurrency()->getCode());
    }

    public function test_value_supports_precise_arithmetic(): void
    {
        // The whole point of switching to moneyphp — arithmetic must be exact.
        $a = $this->make(['currency' => 'USD'])->value(10);  // $0.10
        $b = $this->make(['currency' => 'USD'])->value(20);  // $0.20
        $sum = $a->add($b);
        $this->assertSame('30', $sum->getAmount());  // exactly 30 cents, no float drift
    }

    public function test_value_returns_null_for_null(): void
    {
        $this->assertNull($this->make()->value(null));
    }

    public function test_prepare_for_query_matches_storage_for_valid_input(): void
    {
        $type = $this->make(['currency' => 'USD']);
        $this->assertSame(4250, $type->prepareForQuery('42.50'));
    }

    public function test_prepare_for_query_falls_back_on_invalid_input(): void
    {
        // Invalid query input must NOT throw — should pass through so the WHERE
        // just produces zero results.
        $type = $this->make(['currency' => 'USD']);
        $this->assertSame('garbage', $type->prepareForQuery('garbage'));
    }

    public function test_settings_form_options_use_setting_key_and_value_label_shape(): void
    {
        $options = $this->make()->settingsFormOptions();
        // Key MUST match the settings_form entry it populates ("currency"),
        // not the semantic plural "currencies" — that's what the admin
        // settings-panel builder matches against.
        $this->assertArrayHasKey('currency', $options);
        $first = $options['currency'][0];
        $this->assertArrayHasKey('value', $first);
        $this->assertArrayHasKey('label', $first);
        $this->assertArrayNotHasKey('code', $first);
        $this->assertArrayNotHasKey('name', $first);
    }
}
