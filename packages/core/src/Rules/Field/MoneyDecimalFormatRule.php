<?php

namespace AdAstra\Rules\Field;

use AdAstra\Support\Iso\Currencies;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Money\Currencies\ISOCurrencies;
use Money\Currency;
use Money\Exception\ParserException;
use Money\Parser\DecimalMoneyParser;

/**
 * Validates that a money input is a well-formed decimal string for the
 * configured currency.
 *
 * Two-stage check: moneyphp's DecimalMoneyParser handles the "is this even
 * a decimal" shape (catches "abc", "4.2.0", etc.), and we add an explicit
 * fractional-digit-count check because moneyphp silently rounds excessive
 * precision rather than rejecting it. Our contract is "no implicit rounding"
 * — too many digits is a user error worth reporting.
 */
readonly class MoneyDecimalFormatRule implements ValidationRule
{
    public function __construct(private string $currency)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            $fail("The :attribute must be a valid decimal.");
            return;
        }

        $str = (string)$value;

        try {
            (new DecimalMoneyParser(new ISOCurrencies()))
                ->parse($str, new Currency(strtoupper($this->currency)));
        } catch (ParserException) {
            $fail("The :attribute must be a valid decimal.");
            return;
        }

        $decimals = Currencies::decimals($this->currency);
        if (preg_match('/^-?\d*\.(\d+)$/', trim($str), $m) && strlen($m[1]) > $decimals) {
            $code = strtoupper($this->currency);
            $fail("The :attribute may have at most {$decimals} decimal places for {$code}.");
        }
    }
}
