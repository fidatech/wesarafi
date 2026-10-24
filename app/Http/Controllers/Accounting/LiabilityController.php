<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Liability;
use App\Models\LiabilityPayment;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\Account;
use App\Models\ChartOfAccount;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\DB;

class LiabilityController extends Controller
{
    public function index()
    {
        $currencies = Currency::all();
        $branches = Branch::all();
        return view('accounting.liabilities.index', compact('currencies', 'branches'));
    }

    public function data(Request $request)
    {
        $query = Liability::with(['account', 'currency', 'branch'])
            ->withSum('payments as paid_amount', 'amount') // 👈 SUM from relation
            ->latest();

        if ($request->branch_id) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->currency_id) {
            $query->where('currency_id', $request->currency_id);
        }

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('account', fn($row) => $row->account->full_name ?? '-')
            ->addColumn('currency', fn($row) => $row->currency->code ?? '-')
            ->addColumn('branch', fn($row) => $row->branch->name ?? '-')
            ->addColumn('status', fn($row) => ucfirst($row->status))
            ->addColumn('amount', function ($row) {
                $total = number_format($row->amount, 2);
                $paid = number_format($row->paid_amount ?? 0, 2);
                $remaining = number_format($row->amount - $row->paid_amount, 2);

                return '
                    <div class="rounded border shadow-sm p-2" style="background-color: #f8f9fa;">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="text-muted fw-semibold">Total</span>
                            <span class="fw-bold text-dark">' . $total . '</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="text-muted fw-semibold">Paid</span>
                            <span class="fw-bold text-success">' . $paid . '</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted fw-semibold">Remaining</span>
                            <span class="fw-bold text-danger">' . $remaining . '</span>
                        </div>
                    </div>
                ';
            })


            ->addColumn('action', function ($row) {
                return '
        <button onclick="editLiability(' . $row->id . ')" class="btn btn-sm btn-warning me-1">
            <i class="bi bi-pencil"></i>
        </button>
        <button onclick="deleteLiability(' . $row->id . ')" class="btn btn-sm btn-danger me-1">
            <i class="bi bi-trash"></i>
        </button>
        <button onclick="makePayment(' . $row->id . ')" class="btn btn-sm btn-success me-1">
            <i class="bi bi-cash-coin"></i>
        </button>
        <button onclick="viewPayments(' . $row->id . ')" class="btn btn-sm btn-info">
            <i class="bi bi-receipt"></i>
        </button>';
            })

