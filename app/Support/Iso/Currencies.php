<?php

namespace App\Support\Iso;

use Money\Currencies\ISOCurrencies;
use Money\Currency;
use Money\Exception\UnknownCurrencyException;

/**
 * Thin facade over moneyphp/money's ISOCurrencies, augmented with a local
 * symbol map (moneyphp deliberately doesn't ship currency symbols —
 * they're a display concern, not a currency property).
 *
 * Decimals, name, and existence checks delegate to moneyphp so we inherit
 * its maintained ISO 4217 dataset rather than freezing one in this repo.
 */
final class Currencies
{
    private static ?ISOCurrencies $iso = null;

    /**
     * @return list<array{code: string, name: string, symbol: string, decimals: int}>
     */
    public static function all(): array
    {
        $out = [];
        foreach (self::iso() as $currency) {
            $code = $currency->getCode();
            $out[] = [
                'code' => $code,
                'name' => self::name($code),
                'symbol' => self::symbol($code),
                'decimals' => self::decimals($code),
            ];
        }
        return $out;
    }

    public static function exists(string $code): bool
    {
        return self::iso()->contains(new Currency(strtoupper($code)));
    }

    public static function decimals(string $code): int
    {
        try {
            return self::iso()->subunitFor(new Currency(strtoupper($code)));
        } catch (UnknownCurrencyException) {
            return 2;
        }
    }

    public static function symbol(string $code): string
    {
        return self::SYMBOLS[strtoupper($code)] ?? strtoupper($code);
    }

    public static function name(string $code): string
    {
        $code = strtoupper($code);
        $data = self::isoData();
        return $data[$code]['currency'] ?? $code;
    }

    private static function iso(): ISOCurrencies
    {
        return self::$iso ??= new ISOCurrencies();
    }

    /**
     * Pull the raw ISO data from moneyphp's bundled resource so we can read
     * the name field directly. (The public API only exposes existence and
     * subunit — name lives in the underlying array.)
     *
     * @return array<string, array{alphabeticCode: string, currency: string, minorUnit: int, numericCode: int}>
     */
    private static function isoData(): array
    {
        static $data = null;
        if ($data === null) {
            $data = require __DIR__ . '/../../../vendor/moneyphp/money/resources/currency.php';
        }
        return $data;
    }

    /**
     * Local symbol map. moneyphp doesn't carry symbols because they're a
     * display convention (and locale-dependent in many cases). For the
     * field-form prefix this gets the job done without an intl dep.
     */
    private const SYMBOLS = [
        'AED' => 'د.إ', 'AFN' => '؋', 'ALL' => 'L', 'AMD' => '֏', 'ANG' => 'ƒ',
        'AOA' => 'Kz', 'ARS' => '$', 'AUD' => 'A$', 'AWG' => 'ƒ', 'AZN' => '₼',
        'BAM' => 'KM', 'BBD' => 'Bds$', 'BDT' => '৳', 'BGN' => 'лв', 'BHD' => '.د.ب',
        'BIF' => 'FBu', 'BMD' => 'BD$', 'BND' => 'B$', 'BOB' => 'Bs.', 'BRL' => 'R$',
        'BSD' => 'B$', 'BTN' => 'Nu.', 'BWP' => 'P', 'BYN' => 'Br', 'BZD' => 'BZ$',
        'CAD' => 'CA$', 'CDF' => 'FC', 'CHF' => 'CHF', 'CLF' => 'UF', 'CLP' => '$',
        'CNY' => '¥', 'COP' => '$', 'CRC' => '₡', 'CUP' => '$', 'CVE' => '$',
        'CZK' => 'Kč', 'DJF' => 'Fdj', 'DKK' => 'kr', 'DOP' => 'RD$', 'DZD' => 'دج',
        'EGP' => 'E£', 'ERN' => 'Nfk', 'ETB' => 'Br', 'EUR' => '€', 'FJD' => 'FJ$',
        'FKP' => '£', 'GBP' => '£', 'GEL' => '₾', 'GHS' => '₵', 'GIP' => '£',
        'GMD' => 'D', 'GNF' => 'FG', 'GTQ' => 'Q', 'GYD' => 'G$', 'HKD' => 'HK$',
        'HNL' => 'L', 'HTG' => 'G', 'HUF' => 'Ft', 'IDR' => 'Rp', 'ILS' => '₪',
        'INR' => '₹', 'IQD' => 'ع.د', 'IRR' => '﷼', 'ISK' => 'kr', 'JMD' => 'J$',
        'JOD' => 'JD', 'JPY' => '¥', 'KES' => 'KSh', 'KGS' => 'с', 'KHR' => '៛',
        'KMF' => 'CF', 'KPW' => '₩', 'KRW' => '₩', 'KWD' => 'KD', 'KYD' => 'CI$',
        'KZT' => '₸', 'LAK' => '₭', 'LBP' => 'L£', 'LKR' => 'Rs', 'LRD' => 'L$',
        'LSL' => 'L', 'LYD' => 'LD', 'MAD' => 'د.م.', 'MDL' => 'L', 'MGA' => 'Ar',
        'MKD' => 'ден', 'MMK' => 'K', 'MNT' => '₮', 'MOP' => 'MOP$', 'MRU' => 'UM',
        'MUR' => '₨', 'MVR' => 'Rf', 'MWK' => 'MK', 'MXN' => 'Mex$', 'MYR' => 'RM',
        'MZN' => 'MT', 'NAD' => 'N$', 'NGN' => '₦', 'NIO' => 'C$', 'NOK' => 'kr',
        'NPR' => '₨', 'NZD' => 'NZ$', 'OMR' => 'ر.ع.', 'PAB' => 'B/.', 'PEN' => 'S/.',
        'PGK' => 'K', 'PHP' => '₱', 'PKR' => '₨', 'PLN' => 'zł', 'PYG' => '₲',
        'QAR' => 'ر.ق', 'RON' => 'lei', 'RSD' => 'дин.', 'RUB' => '₽', 'RWF' => 'FRw',
        'SAR' => 'ر.س', 'SBD' => 'SI$', 'SCR' => '₨', 'SDG' => 'ج.س.', 'SEK' => 'kr',
        'SGD' => 'S$', 'SHP' => '£', 'SLE' => 'Le', 'SOS' => 'S', 'SRD' => 'Sr$',
        'SSP' => '£', 'STN' => 'Db', 'SVC' => '₡', 'SYP' => 'S£', 'SZL' => 'L',
        'THB' => '฿', 'TJS' => 'SM', 'TMT' => 'm', 'TND' => 'د.ت', 'TOP' => 'T$',
        'TRY' => '₺', 'TTD' => 'TT$', 'TWD' => 'NT$', 'TZS' => 'TSh', 'UAH' => '₴',
        'UGX' => 'USh', 'USD' => '$', 'UYU' => '$U', 'UZS' => 'soʻm', 'VES' => 'Bs.S',
        'VND' => '₫', 'VUV' => 'VT', 'WST' => 'WS$', 'XAF' => 'FCFA', 'XCD' => 'EC$',
        'XOF' => 'CFA', 'XPF' => '₣', 'YER' => '﷼', 'ZAR' => 'R', 'ZMW' => 'ZK',
    ];
}
