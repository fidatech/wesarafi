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
        $query = Transaction::with(['account', 'currency', 'chartOfAccount', 'createdBy'])
            ->where('approval_status', 'pending')->orderByDesc('date');
        if ($request->user()->branch_id) {
            $query->where('branch_id', $request->user()->branch_id);
        }

        return view('journal.approvals.index', ['groups' => $query->get()->groupBy('linked_id')]);
    }

    private function authorizeGroup(Request $request, string $linked): void
    {
        abort_unless($request->user()->can('edit journal') || $request->user()->hasRole('Super Admin'), 403);
        $group = Transaction::where('linked_id', $linked)->get();
        abort_if($group->isEmpty(), 404);
        abort_unless($group->every(fn ($entry) => !$request->user()->branch_id || (int) $entry->branch_id === (int) $request->user()->branch_id), 403);
        abort_unless($group->every(fn ($entry) => $entry->approval_status === 'pending'), 422, 'Only pending journal groups can be reviewed.');
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
                    'rejection_reason' => $data['reason'],
                ])
            );
        });

        return back()->with('success', 'Journal rejected.');
    }
}
