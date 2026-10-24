<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Remittance;
use App\Models\RemittanceLeg;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RemittanceWorkflowService
{
    private array $lines = [];

    private function line(int $branch, string $coa, float $amount, string $direction, ?int $account = null): void
    {
        if ($amount <= 0) {
            return;
        }
        $this->lines[] = ['branch_id' => $branch, 'chart_of_account_id' => app(OperationalLedgerService::class)->coa($coa), 'amount' => $amount, 'direction' => $direction, 'account_id' => $account];
    }

    private function counterparty(int $owner, int $other): int
    {
        return Account::firstOrCreate(['account_code' => 'INTERBRANCH-'.$owner.'-'.$other],
            ['branch_id' => $owner, 'full_name' => Branch::findOrFail($other)->name.' — Interbranch', 'type' => 'branch', 'is_active' => true])->id;
    }

    private function post(Remittance $r, string $module, int $id, string $date, ?float $rate = null): void
    {
        $common = ['currency_id' => $r->currency_id, 'date' => $date, 'exchange_rate_afn' => $rate ?? (float) $r->exchange_rate_afn,
            'module_name' => $module, 'module_id' => $id, 'description' => $r->reference.' '.$module, 'created_by' => auth()->id()];
        TransactionService::postMany(array_map(fn ($line) => $line + $common, $this->lines));
        $this->lines = [];
    }

    public function approve(Remittance $r): void
    {
        DB::transaction(function () use ($r) {
            $r = Remittance::lockForUpdate()->findOrFail($r->id);
            abort_unless($r->status === 'pending', 422, 'Only pending remittances can be approved.');
            $this->lines = [];
            $source = (int) $r->from_branch_id;
            $dest = (int) $r->to_branch_id;
            $bridge = $r->bridge_branch_id;
            $principal = (float) $r->amount;
            $senderFee = (float) $r->sender_branch_commission;
            $receiverFee = (float) $r->receiver_branch_commission;
            $bridgeFee = (float) $r->bridge_commission;
            $next = $bridge ?: $dest;
            $forward = round($principal + $receiverFee + $bridgeFee, 4);
            // Source collects principal plus commissions and owes the next branch.
            $this->line($source, $r->collection_method === 'account' ? '1002' : '1000', $r->total_collected, 'debit', $r->sender_account_id);
            $this->line($source, '4001', $senderFee, 'credit');
            $this->line($source, '2002', $forward, 'credit', $this->counterparty($source, $next));
            $r->legs()->create(['debtor_branch_id' => $source, 'creditor_branch_id' => $next, 'amount' => $forward]);
            if ($bridge) {
                $this->line($bridge, '1006', $forward, 'debit', $this->counterparty($bridge, $source));
                $this->line($bridge, '4001', $bridgeFee, 'credit', $this->counterparty($bridge, $source));
                $this->line($bridge, '2002', $principal + $receiverFee, 'credit', $this->counterparty($bridge, $dest));
                $r->legs()->create(['debtor_branch_id' => $bridge, 'creditor_branch_id' => $dest, 'amount' => $principal + $receiverFee]);
            }
            $this->line($dest, '1006', $principal + $receiverFee, 'debit', $this->counterparty($dest, $bridge ?: $source));
            $this->line($dest, '2001', $principal, 'credit', $r->receiver_account_id);
            $this->line($dest, '4001', $receiverFee, 'credit', $this->counterparty($dest, $bridge ?: $source));
            $this->post($r, 'remittance', $r->id, $r->date->toDateString());
            $r->update(['status' => 'approved', 'workflow_version' => 2, 'approved_by' => auth()->id(), 'approved_at' => now(), 'rejection_reason' => null]);
        });
    }

    public function complete(Remittance $r, array $identity): void
    {
        DB::transaction(function () use ($r, $identity) {
            $r = Remittance::lockForUpdate()->findOrFail($r->id);
            abort_unless($r->status === 'approved', 422, 'Only approved remittances can be paid.');
            abort_unless($r->workflow_version == 2, 422, 'This remittance uses the previous ledger workflow and must be reconciled before payout.');
            $this->lines = [];
            $this->line($r->to_branch_id, '2001', (float) $r->amount, 'debit', $r->receiver_account_id);
            $this->line($r->to_branch_id, '1000', (float) $r->amount, 'credit', $r->receiver_account_id);
            $this->post($r, 'remittance_payout', $r->id, now()->toDateString());
            $r->update($identity + ['status' => 'completed', 'completed_by' => auth()->id(), 'completed_at' => now()]);
        });
    }

    public function settle(RemittanceLeg $leg, array $data): void
    {
        DB::transaction(function () use ($leg, $data) {
            $r = Remittance::lockForUpdate()->findOrFail($leg->remittance_id);
            $leg = RemittanceLeg::lockForUpdate()->findOrFail($leg->id);
            abort_unless(in_array($r->status, ['approved', 'completed']), 422, 'Approve the remittance before settling.');
            if ((float) $data['amount'] > $leg->outstanding) {
                throw ValidationException::withMessages(['amount' => 'Settlement exceeds the outstanding branch debt.']);
            }
            if ($data['date'] < $r->date->toDateString()) {
                throw ValidationException::withMessages(['date' => 'Settlement cannot be dated before the remittance.']);
            }
            $settlement = $leg->settlements()->create($data + ['created_by' => auth()->id()]);
            $this->lines = [];
            $debtor = $leg->debtor_branch_id;
            $creditor = $leg->creditor_branch_id;
            $amount = (float) $data['amount'];
            $this->line($debtor, '2002', $amount, 'debit', $this->counterparty($debtor, $creditor));
            $this->line($debtor, '1000', $amount, 'credit');
            $this->line($creditor, '1000', $amount, 'debit');
            $this->line($creditor, '1006', $amount, 'credit', $this->counterparty($creditor, $debtor));
            $this->post($r, 'remittance_settlement', $settlement->id, $data['date'], (float) $data['exchange_rate_afn']);
        });
    }
}
