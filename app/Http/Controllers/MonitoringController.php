<?php
namespace App\Http\Controllers;
use App\Models\{Branch,Currency,Transaction}; use Illuminate\Http\Request;
class MonitoringController extends Controller {
 private function base(Request $r){$q=Transaction::with(['account','currency','chartOfAccount','branch','createdBy'])->whereNull('deleted_at')->orderByDesc('date');if(auth()->user()->branch_id)$q->where('branch_id',auth()->user()->branch_id);if($r->branch_id)$q->where('branch_id',$r->branch_id);if($r->currency_id)$q->where('currency_id',$r->currency_id);if($r->from_date)$q->whereDate('date','>=',$r->from_date);if($r->to_date)$q->whereDate('date','<=',$r->to_date);return $q;}
 public function approvals(Request $r){$rows=$this->base($r)->where('approval_status','pending')->paginate(30);return view('monitoring.index',$this->data($rows,'Approve Transactions','approvals'));}
 public function lctr(Request $r){$rows=$this->base($r)->whereRaw('COALESCE(amount_afn, amount * COALESCE(exchange_rate_afn,1)) >= 500000')->paginate(30);return view('monitoring.index',$this->data($rows,'LCTR Transactions (≥ 500,000 AFN)','lctr'));}
 public function suspicious(Request $r){$q=$this->base($r)->where(function($x){$x->where('is_suspicious',1)->orWhereHas('account',fn($a)=>$a->where('risk_level','high'))->orWhereRaw('COALESCE(amount_afn, amount * COALESCE(exchange_rate_afn,1)) >= 5000000');});return view('monitoring.index',$this->data($q->paginate(30),'Suspicious Transactions','suspicious'));}
 public function flag(Request $r,Transaction $transaction){$d=$r->validate(['reason'=>'required|string|max:1000']);Transaction::where('linked_id',$transaction->linked_id)->update(['is_suspicious'=>1,'suspicious_reason'=>$d['reason']]);return back()->with('success','Transaction flagged.');}
 private function data($rows,$title,$type){return compact('rows','title','type')+['branches'=>Branch::all(),'currencies'=>Currency::where('is_active',1)->get()];}
}
