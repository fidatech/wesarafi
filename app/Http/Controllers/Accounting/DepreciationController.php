<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Depreciation;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class DepreciationController extends Controller
{
    public function index()
    {
        $tangibleTypeId = ChartOfAccount::where('code', '1100')->value('id');
        $assets = Asset::with('currency')->where('asset_type', $tangibleTypeId)->orderBy('name')->get();
        $currencies = Currency::where('is_active', true)->orderBy('code')->get();

        return view('accounting.depreciations.index', compact('assets', 'currencies'));
    }

    public function data()
    {
        return DataTables::of(Depreciation::with(['asset', 'currency', 'branch'])->latest())
            ->addIndexColumn()
            ->addColumn('asset', fn (Depreciation $row) => $row->asset?->name ?? '-')
            ->addColumn('currency', fn (Depreciation $row) => $row->currency?->code ?? '-')
            ->addColumn('branch', fn (Depreciation $row) => $row->branch?->name ?? '-')
            ->addColumn('amount', fn (Depreciation $row) => number_format((float) $row->amount, 2))
            ->addColumn('note', fn (Depreciation $row) => $row->note ?: '-')
            ->addColumn('action', fn (Depreciation $row) => '
                <button class="btn btn-sm btn-primary edit-btn" data-id="' . $row->id . '"><i class="bx bx-edit"></i></button>
                <button class="btn btn-sm btn-danger delete-btn" data-id="' . $row->id . '"><i class="bx bx-trash"></i></button>')
            ->rawColumns(['action'])
            ->make(true);
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);

        return DB::transaction(function () use ($validated) {
            $asset = Asset::findOrFail($validated['asset_id']);
            $this->validateAgainstAsset($asset, $validated, null);

            $depreciation = Depreciation::create([
                ...$validated,
                'branch_id' => $asset->branch_id,
                'currency_id' => $asset->currency_id,
                'created_by' => Auth::id(),
            ]);

            TransactionService::postMany($this->entries($depreciation, $asset));

            return response()->json(['success' => true, 'message' => 'Depreciation recorded successfully.']);
        });
    }

    public function show($id)
    {
        return response()->json(Depreciation::findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $validated = $this->validated($request);

        return DB::transaction(function () use ($validated, $id) {
            $depreciation = Depreciation::findOrFail($id);
            $asset = Asset::findOrFail($validated['asset_id']);
            $this->validateAgainstAsset($asset, $validated, $depreciation->id);

            $depreciation->update([
                ...$validated,
                'branch_id' => $asset->branch_id,
                'currency_id' => $asset->currency_id,
            ]);

            TransactionService::repost('depreciations', $depreciation->id, fn () => $this->entries($depreciation->fresh(), $asset, false));

            return response()->json(['success' => true, 'message' => 'Depreciation updated successfully.']);
        });
    }

    public function destroy($id)
    {
        DB::transaction(function () use ($id) {
            $depreciation = Depreciation::findOrFail($id);
            TransactionService::delete('depreciations', $depreciation->id);
            $depreciation->delete();
        });

        return response()->json(['success' => true, 'message' => 'Depreciation deleted.']);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'asset_id' => 'required|exists:assets,id',
            'currency_id' => 'required|exists:currencies,id',
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date',
            'note' => 'nullable|string|max:2000',
        ]);
    }

    private function validateAgainstAsset(Asset $asset, array $validated, ?int $ignoreId): void
    {
        $tangibleTypeId = (int) ChartOfAccount::where('code', '1100')->value('id');
        if ((int) $asset->asset_type !== $tangibleTypeId) {
            throw ValidationException::withMessages(['asset_id' => 'Only tangible assets can be depreciated.']);
        }

        if ((int) $validated['currency_id'] !== (int) $asset->currency_id) {
            throw ValidationException::withMessages(['currency_id' => 'Depreciation must use the asset currency.']);
        }

        $alreadyRecorded = (float) Depreciation::where('asset_id', $asset->id)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->sum('amount');

        if ($alreadyRecorded + (float) $validated['amount'] > (float) $asset->amount + 0.005) {
            throw ValidationException::withMessages([
                'amount' => 'Total depreciation cannot exceed the asset cost. Remaining depreciable amount: '
                    . number_format(max((float) $asset->amount - $alreadyRecorded, 0), 2) . '.',
            ]);
        }
    }

    private function entries(Depreciation $depreciation, Asset $asset, bool $includeModule = true): array
    {
        $expenseCoaId = (int) ChartOfAccount::where('code', '5013')->value('id');
        $accumulatedCoaId = (int) ChartOfAccount::where('code', '1199')->value('id');
        if (! $expenseCoaId || ! $accumulatedCoaId) {
            throw new \RuntimeException('Depreciation expense (5013) or accumulated depreciation (1199) COA is missing.');
        }

        $common = [
            'branch_id' => $asset->branch_id,
            'currency_id' => $asset->currency_id,
            'amount' => $depreciation->amount,
            'date' => $depreciation->date,
            'created_by' => Auth::id(),
            'accountable_type' => Asset::class,
            'accountable_id' => $asset->id,
        ];
        if ($includeModule) {
            $common['module_name'] = 'depreciations';
            $common['module_id'] = $depreciation->id;
        }

        return [
            array_merge($common, [
                'chart_of_account_id' => $expenseCoaId,
                'direction' => 'debit',
                'description' => $depreciation->note ?: 'Depreciation expense — ' . $asset->name,
            ]),
            array_merge($common, [
                'chart_of_account_id' => $accumulatedCoaId,
                'direction' => 'credit',
                'description' => $depreciation->note ?: 'Accumulated depreciation — ' . $asset->name,
            ]),
        ];
    }
}
