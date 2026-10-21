<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Capital;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Shareholder;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CapitalController extends Controller
{
    public function index()
    {
        return view('accounting.capital.index', [
            'branches' => Branch::orderBy('id')->get(),
            'accounts' => Account::where('type', 'branch')->where('account_code', 'not like', 'INTERBRANCH-%')->orderBy('full_name')->get(),
            'shareholders' => Shareholder::orderBy('id')->get(),
            'currencies' => Currency::where('is_active', true)->orderBy('id')->get(),
            'coas' => ChartOfAccount::where('type', 'equity')->where('is_active', true)->orderBy('code')->get(),
        ]);
    }

    public function data()
    {
        return datatables()
            ->eloquent(Capital::with(['shareholder', 'branch', 'account', 'currency']))
            ->addIndexColumn()
            ->addColumn('shareholder', fn (Capital $row) => $row->shareholder?->name ?? '-')
            ->addColumn('branch', fn (Capital $row) => $row->branch?->name ?? '-')
            ->addColumn('account', fn (Capital $row) => $row->account?->full_name ?? '-')
            ->addColumn('currency', fn (Capital $row) => $row->currency?->code ?? '-')
            ->addColumn('amount', fn (Capital $row) => number_format((float) $row->amount, 2))
            ->addColumn('share_percentage', fn (Capital $row) => number_format((float) $row->share_percentage, 2) . '%')
            ->addColumn('date', fn (Capital $row) => $row->date?->format('Y-m-d') ?? '-')
            ->addColumn('actions', fn (Capital $row) => '
                <button class="btn btn-sm btn-primary editCapital" data-id="' . $row->id . '"><i class="bx bx-edit"></i></button>
                <button class="btn btn-sm btn-danger deleteCapital" data-id="' . $row->id . '"><i class="bx bx-trash"></i></button>')
            ->rawColumns(['actions'])
            ->toJson();
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request, true);

        return DB::transaction(function () use ($validated) {
            $this->assertBranchAccount($validated['account_id'], $validated['branch_id']);
            [$shareCapitalCoaId, $cashCoaId] = $this->coaIds();

            $capital = Capital::create(array_merge($validated, [
                'chart_of_account_id' => $shareCapitalCoaId,
                'created_by' => Auth::id(),
            ]));

            TransactionService::postMany($this->entries($capital, $shareCapitalCoaId, $cashCoaId));

            return response()->json(['success' => true, 'message' => 'Capital entry added successfully.']);
        });
    }

    public function show(Capital $capital)
    {
        return response()->json($capital);
    }

    public function update(Request $request, Capital $capital)
    {
        $validated = $this->validated($request, false);

        return DB::transaction(function () use ($validated, $capital) {
            $this->assertBranchAccount($validated['account_id'], $validated['branch_id']);
            [$shareCapitalCoaId, $cashCoaId] = $this->coaIds();

            $capital->update(array_merge($validated, ['chart_of_account_id' => $shareCapitalCoaId]));
            TransactionService::repost(
                'capital',
                $capital->id,
                fn () => $this->entries($capital->fresh(), $shareCapitalCoaId, $cashCoaId, false)
            );

            return response()->json(['success' => true, 'message' => 'Capital updated successfully.']);
        });
    }

    public function destroy(Capital $capital)
    {
        DB::transaction(function () use ($capital) {
            TransactionService::delete('capital', $capital->id);
            $capital->delete();
        });

        return response()->json(['success' => true, 'message' => 'Capital deleted.']);
    }

    private function validated(Request $request, bool $shareRequired): array
    {
        return $request->validate([
            'shareholder_id' => 'required|exists:shareholders,id',
            'branch_id' => 'required|exists:branches,id',
            'account_id' => 'required|exists:accounts,id',
            'currency_id' => 'required|exists:currencies,id',
            'amount' => 'required|numeric|min:0.01',
            'share_percentage' => ($shareRequired ? 'required' : 'nullable') . '|numeric|min:0|max:100',
            'date' => 'required|date',
            'description' => 'nullable|string|max:2000',
        ]);
    }

    private function assertBranchAccount(int|string $accountId, int|string $branchId): void
    {
        $valid = Account::whereKey($accountId)
            ->where('type', 'branch')
            ->where('account_code', 'not like', 'INTERBRANCH-%')
            ->where('branch_id', $branchId)
            ->exists();

        if (! $valid) {
            throw ValidationException::withMessages(['account_id' => 'The selected account does not belong to the selected branch.']);
        }
    }

    private function coaIds(): array
    {
        $shareCapitalCoaId = (int) ChartOfAccount::where('code', '3003')->value('id');
        $cashCoaId = (int) ChartOfAccount::where('code', '1000')->value('id');
        if (! $shareCapitalCoaId || ! $cashCoaId) {
            throw new \RuntimeException('Share Capital (3003) or Cash on Hand (1000) COA is missing.');
        }

        return [$shareCapitalCoaId, $cashCoaId];
    }

    private function entries(Capital $capital, int $shareCapitalCoaId, int $cashCoaId, bool $includeModule = true): array
    {
        $common = [
            'branch_id' => $capital->branch_id,
            'currency_id' => $capital->currency_id,
            'amount' => $capital->amount,
            'date' => $capital->date,
            'created_by' => Auth::id(),
            'accountable_type' => Account::class,
            'accountable_id' => $capital->account_id,
        ];
        if ($includeModule) {
            $common['module_name'] = 'capital';
            $common['module_id'] = $capital->id;
        }

        return [
            array_merge($common, [
                'direction' => 'debit',
                'chart_of_account_id' => $cashCoaId,
                'description' => $capital->description ?: 'Cash received for capital',
            ]),
            array_merge($common, [
                'direction' => 'credit',
                'chart_of_account_id' => $shareCapitalCoaId,
                'description' => $capital->description ?: 'Share capital contribution',
            ]),
        ];
    }
}
