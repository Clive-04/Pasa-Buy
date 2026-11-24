<?php

/*
 * UI building blocks shared by all pages: icons, page headers, request
 * progress and "what happens next" guidance.
 *
 * Icons are inline SVG paths adapted from Lucide (https://lucide.dev,
 * ISC License), so no icon font or CDN is needed.
 */

if (! function_exists('icon')) {
    function icon(string $name, string $class = ''): string
    {
        static $paths = [
            'home'        => '<path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>',
            'dashboard'   => '<rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/>',
            'plus'        => '<circle cx="12" cy="12" r="10"/><path d="M8 12h8M12 8v8"/>',
            'list'        => '<rect x="8" y="2" width="8" height="4" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M12 11h4M12 16h4M8 11h.01M8 16h.01"/>',
            'search'      => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
            'truck'       => '<path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/><path d="M15 18H9"/><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.62l-3.48-4.35A1 1 0 0 0 17.52 8H14"/><circle cx="17" cy="18" r="2"/><circle cx="7" cy="18" r="2"/>',
            'wallet'      => '<path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/><path d="M3 5v14a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-4"/>',
            'message'     => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
            'bell'        => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
            'user'        => '<circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 0 0-16 0"/>',
            'users'       => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
            'logout'      => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5M21 12H9"/>',
            'arrow-left'  => '<path d="m12 19-7-7 7-7M19 12H5"/>',
            'arrow-right' => '<path d="M5 12h14M12 5l7 7-7 7"/>',
            'chevron'     => '<path d="m9 18 6-6-6-6"/>',
            'check'       => '<path d="M20 6 9 17l-5-5"/>',
            'check-circle'=> '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/>',
            'x'           => '<path d="M18 6 6 18M6 6l12 12"/>',
            'x-circle'    => '<circle cx="12" cy="12" r="10"/><path d="m15 9-6 6M9 9l6 6"/>',
            'clock'       => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
            'map-pin'     => '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',
            'store'       => '<path d="M3 9 4.5 4h15L21 9"/><path d="M4 9v11h16V9"/><path d="M3 9h18"/><path d="M9 20v-6h6v6"/>',
            'package'     => '<path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5M12 22V12"/>',
            'cash'        => '<rect width="20" height="12" x="2" y="6" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/>',
            'star'        => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
            'edit'        => '<path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/>',
            'mail'        => '<rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-10 5L2 7"/>',
            'phone'       => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/>',
            'calendar'    => '<rect width="18" height="18" x="3" y="4" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
            'shield'      => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
            'lock'        => '<rect width="18" height="11" x="3" y="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
            'info'        => '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>',
            'alert'       => '<circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/>',
            'send'        => '<path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/>',
            'paperclip'   => '<path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48"/>',
            'menu'        => '<path d="M4 6h16M4 12h16M4 18h16"/>',
            'eye'         => '<path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>',
            'eye-off'     => '<path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.4 10.4 0 0 1 12 5c7 0 10 7 10 7a13.2 13.2 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.5 13.5 0 0 0 2 12s3 7 10 7a9.7 9.7 0 0 0 5.39-1.61"/><path d="m2 2 20 20"/>',
            'help'        =>'<circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3M12 17h.01"/>',
            'gift'        => '<rect x="3" y="8" width="18" height="4" rx="1"/><path d="M12 8v13M19 12v7a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-7"/><path d="M7.5 8a2.5 2.5 0 0 1 0-5C11 3 12 8 12 8s1-5 4.5-5a2.5 2.5 0 0 1 0 5"/>',
            'save'        => '<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><path d="M17 21v-8H7v8M7 3v5h8"/>',
            'sun'         => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/>',
            'trash'       => '<path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/>',
            'refresh'     =>'<path d="M21 12a9 9 0 1 1-2.64-6.36L21 8"/><path d="M21 3v5h-5"/>',
            'moon'        => '<path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>',
            'settings'    => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
        ];

        $svg = $paths[$name] ?? $paths['info'];

        return '<svg class="i' . ($class !== '' ? ' ' . esc($class, 'attr') : '') . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $svg . '</svg>';
    }
}

