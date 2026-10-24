<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Amortization;
use App\Models\Asset;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Depreciation;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AssetController extends Controller
{
    public function index()
    {
        return view('accounting.assets.index', [
            'currencies' => Currency::where('is_active', true)->orderBy('code')->get(),
            'assetTypes' => ChartOfAccount::whereIn('code', ['1100', '1200'])->where('is_active', true)->orderBy('code')->get(),
            'assetAccounts' => ChartOfAccount::whereIn('code', ['1010', '1011', '1012', '1013', '1014', '1015', '1020', '1021', '1022'])
                ->where('is_active', true)
                ->orderBy('code')
                ->get(),
        ]);
    }

    public function show(Asset $asset)
    {
        return response()->json($asset);
    }

    public function data()
    {
        return datatables()
            ->eloquent(Asset::with(['branch', 'account', 'currency', 'chartOfAccount']))
            ->addIndexColumn()
            ->addColumn('asset_type', fn (Asset $row) => $row->chartOfAccount?->account_name ?? '-')
            ->addColumn('amount', fn (Asset $row) => number_format((float) $row->amount, 2))
            ->addColumn('currency_code', fn (Asset $row) => $row->currency?->code ?? '-')
            ->addColumn('branch_name', fn (Asset $row) => $row->branch?->name ?? '-')
            ->addColumn('date', fn (Asset $row) => $row->date?->format('Y-m-d') ?? '-')
            ->addColumn('useful_life_years', fn (Asset $row) => $row->useful_life_years ?: '-')
            ->addColumn('actions', fn (Asset $row) => '
                <button class="btn btn-sm btn-primary editAsset" data-id="' . $row->id . '"><i class="bx bx-edit"></i></button>
                <button class="btn btn-sm btn-danger deleteAsset" data-id="' . $row->id . '"><i class="bx bx-trash"></i></button>')
            ->rawColumns(['actions'])
            ->toJson();
    }

    public function store(Request $request)
    {
        $validated = $this->validateAsset($request);

        return DB::transaction(function () use ($validated) {
            $branchId = (int) Auth::user()->branch_id;
            $accountId = (int) Account::where('type', 'branch')->where('branch_id', $branchId)->value('id');
            $cashCoaId = (int) ChartOfAccount::where('code', '1000')->value('id');

            if (! $accountId || ! $cashCoaId) {
                return response()->json(['success' => false, 'message' => 'Branch account or Cash on Hand COA is missing.'], 422);
            }

            $asset = Asset::create(array_merge($validated, [
                'branch_id' => $branchId,
                'account_id' => $accountId,
            ]));

            TransactionService::postMany($this->journalEntries($asset, $cashCoaId));

            return response()->json(['success' => true, 'message' => 'Asset added successfully.']);
        });
    }

    public function update(Request $request, Asset $asset)
    {
        $validated = $this->validateAsset($request);

        return DB::transaction(function () use ($validated, $asset) {
            $accumulated = (float) Depreciation::where('asset_id', $asset->id)->sum('amount')
                + (float) Amortization::where('asset_id', $asset->id)->sum('amount');

            if ((float) $validated['amount'] + 0.005 < $accumulated) {
                return response()->json([
                    'success' => false,
                    'message' => 'Asset cost cannot be lower than accumulated depreciation/amortization (' . number_format($accumulated, 2) . ').',
                ], 422);
            }

            if ($accumulated > 0 && (
                (int) $validated['asset_type'] !== (int) $asset->asset_type
                || (int) $validated['currency_id'] !== (int) $asset->currency_id
            )) {
                return response()->json([
                    'success' => false,
                    'message' => 'Asset category and currency cannot be changed after depreciation or amortization has been recorded.',
                ], 422);
            }

            $branchId = (int) Auth::user()->branch_id;
            $accountId = (int) Account::where('type', 'branch')->where('branch_id', $branchId)->value('id');
            $cashCoaId = (int) ChartOfAccount::where('code', '1000')->value('id');
            if (! $accountId || ! $cashCoaId) {
                return response()->json(['success' => false, 'message' => 'Branch account or Cash on Hand COA is missing.'], 422);
            }

            $asset->update(array_merge($validated, [
                'branch_id' => $branchId,
                'account_id' => $accountId,
            ]));

            TransactionService::repost('asset', $asset->id, fn () => $this->journalEntries($asset->fresh(), $cashCoaId, false));

            return response()->json(['success' => true, 'message' => 'Asset updated successfully.']);
        });
    }

    public function destroy(Asset $asset)
    {
        if (Depreciation::where('asset_id', $asset->id)->exists() || Amortization::where('asset_id', $asset->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This asset has depreciation or amortization entries. Reverse those entries before deleting the asset.',
            ], 422);
        }

        DB::transaction(function () use ($asset) {
            TransactionService::delete('asset', $asset->id);
            $asset->delete();
        });

        return response()->json(['success' => true, 'message' => 'Asset deleted successfully.']);
    }

    private function validateAsset(Request $request): array
    {
        $validated = $request->validate([
            'asset_type' => [
                'required',
                'integer',
                Rule::exists('chart_of_accounts', 'id')->where(fn ($query) => $query->whereIn('code', ['1100', '1200'])),
            ],
            'chart_of_account_id' => [
                'required',
                'integer',
                Rule::exists('chart_of_accounts', 'id')->where(fn ($query) => $query
                    ->where('type', 'asset')
                    ->whereIn('code', ['1010', '1011', '1012', '1013', '1014', '1015', '1020', '1021', '1022'])),
            ],
            'name' => 'required|string|max:255',
            'currency_id' => 'required|exists:currencies,id',
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date',
            'description' => 'nullable|string|max:2000',
            'useful_life_years' => 'nullable|integer|min:1|max:100',
        ]);

        $typeCode = ChartOfAccount::whereKey($validated['asset_type'])->value('code');
        $accountCode = ChartOfAccount::whereKey($validated['chart_of_account_id'])->value('code');
        $validPair = $typeCode === '1100'
            ? str_starts_with((string) $accountCode, '101')
            : str_starts_with((string) $accountCode, '102');

        if (! $validPair) {
            abort(422, 'The selected asset account does not belong to the selected tangible/intangible category.');
        }

        return $validated;
    }

    private function journalEntries(Asset $asset, int $cashCoaId, bool $includeModule = true): array
    {
        $common = [
            'branch_id' => $asset->branch_id,
            'currency_id' => $asset->currency_id,
            'amount' => $asset->amount,
            'date' => $asset->date,
            'created_by' => Auth::id(),
            'accountable_type' => Account::class,
            'accountable_id' => $asset->account_id,
        ];

        if ($includeModule) {
            $common['module_name'] = 'asset';
            $common['module_id'] = $asset->id;
        }

        return [
            array_merge($common, [
                'direction' => 'debit',
                'chart_of_account_id' => $asset->chart_of_account_id,
                'description' => $asset->description ?: 'Asset acquired',
            ]),
            array_merge($common, [
                'direction' => 'credit',
                'chart_of_account_id' => $cashCoaId,
                'description' => $asset->description ?: 'Cash paid for asset',
            ]),
        ];
    }
}
