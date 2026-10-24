<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Amortization;
use App\Models\Asset;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class AmortizationController extends Controller
{
    public function index()
    {
        $intangibleTypeId = ChartOfAccount::where('code', '1200')->value('id');
        $assets = Asset::with('currency')->where('asset_type', $intangibleTypeId)->orderBy('name')->get();
        $currencies = Currency::where('is_active', true)->orderBy('code')->get();

        return view('accounting.amortizations.index', compact('assets', 'currencies'));
    }

    public function data()
    {
        return DataTables::of(Amortization::with(['asset', 'currency', 'branch'])->latest())
            ->addIndexColumn()
            ->addColumn('asset', fn (Amortization $row) => $row->asset?->name ?? '-')
            ->addColumn('currency', fn (Amortization $row) => $row->currency?->code ?? '-')
            ->addColumn('branch', fn (Amortization $row) => $row->branch?->name ?? '-')
            ->addColumn('amount', fn (Amortization $row) => number_format((float) $row->amount, 2))
            ->addColumn('note', fn (Amortization $row) => $row->note ?: '-')
            ->addColumn('action', fn (Amortization $row) => '
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
            $expenseCoaId = $this->expenseCoaId();

            $amortization = Amortization::create([
                ...$validated,
                'branch_id' => $asset->branch_id,
                'currency_id' => $asset->currency_id,
                'chart_of_account_id' => $expenseCoaId,
                'created_by' => Auth::id(),
            ]);

            TransactionService::postMany($this->entries($amortization, $asset));

            return response()->json(['success' => true, 'message' => 'Amortization recorded successfully.']);
        });
    }

    public function show($id)
    {
        return response()->json(Amortization::findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $validated = $this->validated($request);

        return DB::transaction(function () use ($validated, $id) {
            $amortization = Amortization::findOrFail($id);
            $asset = Asset::findOrFail($validated['asset_id']);
            $this->validateAgainstAsset($asset, $validated, $amortization->id);

            $amortization->update([
                ...$validated,
                'branch_id' => $asset->branch_id,
                'currency_id' => $asset->currency_id,
                'chart_of_account_id' => $this->expenseCoaId(),
            ]);

            TransactionService::repost('amortizations', $amortization->id, fn () => $this->entries($amortization->fresh(), $asset, false));

            return response()->json(['success' => true, 'message' => 'Amortization updated successfully.']);
        });
    }

    public function destroy($id)
    {
        DB::transaction(function () use ($id) {
            $amortization = Amortization::findOrFail($id);
            TransactionService::delete('amortizations', $amortization->id);
            $amortization->delete();
        });

        return response()->json(['success' => true, 'message' => 'Amortization deleted.']);
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
        $intangibleTypeId = (int) ChartOfAccount::where('code', '1200')->value('id');
        if ((int) $asset->asset_type !== $intangibleTypeId) {
            throw ValidationException::withMessages(['asset_id' => 'Only intangible assets can be amortized.']);
        }

        if ((int) $validated['currency_id'] !== (int) $asset->currency_id) {
            throw ValidationException::withMessages(['currency_id' => 'Amortization must use the asset currency.']);
        }

        $alreadyRecorded = (float) Amortization::where('asset_id', $asset->id)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->sum('amount');

        if ($alreadyRecorded + (float) $validated['amount'] > (float) $asset->amount + 0.005) {
            throw ValidationException::withMessages([
                'amount' => 'Total amortization cannot exceed the asset cost. Remaining amortizable amount: '
                    . number_format(max((float) $asset->amount - $alreadyRecorded, 0), 2) . '.',
            ]);
        }
    }

    private function expenseCoaId(): int
    {
        $id = (int) ChartOfAccount::where('code', '5014')->value('id');
        if (! $id) {
            throw new \RuntimeException('Amortization expense COA 5014 is missing.');
        }
        return $id;
    }

    private function entries(Amortization $amortization, Asset $asset, bool $includeModule = true): array
    {
        $expenseCoaId = $this->expenseCoaId();
        $accumulatedCoaId = (int) ChartOfAccount::where('code', '1299')->value('id');
        if (! $accumulatedCoaId) {
            throw new \RuntimeException('Accumulated amortization COA 1299 is missing.');
        }

        $common = [
            'branch_id' => $asset->branch_id,
            'currency_id' => $asset->currency_id,
            'amount' => $amortization->amount,
            'date' => $amortization->date,
            'created_by' => Auth::id(),
            'accountable_type' => Asset::class,
            'accountable_id' => $asset->id,
        ];
        if ($includeModule) {
            $common['module_name'] = 'amortizations';
            $common['module_id'] = $amortization->id;
        }

        return [
            array_merge($common, [
                'chart_of_account_id' => $expenseCoaId,
                'direction' => 'debit',
                'description' => $amortization->note ?: 'Amortization expense — ' . $asset->name,
            ]),
            array_merge($common, [
                'chart_of_account_id' => $accumulatedCoaId,
                'direction' => 'credit',
                'description' => $amortization->note ?: 'Accumulated amortization — ' . $asset->name,
            ]),
        ];
    }
}
