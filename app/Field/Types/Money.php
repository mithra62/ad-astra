<?php

namespace App\Field\Types;

use App\Field\AbstractField;
use App\Rules\Field\MoneyDecimalFormatRule;
use App\Rules\Field\MoneyRangeRule;
use App\Support\Iso\Currencies;
use InvalidArgumentException;
use Money\Currencies\ISOCurrencies;
use Money\Currency;
use Money\Exception\ParserException;
use Money\Formatter\DecimalMoneyFormatter;
use Money\Money as PhpMoney;
use Money\Parser\DecimalMoneyParser;

class Money extends AbstractField
{
    protected string $handle = 'money';

    protected string $name = 'Money';

    protected array $settings_form = [
        'currency' => [
            'type' => 'select',
            'label' => 'Currency',
            'options' => 'currency',
            'instructions' => 'ISO 4217 currency code. Determines decimal precision and display symbol.',
            'default' => 'USD',
            'rules' => 'required|string|size:3',
        ],
        'min' => [
            'type' => 'text',
            'label' => 'Minimum',
            'instructions' => 'Smallest allowed value in major units (e.g. 0.00). Optional.',
            'default' => null,
            'rules' => 'nullable|string',
        ],
        'max' => [
            'type' => 'text',
            'label' => 'Maximum',
            'instructions' => 'Largest allowed value in major units. Optional.',
            'default' => null,
            'rules' => 'nullable|string',
        ],
        'default' => [
            'type' => 'text',
            'label' => 'Default Value',
            'instructions' => 'Pre-filled value in major units. Optional.',
            'default' => null,
            'rules' => 'nullable|string',
        ],
    ];

    public function settingsFormOptions(): array
    {
        return [
            'currency' => array_map(
                fn ($c) => ['value' => $c['code'], 'label' => "{$c['code']} — {$c['name']}"],
                Currencies::all(),
            ),
        ];
    }

    public function storageColumn(): string
    {
        return 'value_integer';
    }

    public function getRules(): array
    {
        $currency = (string) $this->getSetting('currency', 'USD');
        return [
            'nullable',
            new MoneyDecimalFormatRule($currency),
            new MoneyRangeRule(
                min: $this->getSetting('min'),
                max: $this->getSetting('max'),
                currency: $currency,
            ),
        ];
    }

    /**
     * Convert a validated decimal-string into integer minor units via
     * moneyphp's DecimalMoneyParser. Throws InvalidArgumentException for
     * unparseable input (programmer-error surfacing).
     */
    public function prepareForStorage(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            throw new InvalidArgumentException('Money value must be a numeric scalar.');
        }

        $currency = (string) $this->getSetting('currency', 'USD');
        $str = (string) $value;

        // moneyphp's parser silently rounds excess precision, but our contract
        // is "no implicit rounding". Pre-check the fractional digit count and
        // throw if it exceeds the currency's minor-unit precision.
        $decimals = Currencies::decimals($currency);
        if (preg_match('/^-?\d*\.(\d+)$/', trim($str), $m) && strlen($m[1]) > $decimals) {
            throw new InvalidArgumentException(
                "Money value has too many fractional digits for {$currency}: {$str}",
            );
        }

        try {
            $money = (new DecimalMoneyParser(new ISOCurrencies()))
                ->parse($str, new Currency($currency));
        } catch (ParserException $e) {
            throw new InvalidArgumentException(
                "Money value is not a well-formed decimal for {$currency}: {$str}",
                previous: $e,
            );
        }

        return (int) $money->getAmount();
    }

    public function cast(mixed $value): mixed
    {
        return $value === null ? null : (int) $value;
    }

    /**
     * Resolve the stored minor-unit integer to a moneyphp Money instance.
     * Consumers get all the precision-safe arithmetic, comparison, and
     * formatting moneyphp provides.
     */
    public function value(mixed $raw): ?PhpMoney
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $currency = (string) $this->getSetting('currency', 'USD');
        return new PhpMoney((string) $raw, new Currency($currency));
    }

    public function render(array $params): string
    {
        $currency = (string) $this->getSetting('currency', 'USD');
        $decimals = Currencies::decimals($currency);
        $params['currency_symbol'] = Currencies::symbol($currency);
        $params['currency_code'] = $currency;
        $params['decimals'] = $decimals;
        $params['step'] = $decimals === 0 ? '1' : '0.' . str_repeat('0', $decimals - 1) . '1';

        // Convert stored minor units back to a decimal-string for the input,
        // via moneyphp's formatter so the precision is exact.
        $stored = $params['value'] ?? null;
        if ($stored !== null && $stored !== '') {
            $money = new PhpMoney((string) $stored, new Currency($currency));
            $params['value'] = (new DecimalMoneyFormatter(new ISOCurrencies()))->format($money);
        }

        return view('_fields.money', $params)->render();
    }
}
