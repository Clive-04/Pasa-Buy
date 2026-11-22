<?php

namespace App\Controllers;

use App\Models\RequestModel;

class AdminController extends BaseController
{
    public function dashboard()
    {
        if (session()->get('role') !== 'admin') {
            return redirect()->to('/dashboard');
        }

        $requestModel = new RequestModel();

        // Requests shown in the table = delivered only
        $deliveredRequests = $requestModel
            ->where('status', 'delivered')
            ->orderBy('created_at', 'DESC')
            ->findAll();

        // Count beside tax = currently ongoing only
        $ongoingCount = $requestModel
            ->where('status !=', 'delivered')
            ->countAllResults();

        $taxResult = $requestModel
            ->select('COALESCE(SUM(tax), 0) as totalTax')
            ->whereIn('status', ['delivered'])
            ->first();

        $data = [
            'title' => 'Admin Dashboard',
            'ongoing' => $deliveredRequests,
            'ongoingCount' => $ongoingCount,
            'totalTax' => $taxResult['totalTax'] ?? 0,
        ];

        return view('admin/dashboard', $data);
    }

    public function deleteRequest($id)
    {
        if (session()->get('role') !== 'admin') {
            return redirect()->to('/dashboard');
        }

        $requestModel = new RequestModel();
        $requestModel->delete($id);

        return redirect()->to('/admin/dashboard')
            ->with('success', 'Request deleted successfully.');
    }
}