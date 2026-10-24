<?php

namespace App\Http\Controllers\FinancialReports;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\Remittance;
use Illuminate\Http\Request;

class RemittanceReportController extends Controller
{
    public function index(Request $r)
    {
        $q = Remittance::with(['fromBranch', 'toBranch', 'bridgeBranch', 'currency'])->latest('date');
        $branch = auth()->user()->branch_id ?: $r->branch_id;
        $q->forBranch($branch);
        foreach (['status', 'direction', 'currency_id'] as $f) {
            if ($r->$f) {
                $q->where($f, $r->$f);
            }
        }if ($r->from) {
            $q->whereDate('date', '>=', $r->from);
        }if ($r->to) {
            $q->whereDate('date', '<=', $r->to);
        }

return view('financial_reports.remittances', ['rows' => $q->paginate(50)->withQueryString(), 'branches' => Branch::all(), 'currencies' => Currency::all()]);
    }
}
