<?php

namespace Tests\Unit\Rules\Field;

use AdAstra\Rules\Field\SubdivisionCodeRule;
use PHPUnit\Framework\TestCase;

class SubdivisionCodeRuleTest extends TestCase
{
    private function runRule(SubdivisionCodeRule $rule, mixed $value): ?string
    {
        $error = null;
        $rule->validate('state', $value, function (string $msg) use (&$error) {
            $error = $msg;
        });
        return $error;
    }

    public function test_data_backed_country_accepts_known_subdivision(): void
    {
        $this->assertNull($this->runRule(new SubdivisionCodeRule('US'), 'US-CA'));
        $this->assertNull($this->runRule(new SubdivisionCodeRule('CA'), 'CA-ON'));
    }

    public function test_data_backed_country_rejects_unknown_subdivision(): void
    {
        $error = $this->runRule(new SubdivisionCodeRule('US'), 'US-XX');
        $this->assertNotNull($error);
        $this->assertStringContainsString('United States', $error);
    }

    public function test_no_data_country_with_freetext_fallback_accepts_string(): void
    {
        // Finland is not in the v1 subdivision subset.
        $this->assertNull($this->runRule(new SubdivisionCodeRule('FI', true), 'Uusimaa'));
    }

    public function test_no_data_country_without_freetext_fallback_rejects(): void
    {
        $error = $this->runRule(new SubdivisionCodeRule('FI', false), 'Uusimaa');
        $this->assertNotNull($error);
        $this->assertStringContainsString('not available', $error);
    }

    public function test_freetext_length_limit_enforced(): void
    {
        $rule = new SubdivisionCodeRule('FI', true);
        $this->assertNull($this->runRule($rule, str_repeat('a', 100)));
        $error = $this->runRule($rule, str_repeat('a', 101));
        $this->assertNotNull($error);
        $this->assertStringContainsString('100 characters', $error);
    }

    public function test_null_and_empty_pass(): void
    {
        $this->assertNull($this->runRule(new SubdivisionCodeRule('US'), null));
        $this->assertNull($this->runRule(new SubdivisionCodeRule('US'), ''));
    }

    public function test_non_string_rejected(): void
    {
        $this->assertNotNull($this->runRule(new SubdivisionCodeRule('US'), 123));
    }
}
