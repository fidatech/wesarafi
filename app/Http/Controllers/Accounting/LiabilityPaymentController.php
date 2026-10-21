<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\ChartOfAccount;
use App\Models\Liability;
use App\Models\LiabilityPayment;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LiabilityPaymentController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'liability_id' => 'required|exists:liabilities,id',
            'account_id' => 'required|exists:accounts,id',
            'currency_id' => 'required|exists:currencies,id',
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date',
            'description' => 'nullable|string|max:2000',
        ]);

        $liability = Liability::with('payments')->findOrFail($validated['liability_id']);
        $this->assertPaymentIsValid($liability, $validated, null);
        $cashCoaId = $this->cashCoaId();

        DB::transaction(function () use ($validated, $liability, $cashCoaId) {
            $payment = LiabilityPayment::create([
                ...$validated,
                'created_by' => Auth::id(),
            ]);

            TransactionService::postMany(
                $this->journalEntries($payment, $liability, $cashCoaId)
            );

            $this->updateLiabilityStatus($liability);
        });

        return response()->json(['success' => true, 'message' => 'Liability payment recorded successfully.']);
    }

    public function edit($id)
    {
        return response()->json([
            'success' => true,
            'payment' => LiabilityPayment::findOrFail($id),
        ]);
    }

    public function update(Request $request, $id)
    {
        $payment = LiabilityPayment::with('liability.payments')->findOrFail($id);
        $validated = $request->validate([
            'account_id' => 'required|exists:accounts,id',
            'currency_id' => 'required|exists:currencies,id',
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date',
            'description' => 'nullable|string|max:2000',
        ]);

        $liability = $payment->liability;
        $this->assertPaymentIsValid($liability, $validated, (int) $payment->id);
        $cashCoaId = $this->cashCoaId();

        DB::transaction(function () use ($payment, $validated, $liability, $cashCoaId) {
            $payment->update($validated);

            TransactionService::repost('liability_payments', $payment->id, function () use ($payment, $liability, $cashCoaId) {
                return $this->journalEntries($payment->fresh(), $liability, $cashCoaId);
            });

            $this->updateLiabilityStatus($liability);
        });

        return response()->json(['success' => true, 'message' => 'Payment updated successfully.']);
    }

    public function destroy($id)
    {
        $payment = LiabilityPayment::with('liability')->findOrFail($id);
        $liability = $payment->liability;

        DB::transaction(function () use ($payment, $liability) {
            TransactionService::delete('liability_payments', $payment->id);
            $payment->delete();
            $this->updateLiabilityStatus($liability);
        });

        return response()->json(['success' => true, 'message' => 'Payment deleted successfully.']);
    }

    private function assertPaymentIsValid(Liability $liability, array $validated, ?int $ignorePaymentId): void
    {
        if ((int) $validated['currency_id'] !== (int) $liability->currency_id) {
            throw ValidationException::withMessages([
                'currency_id' => 'Payment currency must match the liability currency.',
            ]);
        }

        $account = Account::find($validated['account_id']);
        if (! $account || (int) $account->branch_id !== (int) $liability->branch_id) {
            throw ValidationException::withMessages([
                'account_id' => 'The payment account must belong to the liability branch.',
            ]);
        }

        $paid = $liability->payments()
            ->when($ignorePaymentId, fn ($query) => $query->where('id', '!=', $ignorePaymentId))
            ->sum('amount');
        $outstanding = max(0, (float) $liability->amount - (float) $paid);

        if ((float) $validated['amount'] > $outstanding + 0.005) {
            throw ValidationException::withMessages([
                'amount' => 'Payment exceeds the outstanding liability balance of ' . number_format($outstanding, 2) . '.',
            ]);
        }
    }

    private function journalEntries(LiabilityPayment $payment, Liability $liability, int $cashCoaId): array
    {
        $base = [
            'branch_id' => $liability->branch_id,
            'currency_id' => $payment->currency_id,
            'amount' => $payment->amount,
            'account_id' => $payment->account_id,
            'accountable_type' => Account::class,
            'accountable_id' => $payment->account_id,
            'module_name' => 'liability_payments',
            'module_id' => $payment->id,
            'date' => $payment->date,
            'created_by' => $payment->created_by ?: Auth::id(),
        ];

        return [
            array_merge($base, [
                'direction' => 'debit',
                'chart_of_account_id' => $liability->chart_of_account_id,
                'description' => $payment->description ?: 'Liability payment',
            ]),
            array_merge($base, [
                'direction' => 'credit',
                'chart_of_account_id' => $cashCoaId,
                'description' => 'Cash paid for liability',
            ]),
        ];
    }

    private function cashCoaId(): int
    {
        $id = ChartOfAccount::where('code', '1000')->value('id');
        if (! $id) {
            throw new \RuntimeException('COA code 1000 (Cash on Hand) was not found.');
        }

        return (int) $id;
    }

    private function updateLiabilityStatus(Liability $liability): void
    {
        $totalPaid = (float) $liability->payments()->sum('amount');
        $status = $totalPaid >= (float) $liability->amount
            ? 'paid'
            : ($totalPaid > 0 ? 'partial' : 'unpaid');

        $liability->update(['status' => $status]);
    }
}
