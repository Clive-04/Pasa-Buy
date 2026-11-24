<?php

namespace App\Libraries;

use App\Services\ServiceException;
use CodeIgniter\HTTP\CURLRequest;
use Config\Xendit;
use Throwable;

/**
 * Xendit Payment Sessions (docs: https://docs.xendit.co).
 *
 * - Create:  POST /sessions  {reference_id, session_type: PAY, mode: PAYMENT_LINK,
 *            amount, currency: PHP, country: PH, allowed_payment_channels, ...}
 *            → payment_session_id + payment_link_url (redirect target).
 * - Status:  GET /sessions/{id}            → status COMPLETED + payment_id
 *            GET /v3/payments/{payment_id}  (api-version 2024-11-11)
 *                                          → status SUCCEEDED, channel_code, amount
 * Auth: HTTP Basic with the secret key as username and empty password.
 *
 * Xendit amounts are in the currency's main unit (pesos), so centavos are
 * converted at this boundary only.
 */
class XenditClient implements PaymentGateway
{
    public function __construct(
        private readonly Xendit $config,
        private readonly ?CURLRequest $http = null,
    ) {
    }

    public function name(): string
    {
        return 'xendit';
    }

    public function keyPrefix(): string
    {
        return 'xd_';
    }

    public function createCheckout(int $amountCents, string $description, string $reference, ?string $successUrl, ?string $cancelUrl, array $metadata): array
    {
        $body = [
            'reference_id'             => $reference,
            'session_type'             => 'PAY',
            'mode'                     => 'PAYMENT_LINK',
            'amount'                   => round($amountCents / 100, 2),
            'currency'                 => 'PHP',
            'country'                  => 'PH',
            'description'              => $description,
            'allowed_payment_channels' => $this->config->channels,
            'metadata'                 => $metadata,
        ];

        // Xendit only accepts HTTPS return URLs.
        if ($successUrl !== null && str_starts_with($successUrl, 'https://')) {
            $body['success_return_url'] = $successUrl;
        }

        if ($cancelUrl !== null && str_starts_with($cancelUrl, 'https://')) {
            $body['cancel_return_url'] = $cancelUrl;
        }

        $data = $this->request('POST', '/sessions', $body);
        $id   = $data['payment_session_id'] ?? null;
        $url  = $data['payment_link_url'] ?? null;

        if (! is_string($id) || ! is_string($url) || ! str_starts_with($url, 'https://')) {
            throw new ServiceException('The payment page could not be created. Please try again.');
        }

        return ['id' => $id, 'checkout_url' => $url];
    }

    public function fetchPaid(string $checkoutId): ?array
    {
        if (! preg_match('/^ps-[A-Za-z0-9-]+$/', $checkoutId)) {
            throw new ServiceException('Unknown payment session.');
        }

        $session = $this->request('GET', '/sessions/' . $checkoutId);

        if (($session['status'] ?? null) !== 'COMPLETED' || empty($session['payment_id'])) {
            return null;
        }

        $payment = $this->request('GET', '/v3/payments/' . rawurlencode((string) $session['payment_id']), null, ['api-version' => '2024-11-11']);

        if (($payment['status'] ?? null) !== 'SUCCEEDED' || ($payment['currency'] ?? 'PHP') !== 'PHP') {
            return null;
        }

        $captured = 0.0;

        foreach ($payment['captures'] ?? [] as $capture) {
            $captured += (float) ($capture['capture_amount'] ?? 0);
        }

        $amount = $captured > 0 ? $captured : (float) ($payment['request_amount'] ?? $session['amount'] ?? 0);

        return [
            'payment_id'   => (string) $session['payment_id'],
            'amount_cents' => (int) round($amount * 100),
            'method'       => strtolower((string) ($payment['channel_code'] ?? 'xendit')),
            'livemode'     => false,
        ];
    }

    /**
     * Webhook check: Xendit sends the dashboard verification token in the
     * x-callback-token header (no signature scheme is documented). We still
     * re-fetch the session from the API before crediting anything.
     */
    public static function verifyCallbackToken(string $given, string $expected): bool
    {
        return $expected !== '' && $given !== '' && hash_equals($expected, $given);
    }

    /** @return array<string, mixed> */
    private function request(string $method, string $path, ?array $json = null, array $headers = []): array
    {
        if (! $this->config->isConfigured()) {
            throw new ServiceException('Online payments are not configured.');
        }

        if ($this->config->isLiveKey()) {
            throw new ServiceException('Only Xendit test keys are allowed in this demo.');
        }

        $http = $this->http ?? \Config\Services::curlrequest(['timeout' => 20, 'http_errors' => false], null, null, false);

        try {
            $options = [
                'auth'    => [$this->config->secretKey, ''],
                'headers' => ['Accept' => 'application/json'] + $headers,
            ];

            if ($json !== null) {
                $options['json'] = $json;
            }

            $response = $http->request($method, rtrim($this->config->apiBase, '/') . $path, $options);
        } catch (Throwable $e) {
            log_message('error', 'Xendit request failed: {m}', ['m' => $e->getMessage()]);

            throw new ServiceException('The payment service could not be reached. Please try again.');
        }

        $status = $response->getStatusCode();
        $data   = json_decode((string) $response->getBody(), true);

        if ($status < 200 || $status >= 300 || ! is_array($data)) {
            log_message('error', 'Xendit {m} {p} returned {s}: {b}', ['m' => $method, 'p' => $path, 's' => $status, 'b' => ProviderLog::summarize((string) $response->getBody())]);

            throw new ServiceException('The payment service rejected the request. Please try again.');
        }

        return $data;
    }
}
