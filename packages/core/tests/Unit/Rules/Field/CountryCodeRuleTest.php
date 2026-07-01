<?php

namespace Tests\Unit\Rules\Field;

use AdAstra\Rules\Field\CountryCodeRule;
use PHPUnit\Framework\TestCase;

class CountryCodeRuleTest extends TestCase
{
    public function test_accepts_known_codes(): void
    {
        $rule = new CountryCodeRule();
        $this->assertNull($this->runRule($rule, 'US'));
        $this->assertNull($this->runRule($rule, 'GB'));
        $this->assertNull($this->runRule($rule, 'JP'));
    }

    private function runRule(CountryCodeRule $rule, mixed $value): ?string
    {
        $error = null;
        $rule->validate('country', $value, function (string $msg) use (&$error) {
            $error = $msg;
        });
        return $error;
    }

    public function test_accepts_null_and_empty(): void
    {
        $rule = new CountryCodeRule();
        $this->assertNull($this->runRule($rule, null));
        $this->assertNull($this->runRule($rule, ''));
    }

    public function test_rejects_unknown_code(): void
    {
        $this->assertNotNull($this->runRule(new CountryCodeRule(), 'XX'));
    }

    public function test_rejects_lowercase(): void
    {
        // Stored values must be canonical-uppercase; lowercase is a programmer-error path.
        $this->assertNotNull($this->runRule(new CountryCodeRule(), 'us'));
    }

    public function test_rejects_non_string(): void
    {
        $this->assertNotNull($this->runRule(new CountryCodeRule(), 123));
    }

    public function test_whitelist_filtering(): void
    {
        $rule = new CountryCodeRule(['US', 'CA']);
        $this->assertNull($this->runRule($rule, 'US'));
        $this->assertNull($this->runRule($rule, 'CA'));
        $error = $this->runRule($rule, 'GB');
        $this->assertNotNull($error);
        $this->assertStringContainsString('not an allowed country', $error);
    }

    public function test_empty_whitelist_allows_any_known_code(): void
    {
        $this->assertNull($this->runRule(new CountryCodeRule([]), 'FR'));
    }
}
