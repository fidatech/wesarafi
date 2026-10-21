<?php

namespace App\Http\Controllers;

use App\Models\{Branch, Currency, FxRate};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExchangeRateController extends Controller
{
    public function index(Request $request)
    {
        $branch = $request->filled('branch_id') ? $request->integer('branch_id') : auth()->user()->branch_id;
        $date = $request->date('date')?->toDateString() ?? now()->toDateString();
        $rates = FxRate::where('branch_id', $branch)->whereDate('as_of', $date)->get()->groupBy('currency_id');

        return view('exchange.rates.index', compact('branch', 'date', 'rates') + [
            'branches' => Branch::orderBy('name')->get(),
            'currencies' => Currency::where('is_active', true)->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request)
    {
        // AFN is the valuation unit: ignore any submitted override of its own rate.
        $rates = $request->input('rates');
        $afnId = Currency::where('code', 'AFN')->value('id');
        if (is_array($rates) && $afnId) {
            $rates[$afnId] = ['open' => 1, 'close' => 1];
            $request->merge(['rates' => $rates]);
        }
        $data = $request->validate([
            'branch_id' => 'required|exists:branches,id', 'date' => 'required|date',
            'rates' => 'required|array', 'rates.*' => 'required|array',
            'rates.*.open' => 'required|numeric|min:.000001',
            'rates.*.close' => 'nullable|numeric|min:.000001',
        ]);
        $currencyIds = Currency::whereIn('id', array_keys($data['rates']))->pluck('id');
        if ($currencyIds->count() !== count($data['rates'])) {
            throw ValidationException::withMessages(['rates' => 'Select valid currencies.']);
        }
        DB::transaction(function () use ($data) {
            foreach ($data['rates'] as $currencyId => $rate) {
                foreach (['open' => $rate['open'], 'close' => $rate['close'] ?? $rate['open']] as $type => $value) {
                    FxRate::updateOrCreate([
                        'branch_id' => $data['branch_id'], 'currency_id' => $currencyId,
                        'as_of' => $data['date'], 'type' => $type,
                    ], ['rate_afn_per_unit' => $value]);
                }
            }
        });

        return back()->with('success', 'Branch exchange rates saved.');
    }
}