            ->rawColumns(['amount', 'action'])
            ->make(true);
    }


    public function store(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'currency_id' => 'required|exists:currencies,id',
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date',
            'chart_of_account_id' => 'required|exists:chart_of_accounts,id',
            'description' => 'nullable|string|max:2000',
        ]);

        $this->assertLiabilityAccount((int) $validated['chart_of_account_id']);

        $branchId = (int) $validated['branch_id'];
        $accountId = Account::where('type', 'branch')->where('branch_id', $branchId)->value('id');

        $cashCoaId = ChartOfAccount::where('code', '1000')->value('id');
        if (! $accountId || ! $cashCoaId) {
            return response()->json(['success' => false, 'message' => 'Branch account or Cash on Hand COA is missing.'], 422);
        }

        DB::beginTransaction();
        try {
            $liability = Liability::create([
                'branch_id' => $validated['branch_id'],
                'account_id' => $accountId,
                'currency_id' => $validated['currency_id'],
                'amount' => $validated['amount'],
                'status' => 'unpaid',
                'date' => $validated['date'],
                'description' => $validated['description'] ?? null,
                'chart_of_account_id' => $validated['chart_of_account_id'],
                'created_by' => Auth::id(),
            ]);

            TransactionService::postMany([
                [
                    'direction' => 'debit',
                    'chart_of_account_id' => $cashCoaId,
                    'currency_id' => $validated['currency_id'],
                    'branch_id' => $branchId,
                    'amount' => $validated['amount'],
                    'accountable_type' => \App\Models\Account::class,
                    'accountable_id' => $accountId,
                    'module_name' => 'liabilities',
                    'module_id' => $liability->id,
                    'description' => 'Cash received from liability',
                    'date' => $validated['date'],
                    'created_by' => Auth::id(),
                ],
                [
                    'direction' => 'credit',
                    'chart_of_account_id' => $validated['chart_of_account_id'],
                    'currency_id' => $validated['currency_id'],
                    'branch_id' => $branchId,
                    'amount' => $validated['amount'],
                    'accountable_type' => \App\Models\Account::class,
                    'accountable_id' => $accountId,
                    'module_name' => 'liabilities',
                    'module_id' => $liability->id,
                    'description' => ($validated['description'] ?? null) ?: 'Liability recognized',
                    'date' => $validated['date'],
                    'created_by' => Auth::id(),
                ],
            ]);

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Liability added successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

    public function edit($id)
    {
        $liability = Liability::findOrFail($id);
        return response()->json($liability);
    }

    public function update(Request $request, $id)
    {
        $liability = Liability::findOrFail($id);

        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'currency_id' => 'required|exists:currencies,id',
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date',
            'chart_of_account_id' => 'required|exists:chart_of_accounts,id',
            'description' => 'nullable|string',
        ]);

        $this->assertLiabilityAccount((int) $validated['chart_of_account_id']);

        $branchId = (int) $validated['branch_id'];
        $accountId = Account::where('type', 'branch')->where('branch_id', $branchId)->value('id');
        $cashCoaId = ChartOfAccount::where('code', '1000')->value('id');
        if (! $accountId || ! $cashCoaId) {
            return response()->json(['success' => false, 'message' => 'Branch account or Cash on Hand COA is missing.'], 422);
        }

        $paidAmount = (float) $liability->payments()->sum('amount');
        if ($paidAmount > 0 && (
            (int) $validated['currency_id'] !== (int) $liability->currency_id
            || (int) $validated['branch_id'] !== (int) $liability->branch_id
        )) {
            return response()->json([
                'success' => false,
                'message' => 'Branch and currency cannot be changed after liability payments have been recorded.',
            ], 422);
        }

        if ((float) $validated['amount'] + 0.005 < $paidAmount) {
            return response()->json([
                'success' => false,
                'message' => 'Liability amount cannot be lower than payments already recorded (' . number_format($paidAmount, 2) . ').',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $liability->update([
                'branch_id' => $validated['branch_id'],
                'account_id' => $accountId,
                'currency_id' => $validated['currency_id'],
                'amount' => $validated['amount'],
                'date' => $validated['date'],
                'description' => $validated['description'] ?? null,
                'chart_of_account_id' => $validated['chart_of_account_id'],
            ]);

            TransactionService::repost('liabilities', $liability->id, function () use ($liability, $accountId, $cashCoaId) {
                return [
                    // CREDIT → Liability
                    [
                        'branch_id'           => $liability->branch_id,
                        'chart_of_account_id' => $liability->chart_of_account_id, // e.g., 2000
                        'currency_id'         => $liability->currency_id,
                        'amount'              => $liability->amount,
                        'direction'           => 'credit',
                        'description'         => $liability->description ?? 'Liability updated',
                        'date'                => $liability->date,
                        'created_by'          => auth()->id(),
                        'accountable_type'    => \App\Models\Account::class,
                        'accountable_id'      => $accountId,
                    ],
                    // DEBIT → Cash received
                    [
                        'branch_id'           => $liability->branch_id,
                        'chart_of_account_id' => $cashCoaId,
                        'currency_id'         => $liability->currency_id,
                        'amount'              => $liability->amount,
                        'direction'           => 'debit',
                        'description'         => 'Cash received from liability update',
                        'date'                => $liability->date,
                        'created_by'          => auth()->id(),
                        'accountable_type'    => \App\Models\Account::class,
                        'accountable_id'      => $accountId,
                    ],
                ];
            });

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Liability updated successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }


    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $liability = Liability::findOrFail($id);
            if ($liability->payments()->exists()) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'This liability has payments and cannot be deleted. Delete or reverse the payments first.',
                ], 422);
            }

            TransactionService::delete('liabilities', $liability->id);
            $liability->delete();

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Liability deleted']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

    public function payments($id)
    {
        $liability = Liability::with(['payments.account', 'currency'])->findOrFail($id);

        return view('accounting.liabilities.payments', compact('liability'));
    }

    public function summary()
    {
        $currencies = \App\Models\Currency::all();

        $view = view('accounting.liabilities.summary', compact('currencies'))->render();

        return response()->json([
            'success' => true,
            'html' => $view
        ]);
    }
    private function assertLiabilityAccount(int $chartOfAccountId): void
    {
        $isLiability = ChartOfAccount::whereKey($chartOfAccountId)
            ->where('type', 'liability')
            ->where('is_active', true)
            ->exists();

        if (! $isLiability) {
            abort(422, 'The selected chart of account must be an active liability account.');
        }
    }

}
