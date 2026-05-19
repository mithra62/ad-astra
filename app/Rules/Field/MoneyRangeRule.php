<?php

namespace App\Rules\Field;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Money\Currencies\ISOCurrencies;
use Money\Currency;
use Money\Exception\ParserException;
use Money\Parser\DecimalMoneyParser;

/**
 * Validates that a money input falls inside [min, max] in major units.
 *
 * Parses both the input and the configured bounds through moneyphp's
 * DecimalMoneyParser, so comparisons are integer minor-unit comparisons
 * with no float drift. Format errors are reported by MoneyDecimalFormatRule
 * — this rule stays silent on unparseable input to avoid double-reporting.
 */
readonly class MoneyRangeRule implements ValidationRule
{
    public function __construct(
        private ?string $min,
        private ?string $max,
        private string $currency,
    ) {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            return;
        }

        $parser = new DecimalMoneyParser(new ISOCurrencies());
        $currency = new Currency(strtoupper($this->currency));

        try {
            $money = $parser->parse((string) $value, $currency);
        } catch (ParserException) {
            return;
        }

        if ($this->min !== null) {
            try {
                $min = $parser->parse($this->min, $currency);
                if ($money->lessThan($min)) {
                    $fail("The :attribute must be at least {$this->min} {$currency->getCode()}.");
                    return;
                }
            } catch (ParserException) {
                // Misconfigured min — surface as a generic upper-only check.
            }
        }

        if ($this->max !== null) {
            try {
                $max = $parser->parse($this->max, $currency);
                if ($money->greaterThan($max)) {
                    $fail("The :attribute must be at most {$this->max} {$currency->getCode()}.");
                }
            } catch (ParserException) {
                // Misconfigured max — fail silently rather than confuse the user.
            }
        }
    }
}
