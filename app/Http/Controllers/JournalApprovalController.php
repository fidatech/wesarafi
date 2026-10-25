<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JournalApprovalController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->can('view journal') || $request->user()->hasRole('Super Admin'), 403);
        abort_if(! $request->user()->hasRole('Super Admin') && ! $request->user()->branch_id, 403);

        // A reviewable journal is an entire pending group, not just its pending
        // rows. Exclude groups containing finalized rows or foreign-branch rows.
        $query = Transaction::with(['account', 'currency', 'chartOfAccount', 'createdBy'])
            ->where('approval_status', 'pending')
            ->whereNotIn('linked_id', Transaction::select('linked_id')->where(function ($query) {
                $query->where('approval_status', '!=', 'pending')->orWhereNull('approval_status');
            }))
            ->orderByDesc('date');

        if (! $request->user()->hasRole('Super Admin')) {
            $branchId = (int) $request->user()->branch_id;
            $query->where('branch_id', $branchId)
                ->whereNotIn('linked_id', Transaction::select('linked_id')->where(function ($query) use ($branchId) {
                    $query->where('branch_id', '!=', $branchId)->orWhereNull('branch_id');
                }));
        }

        $groups = $query->get()->groupBy('linked_id');

        return view('journal.approvals.index', ['groups' => $groups]);
    }

    private function authorizeGroup(Request $request, string $linked): void
    {
        abort_unless($request->user()->can('edit journal') || $request->user()->hasRole('Super Admin'), 403);
        $group = Transaction::where('linked_id', $linked)->lockForUpdate()->get();
        abort_if($group->isEmpty(), 404);
        $isSuper = $request->user()->hasRole('Super Admin');
        abort_if(! $isSuper && ! $request->user()->branch_id, 403);
        abort_unless($isSuper || $group->every(fn ($entry) => (int) $entry->branch_id === (int) $request->user()->branch_id), 403);
        abort_unless($group->every(fn ($entry) => $entry->approval_status === 'pending'), 422, 'Only pending journal groups can be reviewed.');
        if ($request->routeIs('journal.approvals.approve')) {
            abort_unless($group->count() >= 2, 422, 'Journal group requires at least two entries.');
            $debits = $group->where('direction', 'debit')->sum('amount_afn');
            $credits = $group->where('direction', 'credit')->sum('amount_afn');
            abort_unless($group->every(fn ($entry) => in_array($entry->direction, ['debit', 'credit'], true)) && abs($debits - $credits) <= 0.02, 422, 'Unbalanced journal group cannot be approved.');
        }
    }

    public function approve(Request $request, string $linked)
    {
        DB::transaction(function () use ($request, $linked) {
            $this->authorizeGroup($request, $linked);
            Transaction::where('linked_id', $linked)->where('approval_status', 'pending')->get()->each(
                fn ($entry) => $entry->update([
                    'approval_status' => 'approved', 'is_approved' => true,
                    'approved_by' => $request->user()->id, 'approved_at' => now(), 'rejection_reason' => null,
                ])
            );
        });

        return back()->with('success', 'Journal approved.');
    }

    public function reject(Request $request, string $linked)
    {
        $data = $request->validate(['reason' => 'required|string|max:1000']);
        DB::transaction(function () use ($request, $linked, $data) {
            $this->authorizeGroup($request, $linked);
            Transaction::where('linked_id', $linked)->where('approval_status', 'pending')->get()->each(
                fn ($entry) => $entry->update([
                    'approval_status' => 'rejected', 'is_approved' => false,
                    'approved_by' => null, 'approved_at' => null,
                    'rejection_reason' => $data['reason'],
                ])
            );
        });

        return back()->with('success', 'Journal rejected.');
    }
}
