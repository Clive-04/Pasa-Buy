<?php

namespace App\Controllers;

use App\Models\RequestNotificationModel;

class RequestNotification extends BaseController
{
    public function index()
    {
        $model = new RequestNotificationModel();

        $model->select('request_notifications.*, users.first_name, users.last_name')
            ->join('users', 'users.id = request_notifications.sender_id', 'left')
            ->where('request_notifications.user_id', $this->currentUserId())
            ->orderBy('request_notifications.created_at', 'DESC')
            ->orderBy('request_notifications.id', 'DESC');

        return view('notifications_page', [
            'title'         => 'Notifications | Pasa-Buy',
            'notifications' => $model->paginate(config('PasaBuy')->perPage * 2),
            'pager'         => $model->pager,
        ]);
    }

    /**
     * Small JSON endpoint polled by the page to refresh unread badges
     * without reloading (replaces the old 5-second full-page refresh).
     */
    public function unreadCount()
    {
        $userId = $this->currentUserId();
        $db     = db_connect();

        return $this->response
            ->setHeader('Cache-Control', 'no-store, private')
            ->setJSON([
                'notifications' => $db->table('request_notifications')->where('user_id', $userId)->where('is_read', 0)->countAllResults(),
                // With TalkJS, message counts come from TalkJS itself (null = don't touch).
                'messages'      => config('TalkJS')->isConfigured() ? null : $db->table('notifications')->where('user_id', $userId)->where('type', 'message')->where('is_read', 0)->countAllResults(),
            ]);
    }

    /**
     * Latest notifications for the top-bar dropdown (JSON). The browser
     * renders these with textContent, so no HTML is ever injected.
     */
    public function feed()
    {
        $rows = (new RequestNotificationModel())
            ->where('user_id', $this->currentUserId())
            ->orderBy('created_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->findAll(8);

        return $this->response
            ->setHeader('Cache-Control', 'no-store, private')
            ->setJSON([
                'unread' => unread_counts()['notifications'],
                'items'  => array_map(static fn (array $n): array => [
                    'title'  => (string) $n['title'],
                    'text'   => (string) $n['content'],
                    'when'   => time_ago($n['created_at']),
                    'type'   => (string) $n['type'],
                    'unread' => ! $n['is_read'],
                    'url'    => base_url('notifications/open/' . (int) $n['id']),
                ], $rows),
            ]);
    }

    public function open($id)
    {
        $model        = new RequestNotificationModel();
        $notification = $model->where('id', (int) $id)->where('user_id', $this->currentUserId())->first();

        if ($notification === null) {
            return redirect()->to('/notifications')->with('error', 'Notification not found.');
        }

        $model->update($notification['id'], ['is_read' => 1]);

        if (! empty($notification['request_id'])) {
            return redirect()->to('/request-details/' . (int) $notification['request_id']);
        }

        return redirect()->to('/notifications');
    }

    public function markAllRead()
    {
        (new RequestNotificationModel())
            ->where('user_id', $this->currentUserId())
            ->where('is_read', 0)
            ->set(['is_read' => 1])
            ->update();

        // The navbar dropdown sends this in the background and stays open.
        if ($this->request->isAJAX()) {
            return $this->response->setJSON(['ok' => true, 'notifications' => 0]);
        }

        // Without JavaScript: stay on the page the user was on.
        return redirect()->back()->with('success', 'All notifications marked as read.');
    }
}
