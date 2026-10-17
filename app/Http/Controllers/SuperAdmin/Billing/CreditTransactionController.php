<?php

namespace App\Http\Controllers\SuperAdmin\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\IndexCreditTransactionsRequest;
use App\Services\SuperAdmin\SuperAdminBillingDirectory;
use Illuminate\View\View;

class CreditTransactionController extends Controller
{
    public function __construct(private readonly SuperAdminBillingDirectory $billing) {}

    public function index(IndexCreditTransactionsRequest $request): View
    {
        return view('super-admin.billing.credits', [
            'transactions' => $this->billing->creditTransactions($request->validated()),
            'totals' => $this->billing->creditTotals($request->validated()),
            'filters' => $request->validated(),
        ]);
    }
}
