<?php

namespace App\Libraries;

use App\Services\ServiceException;
use CodeIgniter\HTTP\CURLRequest;
use Config\PayMongo;
use Throwable;

/**
 * Minimal PayMongo client: Checkout Sessions (GCash/Maya) and webhook
 * signature verification. Docs: https://docs.paymongo.com
 *
 * The secret key is sent as HTTP Basic auth (key as username, empty
 * password) and never leaves the server.
 */
class PayMongoClient
{
    public function __construct(
        private readonly PayMongo $config,
        private readonly ?CURLRequest $http = null,
    ) {
    }

    /**
     * Creates a hosted checkout. Returns ['id' => 'cs_...', 'checkout_url' => 'https://...'].
     *
     * @param array<string, string> $metadata string values only
     */
    public function createCheckoutSession(int $amountCents, string $description, string $successUrl, string $cancelUrl, string $reference, array $metadata): array
    {
        $body = ['data' => ['attributes' => [
            'line_items' => [[
                'amount'   => $amountCents,
                'currency' => 'PHP',
                'name'     => $description,
                'quantity' => 1,
            ]],
            'payment_method_types' => $this->config->methods,
            'description'          => $description,
            'reference_number'     => $reference,
            'success_url'          => $successUrl,
            'cancel_url'           => $cancelUrl,
            'send_email_receipt'   => false,
            'show_line_items'      => true,
            'metadata'             => $metadata,
        ]]];

        $data = $this->request('POST', '/checkout_sessions', $body);

        $id  = $data['data']['id'] ?? null;
        $url = $data['data']['attributes']['checkout_url'] ?? null;

        if (! is_string($id) || ! is_string($url) || ! str_starts_with($url, 'https://')) {
            throw new ServiceException('The payment page could not be created. Please try again.');
        }

        return ['id' => $id, 'checkout_url' => $url];
    }

    /** @return array<string, mixed> the checkout session resource (data) */
    public function retrieveCheckoutSession(string $id): array
    {
        if (! preg_match('/^cs_[A-Za-z0-9]+$/', $id)) {
            throw new ServiceException('Unknown payment session.');
        }

        return $this->request('GET', '/checkout_sessions/' . $id)['data'] ?? [];
    }

    /**
     * From a checkout session resource, returns the paid payment or null.
     * The docs state at most one entry in `payments` has status "paid".
     *
     * @param array<string, mixed> $session
     *
     * @return array{id: string, amount: int, method: string}|null
     */
    public static function paidPayment(array $session): ?array
    {
        foreach ($session['attributes']['payments'] ?? [] as $payment) {
            $a = $payment['attributes'] ?? [];

            if (($a['status'] ?? null) === 'paid' && ($a['currency'] ?? 'PHP') === 'PHP') {
                return [
                    'id'     => (string) ($payment['id'] ?? ''),
                    'amount' => (int) ($a['amount'] ?? 0),
                    'method' => (string) ($a['source']['type'] ?? $a['payment_method_type'] ?? 'paymongo'),
                ];
            }
        }

        return null;
    }

    /**
     * Verifies the Paymongo-Signature header: "t=<ts>,te=<hex>,li=<hex>".
     * Signed payload is "<t>.<raw body>" with HMAC-SHA256 and the webhook
     * secret; te is used for test events, li for live events.
     */
    public static function verifySignature(string $rawBody, string $header, string $secret, bool $livemode, int $tolerance = 600, ?int $now = null): bool
    {
        if ($secret === '' || $header === '') {
            return false;
        }

        $parts = [];

        foreach (explode(',', $header) as $piece) {
            [$k, $v] = array_pad(explode('=', trim($piece), 2), 2, '');
            $parts[$k] = $v;
        }

        $timestamp = $parts['t'] ?? '';
        $given     = $parts[$livemode ? 'li' : 'te'] ?? '';

        if (! ctype_digit($timestamp) || $given === '') {
            return false;
        }

        if ($tolerance > 0 && abs(($now ?? time()) - (int) $timestamp) > $tolerance) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret);

        return hash_equals($expected, strtolower($given));
    }

    /** @return array<string, mixed> */
    private function request(string $method, string $path, ?array $json = null): array
    {
        if (! $this->config->isConfigured()) {
            throw new ServiceException('Online payments are not configured.');
        }

        if (! $this->config->isTestKey()) {
            // Demo release: refuse live keys so real money can't move by accident.
            throw new ServiceException('Only PayMongo test keys are allowed in this demo.');
        }

        $http = $this->http ?? service('curlrequest', ['timeout' => 20, 'http_errors' => false], null, false);

        try {
            $options = [
                'auth'    => [$this->config->secretKey, ''],
                'headers' => ['Accept' => 'application/json'],
            ];

            if ($json !== null) {
                $options['json'] = $json;
            }

            $response = $http->request($method, rtrim($this->config->apiBase, '/') . $path, $options);
        } catch (Throwable $e) {
            log_message('error', 'PayMongo request failed: {m}', ['m' => $e->getMessage()]);

            throw new ServiceException('The payment service could not be reached. Please try again.');
        }

        $status = $response->getStatusCode();
        $data   = json_decode((string) $response->getBody(), true);

        if ($status < 200 || $status >= 300 || ! is_array($data)) {
            log_message('error', 'PayMongo {m} {p} returned {s}: {b}', ['m' => $method, 'p' => $path, 's' => $status, 'b' => substr((string) $response->getBody(), 0, 500)]);

            throw new ServiceException('The payment service rejected the request. Please try again.');
        }

        return $data;
    }
}
