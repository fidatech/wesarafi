<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\ChartOfAccount;
use App\Models\Expense;
use App\Models\ExpensePayment;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExpensePaymentController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'expense_id' => 'required|exists:expenses,id',
            'account_id' => 'required|exists:accounts,id',
            'currency_id' => 'required|exists:currencies,id',
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date',
            'note' => 'nullable|string|max:2000',
        ]);

        $expense = Expense::with('payments')->findOrFail($validated['expense_id']);

        if ($expense->payment_type !== 'credit') {
            throw ValidationException::withMessages([
                'expense_id' => 'Only credit expenses can receive a separate payment.',
            ]);
        }

        if ((int) $validated['currency_id'] !== (int) $expense->currency_id) {
            throw ValidationException::withMessages([
                'currency_id' => 'Payment currency must match the expense currency.',
            ]);
        }

        $account = Account::find($validated['account_id']);
        if (! $account || (int) $account->branch_id !== (int) $expense->branch_id) {
            throw ValidationException::withMessages([
                'account_id' => 'The payment account must belong to the expense branch.',
            ]);
        }

        $paid = (float) $expense->payments()->sum('amount');
        $outstanding = max(0, (float) $expense->amount - $paid);
        if ((float) $validated['amount'] > $outstanding + 0.005) {
            throw ValidationException::withMessages([
                'amount' => 'Payment exceeds the outstanding expense balance of ' . number_format($outstanding, 2) . '.',
            ]);
        }

        $payableCoaId = (int) ChartOfAccount::where('code', '2000')->value('id');
        $cashCoaId = (int) ChartOfAccount::where('code', '1000')->value('id');
        if (! $payableCoaId || ! $cashCoaId) {
            throw ValidationException::withMessages([
                'expense_id' => 'Accounts Payable (2000) or Cash on Hand (1000) is missing from the chart of accounts.',
            ]);
        }

        DB::transaction(function () use ($validated, $expense, $payableCoaId, $cashCoaId) {
            $payment = ExpensePayment::create([
                ...$validated,
                'created_by' => Auth::id(),
            ]);

            $base = [
                'branch_id' => $expense->branch_id,
                'currency_id' => $payment->currency_id,
                'amount' => $payment->amount,
                'account_id' => $payment->account_id,
                'accountable_type' => Account::class,
                'accountable_id' => $payment->account_id,
                'module_name' => 'expense_payments',
                'module_id' => $payment->id,
                'date' => $payment->date,
                'created_by' => $payment->created_by,
            ];

            TransactionService::postMany([
                array_merge($base, [
                    'direction' => 'debit',
                    'chart_of_account_id' => $payableCoaId,
                    'description' => $payment->note ?: 'Payment for expense #' . $expense->id,
                ]),
                array_merge($base, [
                    'direction' => 'credit',
                    'chart_of_account_id' => $cashCoaId,
                    'description' => 'Cash paid for expense #' . $expense->id,
                ]),
            ]);

            $totalPaid = (float) $expense->payments()->sum('amount');
            $expense->update([
                'payment_status' => $totalPaid >= (float) $expense->amount
                    ? 'paid'
                    : ($totalPaid > 0 ? 'partial' : 'unpaid'),
            ]);
        });

        return response()->json(['success' => true, 'message' => 'Expense payment added successfully.']);
    }
}
