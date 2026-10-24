<?php
namespace App\Http\Controllers;
use App\Models\Transaction; use Illuminate\Http\Request; use Illuminate\Support\Facades\DB;
class JournalApprovalController extends Controller {
 public function index(Request $r){$q=Transaction::with(['account','currency','chartOfAccount','createdBy'])->where('approval_status','pending')->orderByDesc('date'); if(auth()->user()->branch_id)$q->where('branch_id',auth()->user()->branch_id); return view('journal.approvals.index',['groups'=>$q->get()->groupBy('linked_id')]);}
 public function approve(Request $r,string $linked){DB::transaction(fn()=>Transaction::where('linked_id',$linked)->update(['approval_status'=>'approved','is_approved'=>1,'approved_by'=>auth()->id(),'approved_at'=>now(),'rejection_reason'=>null])); return back()->with('success','Journal approved.');}
 public function reject(Request $r,string $linked){$d=$r->validate(['reason'=>'required|string|max:1000']); Transaction::where('linked_id',$linked)->update(['approval_status'=>'rejected','is_approved'=>0,'rejection_reason'=>$d['reason']]); return back()->with('success','Journal rejected.');}
}
