<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Currency;
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
}
