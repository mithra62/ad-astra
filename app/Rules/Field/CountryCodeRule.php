<?php

namespace App\Rules\Field;

use App\Support\Iso\Countries;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates that a value is a valid ISO 3166-1 alpha-2 country code,
 * optionally restricted to an explicit whitelist.
 *
 * Case-sensitive: we require uppercase to avoid downstream surprises where
 * "us" vs "US" produce different equality.
 */
readonly class CountryCodeRule implements ValidationRule
{
    /**
     * @param list<string> $allowed Uppercase ISO-2 codes. Empty = all valid codes accepted.
     */
    public function __construct(private array $allowed = [])
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (!is_string($value) || $value !== strtoupper($value) || !Countries::exists($value)) {
            $fail("The :attribute must be a valid country code.");
            return;
        }

        if (!empty($this->allowed) && !in_array($value, $this->allowed, true)) {
            $fail("The :attribute is not an allowed country.");
        }
    }
}
