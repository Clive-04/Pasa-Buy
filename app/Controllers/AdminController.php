<?php

namespace App\Controllers;

use App\Libraries\Money;
use App\Models\RequestModel;
use App\Services\RequestLifecycle;
use App\Services\TalkAccess;

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
     * Moderation queue: reports from users, open ones first. Shows the
     * reported message only when the report points at one (evidence).
     */
    public function reports()
    {
        $status = (string) $this->request->getGet('status');
        $status = in_array($status, ['open', 'reviewing', 'resolved', 'dismissed', 'all'], true) ? $status : 'active';
        $db     = db_connect();

        $builder = $db->table('user_reports r')
            ->select("r.*, rep.first_name AS reporter_first, rep.last_name AS reporter_last,
                      tgt.first_name AS reported_first, tgt.last_name AS reported_last,
                      m.message AS message_text, m.created_at AS message_at, m.sender_id AS message_sender,
                      q.item_name AS request_item, q.status AS request_status")
            ->join('users rep', 'rep.id = r.reporter_id')
            ->join('users tgt', 'tgt.id = r.reported_user_id')
            ->join('messages m', 'm.id = r.message_id', 'left')
            ->join('requests q', 'q.id = r.request_id', 'left');

        if ($status === 'active') {
            $builder->whereIn('r.status', ['open', 'reviewing']);
        } elseif ($status !== 'all') {
            $builder->where('r.status', $status);
        }

        $counts = array_fill_keys(['open', 'reviewing', 'resolved', 'dismissed'], 0);

        foreach ($db->table('user_reports')->select('status, COUNT(*) AS n')->groupBy('status')->get()->getResultArray() as $row) {
            $counts[$row['status']] = (int) $row['n'];
        }

        return view('admin/reports', [
            'title'   => 'Reports | Pasa-Buy Admin',
            'status'  => $status,
            'counts'  => $counts,
            'reports' => $builder->orderBy("FIELD(r.status, 'open', 'reviewing', 'resolved', 'dismissed')", '', false)
                ->orderBy('r.created_at', 'DESC')->limit(100)->get()->getResultArray(),
            'reasons' => Moderation::REASONS,
        ]);
    }

    public function updateReport($id)
    {
        $status = (string) $this->request->getPost('status');
        $note   = trim((string) $this->request->getPost('admin_note'));

        if (! in_array($status, ['reviewing', 'resolved', 'dismissed', 'open'], true) || mb_strlen($note) > 1000) {
            return redirect()->to('/admin/reports')->with('error', 'Choose a valid status (note: up to 1,000 characters).');
        }

        $updated = db_connect()->table('user_reports')->where('id', (int) $id)->update([
            'status'     => $status,
            'admin_note' => $note !== '' ? $note : null,
            'handled_by' => $this->currentUserId(),
            'handled_at' => date('Y-m-d H:i:s'),
        ]);

        return $updated && db_connect()->affectedRows() > 0
            ? redirect()->to('/admin/reports')->with('success', 'Report #' . (int) $id . ' marked ' . $status . '.')
            : redirect()->to('/admin/reports')->with('error', 'That report could not be found.');
    }

    /**
     * Replaces the former hard delete: financial and delivery history are
     * kept, and only unsettled requests can be cancelled.
     */
    public function cancelRequest($id)
    {
        $response = $this->attempt(
            fn () => (new RequestLifecycle())->cancel((int) $id, $this->currentUserId(), true),
            'Request #' . (int) $id . ' cancelled. Its history is kept.',
            '/admin/dashboard',
        );

        // A cancelled errand closes its TalkJS chat for new messages (history stays).
        (new TalkAccess())->syncRequest((int) $id);

        return $response;
    }
}
