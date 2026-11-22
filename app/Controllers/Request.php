<?php

namespace App\Controllers;

use App\Libraries\FeePolicy;
use App\Libraries\Money;
use App\Models\AcceptedRequestModel;
use App\Models\RequestModel;
use App\Models\UserModel;
use App\Services\RequestLifecycle;
use App\Services\WalletService;

/**
 * Request pages. All state changes are delegated to RequestLifecycle /
 * WalletService, which perform them atomically with row locks.
 * Authentication is enforced by the `auth` route filter.
 */
class Request extends BaseController
{
    public function create()
    {
        return view('post_request_page', [
            'title'           => 'Post Request | Pasa-Buy',
            'categories'      => RequestLifecycle::CATEGORIES,
            'platformFeeRate' => FeePolicy::PLATFORM_FEE_BASIS_POINTS / 100,
        ]);
    }

    public function process()
    {
        $config = config('PasaBuy');

        $rules = [
            'item_name'        => ['label' => 'Item name', 'rules' => 'required|max_length[255]'],
            'category'         => ['label' => 'Category', 'rules' => 'required|in_list[' . implode(',', RequestLifecycle::CATEGORIES) . ']'],
            'quantity'         => ['label' => 'Quantity', 'rules' => 'required|is_natural_no_zero|less_than_equal_to[999]'],
            'brand'            => ['label' => 'Brand', 'rules' => 'permit_empty|max_length[255]'],
            'item_notes'       => ['label' => 'Item notes', 'rules' => 'permit_empty|max_length[1000]'],
            'store_name'       => ['label' => 'Store name', 'rules' => 'required|max_length[255]'],
            'alt_store'        => ['label' => 'Alternative store', 'rules' => 'permit_empty|max_length[255]'],
            'store_address'    => ['label' => 'Store address', 'rules' => 'required|max_length[500]'],
            'delivery_address' => ['label' => 'Delivery address', 'rules' => 'required|max_length[500]'],
            'contact_number'   => ['label' => 'Contact number', 'rules' => 'required|regex_match[/^(\+639|09)\d{9}$/]'],
            'landmark'         => ['label' => 'Landmark', 'rules' => 'permit_empty|max_length[255]'],
            'delivery_notes'   => ['label' => 'Delivery notes', 'rules' => 'permit_empty|max_length[1000]'],
            'estimated_price'  => ['label' => 'Estimated price', 'rules' => 'required|regex_match[/^\d{1,8}(\.\d{1,2})?$/]'],
            'service_fee'      => ['label' => 'Service fee', 'rules' => 'required|regex_match[/^\d{1,8}(\.\d{1,2})?$/]'],
            'payment_method'   => ['label' => 'Payment method', 'rules' => 'required|in_list[' . implode(',', RequestLifecycle::PAYMENT_METHODS) . ']'],
            'urgency'          => ['label' => 'Urgency', 'rules' => 'required|in_list[' . implode(',', RequestLifecycle::URGENCIES) . ']'],
        ];

        $messages = [
            'contact_number'  => ['regex_match' => 'Enter a Philippine mobile number like 09171234567.'],
            'estimated_price' => ['regex_match' => 'Enter an amount like 150 or 150.50.'],
            'service_fee'     => ['regex_match' => 'Enter an amount like 50 or 49.50.'],
        ];

        $input = [];

        foreach (array_keys($rules) as $field) {
            $input[$field] = trim((string) $this->request->getPost($field));
        }

        $input['contact_number'] = preg_replace('/[\s-]/', '', $input['contact_number']);

        $validation = service('validation')->setRules($rules, $messages);

        if (! $validation->run($input)) {
            return $this->backWithErrors($validation->getErrors());
        }

        if (Money::toCents($input['estimated_price']) > $config->maxItemPriceCents) {
            return $this->backWithErrors(['estimated_price' => 'Estimated price must not exceed ' . Money::format($config->maxItemPriceCents) . '.']);
        }

        if (Money::toCents($input['service_fee']) > $config->maxServiceFeeCents) {
            return $this->backWithErrors(['service_fee' => 'Service fee must not exceed ' . Money::format($config->maxServiceFeeCents) . '.']);
        }

        return $this->attempt(
            fn () => (new RequestLifecycle())->create($this->currentUserId(), $input),
            'Request posted. Nearby runners can now accept it.',
            '/my-requests',
            true,
        );
    }

    public function browse()
    {
        $config   = config('PasaBuy');
        $model    = new RequestModel();
        $search   = trim((string) $this->request->getGet('search'));
        $category = (string) $this->request->getGet('category');
        $urgency  = (string) $this->request->getGet('urgency');
        $sort     = (string) $this->request->getGet('sort');

        $model->select('id, user_id, item_name, category, quantity, store_name, store_address, landmark, estimated_price, service_fee, platform_fee, payment_method, urgency, status, created_at')
            ->where('status', 'pending')
            ->where('user_id !=', $this->currentUserId());

        if ($search !== '') {
            $model->groupStart()->like('item_name', $search)->orLike('store_name', $search)->groupEnd();
        }

        if (in_array($category, RequestLifecycle::CATEGORIES, true)) {
            $model->where('category', $category);
        }

        if (in_array($urgency, RequestLifecycle::URGENCIES, true)) {
            $model->where('urgency', $urgency);
        }

        $sort === 'highest_fee'
            ? $model->orderBy('service_fee', 'DESC')->orderBy('id', 'DESC')
            : $model->orderBy('created_at', 'DESC')->orderBy('id', 'DESC');

        return view('browse_requests_page', [
            'title'      => 'Browse Requests | Pasa-Buy',
            'requests'   => $model->paginate($config->perPage),
            'pager'      => $model->pager,
            'categories' => RequestLifecycle::CATEGORIES,
            'filters'    => compact('search', 'category', 'urgency', 'sort'),
        ]);
    }

