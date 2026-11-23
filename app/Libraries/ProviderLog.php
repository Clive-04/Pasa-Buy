<?php

namespace App\Libraries;

/**
 * Safe log summaries of payment/chat provider responses.
 *
 * Provider error bodies can echo back names, emails, phone numbers or
 * payment details, so they are never written to the log. Only short,
 * well-known error identifiers are kept (e.g. Xendit `error_code`,
 * PayMongo `errors[].code`, TalkJS `errorCode`), which are enough to look
 * up the failure in the provider's documentation or dashboard.
 */
final class ProviderLog
{
    private const ID_KEYS = ['error_code', 'errorCode', 'code', 'type'];

    /** "error_code=API_VALIDATION_ERROR" or "no error code" — never the body. */
    public static function summarize(string $body): string
    {
        $data = json_decode($body, true);

        if (! is_array($data)) {
            return 'non-JSON body (' . strlen($body) . ' bytes, not logged)';
        }

        $found = [];
        self::collect($data, $found, 0);

        return $found === [] ? 'no error code (body not logged)' : implode(', ', array_slice(array_unique($found), 0, 5));
    }

    /** @param array<mixed> $data @param list<string> $found */
    private static function collect(array $data, array &$found, int $depth): void
    {
        if ($depth > 3) {
            return;
        }

        foreach ($data as $key => $value) {
            if (is_string($key) && in_array($key, self::ID_KEYS, true) && is_scalar($value) && self::isIdentifier((string) $value)) {
                $found[] = $key . '=' . $value;
            } elseif (is_array($value) && ($key === 'errors' || $key === 'error' || is_int($key))) {
                self::collect($value, $found, $depth + 1);
            }
        }
    }

    /** Machine-style codes only: free text could contain personal data. */
    private static function isIdentifier(string $value): bool
    {
        return preg_match('/^[A-Za-z0-9_.\-]{1,64}$/', $value) === 1;
    }
}
