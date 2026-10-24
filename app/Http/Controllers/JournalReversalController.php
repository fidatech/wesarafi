<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class JournalReversalController extends Controller
{
    public function store(Request $request, string $linked)
    {
        abort_unless($request->user()->hasRole('Super Admin') || $request->user()->can('edit journal'), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);

        $reference = DB::transaction(function () use ($request, $linked, $data) {
            $original = Transaction::where('linked_id', $linked)->lockForUpdate()->get();
            abort_if($original->isEmpty(), 404);
            abort_unless($original->every(fn ($line) => !$request->user()->branch_id
                || (int) $line->branch_id === (int) $request->user()->branch_id), 403);
            abort_unless($original->count() >= 2 && $original->every(fn ($line) =>
                $line->module_name === 'account_ledger' && $line->is_approved
                && $line->approval_status === 'approved'), 422, 'Only approved manual account-ledger groups can be reversed.');
            abort_if(DB::table('journal_reversals')->where('original_linked_id', $linked)->exists(), 422, 'Journal already reversed.');
            $debits = $original->where('direction', 'debit')->sum('amount_afn');
            $credits = $original->where('direction', 'credit')->sum('amount_afn');
            abort_unless(abs($debits - $credits) <= 0.02, 422, 'Original journal is not balanced.');

            $reference = (string) Str::uuid();
            DB::table('journal_reversals')->insert([
                'original_linked_id' => $linked, 'reversal_linked_id' => $reference,
                'reason' => $data['reason'], 'created_by' => $request->user()->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $entries = $original->map(fn ($line) => [
                'branch_id' => $line->branch_id, 'date' => now()->toDateString(),
                'currency_id' => $line->currency_id, 'chart_of_account_id' => $line->chart_of_account_id,
                'account_id' => $line->account_id, 'accountable_type' => $line->accountable_type,
                'accountable_id' => $line->accountable_id, 'is_safe' => $line->is_safe,
                'amount' => $line->amount, 'amount_afn' => $line->amount_afn,
                'exchange_rate_afn' => $line->exchange_rate_afn,
                'direction' => $line->direction === 'debit' ? 'credit' : 'debit',
                'module_name' => 'account_ledger_reversal', 'created_by' => $request->user()->id,
                'description' => 'Reversal of '.$linked.': '.$data['reason'],
            ])->all();
            TransactionService::postMany($entries, $reference);
            return $reference;
        });

        return redirect()->route('journal.transactions.account-ledgers')
            ->with('success', 'Journal reversed. Reference: '.$reference);
    }
}
