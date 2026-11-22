<?php

use App\Libraries\Money;

/*
 * View helpers shared by Pasa-Buy pages. Loaded for every controller via
 * BaseController::$helpers.
 */

if (! function_exists('money')) {
    /** DECIMAL value → "₱1,234.50". */
    function money(string|int|null $value): string
    {
        return Money::formatDecimal($value);
    }
}

if (! function_exists('money_cents')) {
    function money_cents(int $cents): string
    {
        return Money::format($cents);
    }
}

if (! function_exists('is_demo_mode')) {
    function is_demo_mode(): bool
    {
        return (bool) config('PasaBuy')->demoMode;
    }
}

if (! function_exists('form_value')) {
    /**
     * Previously submitted value for a field after a failed submission.
     * Checks CodeIgniter's old input first, then a manually flashed
     * `form_old` array (used where passwords must not be flashed).
     */
    function form_value(string $field, string $default = ''): string
    {
        $old = old($field);

        if ($old !== null && $old !== '') {
            return (string) $old;
        }

        $flashed = session('form_old');

        if (is_array($flashed) && isset($flashed[$field])) {
            return (string) $flashed[$field];
        }

        return $default;
    }
}

if (! function_exists('field_error')) {
    /** Escaped inline validation message for a field, or ''. */
    function field_error(string $field): string
    {
        $errors = session('errors');

        if (! is_array($errors) || empty($errors[$field])) {
            return '';
        }

        return '<p class="field-error" id="' . esc($field, 'attr') . '-error">' . esc($errors[$field]) . '</p>';
    }
}

if (! function_exists('field_invalid_attrs')) {
    /** aria-invalid/aria-describedby attributes for a field with an error. */
    function field_invalid_attrs(string $field): string
    {
        $errors = session('errors');

        if (! is_array($errors) || empty($errors[$field])) {
            return '';
        }

        return ' aria-invalid="true" aria-describedby="' . esc($field, 'attr') . '-error"';
    }
}

if (! function_exists('status_label')) {
    function status_label(?string $status): string
    {
        return match ($status) {
            'pending'    => 'Pending',
            'accepted'   => 'Accepted',
            'on_the_way' => 'On the way',
            'delivered'  => 'Delivered',
            'cancelled'  => 'Cancelled',
            default      => ucfirst(str_replace('_', ' ', (string) $status)),
        };
    }
}

if (! function_exists('payment_method_label')) {
    function payment_method_label(?string $method): string
    {
        return match ($method) {
            'cod'    => 'Cash on delivery',
            'wallet' => is_demo_mode() ? 'Wallet (demo credits)' : 'Wallet',
            // Values from before the demo release; never real transfers.
            'gcash', 'maya', 'bank_transfer' => 'Wallet (legacy: ' . ucfirst(str_replace('_', ' ', $method)) . ')',
            default => ucfirst((string) $method),
        };
    }
}

if (! function_exists('payment_status_label')) {
    function payment_status_label(?string $status): string
    {
        return match ($status) {
            'unpaid'      => 'Not paid yet',
            'cod_pending' => 'Cash due on delivery',
            'paid'        => 'Paid via wallet',
            'paid_cod'    => 'Paid in cash',
            default       => ucfirst(str_replace('_', ' ', (string) $status)),
        };
    }
}

if (! function_exists('ledger_label')) {
    function ledger_label(?string $type): string
    {
        return match ($type) {
            'demo_top_up'     => 'Demo credits added',
            'request_payment' => 'Paid a runner',
            'request_earning' => 'Received from requester',
            'platform_fee'    => 'Platform fee',
            'top_up'          => 'Wallet top-up',
            'legacy_top_up'   => 'Top-up (before demo release)',
            default           => ucfirst(str_replace('_', ' ', (string) $type)),
        };
    }
}

if (! function_exists('avatar_url')) {
    function avatar_url(?string $photo): ?string
    {
        if ($photo === null || $photo === '') {
            return null;
        }

        return base_url('media/avatar/' . rawurlencode($photo));
    }
}

if (! function_exists('initials')) {
    function initials(?string $first, ?string $last = null): string
    {
        $text = trim(mb_substr((string) $first, 0, 1) . mb_substr((string) $last, 0, 1));

        return $text === '' ? '?' : mb_strtoupper($text);
    }
}
