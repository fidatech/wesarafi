<?php

namespace App\Http\Controllers\FinancialReports;

use Illuminate\Http\Request;

class AuctionReportController extends TransactionReportController
{
    public function index(Request $request)
    {
        return $this->renderTransactionReport(
            $request,
            'DAB Auction Transactions',
            ['auction', 'auctions'],
            'financial_reports.auctions.index'
        );
    }
}
