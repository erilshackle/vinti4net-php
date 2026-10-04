<?php

declare(strict_types=1);

namespace Erilshk\Sisp;

use Erilshk\Sisp\Exceptions\Vinti4Exception;

/**
 * ISO 4217 numeric codes for the supported name mappings.
 * A currency code does not imply support by the payment gateway.
 *
 * @see https://www.iso.org/iso-4217-currency-codes.html
 */
final class Currency
{
    public const CVE = '132';
    public const USD = '840';
    public const EUR = '978';
    public const BRL = '986';
    public const GBP = '826';
    public const JPY = '392';

    /**
     * Convert an alphabetic name to its numeric code, or keep a numeric code.
     *
     * @param 'CVE'|'132'|'USD'|'840'|'EUR'|'978'|'BRL'|'986'|'GBP'|'826'|'JPY'|'392'|string $currency
     * @throws Vinti4Exception When the currency format/name is unknown.
     */
    public static function toNumeric(string $currency): string
    {
        $currency = strtoupper(trim($currency));

        if (preg_match('/^\d{3}$/', $currency)) {
            return $currency;
        }

        $constant = self::class . '::' . $currency;
        if (defined($constant)) {
            return (string) constant($constant);
        }

        throw new Vinti4Exception("Moeda inválida: {$currency}.");
    }

    private function __construct() {}
}
