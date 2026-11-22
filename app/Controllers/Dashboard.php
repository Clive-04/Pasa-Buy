<?php

namespace App\Controllers;

use App\Models\RequestModel;
use App\Models\RequestNotificationModel;

class Dashboard extends BaseController
{
    public function index()
    {
        $userId = $this->currentUserId();
        $db     = db_connect();

        $myActive = (new RequestModel())
            ->where('user_id', $userId)
            ->whereIn('status', ['pending', 'accepted', 'on_the_way'])
            ->orderBy('created_at', 'DESC')
            ->findAll(5);

        $deliveries = $db->table('accepted_requests ar')
            ->select('ar.status AS run_status, r.id, r.item_name, r.payment_method, r.payment_status, r.total_amount, r.platform_fee')
            ->join('requests r', 'r.id = ar.request_id')
            ->where('ar.runner_id', $userId)
            ->whereIn('ar.status', ['accepted', 'on_the_way'])
            ->orderBy('ar.accepted_at', 'DESC')
            ->get()->getResultArray();

        // Things this person should do next, most urgent first.
        $attention = [];

        foreach ($myActive as $request) {
            if (in_array($request['status'], ['accepted', 'on_the_way'], true)
                && $request['payment_method'] !== 'cod' && $request['payment_status'] === 'unpaid') {
                $attention[] = [
                    'icon'   => 'wallet',
                    'title'  => 'Pay your runner for ' . $request['item_name'],
                    'text'   => money($request['total_amount']) . ' from your wallet. The runner can\'t complete the delivery until you pay.',
                    'url'    => 'request-details/' . $request['id'],
                    'action' => 'Pay now',
                ];
            }
        }

        foreach ($deliveries as $delivery) {
            if ($delivery['run_status'] === 'accepted') {
                $attention[] = [
                    'icon'   => 'truck',
                    'title'  => 'Start delivering ' . $delivery['item_name'],
                    'text'   => 'Buy the items, then mark the request On the way.',
                    'url'    => 'accepted-requests?status=accepted',
                    'action' => 'Update status',
                ];
            } elseif ($delivery['payment_method'] === 'cod' || $delivery['payment_status'] === 'paid') {
                $attention[] = [
                    'icon'   => 'check-circle',
                    'title'  => 'Complete delivery of ' . $delivery['item_name'],
                    'text'   => $delivery['payment_method'] === 'cod'
                        ? 'Collect ' . money($delivery['total_amount']) . ' in cash, then mark it Delivered.'
                        : 'Payment received. Mark it Delivered after handing over the items.',
                    'url'    => 'accepted-requests?status=on_the_way',
                    'action' => 'Complete',
                ];
            }
        }

        $unreadMessages = unread_counts()['messages'];

        if ($unreadMessages > 0) {
            $attention[] = [
                'icon'   => 'message',
                'title'  => $unreadMessages . ' unread message' . ($unreadMessages === 1 ? '' : 's'),
                'text'   => 'Someone is waiting for your reply.',
                'url'    => 'messages',
                'action' => 'Open messages',
            ];
        }

        return view('dashboard_page', [
            'title'               => 'Dashboard | Pasa-Buy',
            'me'                  => current_user(),
            'attention'           => $attention,
            'activeRequests'      => (new RequestModel())->where('user_id', $userId)->whereIn('status', ['pending', 'accepted', 'on_the_way'])->countAllResults(),
            'activeDeliveries'    => count($deliveries),
            'completedDeliveries' => $db->table('accepted_requests')->where('runner_id', $userId)->where('status', 'delivered')->countAllResults(),
            'openRequests'        => (new RequestModel())->where('status', 'pending')->where('user_id !=', $userId)->countAllResults(),
            'myActiveRequests'    => $myActive,
            'availableRequests'   => (new RequestModel())
                ->select('id, item_name, store_name, service_fee, platform_fee, estimated_price, urgency, payment_method, created_at')
                ->where('status', 'pending')->where('user_id !=', $userId)
                ->orderBy('created_at', 'DESC')->findAll(4),
            'recentActivity'      => (new RequestNotificationModel())->where('user_id', $userId)->orderBy('created_at', 'DESC')->orderBy('id', 'DESC')->findAll(5),
        ]);
    }
}
