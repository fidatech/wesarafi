<?php
namespace App\Http\Controllers\FinancialReports;
use App\Http\Controllers\Controller; use App\Models\{Branch,Currency,CustomerExchange}; use Illuminate\Http\Request;
class ExchangeReportController extends Controller { public function index(Request $r){$q=CustomerExchange::with(['customer','branch','fromCurrency','toCurrency'])->latest('date');$branch=$r->branch_id?:auth()->user()->branch_id;if($branch)$q->where('branch_id',$branch);if($r->from)$q->whereDate('date','>=',$r->from);if($r->to)$q->whereDate('date','<=',$r->to);return view('financial_reports.exchange',['rows'=>$q->paginate(50)->withQueryString(),'branches'=>Branch::all(),'currencies'=>Currency::all()]);}}
