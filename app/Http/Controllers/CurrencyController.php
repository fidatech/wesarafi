<?php
// app/Http/Controllers/CurrencyController.php

namespace App\Http\Controllers;

use App\Models\Currency;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\DataTables;

class CurrencyController extends Controller
{
    public function index()
    {
        return view('configuration.currencies.index');
    }

    public function data(Request $request)
    {
        $query = Currency::query();

        return DataTables::of($query)
            ->addColumn('name_with_flag', function ($currency) {
                $flag = $currency->flag ? asset($currency->flag) : asset('assets/flags/unknown.svg');
                return '<div class="d-flex align-items-center gap-2">'
                    . '<img src="' . $flag . '" width="24" height="18">'
                    . '<strong>' . e($currency->name) . '</strong>'
                    . '<span class="text-muted">(' . e($currency->code) . ')</span>'
                    . '</div>';
            })
            ->addColumn('symbol', function ($currency) {
                return $currency->symbol ?? '-';
            })
            ->addColumn('exchange_rate', function ($currency) {
                return number_format($currency->exchange_rate, 2);
            })
            ->addColumn('status', function ($currency) {
                $active = $currency->is_active ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>';
                $default = $currency->is_default ? '<span class="badge bg-warning ms-1">Default</span>' : '';
                return $active . $default;
            })
            ->addColumn('actions', function ($currency) {
                return '<div class="d-flex gap-1">'
                    . '<button class="btn btn-sm btn-outline-primary btn-edit-currency" data-id="' . $currency->id . '" title="Edit">'
                    . '<i class="bi bi-pencil"></i></button>'
                    . '<button class="btn btn-sm btn-outline-danger btn-delete-currency" data-id="' . $currency->id . '" title="Delete">'
                    . '<i class="bi bi-trash"></i></button>'
                    . '</div>';
            })
            ->rawColumns(['name_with_flag', 'status', 'actions'])
            ->make(true);
    }


    public function create()
    {
        //
    }

    public function store(Request $request)
    {
        try {
            // Ensure boolean fields are always set before validation
            $request->merge([
                'is_default' => $request->has('is_default'),
                'is_active' => $request->has('is_active'),
            ]);

            $validated = $request->validate([
                'name' => 'required|string|max:100',
                'code' => 'required|string|max:10|unique:currencies',
                'symbol' => 'nullable|string|max:10',
                'country' => 'nullable|string|max:100',
                'flag' => 'nullable|string|max:255',
                'exchange_rate' => 'required|numeric|min:0',
                'is_default' => 'boolean',
                'is_active' => 'boolean',
            ]);

            $code = strtoupper($validated['code']);
            $validated['flag'] = 'assets/flags/' . strtolower(substr($code, 0, 2)) . '.svg';

            if ($validated['is_default']) {
                Currency::where('is_default', true)->update(['is_default' => false]);
            }

            Currency::create($validated);

            return redirect()->route('configuration.currencies.index')->with('success', 'Currency added successfully.');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return back()->with('error', 'Something went wrong. Please check logs for more details.');
        }
    }




    public function edit($id)
    {
        $currency = Currency::findOrFail($id);

        return response()->json([
            'id' => $currency->id,
            'name' => $currency->name,
            'code' => $currency->code,
            'symbol' => $currency->symbol,
            'country' => $currency->country,
            'exchange_rate' => $currency->exchange_rate,
            'is_default' => $currency->is_default,
            'is_active' => $currency->is_active,
            'flag' => $currency->flag,
        ]);
    }


    public function update(Request $request, Currency $currency)
{
    try {
        // Ensure boolean fields are always set before validation
        $request->merge([
            'is_default' => $request->has('is_default'),
            'is_active' => $request->has('is_active'),
        ]);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:10|unique:currencies,code,' . $currency->id,
            'symbol' => 'nullable|string|max:10',
            'country' => 'nullable|string|max:100',
            'flag' => 'nullable|string|max:255',
            'exchange_rate' => 'required|numeric|min:0',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ]);

        // Auto-assign flag path based on code (no file upload)
        $code = strtoupper($validated['code']);
        $validated['flag'] = 'assets/flags/' . strtolower(substr($code, 0, 2)) . '.svg';

        if ($validated['is_default']) {
            Currency::where('is_default', true)->update(['is_default' => false]);
        }

        $currency->update($validated);

        return redirect()->route('configuration.currencies.index')->with('success', 'Currency updated successfully.');
    } catch (\Exception $e) {
        Log::error($e->getMessage());
        return back()->with('error', 'Something went wrong during update.');
    }
}



    public function destroy(Currency $currency)
    {
        if ($currency->is_default || $currency->code === config('dab_reports.base_currency', 'AFN')) {
            return back()->with('error', 'The default/base currency cannot be deleted.');
        }

        if (Transaction::where('currency_id', $currency->id)->exists()) {
            return back()->with('error', 'This currency has journal transactions and cannot be deleted. Deactivate it instead.');
        }

        $currency->delete();

        return redirect()->route('configuration.currencies.index')->with('success', 'Currency deleted.');
    }

    public function toggleActive(Currency $currency)
    {
        $currency->update(['is_active' => !$currency->is_active]);
        return response()->json(['success' => true, 'status' => $currency->is_active]);
    }
}
