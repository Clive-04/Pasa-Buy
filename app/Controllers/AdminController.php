<?php

namespace App\Controllers;

use App\Libraries\Money;
use App\Models\RequestModel;
use App\Services\RequestLifecycle;

/**
 * Admin area (route group protected by AdminFilter, which re-checks the
 * role in the database on every request).
 *
 * Reporting rules:
 *  - "Platform fees collected" is the sum of platform_fee ledger entries
 *    actually recorded in payments — not a figure derived from requests.
 *  - It is reconciled against the platform_fee of settled requests; any
 *    difference is shown instead of hidden.
 */
class AdminController extends BaseController
{
    public function dashboard()
    {
        $db     = db_connect();
        $config = config('PasaBuy');
        $tab    = $this->request->getGet('tab') === 'delivered' ? 'delivered' : 'ongoing';

        $counts = array_fill_keys(['pending', 'accepted', 'on_the_way', 'delivered', 'cancelled'], 0);

        foreach ($db->table('requests')->select('status, COUNT(*) AS n')->groupBy('status')->get()->getResultArray() as $row) {
            $counts[$row['status']] = (int) $row['n'];
        }

        $collected = $db->table('payments')
            ->select('COALESCE(SUM(amount), 0) AS total, COUNT(*) AS n')
            ->where('type', 'platform_fee')
            ->get()->getRowArray();

        $expected = $db->table('requests')
            ->select('COALESCE(SUM(platform_fee), 0) AS total')
            ->whereIn('payment_status', ['paid', 'paid_cod'])
            ->get()->getRowArray();

        // Wallets whose cached balance differs from their ledger total.
        $ledgerMismatches = (int) $db->query("
            SELECT COUNT(*) AS n FROM (
                SELECT u.id
                FROM users u
                LEFT JOIN payments p ON p.user_id = u.id
                GROUP BY u.id, u.wallet_balance
                HAVING u.wallet_balance <> COALESCE(SUM(CASE WHEN p.direction = 'credit' THEN p.amount ELSE -p.amount END), 0)
            ) mismatched
        ")->getRow()->n;

        $model = new RequestModel();
        $model->select('requests.id, requests.item_name, requests.status, requests.payment_method, requests.payment_status,
                requests.total_amount, requests.service_fee, requests.platform_fee, requests.created_at,
                u.first_name, u.last_name')
            ->join('users u', 'u.id = requests.user_id');

        $tab === 'delivered'
            ? $model->where('requests.status', 'delivered')->orderBy('requests.updated_at', 'DESC')
            : $model->whereIn('requests.status', ['pending', 'accepted', 'on_the_way'])->orderBy('requests.created_at', 'DESC');

        return view('admin/dashboard', [
            'title'            => 'Admin Dashboard | Pasa-Buy',
            'tab'              => $tab,
            'counts'           => $counts,
            'ongoingCount'     => $counts['pending'] + $counts['accepted'] + $counts['on_the_way'],
            'collectedCents'   => Money::toCents((string) $collected['total']),
            'collectedEntries' => (int) $collected['n'],
            'expectedCents'    => Money::toCents((string) $expected['total']),
            'ledgerMismatches' => $ledgerMismatches,
            'requests'         => $model->orderBy('requests.id', 'DESC')->paginate($config->perPage),
            'pager'            => $model->pager,
        ]);
    }

    /**
     * Replaces the former hard delete: financial and delivery history are
     * kept, and only unsettled requests can be cancelled.
     */
    public function cancelRequest($id)
    {
        return $this->attempt(
            fn () => (new RequestLifecycle())->cancel((int) $id, $this->currentUserId(), true),
            'Request #' . (int) $id . ' cancelled. Its history is kept.',
            '/admin/dashboard',
        );
    }
}
