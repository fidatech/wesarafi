<?php

namespace App\Services;

use App\Models\RemittanceLeg;
use App\Models\Transaction;

/** Read-only checks of interbranch remittance obligations and settlement evidence. */
class InterbranchSettlementAudit
{
    public function inspect(?int $branchId = null): array
    {
        $issues = [];
        $checked = 0;
        $legs = RemittanceLeg::query()
            ->with(['remittance', 'settlements'])
            ->when($branchId, fn ($query) => $query->where(fn ($q) => $q->where('debtor_branch_id', $branchId)->orWhere('creditor_branch_id', $branchId)))
            ->orderBy('id')->cursor();

        foreach ($legs as $leg) {
            $checked++;
            $principal = (float) $leg->amount;
            $settled = round($leg->settlements->sum(fn ($row) => (float) $row->amount), 4);
            if ($principal <= 0 || $settled < 0 || $settled > $principal + 0.00005) {
                $issues[] = ['leg_id' => $leg->id, 'type' => 'invalid_obligation', 'amount' => $principal, 'settled' => $settled];
            }
            if (! $leg->remittance || ! in_array($leg->remittance->status, ['approved', 'completed'], true)) {
                $issues[] = ['leg_id' => $leg->id, 'type' => 'unapproved_obligation'];
            } elseif (! Transaction::withTrashed()->where('module_name', 'remittance')->where('module_id', $leg->remittance_id)->exists()) {
                $issues[] = ['leg_id' => $leg->id, 'type' => 'missing_approval_journal'];
            }
            foreach ($leg->settlements as $settlement) {
                if ((float) $settlement->amount <= 0) {
                    $issues[] = ['leg_id' => $leg->id, 'settlement_id' => $settlement->id, 'type' => 'invalid_settlement'];
                }
                $posted = Transaction::withTrashed()->where('module_name', 'remittance_settlement')->where('module_id', $settlement->id)->exists();
                if (! $posted) {
                    $issues[] = ['leg_id' => $leg->id, 'settlement_id' => $settlement->id, 'type' => 'missing_settlement_journal'];
                }
            }
        }

        return ['legs_checked' => $checked, 'issues' => $issues, 'issue_count' => count($issues)];
    }
}
