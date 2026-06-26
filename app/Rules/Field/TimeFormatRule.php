<?php

namespace App\Rules\Field;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates that a time input is a well-formed 24-hour time and (optionally)
 * falls within a configured min/max range.
 *
 * Accepts H:MM, HH:MM, H:MM:SS, HH:MM:SS. Comparison is done on canonical
 * zero-padded form so string compare produces correct ordering.
 */
readonly class TimeFormatRule implements ValidationRule
{
    public function __construct(
        private bool $includeSeconds = false,
        private ?string $minTime = null,
        private ?string $maxTime = null,
    ) {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (!is_string($value)) {
            $fail("The :attribute must be a valid time.");
            return;
        }

        $canonical = $this->canonicalize($value);
        if ($canonical === null) {
            $fail("The :attribute must be a valid time.");
            return;
        }

        if ($this->minTime !== null) {
            $min = $this->canonicalize($this->minTime);
            if ($min !== null && strcmp($canonical, $min) < 0) {
                $fail("The :attribute must be at or after {$min}.");
                return;
            }
        }

        if ($this->maxTime !== null) {
            $max = $this->canonicalize($this->maxTime);
            if ($max !== null && strcmp($canonical, $max) > 0) {
                $fail("The :attribute must be at or before {$max}.");
            }
        }
    }

    /**
     * Returns a zero-padded canonical "HH:MM" or "HH:MM:SS" form, or null
     * if the input doesn't match an accepted time shape.
     */
    private function canonicalize(string $value): ?string
    {
        $value = trim($value);
        if (!preg_match('/^([01]?\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?$/', $value, $m)) {
            return null;
        }
        $h = str_pad($m[1], 2, '0', STR_PAD_LEFT);
        $base = "{$h}:{$m[2]}";
        return isset($m[3]) ? "{$base}:{$m[3]}" : $base;
    }
}