if (! function_exists('page_header')) {
    /**
     * Breadcrumb + title + optional actions, used at the top of every page
     * so users always see where they are and where "back" goes.
     *
     * @param array{
     *     crumbs?: list<array{0: string, 1: ?string}>,
     *     eyebrow?: string, title: string, lead?: string, actions?: string
     * } $o
     */
    function page_header(array $o): string
    {
        $html = '<header class="page-head">';

        if (! empty($o['crumbs'])) {
            $html .= '<nav class="crumbs" aria-label="Breadcrumb"><ol>';
            $last = count($o['crumbs']) - 1;

            foreach ($o['crumbs'] as $i => [$label, $url]) {
                $html .= $i === $last || $url === null
                    ? '<li aria-current="page">' . esc($label) . '</li>'
                    : '<li><a href="' . esc(base_url($url), 'attr') . '">' . ($i === 0 ? icon('arrow-left', 'crumb-back') : '') . esc($label) . '</a></li>';
            }

            $html .= '</ol></nav>';
        }

        $html .= '<div class="page-head-row"><div class="page-head-text">';

        if (! empty($o['eyebrow'])) {
            $html .= '<p class="eyebrow">' . esc($o['eyebrow']) . '</p>';
        }

        $html .= '<h1>' . esc($o['title']) . '</h1>';

        $html .= '</div>';

        if (! empty($o['actions'])) {
            $html .= '<div class="page-head-actions">' . $o['actions'] . '</div>';
        }

        return $html . '</div></header>';
    }
}

if (! function_exists('request_steps')) {
    /**
     * Progress tracker for a request: Posted → Accepted → On the way → Delivered.
     *
     * @param array<string, mixed> $request needs status, payment_method, payment_status
     */
    function request_steps(array $request, bool $compact = false): string
    {
        $status = (string) $request['status'];

        if ($status === 'cancelled') {
            return '<div class="steps steps-cancelled' . ($compact ? ' steps-compact' : '') . '" role="img" aria-label="Request cancelled">'
                . icon('x-circle') . '<span>Cancelled</span></div>';
        }

        $order   = ['pending' => 0, 'accepted' => 1, 'on_the_way' => 2, 'delivered' => 3];
        $current = $order[$status] ?? 0;
        $isCod   = ($request['payment_method'] ?? '') === 'cod';
        $paid    = in_array($request['payment_status'] ?? '', ['paid', 'paid_cod'], true);

        $steps = [
            ['Posted', 'package'],
            ['Accepted', 'user'],
            ['On the way', 'truck'],
            ['Delivered', 'check'],
        ];

        $label = 'Progress: step ' . ($current + 1) . ' of 4, ' . $steps[$current][0];
        $html  = '<ol class="steps' . ($compact ? ' steps-compact' : '') . '" aria-label="' . esc($label, 'attr') . '" style="--progress:' . ($current / 3) . '">';

        foreach ($steps as $i => [$name, $iconName]) {
            $state = $i < $current ? 'done' : ($i === $current ? 'current' : 'todo');

            if ($status === 'delivered') {
                $state = 'done';
            }

            $html .= '<li class="step step-' . $state . '"' . ($state === 'current' ? ' aria-current="step"' : '') . '>'
                . '<span class="step-dot">' . icon($state === 'done' ? 'check' : $iconName) . '</span>'
                . '<span class="step-label">' . $name . '</span></li>';
        }

        $html .= '</ol>';

        if (! $compact) {
            $html .= '<p class="steps-payment">' . icon($isCod ? 'cash' : 'wallet') . ' '
                . esc(payment_method_label($request['payment_method'] ?? '')) . ' · '
                . '<span class="' . ($paid ? 'text-success' : 'text-muted') . '">' . esc(payment_status_label($request['payment_status'] ?? '')) . '</span></p>';
        }

        return $html;
    }
}

