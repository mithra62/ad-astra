<?php

namespace AdAstra\Rules\Field;

use AdAstra\Support\Iso\Countries;
use AdAstra\Support\Iso\Subdivisions;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates a subdivision code against the configured country.
 *
 * - If subdivision data exists for the country, value must match an ISO 3166-2
 *   code for that country.
 * - If subdivision data does not exist and freetext fallback is on, any
 *   non-empty string up to 100 chars is accepted.
 * - If subdivision data does not exist and freetext fallback is off, the
 *   value is rejected with a clear error.
 */
readonly class SubdivisionCodeRule implements ValidationRule
{
    public function __construct(
        private string $country,
        private bool   $allowFreetextFallback = true,
    )
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (!is_string($value)) {
            $fail("The :attribute must be a valid subdivision.");
            return;
        }

        $countryName = Countries::name($this->country);

        if (Subdivisions::hasData($this->country)) {
            if (!Subdivisions::exists($this->country, $value)) {
                $fail("The :attribute must be a valid {$countryName} subdivision.");
            }
            return;
        }

        if (!$this->allowFreetextFallback) {
            $fail("Subdivision data is not available for {$countryName}; no value can be accepted.");
            return;
        }

        if (strlen($value) > 100) {
            $fail("The :attribute may not exceed 100 characters.");
        }
    }
}
