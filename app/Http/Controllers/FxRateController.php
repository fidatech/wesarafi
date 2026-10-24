<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Currency;
use App\Models\BusinessDay;
use Illuminate\Support\Facades\DB;
use App\Services\DailyBalanceState;
use App\Services\DailyValuationService;
use App\Services\FxValuationService as FX;
use Illuminate\Support\Facades\Auth;

class FxRateController extends Controller
{
    public function meta(Request $req)
    {
        $date = now('Asia/Kabul')->toDateString();
        $branchId = (int) (Auth::user()?->branch_id ?: 1);

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
            'date' => $date,
            'branch_id' => $branchId,
            'currencies' => $data,
        ]);
    }

    public function setOpen(Request $req)
    {
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
            ]);
            DailyValuationService::setOpeningRates($v['date'], $branchId, $v['rates']);
        });
        return response()->json(['success'=>true,'message'=>'Opening rates saved']);
    }

    public function setClose(Request $req)
    {
        $v = $req->validate([
            'date' => 'required|date',
            'branch_id' => 'required|integer|exists:branches,id',
            'rates' => 'required|array|min:1',
            'rates.*' => 'numeric|min:0.000001',
        ]);

        $this->validateCurrentDay($v['date'], (int) $v['branch_id']);
        $state = DailyBalanceState::forBranch((int) $v['branch_id']);
        if (!$state['can_close']) return response()->json(['message' => 'Closing requires saved opening balances and cannot be repeated.'], 409);
        $vid = DB::transaction(function () use ($v) {
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
            $day->update(['status' => 'closing']);
            $valuationId = DailyValuationService::setClosingAndValue($v['date'], $branchId, $v['rates']);
            $valuation = \App\Models\FxDailyValuation::find($valuationId);
            $day->update([
                'status' => 'closed', 'closed_by' => Auth::id(), 'closed_at' => now(),
                'closing_snapshot' => [
                    'realized_pl_afn' => $valuation?->realized_pl_afn,
                    'unrealized_pl_afn' => $valuation?->unrealized_pl_afn,
                    'temp_pnl_afn' => $valuation?->temp_pnl_afn,
                ],
            ]);
            return $valuationId;
        });
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
        abort_unless((int) (Auth::user()?->branch_id ?: 1) === $branchId || Auth::user()?->hasRole('Super Admin'), 403);
    }

}