if (! function_exists('next_step')) {
    /**
     * Plain-language "what happens next" for the person looking at a request.
     *
     * @param array<string, mixed> $request
     *
     * @return array{tone: string, title: string, text: string}
     */
    function next_step(array $request, string $viewer): array
    {
        $status = (string) $request['status'];
        $isCod  = ($request['payment_method'] ?? '') === 'cod';
        $unpaid = ($request['payment_status'] ?? '') === 'unpaid';
        $total  = money($request['total_amount'] ?? '0');
        $net    = money_cents(\App\Libraries\Money::toCents((string) ($request['total_amount'] ?? '0')) - \App\Libraries\Money::toCents((string) ($request['platform_fee'] ?? '0')));

        $r = static fn (string $tone, string $title, string $text): array => compact('tone', 'title', 'text');

        // R5 price approval applies to assigned, unsettled errands (not to legacy settled ones).
        $price     = (string) ($request['price_status'] ?? 'not_required');
        $priceOpen = in_array($status, ['accepted', 'on_the_way'], true)
            && in_array($request['payment_status'] ?? '', ['unpaid', 'cod_pending'], true)
            && $price !== 'not_required';

        return match (true) {
            $status === 'cancelled' => $r('muted', 'This request was cancelled', 'Nothing else needs to happen. It stays in the history for reference.'),
            $status === 'delivered' => $r('success', 'Completed', $viewer === 'other'
                ? 'This errand is finished.'
                : 'The errand is finished. Rate how it went: every delivery gets its own rating.'),

            $status === 'pending' && $viewer === 'requester' => $r('info', 'Waiting for a runner', 'Nearby runners can see your request. You will be notified when someone accepts it. You can still cancel it.'),
            $status === 'pending' => $r('action', 'Want to do this errand?', 'Accept it to become the runner. You buy the items (about ' . money($request['estimated_price'] ?? '0') . '), deliver them, and receive ' . $net . ' in total. That is the item cost back plus your service fee, minus the 10% platform fee.'),

            // R5: the actual price (with a receipt) must be approved before any payment.
            $viewer === 'requester' && $priceOpen && $price === 'submitted' => $r('action', 'Check the actual price', 'Your runner sent the actual item price and a receipt. Approve it to continue, or reject it with a reason.'),
            $viewer === 'requester' && $priceOpen && $price === 'rejected' => $r('info', 'Waiting for a corrected price', 'You didn’t approve the last price. The runner can send a corrected amount and receipt. Message them if you need to sort it out.'),
            $viewer === 'requester' && $priceOpen && $price !== 'approved' => $r('info', 'Your runner is getting your items', 'After buying, your runner sends the actual price with a receipt for you to approve. You pay only the approved amount.'),
            $viewer === 'runner' && $priceOpen && in_array($price, ['awaiting_receipt', 'rejected'], true) => $r('action', $price === 'rejected' ? 'Send a corrected price' : 'Send the actual price', $price === 'rejected'
                ? 'The requester didn’t approve the last price. Send the correct amount with the receipt, or message them.'
                : 'After buying the items, send the actual amount with a photo of the receipt. The requester approves it before paying.'),
            $viewer === 'runner' && $priceOpen && $price === 'submitted' => $r('info', 'Waiting for the requester to approve the price', 'You can mark the errand On the way meanwhile. Payment uses the approved amount.'),

            $viewer === 'requester' && ! $isCod && $unpaid => $r('action', 'Pay your runner', 'Pay ' . $total . ' from your wallet. The runner cannot complete the delivery until you pay.'),
            $viewer === 'requester' && $isCod && $status === 'accepted' => $r('info', 'Your runner is getting your items', 'Have ' . $total . ' in cash ready to pay the runner on delivery.'),
            $viewer === 'requester' && $status === 'accepted' => $r('info', 'Your runner is getting your items', 'You have paid. You will be notified when your items are on the way.'),
            $viewer === 'requester' => $r('info', 'Your items are on the way', $isCod ? 'Pay the runner ' . $total . ' in cash when they arrive.' : 'You have paid. The runner will mark it delivered when they hand over your items.'),

            $viewer === 'runner' && $status === 'accepted' => $r('action', 'Buy the items, then set off', 'When you leave the store, mark the request as On the way so the requester knows.'),
            $viewer === 'runner' && ! $isCod && $unpaid => $r('info', 'Waiting for the requester to pay', 'You can complete the delivery once the requester pays from their wallet. Message them if needed.'),
            $viewer === 'runner' => $r('action', 'Hand over the items', $isCod ? 'Collect ' . $total . ' in cash, then mark the request Delivered.' : 'Payment received. Mark the request Delivered after handing over the items.'),

            default => $r('info', 'In progress', 'The requester and runner are completing this errand.'),
        };
    }
}

