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
        $provider = TopUpService::activeProvider();
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
            'gateway'        => $provider,
            'gatewayLabel'   => TopUpService::providerLabel($provider),
            'pendingTopUps'  => $provider !== null ? (new TopUpService())->pendingFor($userId) : [],
            // One-time key: resubmitting this exact form never credits twice.
            'idempotencyKey' => bin2hex(random_bytes(16)),
            'topUpMin'       => Money::format($provider !== null ? TopUpService::limits($provider)[0] : 1),
            'topUpMax'       => Money::format($provider !== null ? TopUpService::limits($provider)[1] : $config->demoTopUpMaxCents),
            'walletCap'      => Money::format($config->demoWalletCapCents),
        ]);
    }

    public function topUp()
    {
        $amount = trim((string) $this->request->getPost('amount'));

        // GCash / Maya through Xendit or PayMongo (test mode) when configured.
        if (TopUpService::activeProvider() !== null) {
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
     * The provider sends the user back here after checkout. The redirect
     * proves nothing, so we ask the provider server-to-server before crediting.
     */
    public function topUpReturn()
    {
        return $this->reconcileAndRedirect((int) $this->request->getGet('topup'));
    }

    /** "Check payment" button for a pending top-up (POST, CSRF-protected). */
    public function topUpCheck()
    {
        return $this->reconcileAndRedirect((int) $this->request->getPost('topup'));
    }

    private function reconcileAndRedirect(int $topUpId)
    {
        try {
            $status = (new TopUpService())->reconcile($topUpId, $this->currentUserId());
        } catch (Throwable $e) {
            log_message('error', 'Top-up reconcile failed: {m}', ['m' => $e->getMessage()]);
            $status = 'error';
        }

        return match ($status) {
            'credited', 'already' => redirect()->to('/wallet')->with('success', 'Payment confirmed. Your wallet has been topped up.'),
            'pending'             => redirect()->to('/wallet')->with('success', 'No completed payment yet. If you just paid, wait a moment and press "Check payment" again.'),
            'error'               => redirect()->to('/wallet')->with('error', 'We could not reach the payment service. Please try again shortly.'),
            default               => redirect()->to('/wallet')->with('error', 'That top-up could not be found.'),
        };
    }
}
