<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Currency;
use App\Models\BusinessDay;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use App\Services\DailyBalanceState;
use App\Services\BusinessDayReconciliation;
use App\Services\DailyValuationService;
use App\Services\FxValuationService as FX;
use Illuminate\Support\Facades\Auth;

class FxRateController extends Controller
{
    public function meta(Request $req)
    {
        $date = now('Asia/Kabul')->toDateString();
        abort_unless($req->user()->hasRole('Super Admin') || $req->user()->can('manage business day'), 403);
        $branchId = $req->filled('branch_id') ? $req->integer('branch_id') : (int) ($req->user()->branch_id ?: 1);
        $this->authorizeBranch($branchId);

        $currs = Currency::orderBy('code')->get(['id','code']);
        $data = $currs->map(function($c) use ($date,$branchId){
            $open  = FX::effectiveRate($date, 'open',  $c->id, $branchId);
            $close = FX::effectiveRate($date, 'close', $c->id, $branchId);
            return [
                'id'    => (int)$c->id,
                'code'  => $c->code,
                'open'  => $open,
                'close' => $close,
                'is_base' => $c->code === 'AFN',
            ];
        });

        return response()->json([
            'day_state' => DailyBalanceState::forBranch($branchId),
            'reconciliation' => BusinessDayReconciliation::report($branchId, $date),
            'date' => $date,
            'branch_id' => $branchId,
            'currencies' => $data,
        ]);
    }

    public function setOpen(Request $req)
    {
        abort_unless($req->user()->hasRole('Super Admin') || $req->user()->can('manage business day'), 403);
        $v = $req->validate([
            'date' => 'required|date',
            'branch_id' => 'required|integer|exists:branches,id',
            'rates' => 'required|array|min:1', // [currency_id => rate]
            'rates.*' => 'numeric|min:0.000001',
        ]);

        $this->validateCurrentDay($v['date'], (int) $v['branch_id']);
        $state = DailyBalanceState::forBranch((int) $v['branch_id']);
        if (!$state['can_open']) return response()->json(['message' => 'Opening balances have already been saved for today.'], 409);
        DB::transaction(function () use ($v) {
            $branchId = (int) $v['branch_id'];
            // The unique branch/date constraint protects against concurrent openings.
            BusinessDay::create([
                'branch_id' => $branchId, 'business_date' => $v['date'], 'status' => 'open',
                'opened_by' => Auth::id(), 'opened_at' => now(),
                'opening_snapshot' => [
                    'previous_close' => BusinessDay::query()->where('branch_id', $branchId)
                        ->whereDate('business_date', '<', $v['date'])->where('status', 'closed')
                        ->orderByDesc('business_date')->first()?->closing_snapshot,
                    'ledger' => BusinessDayReconciliation::report($branchId, $v['date'])['cash_book_by_currency'],
                ],
            ]);
            DailyValuationService::setOpeningRates($v['date'], $branchId, $v['rates']);
        });
        activity('business-day')->causedBy(Auth::user())->withProperties([
            'branch_id' => (int) $v['branch_id'], 'date' => $v['date'], 'action' => 'opened',
        ])->log('Business day opened');
        return response()->json(['success'=>true,'message'=>'Opening rates saved']);
    }

    public function setClose(Request $req)
    {
        abort_unless($req->user()->hasRole('Super Admin') || $req->user()->can('manage business day'), 403);
        $v = $req->validate([
            'date' => 'required|date',
            'branch_id' => 'required|integer|exists:branches,id',
            'rates' => 'required|array|min:1',
            'rates.*' => 'numeric|min:0.000001',
        ]);

        $cashCounts = $req->validate([
            'cash_counts' => 'sometimes|array',
            'cash_counts.*' => 'required|numeric|min:0',
            'closing_notes' => 'nullable|string|max:2000',
        ]);
        $this->validateCurrentDay($v['date'], (int) $v['branch_id']);
        $state = DailyBalanceState::forBranch((int) $v['branch_id']);
        if (!$state['can_close']) return response()->json(['message' => 'Closing requires saved opening balances and cannot be repeated.'], 409);
        $vid = DB::transaction(function () use ($v, $cashCounts) {
            $branchId = (int) $v['branch_id'];
            $day = BusinessDay::query()->where('branch_id', $branchId)
                ->whereDate('business_date', $v['date'])->lockForUpdate()->first();
            if (!$day) {
                // Existing installations may already have opening FX rates.
                $day = BusinessDay::create([
                    'branch_id' => $branchId, 'business_date' => $v['date'], 'status' => 'open',
                    'opened_by' => Auth::id(), 'opened_at' => now(),
                ]);
            }
            abort_unless($day->status === 'open', 409, 'Business day is already closing or closed.');
            $reconciliation = BusinessDayReconciliation::report($branchId, $v['date']);
            abort_if(!$reconciliation['reconciled'], 409, 'Cannot close: '.$reconciliation['pending_approvals'].' transactions await approval.');
            $expected = $reconciliation['cash_book_by_currency'];
            $actual = $cashCounts['cash_counts'] ?? [];
            foreach ($expected as $currencyId => $balance) {
                abort_unless(array_key_exists($currencyId, $actual), 422, 'Cash count is required for currency '.$currencyId.'.');
                abort_unless(abs((float) $actual[$currencyId] - (float) $balance) < 0.01, 409,
                    'Cash count does not match ledger for currency '.$currencyId.'. Resolve the difference before closing.');
            }
            foreach ($actual as $currencyId => $amount) {
                abort_unless(array_key_exists($currencyId, $expected) || abs((float) $amount) < 0.01, 409,
                    'Unexpected physical cash in currency '.$currencyId.'.');
            }
            $day->update(['status' => 'closing']);
            $valuationId = DailyValuationService::setClosingAndValue($v['date'], $branchId, $v['rates']);
            $valuation = \App\Models\FxDailyValuation::find($valuationId);
            $day->update([
                'status' => 'closed', 'closed_by' => Auth::id(), 'closed_at' => now(),
                'cash_counts' => $actual, 'closing_notes' => $cashCounts['closing_notes'] ?? null,
                'closing_snapshot' => [
                    'realized_pl_afn' => $valuation?->realized_pl_afn,
                    'unrealized_pl_afn' => $valuation?->unrealized_pl_afn,
                    'temp_pnl_afn' => $valuation?->temp_pnl_afn,
                    'reconciliation' => $reconciliation,
                ],
            ]);
            return $valuationId;
        });
        activity('business-day')->causedBy(Auth::user())->withProperties([
            'branch_id' => (int) $v['branch_id'], 'date' => $v['date'], 'action' => 'closed',
            'valuation_id' => $vid,
        ])->log('Business day closed');
        $val = \App\Models\FxDailyValuation::find($vid);

        return response()->json([
            'success'=>true,
            'message'=>'Closing rates saved and daily valuation created',
            'valuation'=>[
                'realized'   => $val?->realized_pl_afn ?? 0,
                'unrealized' => $val?->unrealized_pl_afn ?? 0,
                'temp_pnl'   => $val?->temp_pnl_afn ?? 0,
            ]
        ]);
    }
    private function validateCurrentDay(string $date, int $branchId): void
    {
        abort_unless($date === now('Asia/Kabul')->toDateString(), 422, 'Only today’s business day may be opened or closed.');
        $this->authorizeBranch($branchId);
    }

    private function authorizeBranch(int $branchId): void
    {
        abort_unless(\App\Models\Branch::whereKey($branchId)->exists(), 404);
        abort_unless(Auth::user()?->hasRole('Super Admin') || (int) (Auth::user()?->branch_id ?: 1) === $branchId, 403);
    }

}