if (! function_exists('current_user')) {
    /**
     * The signed-in user's display data, loaded once per request.
     *
     * @return array<string, mixed>|null
     */
    function current_user(): ?array
    {
        // Cached per request object (not per process: worker mode / tests reuse
        // processes). A WeakMap entry dies with its request; spl_object_id()
        // numbers are reused, which could serve a previous request's data.
        static $cache = null;
        $cache ??= new WeakMap();
        $id = (int) session('user_id');

        if ($id === 0) {
            return null;
        }

        $current = service('request');
        $mine    = $cache[$current] ?? [];

        if (! array_key_exists($id, $mine)) {
            $mine[$id] = db_connect()->table('users')
                ->select('id, first_name, last_name, username, profile_photo, wallet_balance, role')
                ->where('id', $id)->get()->getRowArray();
            $cache[$current] = $mine;
        }

        return $mine[$id];
    }
}

if (! function_exists('talkjs_boot')) {
    /**
     * Browser settings for the signed-in user's TalkJS session, or null when
     * TalkJS isn't configured. The token is a short HS256 JWT made locally
     * (no network call), so this is cheap to render on every page.
     *
     * @return array{appId: string, userId: string, token: string}|null
     */
    function talkjs_boot(): ?array
    {
        $id = (int) session('user_id');

        if ($id === 0 || ! config('TalkJS')->isConfigured()) {
            return null;
        }

        $client = service('talkjs');

        return [
            'appId'  => $client->appId(),
            'userId' => \App\Libraries\TalkJsClient::userId($id),
            'token'  => $client->userToken($id),
        ];
    }
}

if (! function_exists('unread_counts')) {
    /** @return array{notifications: int, messages: int} */
    function unread_counts(): array
    {
        static $cache = null;
        $cache ??= new WeakMap(); // per request object, see current_user()
        $id = (int) session('user_id');

        if ($id === 0) {
            return ['notifications' => 0, 'messages' => 0];
        }

        $current = service('request');

        if (isset($cache[$current][$id])) {
            return $cache[$current][$id];
        }

        $mine = $cache[$current] ?? [];

        return ($cache[$current] = $mine + [$id => [
            'notifications' => db_connect()->table('request_notifications')->where('user_id', $id)->where('is_read', 0)->countAllResults(),
            // With TalkJS, unread chat counts come live from the browser session.
            'messages'      => config('TalkJS')->isConfigured() ? 0 : db_connect()->table('notifications')->where('user_id', $id)->where('type', 'message')->where('is_read', 0)->countAllResults(),
        ]])[$id];
    }
}