    public function accept($id)
    {
        return $this->attempt(
            fn () => (new RequestLifecycle())->accept((int) $id, $this->currentUserId()),
            'Request accepted. Message the requester and mark it On the Way when you set off.',
            '/accepted-requests',
        );
    }

    public function updateAcceptedStatus($requestId)
    {
        $status    = (string) $this->request->getPost('status');
        $lifecycle = new RequestLifecycle();
        $runnerId  = $this->currentUserId();

        return match ($status) {
            'on_the_way' => $this->attempt(
                fn () => $lifecycle->markOnTheWay((int) $requestId, $runnerId),
                'Marked as On the Way. The requester has been notified.',
                '/accepted-requests',
            ),
            'delivered' => $this->attempt(
                fn () => $lifecycle->markDelivered((int) $requestId, $runnerId, $this->request->getPost('cod_confirmed') === '1'),
                'Marked as Delivered. The requester has been notified.',
                '/accepted-requests',
            ),
            default => redirect()->to('/accepted-requests')->with('error', 'Invalid status selected.'),
        };
    }

    public function payRunner($requestId)
    {
        return $this->attempt(
            fn () => (new WalletService())->payForRequest((int) $requestId, $this->currentUserId()),
            'Payment sent to the runner from your wallet.',
            '/request-details/' . (int) $requestId,
        );
    }

    public function cancel($requestId)
    {
        return $this->attempt(
            fn () => (new RequestLifecycle())->cancel((int) $requestId, $this->currentUserId()),
            'Request cancelled.',
            '/my-requests',
        );
    }

    public function acceptedRequests()
    {
        $config = config('PasaBuy');
        $status = (string) $this->request->getGet('status');
        $model  = new AcceptedRequestModel();

        $model->select('accepted_requests.id AS accepted_id, accepted_requests.request_id, accepted_requests.runner_id,
                accepted_requests.status AS accepted_status, accepted_requests.accepted_at,
                r.item_name, r.quantity, r.store_name, r.urgency, r.payment_method, r.payment_status,
                r.estimated_price, r.service_fee, r.platform_fee, r.total_amount, r.status AS request_status,
                u.first_name, u.last_name, u.id AS user_id')
            ->join('requests r', 'r.id = accepted_requests.request_id')
            ->join('users u', 'u.id = r.user_id')
            ->where('accepted_requests.runner_id', $this->currentUserId());

        if (in_array($status, ['accepted', 'on_the_way', 'delivered', 'cancelled'], true)) {
            $model->where('accepted_requests.status', $status);
        } else {
            $status = '';
        }

        $runner = (new UserModel())->select('wallet_balance')->find($this->currentUserId());

        return view('accepted_requests_page', [
            'title'            => 'Accepted Requests | Pasa-Buy',
            'acceptedRequests' => $model->orderBy('accepted_requests.accepted_at', 'DESC')->paginate($config->perPage),
            'pager'            => $model->pager,
            'currentStatus'    => $status,
            'walletBalance'    => $runner['wallet_balance'] ?? '0.00',
        ]);
    }

    public function details($id = null)
    {
        $request = (new RequestModel())->find((int) $id);

        if ($request === null) {
            return redirect()->to('/browse-requests')->with('error', 'Request not found.');
        }

        $acceptedInfo = (new AcceptedRequestModel())->where('request_id', $request['id'])->first();
        $viewerId     = $this->currentUserId();
        $isRequester  = (int) $request['user_id'] === $viewerId;
        $isRunner     = $acceptedInfo !== null && (int) $acceptedInfo['runner_id'] === $viewerId;
        $isAdmin      = $this->isAdmin();

        // Non-participants may only look at open requests they could accept.
        if (! $isRequester && ! $isRunner && ! $isAdmin && $request['status'] !== 'pending') {
            return redirect()->to('/browse-requests')->with('error', 'This request is no longer available.');
        }

        $requester = (new UserModel())
            ->select('id, first_name, last_name, profile_photo')
            ->find($request['user_id']);

        $runner = $acceptedInfo !== null
            ? (new UserModel())->select('id, first_name, last_name, profile_photo')->find($acceptedInfo['runner_id'])
            : null;

        $viewer = (new UserModel())->select('wallet_balance')->find($viewerId);

        return view('request_details_page', [
            'title'        => 'Request Details | Pasa-Buy',
            'request'      => $request,
            'requester'    => $requester,
            'runner'       => $runner,
            'acceptedInfo' => $acceptedInfo,
            'isRequester'  => $isRequester,
            'isRunner'     => $isRunner,
            // Exact address and phone are shared only with the participants.
            'canSeeContact' => $isRequester || $isRunner || $isAdmin,
            'walletBalance' => $viewer['wallet_balance'] ?? '0.00',
        ]);
    }

    public function myRequests()
    {
        $config = config('PasaBuy');
        $status = (string) $this->request->getGet('status');
        $model  = new RequestModel();

        $model->where('user_id', $this->currentUserId());

        if (in_array($status, ['pending', 'accepted', 'on_the_way', 'delivered', 'cancelled'], true)) {
            $model->where('status', $status);
        } else {
            $status = '';
        }

        return view('my_requests_page', [
            'title'         => 'My Requests | Pasa-Buy',
            // Active requests first; finished ones (shown as compact rows) after.
            'requests'      => $model->orderBy("status IN ('delivered', 'cancelled')", 'ASC', false)
                ->orderBy('created_at', 'DESC')->orderBy('id', 'DESC')->paginate($config->perPage),
            'pager'         => $model->pager,
            'currentStatus' => $status,
        ]);
    }
}
