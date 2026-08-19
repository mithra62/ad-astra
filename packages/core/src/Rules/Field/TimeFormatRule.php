<?php

namespace AdAstra\Rules\Field;

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
        private bool    $includeSeconds = false,
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

        // The field's include_seconds toggle decides whether a seconds
        // component belongs in the stored value, so enforce it both ways.
        $hasSeconds = substr_count($canonical, ':') === 2;

        if ($this->includeSeconds && !$hasSeconds) {
            $fail("The :attribute must include seconds (HH:MM:SS).");
            return;
        }

        if (!$this->includeSeconds && $hasSeconds) {
            $fail("The :attribute must not include seconds (HH:MM).");
            return;
        }

        if ($this->minTime !== null) {
            $min = $this->canonicalize($this->minTime);
            if ($min !== null && strcmp($this->forCompare($canonical), $this->forCompare($min)) < 0) {
                $fail("The :attribute must be at or after {$min}.");
                return;
            }
        }

        if ($this->maxTime !== null) {
            $max = $this->canonicalize($this->maxTime);
            if ($max !== null && strcmp($this->forCompare($canonical), $this->forCompare($max)) > 0) {
                $fail("The :attribute must be at or before {$max}.");
            }
        }
    }

    /**
     * Pad a canonical time to HH:MM:SS so range comparisons are precision-safe.
     *
     * Without this, a value of "17:00:00" would compare greater than a maxTime
     * of "17:00" under strcmp, because the longer string wins on prefix. That
     * only became reachable once includeSeconds started forcing a seconds
     * component onto values while min/max stay as the admin typed them.
     */
    private function forCompare(string $canonical): string
    {
        return substr_count($canonical, ':') === 2 ? $canonical : $canonical . ':00';
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