if (! function_exists('action_items')) {
    /**
     * Errands waiting for the signed-in user, each listed once with the one
     * thing to do next. Feeds the nav badges and the "Needs you" lists on
     * My requests / My deliveries, so the counts always match.
     *
     *  - requests (as requester): pay the runner (wallet, not yet paid,
     *    accepted / on the way) or rate a delivery from the last 30 days.
     *  - deliveries (as runner): mark On the way (accepted), mark Delivered
     *    (on the way and paid, or cash on delivery), or rate the requester
     *    for a delivery from the last 30 days.
     *
     * Older unrated deliveries can still be rated on their request page;
     * they just don't keep a badge lit forever.
     *
     * @return array{requests: list<array{id: int, item_name: string, action: string}>, deliveries: list<array{id: int, item_name: string, action: string}>}
     */
    function action_items(): array
    {
        // Cached per request object (a WeakMap entry disappears with the object,
        // unlike spl_object_id(), whose numbers are reused).
        static $cache = null;
        $cache ??= new WeakMap();
        $id = (int) session('user_id');

        if ($id === 0 || session('role') === 'admin') {
            return ['requests' => [], 'deliveries' => []];
        }

        $current = service('request');

        if (isset($cache[$current][$id])) {
            return $cache[$current][$id];
        }

        $db     = db_connect();
        $recent = date('Y-m-d H:i:s', time() - 30 * 86400);

        $requests = $db->query(
            "SELECT r.id, r.item_name,
                    CASE WHEN r.status = 'delivered' THEN 'rate' WHEN r.price_status = 'submitted' THEN 'review_price' ELSE 'pay' END AS action
             FROM requests r
             JOIN accepted_requests ar ON ar.request_id = r.id
             WHERE r.user_id = ?
               AND ((r.status IN ('accepted', 'on_the_way') AND r.payment_status IN ('unpaid', 'cod_pending') AND r.price_status = 'submitted')
                 OR (r.status IN ('accepted', 'on_the_way') AND r.payment_method <> 'cod' AND r.payment_status = 'unpaid'
                     AND r.price_status IN ('approved', 'not_required'))
                 OR (r.status = 'delivered' AND r.updated_at >= ?
                     AND NOT EXISTS (SELECT 1 FROM user_ratings ur WHERE ur.rater_id = ? AND ur.request_id = r.id)))
             ORDER BY r.updated_at DESC, r.id DESC",
            [$id, $recent, $id],
        )->getResultArray();

        $deliveries = $db->query(
            "SELECT r.id, r.item_name,
                    CASE
                        WHEN r.status IN ('accepted', 'on_the_way') AND r.price_status IN ('awaiting_receipt', 'rejected')
                             AND r.payment_status IN ('unpaid', 'cod_pending') THEN 'send_price'
                        WHEN r.status = 'accepted' THEN 'start'
                        WHEN r.status = 'on_the_way' THEN 'deliver'
                        ELSE 'rate' END AS action
             FROM accepted_requests ar
             JOIN requests r ON r.id = ar.request_id
             WHERE ar.runner_id = ?
               AND ((r.status IN ('accepted', 'on_the_way') AND r.price_status IN ('awaiting_receipt', 'rejected')
                     AND r.payment_status IN ('unpaid', 'cod_pending'))
                 OR r.status = 'accepted'
                 OR (r.status = 'on_the_way' AND ((r.payment_method = 'cod' AND r.price_status = 'approved') OR r.payment_status = 'paid'))
                 OR (r.status = 'delivered' AND r.updated_at >= ?
                     AND NOT EXISTS (SELECT 1 FROM user_ratings ur WHERE ur.rater_id = ? AND ur.request_id = r.id)))
             ORDER BY r.updated_at DESC, r.id DESC",
            [$id, $recent, $id],
        )->getResultArray();

        $cast = static fn (array $rows): array => array_map(static fn (array $r): array => [
            'id' => (int) $r['id'], 'item_name' => (string) $r['item_name'], 'action' => (string) $r['action'],
        ], $rows);

        $mine      = $cache[$current] ?? [];
        $mine[$id] = ['requests' => $cast($requests), 'deliveries' => $cast($deliveries)];
        $cache[$current] = $mine;

        return $mine[$id];
    }
}

