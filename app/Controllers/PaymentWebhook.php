<?php

namespace App\Controllers;

use App\Libraries\PayMongoClient;
use App\Libraries\XenditClient;
use App\Services\ServiceException;
use App\Services\TopUpService;
use Throwable;

/**
 * Receives PayMongo webhooks (public URL, no session, CSRF-exempt).
 * Trust comes only from the Paymongo-Signature HMAC over the raw body.
 *
 * Register in the PayMongo dashboard (test mode):
 *   URL:    https://<your-domain>/webhooks/paymongo
 *   Events: checkout_session.payment.paid
 */
class PaymentWebhook extends BaseController
{
    public function paymongo()
    {
        $config = config('PayMongo');
        $raw    = (string) $this->request->getBody();
        $event  = json_decode($raw, true);

        if (! is_array($event)) {
            return $this->reply(400, 'invalid payload');
        }

        $livemode = (bool) ($event['data']['attributes']['livemode'] ?? false);
        $valid    = PayMongoClient::verifySignature(
            $raw,
            $this->request->getHeaderLine('Paymongo-Signature'),
            $config->webhookSecret,
            $livemode,
            $config->signatureTolerance,
        );

        if (! $valid) {
            log_message('warning', 'Rejected PayMongo webhook with an invalid signature.');

            return $this->reply(401, 'invalid signature');
        }

        if ($livemode) {
            // Demo release: live events are never credited.
            return $this->reply(200, 'ignored: live mode');
        }

        $type = (string) ($event['data']['attributes']['type'] ?? '');

        if ($type !== 'checkout_session.payment.paid') {
            return $this->reply(200, 'ignored');
        }

        $session = $event['data']['attributes']['data'] ?? [];
        $paid    = PayMongoClient::paidPayment(is_array($session) ? $session : []);

        if ($paid === null || empty($session['id'])) {
            return $this->reply(200, 'no paid payment');
        }

        try {
            $result = (new TopUpService())->confirm((string) $session['id'], $paid['amount'], $paid['id'], $paid['method'], false, 'paymongo');
        } catch (ServiceException $e) {
            // e.g. amount mismatch: acknowledge so PayMongo stops retrying; it is logged for review.
            return $this->reply(200, 'rejected: ' . $e->getMessage());
        } catch (Throwable $e) {
            log_message('error', 'PayMongo webhook processing failed: {m}', ['m' => $e->getMessage()]);

            return $this->reply(500, 'retry later');
        }

        return $this->reply(200, $result);
    }

    /**
     * Xendit webhooks (test mode). Register in Dashboard → Settings → Webhooks:
     *   URL:    https://<your-domain>/webhooks/xendit
     *   Event:  payment_session.completed
     * Xendit only sends a static verification token (x-callback-token), so
     * the body is never trusted: the session is re-fetched from the Xendit
     * API and only a SUCCEEDED payment of the exact amount is credited.
     */
    public function xendit()
    {
        $given = $this->request->getHeaderLine('x-callback-token');

        if (! XenditClient::verifyCallbackToken($given, config('Xendit')->callbackToken)) {
            log_message('warning', 'Rejected Xendit webhook with an invalid callback token.');

            return $this->reply(401, 'invalid token');
        }

        $event = json_decode((string) $this->request->getBody(), true);

        if (! is_array($event)) {
            return $this->reply(400, 'invalid payload');
        }

        if (($event['event'] ?? '') !== 'payment_session.completed') {
            return $this->reply(200, 'ignored');
        }

        $sessionId = (string) ($event['data']['payment_session_id'] ?? '');

        if ($sessionId === '') {
            return $this->reply(200, 'no session');
        }

        try {
            $paid = service('xendit')->fetchPaid($sessionId);

            if ($paid === null) {
                return $this->reply(200, 'not paid');
            }

            $result = (new TopUpService())->confirm($sessionId, $paid['amount_cents'], $paid['payment_id'], $paid['method'], $paid['livemode'], 'xendit');
        } catch (ServiceException $e) {
            return $this->reply(200, 'rejected: ' . $e->getMessage());
        } catch (Throwable $e) {
            log_message('error', 'Xendit webhook processing failed: {m}', ['m' => $e->getMessage()]);

            return $this->reply(500, 'retry later');
        }

        return $this->reply(200, $result);
    }

    private function reply(int $status, string $message)
    {
        return $this->response->setStatusCode($status)->setJSON(['result' => $message]);
    }
}
