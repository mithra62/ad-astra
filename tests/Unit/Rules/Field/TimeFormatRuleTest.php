<?php

namespace Tests\Unit\Rules\Field;

use AdAstra\Rules\Field\TimeFormatRule;
use PHPUnit\Framework\TestCase;

class TimeFormatRuleTest extends TestCase
{
    private function runRule(TimeFormatRule $rule, mixed $value): ?string
    {
        $error = null;
        $rule->validate('time', $value, function (string $msg) use (&$error) {
            $error = $msg;
        });
        return $error;
    }

    public function test_accepts_hh_mm(): void
    {
        $this->assertNull($this->runRule(new TimeFormatRule(), '09:30'));
    }

    public function test_accepts_h_mm(): void
    {
        $this->assertNull($this->runRule(new TimeFormatRule(), '9:30'));
    }

    public function test_accepts_hh_mm_ss(): void
    {
        $this->assertNull($this->runRule(new TimeFormatRule(), '23:59:59'));
    }

    public function test_accepts_null_and_empty(): void
    {
        $this->assertNull($this->runRule(new TimeFormatRule(), null));
        $this->assertNull($this->runRule(new TimeFormatRule(), ''));
    }

    public function test_rejects_invalid_hour(): void
    {
        $this->assertNotNull($this->runRule(new TimeFormatRule(), '25:00'));
    }

    public function test_rejects_invalid_minute(): void
    {
        $this->assertNotNull($this->runRule(new TimeFormatRule(), '09:60'));
    }

    public function test_rejects_am_pm_format(): void
    {
        $this->assertNotNull($this->runRule(new TimeFormatRule(), '9:30 AM'));
    }

    public function test_rejects_nonsense(): void
    {
        $this->assertNotNull($this->runRule(new TimeFormatRule(), 'bad'));
    }

    public function test_rejects_non_string(): void
    {
        $this->assertNotNull($this->runRule(new TimeFormatRule(), 9.5));
    }

    public function test_enforces_min_time(): void
    {
        $rule = new TimeFormatRule(minTime: '09:00');
        $this->assertNull($this->runRule($rule, '09:00'));
        $this->assertNull($this->runRule($rule, '10:00'));
        $error = $this->runRule($rule, '08:59');
        $this->assertNotNull($error);
        $this->assertStringContainsString('09:00', $error);
    }

    public function test_enforces_max_time(): void
    {
        $rule = new TimeFormatRule(maxTime: '17:00');
        $this->assertNull($this->runRule($rule, '17:00'));
        $this->assertNotNull($this->runRule($rule, '17:01'));
    }

    public function test_min_time_compares_correctly_with_unpadded_input(): void
    {
        // Canonicalization should ensure "9:30" passes min "09:00".
        $rule = new TimeFormatRule(minTime: '09:00');
        $this->assertNull($this->runRule($rule, '9:30'));
    }
}