if (! function_exists('action_label')) {
    function action_label(string $action): string
    {
        return match ($action) {
            'pay'          => 'Pay your runner',
            'review_price' => 'Check the actual price',
            'send_price'   => 'Send the actual price',
            'rate'    => 'Rate this delivery',
            'start'   => 'Mark as On the way',
            'deliver' => 'Mark as Delivered',
            default   => 'Open the request',
        };
    }
}

if (! function_exists('count_badge')) {
    /** Small number badge with an accessible explanation; '' when zero. */
    function count_badge(int $count, string $meaning): string
    {
        if ($count <= 0) {
            return '';
        }

        return '<span class="badge badge-action">' . ($count > 9 ? '9+' : $count)
            . '<span class="visually-hidden"> ' . esc($meaning) . '</span></span>';
    }
}

if (! function_exists('unread_badge')) {
    /** Badge that app.js keeps up to date; hidden when zero. */
    function unread_badge(string $kind, string $class = 'badge'): string
    {
        $n = unread_counts()[$kind] ?? 0;

        return '<span class="' . esc($class, 'attr') . '" data-unread="' . esc($kind, 'attr') . '"' . ($n > 0 ? '' : ' hidden') . '>'
            . ($n > 9 ? '9+' : $n) . '<span class="visually-hidden"> unread</span></span>';
    }
}

if (! function_exists('avatar')) {
    /** Round avatar: photo if present, otherwise initials. */
    function avatar(?array $user, string $size = 'md'): string
    {
        $url = avatar_url($user['profile_photo'] ?? null);
        $inner = $url !== null
            ? '<img src="' . esc($url, 'attr') . '" alt="" loading="lazy">'
            : esc(initials($user['first_name'] ?? '?', $user['last_name'] ?? ''));

        return '<span class="avatar avatar-' . esc($size, 'attr') . '" aria-hidden="true">' . $inner . '</span>';
    }
}

if (! function_exists('nav_attrs')) {
    /** ' is-active" aria-current="page' when the current URI matches. */
    function nav_attrs(string ...$paths): string
    {
        $current = trim(uri_string(), '/');

        foreach ($paths as $path) {
            if ($current === $path || ($path !== '' && str_starts_with($current, $path . '/'))) {
                return ' is-active" aria-current="page';
            }
        }

        return '';
    }
}

if (! function_exists('theme_toggle')) {
    /** Light/dark switch; the choice is remembered in this browser. */
    function theme_toggle(): string
    {
        return '<button type="button" class="icon-btn theme-toggle" data-theme-toggle aria-label="Switch between light and dark mode" title="Light / dark mode">'
            . icon('moon', 'i-moon') . icon('sun', 'i-sun') . '</button>';
    }
}

if (! function_exists('brand_mark')) {
    function brand_mark(string $href): string
    {
        return '<a href="' . esc($href, 'attr') . '" class="brand" aria-label="Pasa-Buy home"><span class="brand-tile"><img src="' . base_url('img/logo.PNG') . '" alt=""></span><span class="brand-word">Pasa-Buy<span class="brand-dot">.</span></span></a>';
    }
}

if (! function_exists('time_ago')) {
    function time_ago(?string $timestamp): string
    {
        if ($timestamp === null || $timestamp === '') {
            return '';
        }

        $diff = max(0, time() - strtotime($timestamp));

        return match (true) {
            $diff < 60     => 'just now',
            $diff < 3600   => floor($diff / 60) . ' min ago',
            $diff < 86400  => floor($diff / 3600) . ' h ago',
            $diff < 604800 => floor($diff / 86400) . ' d ago',
            default        => date('M j, Y', strtotime($timestamp)),
        };
    }
}

if (! function_exists('asset')) {
    /** URL for a file under public/ with a ?v= stamp, so browsers pick up changes. */
    function asset(string $path): string
    {
        $file = FCPATH . ltrim($path, '/');

        return base_url($path) . (is_file($file) ? '?v=' . filemtime($file) : '');
    }
}
