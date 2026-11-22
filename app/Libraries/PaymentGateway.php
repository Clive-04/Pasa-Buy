<?php

namespace App\Libraries;

/**
 * A hosted-checkout payment provider (PayMongo, Xendit).
 * Implementations must talk to the provider server-to-server; nothing
 * the browser says is ever treated as proof of payment.
 */
interface PaymentGateway
{
    /** Short provider code stored with each top-up, e.g. "xendit". */
    public function name(): string;

    /** Prefix for ledger idempotency keys, e.g. "xd_". */
    public function keyPrefix(): string;

    /**
     * Creates a hosted checkout for $amountCents (PHP).
     *
     * @param array<string, string> $metadata
     *
     * @return array{id: string, checkout_url: string}
     */
    public function createCheckout(int $amountCents, string $description, string $reference, ?string $successUrl, ?string $cancelUrl, array $metadata): array;

    /**
     * Asks the provider whether the checkout was paid.
     *
     * @return array{payment_id: string, amount_cents: int, method: string, livemode: bool}|null
     */
    public function fetchPaid(string $checkoutId): ?array;
}
