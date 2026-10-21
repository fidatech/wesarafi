<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Expense;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class ExpenseController extends Controller
{
    public function index()
    {
        return view('accounting.expenses.index', [
            'accounts' => Account::where('is_active', true)->orderBy('full_name')->get(),
            'currencies' => Currency::where('is_active', true)->orderBy('code')->get(),
            'chartOfAccounts' => ChartOfAccount::where('type', 'expense')->where('is_active', true)->get(),
        ]);
    }

    public function data()
    {
        $expenses = Expense::with(['account', 'currency', 'chartOfAccount', 'payments'])->latest();

        return DataTables::of($expenses)
            ->addIndexColumn()
            ->addColumn('chart_of_account', fn (Expense $expense) => $expense->chartOfAccount?->account_name ?? '-')
            ->addColumn('account', fn (Expense $expense) => $expense->account?->full_name ?? '-')
            ->addColumn('currency', fn (Expense $expense) => $expense->currency?->code ?? '-')
            ->addColumn('payment_type', fn (Expense $expense) => ucfirst($expense->payment_type))
            ->addColumn('payment_status', fn (Expense $expense) => ucfirst($expense->payment_status))
            ->addColumn('paid', fn (Expense $expense) => round((float) $expense->payments->sum('amount'), 2))
            ->addColumn('remaining', fn (Expense $expense) => round(max(0, (float) $expense->amount - (float) $expense->payments->sum('amount')), 2))
            ->addColumn('actions', function (Expense $expense) {
                $payButton = ($expense->payment_type === 'credit' && $expense->payment_status !== 'paid')
                    ? '<button class="btn btn-sm btn-warning payBtn" data-id="' . $expense->id . '" data-amount="' . $expense->amount . '" data-paid="' . $expense->payments->sum('amount') . '">Pay</button> '
                    : '';

                return $payButton
                    . '<button class="btn btn-sm btn-info editBtn" data-id="' . $expense->id . '">Edit</button> '
                    . '<button class="btn btn-sm btn-danger deleteBtn" data-id="' . $expense->id . '">Delete</button>';
            })
            ->rawColumns(['actions'])
            ->make(true);
    }

    public function store(Request $request)
    {
        $validated = $this->validatedData($request);
        [$cashCoaId, $payableCoaId] = $this->controlAccounts();

        DB::transaction(function () use ($validated, $cashCoaId, $payableCoaId) {
            $expense = Expense::create([
                ...$validated,
                'payment_status' => $validated['payment_type'] === 'cash' ? 'paid' : 'unpaid',
                'created_by' => Auth::id(),
            ]);

            TransactionService::postMany(
                $this->journalEntries($expense, $cashCoaId, $payableCoaId)
            );
        });

        return response()->json(['success' => true, 'message' => 'Expense recorded successfully.']);
    }

    public function edit($id)
    {
        return response()->json(Expense::findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $expense = Expense::with('payments')->findOrFail($id);
        $validated = $this->validatedData($request);
        $paidAmount = (float) $expense->payments->sum('amount');

        if ($paidAmount > 0) {
            if ($validated['payment_type'] !== 'credit') {
                throw ValidationException::withMessages([
                    'payment_type' => 'An expense with recorded payments must remain a credit expense.',
                ]);
            }

            if (
                (int) $validated['branch_id'] !== (int) $expense->branch_id
                || (int) $validated['currency_id'] !== (int) $expense->currency_id
                || (int) $validated['account_id'] !== (int) $expense->account_id
            ) {
                throw ValidationException::withMessages([
                    'account_id' => 'Branch, account, and currency cannot change after payments have been recorded.',
                ]);
            }

            if ((float) $validated['amount'] + 0.005 < $paidAmount) {
                throw ValidationException::withMessages([
                    'amount' => 'Expense amount cannot be lower than payments already recorded (' . number_format($paidAmount, 2) . ').',
                ]);
            }
        }

        [$cashCoaId, $payableCoaId] = $this->controlAccounts();

        DB::transaction(function () use ($expense, $validated, $paidAmount, $cashCoaId, $payableCoaId) {
            $paymentStatus = $validated['payment_type'] === 'cash'
                ? 'paid'
                : ($paidAmount >= (float) $validated['amount']
                    ? 'paid'
                    : ($paidAmount > 0 ? 'partial' : 'unpaid'));

            $expense->update([
                ...$validated,
                'payment_status' => $paymentStatus,
            ]);

            TransactionService::repost('expenses', $expense->id, function () use ($expense, $cashCoaId, $payableCoaId) {
                return $this->journalEntries($expense->fresh(), $cashCoaId, $payableCoaId);
            });
        });

        return response()->json(['success' => true, 'message' => 'Expense updated successfully.']);
    }

    public function destroy($id)
    {
        $expense = Expense::with('payments')->findOrFail($id);

        DB::transaction(function () use ($expense) {
            TransactionService::delete('expenses', $expense->id);

            foreach ($expense->payments as $payment) {
                TransactionService::delete('expense_payments', $payment->id);
            }

            $expense->delete();
        });

        return response()->json(['success' => true, 'message' => 'Expense and related payment journals deleted successfully.']);
    }

    private function validatedData(Request $request): array
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'chart_of_account_id' => [
                'required',
                Rule::exists('chart_of_accounts', 'id')->where(fn ($query) => $query->where('type', 'expense')),
            ],
            'currency_id' => 'required|exists:currencies,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_type' => 'required|in:cash,credit',
            'account_id' => 'required|exists:accounts,id',
            'date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:date',
            'description' => 'nullable|string|max:2000',
        ]);

        $account = Account::find($validated['account_id']);
        if (! $account || (int) $account->branch_id !== (int) $validated['branch_id']) {
            throw ValidationException::withMessages([
                'account_id' => 'The selected account must belong to the expense branch.',
            ]);
        }

        if ($validated['payment_type'] === 'credit' && empty($validated['due_date'])) {
            throw ValidationException::withMessages([
                'due_date' => 'A due date is required for credit expenses.',
            ]);
        }

        return $validated;
    }

    /** @return array{0:int,1:int} */
    private function controlAccounts(): array
    {
        $cashCoaId = (int) ChartOfAccount::where('code', '1000')->value('id');
        $payableCoaId = (int) ChartOfAccount::where('code', '2000')->value('id');

        if (! $cashCoaId || ! $payableCoaId) {
            throw new \RuntimeException('Cash on Hand (1000) or Accounts Payable (2000) is missing from the chart of accounts.');
        }

        return [$cashCoaId, $payableCoaId];
    }

    private function journalEntries(Expense $expense, int $cashCoaId, int $payableCoaId): array
    {
        $base = [
            'branch_id' => $expense->branch_id,
            'currency_id' => $expense->currency_id,
            'amount' => $expense->amount,
            'account_id' => $expense->account_id,
            'accountable_type' => Account::class,
            'accountable_id' => $expense->account_id,
            'module_name' => 'expenses',
            'module_id' => $expense->id,
            'date' => $expense->date,
            'created_by' => $expense->created_by ?: Auth::id(),
        ];

        return [
            array_merge($base, [
                'direction' => 'debit',
                'chart_of_account_id' => $expense->chart_of_account_id,
                'description' => $expense->description ?: 'Expense recognized',
            ]),
            array_merge($base, [
                'direction' => 'credit',
                'chart_of_account_id' => $expense->payment_type === 'cash' ? $cashCoaId : $payableCoaId,
                'description' => $expense->payment_type === 'cash'
                    ? 'Cash paid for expense'
                    : 'Expense payable recognized',
            ]),
        ];
    }
}
