<?php

namespace App\Http\Controllers\FinancialReports;

use Illuminate\Http\Request;

class CommissionReportController extends TransactionReportController
{
    public function index(Request $request)
    {
        return $this->renderTransactionReport(
            $request,
            'Commission Income Transactions',
            ['commission', 'commissions'],
            'financial_reports.commissions.index'
        );
    }
}
