<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Currency;
use App\Services\DailyBalanceState;
use App\Services\DailyValuationService;
use App\Services\FxValuationService as FX;
use Illuminate\Support\Facades\Auth;

class FxRateController extends Controller
{
    public function meta(Request $req)
    {
        $date     = $req->input('date', now()->toDateString());
        $branchId = (int) $req->input('branch_id', Auth::user()?->branch_id ?? 1);

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
        // Store opening rates
        DailyValuationService::setOpeningRates($v['date'], (int) $v['branch_id'], $v['rates']);
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
        // Store closing + build snapshot (realized/unrealized/temp P&L)
        $vid = \App\Services\DailyValuationService::setClosingAndValue($v['date'], (int) $v['branch_id'], $v['rates']);
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
