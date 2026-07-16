<?php

namespace Tests\Unit\Field\Types;

use AdAstra\Field\Types\Time;
use AdAstra\Rules\Field\TimeFormatRule;
use AdAstra\Support\Iso\TimeValue;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class TimeTest extends TestCase
{
    public function test_storage_column_is_value_text(): void
    {
        $this->assertSame('value_text', $this->make()->storageColumn());
    }

    private function make(array $settings = []): Time
    {
        return new Time($settings);
    }

    public function test_get_rules_includes_time_format_rule(): void
    {
        $rules = $this->make()->getRules();
        $hasRule = false;
        foreach ($rules as $rule) {
            if ($rule instanceof TimeFormatRule) {
                $hasRule = true;
            }
        }
        $this->assertTrue($hasRule);
    }

    public function test_prepare_for_storage_canonicalizes_hh_mm(): void
    {
        $this->assertSame('09:30', $this->make()->prepareForStorage('9:30'));
        $this->assertSame('09:30', $this->make()->prepareForStorage('09:30'));
    }

    public function test_prepare_for_storage_drops_seconds_when_disabled(): void
    {
        $this->assertSame('09:30', $this->make(['include_seconds' => false])->prepareForStorage('09:30:45'));
    }

    public function test_prepare_for_storage_adds_seconds_when_enabled(): void
    {
        $this->assertSame('09:30:00', $this->make(['include_seconds' => true])->prepareForStorage('09:30'));
        $this->assertSame('09:30:15', $this->make(['include_seconds' => true])->prepareForStorage('09:30:15'));
    }

    public function test_prepare_for_storage_null_passthrough(): void
    {
        $this->assertNull($this->make()->prepareForStorage(null));
        $this->assertNull($this->make()->prepareForStorage(''));
    }

    public function test_prepare_for_storage_throws_on_invalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->make()->prepareForStorage('25:00');
    }

    public function test_prepare_for_storage_throws_on_garbage(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->make()->prepareForStorage('bad');
    }

    public function test_value_returns_time_value(): void
    {
        $t = $this->make()->value('09:30');
        $this->assertInstanceOf(TimeValue::class, $t);
        $this->assertSame(9, $t->hours);
        $this->assertSame(30, $t->minutes);
        $this->assertSame(0, $t->seconds);
    }

    public function test_value_with_seconds(): void
    {
        $t = $this->make()->value('09:30:15');
        $this->assertSame(15, $t->seconds);
        $this->assertSame(570, $t->toMinutes());
    }

    public function test_value_returns_null_for_null(): void
    {
        $this->assertNull($this->make()->value(null));
    }
}
