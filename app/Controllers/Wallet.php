<?php

namespace App\Controllers;

use App\Libraries\Money;
use App\Models\PaymentModel;
use App\Models\UserModel;
use App\Services\WalletService;

class Wallet extends BaseController
{
    public function index()
    {
        $config = config('PasaBuy');
        $userId = $this->currentUserId();

        $payments = new PaymentModel();
        $payments->where('user_id', $userId)->orderBy('created_at', 'DESC')->orderBy('id', 'DESC');

        return view('wallet_page', [
            'title'          => 'Wallet | Pasa-Buy',
            'user'           => (new UserModel())->find($userId),
            'transactions'   => $payments->paginate($config->perPage),
            'pager'          => $payments->pager,
            // One-time key: resubmitting this exact form never credits twice.
            'idempotencyKey' => bin2hex(random_bytes(16)),
            'topUpMax'       => Money::format($config->demoTopUpMaxCents),
            'walletCap'      => Money::format($config->demoWalletCapCents),
        ]);
    }

    public function topUp()
    {
        $result = null;

        $response = $this->attempt(
            function () use (&$result): void {
                $result = (new WalletService())->addDemoCredits(
                    $this->currentUserId(),
                    trim((string) $this->request->getPost('amount')),
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
}
