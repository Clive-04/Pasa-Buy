<?php

namespace App\Controllers;

use App\Libraries\Money;
use App\Models\RequestModel;
use App\Services\AccountSuspension;
use App\Services\RequestLifecycle;
use App\Services\TalkAccess;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Admin area (route group protected by AdminFilter, which re-checks the
 * role in the database on every request).
 *
 * Reporting rules:
 *  - "Platform fees collected" is the sum of platform_fee ledger entries
 *    actually recorded in payments — not a figure derived from requests.
 *  - It is reconciled against the platform_fee of settled requests; any
 *    difference is shown instead of hidden.
 *  - Top-ups are shown as the provider reported them. A pending top-up is
 *    not money received; only a paid one has been credited to a wallet.
 */
class AdminController extends BaseController
{
    private const STATUSES         = ['pending', 'accepted', 'on_the_way', 'delivered', 'cancelled'];
    private const PAYMENT_STATUSES = ['unpaid', 'cod_pending', 'paid', 'paid_cod'];
    private const CSV_LIMIT        = 5000;

    /** A checkout still pending after this long was most likely abandoned. */
    private const STALE_TOPUP_MINUTES = 30;

    // ================================================================ overview

    public function dashboard()
    {
        // Old link: the delivered list now lives on the Requests page.
        if ($this->request->getGet('tab') === 'delivered') {
            return redirect()->to('/admin/requests?status=delivered');
        }

        $db    = db_connect();
        $range = $this->request->getGet('range') === '12m' ? '12m' : '12w';

        $counts = array_fill_keys(self::STATUSES, 0);

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

        $users = $db->table('users')
            ->select("COUNT(*) AS total,
                      SUM(created_at >= NOW() - INTERVAL 7 DAY) AS new_week,
                      SUM(status = 'suspended') AS suspended", false)
            ->where('role', 'user')
            ->get()->getRowArray();

        $collectedCents = Money::toCents((string) $collected['total']);
        $expectedCents  = Money::toCents((string) $expected['total']);

        $recent = (new RequestModel())
            ->select('requests.id, requests.item_name, requests.status, requests.payment_status, requests.total_amount, requests.created_at, u.first_name, u.last_name')
            ->join('users u', 'u.id = requests.user_id')
            ->orderBy('requests.id', 'DESC')
            ->findAll(6);

        return view('admin/dashboard', [
            'title'            => 'Admin overview | Pasa-Buy',
            'range'            => $range,
            'counts'           => $counts,
            'totalRequests'    => array_sum($counts),
            'ongoingCount'     => $counts['pending'] + $counts['accepted'] + $counts['on_the_way'],
            'collectedCents'   => $collectedCents,
            'collectedEntries' => (int) $collected['n'],
            'expectedCents'    => $expectedCents,
            'users'            => array_map('intval', $users),
            'series'           => $this->timeSeries($range),
            'attention'        => $this->attention($collectedCents - $expectedCents),
            'recent'           => $recent,
        ]);
    }

    /**
     * Requests posted and platform fees collected per week (last 12 weeks)
     * or per month (last 12 months). Empty periods are included as zero.
     *
     * @return list<array{label: string, long: string, requests: int, feesCents: int}>
     */
    private function timeSeries(string $range): array
    {
        $db      = db_connect();
        $monthly = $range === '12m';
        $buckets = [];

        for ($i = 11; $i >= 0; $i--) {
            if ($monthly) {
                $start = strtotime("first day of -{$i} month midnight");
                $key   = date('Y-m', $start);
                $buckets[$key] = ['label' => date('M', $start), 'long' => date('F Y', $start), 'requests' => 0, 'feesCents' => 0];
            } else {
                $start = strtotime("monday this week -{$i} week");
                $key   = date('o-W', $start);
                $buckets[$key] = ['label' => date('M j', $start), 'long' => 'Week of ' . date('M j, Y', $start), 'requests' => 0, 'feesCents' => 0];
            }
        }

        $keyExpr = $monthly ? "DATE_FORMAT(created_at, '%Y-%m')" : "DATE_FORMAT(created_at, '%x-%v')";
        $since   = $monthly ? date('Y-m-01 00:00:00', strtotime('first day of -11 month')) : date('Y-m-d 00:00:00', strtotime('monday this week -11 week'));

        foreach ($db->table('requests')->select("{$keyExpr} AS k, COUNT(*) AS n", false)->where('created_at >=', $since)->groupBy('k')->get()->getResultArray() as $row) {
            if (isset($buckets[$row['k']])) {
                $buckets[$row['k']]['requests'] = (int) $row['n'];
            }
        }

        foreach ($db->table('payments')->select("{$keyExpr} AS k, SUM(amount) AS total", false)->where('type', 'platform_fee')->where('created_at >=', $since)->groupBy('k')->get()->getResultArray() as $row) {
            if (isset($buckets[$row['k']])) {
                $buckets[$row['k']]['feesCents'] = Money::toCents((string) $row['total']);
            }
        }

        return array_values($buckets);
    }

    /**
     * Things an admin should look at, most serious first. Each item has a
     * tone (danger / warn / info), a count, a sentence and a link.
     *
     * @return list<array{tone: string, icon: string, count: int|string, text: string, href: string, action: string}>
     */
    private function attention(int $feeDifferenceCents): array
    {
        $db    = db_connect();
        $items = [];

        $mismatches = (int) $db->query("
            SELECT COUNT(*) AS n FROM (
                SELECT u.id
                FROM users u
                LEFT JOIN payments p ON p.user_id = u.id
                GROUP BY u.id, u.wallet_balance
                HAVING u.wallet_balance <> COALESCE(SUM(CASE WHEN p.direction = 'credit' THEN p.amount ELSE -p.amount END), 0)
            ) mismatched
        ")->getRow()->n;

        if ($feeDifferenceCents !== 0) {
            $items[] = ['tone' => 'danger', 'icon' => 'alert', 'count' => money_cents(abs($feeDifferenceCents)),
                'text' => 'Platform fees in the ledger are ' . ($feeDifferenceCents > 0 ? 'over' : 'short') . ' compared with settled requests.',
                'href' => 'admin/requests?payment=paid', 'action' => 'Check requests'];
        }

        if ($mismatches > 0) {
            $items[] = ['tone' => 'danger', 'icon' => 'wallet', 'count' => $mismatches,
                'text' => $mismatches === 1 ? 'wallet balance differs from its ledger.' : 'wallet balances differ from their ledgers.',
                'href' => 'admin/users?check=ledger', 'action' => 'See which'];
        }

        $openReports = $db->table('user_reports')->where('status', 'open')->countAllResults();

        if ($openReports > 0) {
            $items[] = ['tone' => 'warn', 'icon' => 'shield', 'count' => $openReports,
                'text' => $openReports === 1 ? 'new report is waiting for review.' : 'new reports are waiting for review.',
                'href' => 'admin/reports', 'action' => 'Review'];
        }

        $waiting = $db->table('requests')->where('status', 'pending')->where('created_at <', date('Y-m-d H:i:s', time() - 86400))->countAllResults();

        if ($waiting > 0) {
            $items[] = ['tone' => 'warn', 'icon' => 'clock', 'count' => $waiting,
                'text' => $waiting === 1 ? 'request has waited over a day without a runner.' : 'requests have waited over a day without a runner.',
                'href' => 'admin/requests?status=pending', 'action' => 'View'];
        }

        $staleTopups = $db->table('wallet_topups')->where('status', 'pending')
            ->where('created_at <', date('Y-m-d H:i:s', time() - self::STALE_TOPUP_MINUTES * 60))->countAllResults();

        if ($staleTopups > 0) {
            $items[] = ['tone' => 'info', 'icon' => 'cash', 'count' => $staleTopups,
                'text' => ($staleTopups === 1 ? 'top-up checkout was' : 'top-up checkouts were') . ' started but never paid (no money received).',
                'href' => 'admin/topups?status=pending', 'action' => 'View'];
        }

        $suspended = $db->table('users')->where('status', 'suspended')->countAllResults();

        if ($suspended > 0) {
            $items[] = ['tone' => 'info', 'icon' => 'lock', 'count' => $suspended,
                'text' => $suspended === 1 ? 'account is suspended.' : 'accounts are suspended.',
                'href' => 'admin/users?status=suspended', 'action' => 'View'];
        }

        return $items;
    }

    // ================================================================ requests

    public function requests()
    {
        $filters = $this->requestFilters();
        $model   = new RequestModel();
        $this->applyRequestFilters($model->builder(), $filters);

        $model->select('requests.id, requests.item_name, requests.store_name, requests.status, requests.payment_method, requests.payment_status,
                requests.total_amount, requests.service_fee, requests.platform_fee, requests.created_at, requests.user_id,
                u.first_name, u.last_name, ru.id AS runner_id, ru.first_name AS runner_first, ru.last_name AS runner_last')
            ->join('users u', 'u.id = requests.user_id')
            ->join('accepted_requests ar', 'ar.request_id = requests.id', 'left')
            ->join('users ru', 'ru.id = ar.runner_id', 'left')
            ->orderBy('requests.id', 'DESC');

        return view('admin/requests', [
            'title'    => 'Requests | Pasa-Buy Admin',
            'filters'  => $filters,
            'filtered' => array_filter($filters) !== [],
            'requests' => $model->paginate(config('PasaBuy')->perPage),
            'pager'    => $model->pager,
            'statuses' => self::STATUSES,
            'payments' => self::PAYMENT_STATUSES,
            'returnTo' => 'admin/requests' . ($this->request->getUri()->getQuery() !== '' ? '?' . $this->request->getUri()->getQuery() : ''),
        ]);
    }

    /** Same filters as the Requests page, as a CSV download (spreadsheet-safe). */
    public function exportRequests(): ResponseInterface
    {
        $filters = $this->requestFilters();
        $builder = db_connect()->table('requests');
        $this->applyRequestFilters($builder, $filters);

        $rows = $builder
            ->select("requests.id, requests.created_at, requests.item_name, requests.category, requests.store_name, requests.status,
                      requests.payment_method, requests.payment_status, requests.estimated_price, requests.service_fee,
                      requests.platform_fee, requests.total_amount,
                      CONCAT(u.first_name, ' ', u.last_name) AS requester,
                      CONCAT(ru.first_name, ' ', ru.last_name) AS runner", false)
            ->join('users u', 'u.id = requests.user_id')
            ->join('accepted_requests ar', 'ar.request_id = requests.id', 'left')
            ->join('users ru', 'ru.id = ar.runner_id', 'left')
            ->orderBy('requests.id', 'DESC')
            ->limit(self::CSV_LIMIT)
            ->get()->getResultArray();

        $out = fopen('php://temp', 'w+');
        fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows ₱ and ñ correctly
        fputcsv($out, ['ID', 'Posted', 'Item', 'Category', 'Store', 'Status', 'Payment method', 'Payment status',
            'Item price', 'Service fee', 'Platform fee', 'Total', 'Requester', 'Runner'], ',', '"', '');

        foreach ($rows as $row) {
            fputcsv($out, array_map([$this, 'csvCell'], [
                $row['id'], $row['created_at'], $row['item_name'], $row['category'], $row['store_name'],
                status_label($row['status']), payment_method_label($row['payment_method']), payment_status_label($row['payment_status']),
                $row['estimated_price'], $row['service_fee'], $row['platform_fee'], $row['total_amount'],
                $row['requester'], $row['runner'] ?? '',
            ]), ',', '"', '');
        }

        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=utf-8')
            ->setHeader('Content-Disposition', 'attachment; filename="pasabuy-requests-' . date('Y-m-d') . '.csv"')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setBody($csv);
    }

    /** Stops spreadsheet apps from running user text as a formula (CSV injection). */
    private function csvCell(mixed $value): string
    {
        $value = (string) ($value ?? '');

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) && ! is_numeric($value)
            ? "'" . $value
            : $value;
    }

    /** @return array{q: string, status: string, payment: string, method: string, from: string, to: string} */
    private function requestFilters(): array
    {
        $get  = fn (string $k): string => trim((string) $this->request->getGet($k));
        $date = static fn (string $v): string => preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) === 1 && strtotime($v) !== false ? $v : '';

        return [
            'q'       => mb_substr($get('q'), 0, 100),
            'status'  => in_array($get('status'), self::STATUSES, true) ? $get('status') : '',
            'payment' => in_array($get('payment'), self::PAYMENT_STATUSES, true) ? $get('payment') : '',
            'method'  => in_array($get('method'), ['wallet', 'cod'], true) ? $get('method') : '',
            'from'    => $date($get('from')),
            'to'      => $date($get('to')),
        ];
    }

    private function applyRequestFilters(BaseBuilder $builder, array $f): void
    {
        if ($f['q'] !== '') {
            $id = ltrim($f['q'], '#');
            $builder->groupStart()
                ->like('requests.item_name', $f['q'])
                ->orLike('requests.store_name', $f['q'])
                ->orWhere("requests.user_id IN (SELECT id FROM users WHERE CONCAT(first_name, ' ', last_name) LIKE " . db_connect()->escape('%' . db_connect()->escapeLikeString($f['q']) . '%') . " ESCAPE '!')", null, false);

            if (ctype_digit($id)) {
                $builder->orWhere('requests.id', (int) $id);
            }

            $builder->groupEnd();
        }

        foreach (['status' => 'requests.status', 'payment' => 'requests.payment_status', 'method' => 'requests.payment_method'] as $key => $column) {
            if ($f[$key] !== '') {
                $builder->where($column, $f[$key]);
            }
        }

        if ($f['from'] !== '') {
            $builder->where('requests.created_at >=', $f['from'] . ' 00:00:00');
        }

        if ($f['to'] !== '') {
            $builder->where('requests.created_at <=', $f['to'] . ' 23:59:59');
        }
    }

    // ================================================================== users

    public function users()
    {
        $get    = fn (string $k): string => trim((string) $this->request->getGet($k));
        $q      = mb_substr($get('q'), 0, 100);
        $role   = in_array($get('role'), ['user', 'admin'], true) ? $get('role') : '';
        $status = in_array($get('status'), ['active', 'suspended'], true) ? $get('status') : '';
        $sort   = in_array($get('sort'), ['newest', 'oldest', 'balance', 'requests', 'reports'], true) ? $get('sort') : 'newest';
        $check  = $get('check') === 'ledger' ? 'ledger' : '';

        $db      = db_connect();
        $builder = $db->table('users u')
            ->select("u.id, u.first_name, u.last_name, u.email, u.phone_number, u.profile_photo, u.role, u.status,
                      u.suspended_at, u.suspension_reason, u.wallet_balance, u.created_at,
                      (SELECT COUNT(*) FROM requests r WHERE r.user_id = u.id) AS posted,
                      (SELECT COUNT(*) FROM requests r WHERE r.user_id = u.id AND r.status = 'pending' AND r.payment_status IN ('unpaid', 'cod_pending')) AS pending_posted,
                      (SELECT COUNT(*) FROM accepted_requests ar JOIN requests r ON r.id = ar.request_id WHERE ar.runner_id = u.id AND r.status = 'delivered') AS delivered,
                      (SELECT COUNT(*) FROM requests r LEFT JOIN accepted_requests ar ON ar.request_id = r.id
                        WHERE r.status IN ('accepted', 'on_the_way') AND (r.user_id = u.id OR ar.runner_id = u.id)) AS active_errands,
                      (SELECT COUNT(*) FROM user_reports ur WHERE ur.reported_user_id = u.id AND ur.status IN ('open', 'reviewing')) AS open_reports,
                      (SELECT COALESCE(SUM(CASE WHEN p.direction = 'credit' THEN p.amount ELSE -p.amount END), 0) FROM payments p WHERE p.user_id = u.id) AS ledger_total", false);

        if ($check === 'ledger') {
            $builder->where("u.wallet_balance <> (SELECT COALESCE(SUM(CASE WHEN p.direction = 'credit' THEN p.amount ELSE -p.amount END), 0) FROM payments p WHERE p.user_id = u.id)", null, false);
        }

        if ($q !== '') {
            $like = $db->escape('%' . $db->escapeLikeString($q) . '%');
            $builder->groupStart()
                ->where("CONCAT(u.first_name, ' ', u.last_name) LIKE {$like} ESCAPE '!'", null, false)
                ->orLike('u.email', $q)
                ->orLike('u.phone_number', preg_replace('/[\s-]/', '', $q))
                ->groupEnd();
        }

        if ($role !== '') {
            $builder->where('u.role', $role);
        }

        if ($status !== '') {
            $builder->where('u.status', $status);
        }

        match ($sort) {
            'oldest'   => $builder->orderBy('u.id', 'ASC'),
            'balance'  => $builder->orderBy('u.wallet_balance', 'DESC'),
            'requests' => $builder->orderBy('posted', 'DESC'),
            'reports'  => $builder->orderBy('open_reports', 'DESC'),
            default    => $builder->orderBy('u.id', 'DESC'),
        };

        $perPage = config('PasaBuy')->perPage;
        $page    = max(1, (int) $this->request->getGet('page'));
        $total   = (clone $builder)->countAllResults(false);
        $rows    = $builder->orderBy('u.id', 'DESC')->limit($perPage, ($page - 1) * $perPage)->get()->getResultArray();
        $pager   = service('pager');
        $pager->store('default', $page, $perPage, $total);

        $summary = $db->table('users')
            ->select("COUNT(*) AS total, SUM(role = 'admin') AS admins, SUM(status = 'suspended') AS suspended", false)
            ->get()->getRowArray();

        return view('admin/users', [
            'title'    => 'Users | Pasa-Buy Admin',
            'users'    => $rows,
            'pager'    => $pager,
            'total'    => $total,
            'summary'  => array_map('intval', $summary),
            'filters'  => compact('q', 'role', 'status', 'sort', 'check'),
            'filtered' => $q !== '' || $role !== '' || $status !== '' || $sort !== 'newest' || $check !== '',
            'me'       => $this->currentUserId(),
            'returnTo' => 'admin/users' . ($this->request->getUri()->getQuery() !== '' ? '?' . $this->request->getUri()->getQuery() : ''),
        ]);
    }

    public function suspendUser($id)
    {
        $userId = (int) $id;
        $back   = $this->safeReturn('admin/users');
        $result = null;

        $response = $this->attempt(function () use ($userId, &$result): void {
            $result = (new AccountSuspension())->suspend(
                $userId,
                $this->currentUserId(),
                (string) $this->request->getPost('reason'),
                $this->request->getPost('cancel_pending') === '1',
            );
        }, 'Account suspended. They are signed out and cannot log in.', $back);

        if ($result !== null && $result['cancelled'] + $result['skipped'] > 0) {
            $note = ' ' . $result['cancelled'] . ' pending request' . ($result['cancelled'] === 1 ? ' was' : 's were') . ' cancelled'
                . ($result['skipped'] > 0 ? '; ' . $result['skipped'] . ' had changed and were left as they are' : '') . '.';
            session()->setFlashdata('success', session()->getFlashdata('success') . $note);
        }

        return $response;
    }

    public function restoreUser($id)
    {
        return $this->attempt(
            fn () => (new AccountSuspension())->restore((int) $id),
            'Account restored. They can log in again.',
            $this->safeReturn('admin/users'),
        );
    }

    // ================================================================= top-ups

    public function topups()
    {
        $status = (string) $this->request->getGet('status');
        $status = in_array($status, ['pending', 'paid', 'failed'], true) ? $status : '';
        $db     = db_connect();

        $builder = $db->table('wallet_topups t')
            ->select('t.id, t.user_id, t.amount, t.provider, t.status, t.payment_method, t.livemode, t.created_at, t.paid_at,
                      u.first_name, u.last_name, u.email')
            ->join('users u', 'u.id = t.user_id');

        if ($status !== '') {
            $builder->where('t.status', $status);
        }

        $totals = array_fill_keys(['pending', 'paid', 'failed'], ['n' => 0, 'cents' => 0]);

        foreach ($db->table('wallet_topups')->select('status, COUNT(*) AS n, COALESCE(SUM(amount), 0) AS total')->groupBy('status')->get()->getResultArray() as $row) {
            $totals[$row['status']] = ['n' => (int) $row['n'], 'cents' => Money::toCents((string) $row['total'])];
        }

        $demoCredits = $db->table('payments')->select('COUNT(*) AS n, COALESCE(SUM(amount), 0) AS total')
            ->where('type', 'demo_top_up')->get()->getRowArray();

        return view('admin/topups', [
            'title'       => 'Top-ups | Pasa-Buy Admin',
            'status'      => $status,
            'topups'      => $builder->orderBy('t.id', 'DESC')->limit(200)->get()->getResultArray(),
            'totals'      => $totals,
            'demoCredits' => ['n' => (int) $demoCredits['n'], 'cents' => Money::toCents((string) $demoCredits['total'])],
            'staleAfter'  => self::STALE_TOPUP_MINUTES,
        ]);
    }

    // ================================================================= reports

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
                      tgt.first_name AS reported_first, tgt.last_name AS reported_last, tgt.status AS reported_status,
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
            $this->safeReturn('admin/dashboard'),
        );

        // A cancelled errand closes its TalkJS chat for new messages (history stays).
        (new TalkAccess())->syncRequest((int) $id);

        return $response;
    }

    /** Where to go after an admin action: only admin list pages, never an outside URL. */
    private function safeReturn(string $default): string
    {
        $return = (string) $this->request->getPost('return');

        return preg_match('#^admin/(dashboard|requests|users)(\?[\w=&%.+\-\#]*)?$#', $return) === 1 ? '/' . $return : '/' . $default;
    }
}
