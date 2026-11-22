<?php

namespace App\Libraries;

use InvalidArgumentException;

/**
 * Exact money handling in integer centavos.
 *
 * Floats cannot represent most decimal amounts exactly (0.1 + 0.2 !== 0.3),
 * so balances and fees are converted to whole centavos, calculated with
 * integers, and converted back to DECIMAL(10,2) strings for the database.
 */
final class Money
{
    /**
     * Parses user input or a DECIMAL column value ("12", "12.5", "12.50")
     * into centavos. Rejects negatives, signs, exponents and >2 decimals.
     */
    public static function toCents(string|int|null $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if (is_int($value)) {
            if ($value < 0) {
                throw new InvalidArgumentException('Amount cannot be negative.');
            }

            return $value * 100;
        }

        $value = trim($value);

        if (! preg_match('/^(\d{1,8})(?:\.(\d{1,2}))?$/', $value, $m)) {
            throw new InvalidArgumentException('Enter an amount like 150 or 150.50.');
        }

        $fraction = str_pad($m[2] ?? '', 2, '0');

        return ((int) $m[1]) * 100 + (int) $fraction;
    }

    /** True when the string is a valid non-negative amount with ≤2 decimals. */
    public static function isValid(?string $value): bool
    {
        try {
            self::toCents($value);

            return $value !== null && trim($value) !== '';
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /** Centavos → "1234.50" for DECIMAL(10,2) columns. */
    public static function toDecimal(int $cents): string
    {
        $sign  = $cents < 0 ? '-' : '';
        $cents = abs($cents);

        return sprintf('%s%d.%02d', $sign, intdiv($cents, 100), $cents % 100);
    }

    /** Centavos → "₱1,234.50" for display. */
    public static function format(int $cents): string
    {
        $sign  = $cents < 0 ? '-' : '';
        $cents = abs($cents);

        return $sign . '₱' . number_format(intdiv($cents, 100)) . '.' . sprintf('%02d', $cents % 100);
    }

    /** DECIMAL column value → "₱1,234.50". */
    public static function formatDecimal(string|int|null $value): string
    {
        return self::format(self::toCents($value === null ? '0' : (string) $value));
    }
}
