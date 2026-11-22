<?php

namespace App\Controllers;

use App\Libraries\Money;
use App\Models\PaymentModel;
use App\Models\UserModel;
use App\Services\ServiceException;
use App\Services\TopUpService;
use App\Services\WalletService;
use Throwable;

class Wallet extends BaseController
{
    public function index()
    {
        $config   = config('PasaBuy');
        $paymongo = config('PayMongo');
        $userId   = $this->currentUserId();

        $payments = new PaymentModel();
        $payments->where('user_id', $userId)->orderBy('created_at', 'DESC')->orderBy('id', 'DESC');

        if ($this->request->getGet('topup') === 'cancelled') {
            session()->setFlashdata('error', 'Top-up cancelled. Your wallet was not charged.');
        }

        return view('wallet_page', [
            'title'          => 'Wallet | Pasa-Buy',
            'user'           => (new UserModel())->find($userId),
            'transactions'   => $payments->paginate($config->perPage),
            'pager'          => $payments->pager,
            'usePayMongo'    => $paymongo->isConfigured(),
            // One-time key: resubmitting this exact form never credits twice.
            'idempotencyKey' => bin2hex(random_bytes(16)),
            'topUpMin'       => Money::format($paymongo->isConfigured() ? $paymongo->minTopUpCents : 1),
            'topUpMax'       => Money::format($paymongo->isConfigured() ? $paymongo->maxTopUpCents : $config->demoTopUpMaxCents),
            'walletCap'      => Money::format($config->demoWalletCapCents),
        ]);
    }

    public function topUp()
    {
        $amount = trim((string) $this->request->getPost('amount'));

        // GCash / Maya through PayMongo (test mode) when configured.
        if (config('PayMongo')->isConfigured()) {
            try {
                $checkoutUrl = (new TopUpService())->start($this->currentUserId(), $amount);

                return redirect()->to($checkoutUrl);
            } catch (ServiceException $e) {
                return redirect()->to('/wallet')->withInput()->with('error', $e->getMessage());
            } catch (Throwable $e) {
                log_message('error', 'Top-up start failed: {m}', ['m' => $e->getMessage()]);

                return redirect()->to('/wallet')->withInput()->with('error', 'The payment page could not be opened. Please try again.');
            }
        }

        // Otherwise: simulated demo credits.
        $result = null;

        $response = $this->attempt(
            function () use (&$result, $amount): void {
                $result = (new WalletService())->addDemoCredits(
                    $this->currentUserId(),
                    $amount,
                    (string) $this->request->getPost('idempotency_key'),
                );
            },
            'Demo credits added to your wallet. These are simulated funds, not real money.',
            '/wallet',
            true,
        );

        // A repeated submission of the same form is a no-op, not an error.
        if (is_array($result) && $result['credited'] === false) {
            return redirect()->to('/wallet')->with('success', 'That top-up was already processed, so it was not added twice.');
        }

        return $response;
    }

    /**
     * PayMongo sends the user back here after checkout. The redirect proves
     * nothing, so we ask PayMongo server-to-server before crediting.
     */
    public function topUpReturn()
    {
        $topUpId = (int) $this->request->getGet('topup');

        try {
            $status = (new TopUpService())->reconcile($topUpId, $this->currentUserId());
        } catch (Throwable $e) {
            log_message('error', 'Top-up reconcile failed: {m}', ['m' => $e->getMessage()]);
            $status = 'pending';
        }

        return match ($status) {
            'credited', 'already' => redirect()->to('/wallet')->with('success', 'Payment confirmed by PayMongo. Your wallet has been topped up.'),
            'pending'             => redirect()->to('/wallet')->with('success', 'Payment received by PayMongo is still being confirmed. Your balance updates automatically once it is.'),
            default               => redirect()->to('/wallet')->with('error', 'That top-up could not be found.'),
        };
    }
}
