<?php

namespace App\Services;

use App\Models\RemittanceLeg;
use App\Models\Transaction;

/** Read-only checks of interbranch remittance obligations and settlement evidence. */
class InterbranchSettlementAudit
{
    private function journalIssues(string $module, int $moduleId, int $legId): array
    {
        $entries = Transaction::withTrashed()->where('module_name', $module)->where('module_id', $moduleId)->get();
        if ($entries->isEmpty()) {
            return [['leg_id' => $legId, 'type' => 'missing_journal']];
        }
        $issues = [];
        if ($entries->contains(fn ($entry) => $entry->trashed())) {
            $issues[] = ['leg_id' => $legId, 'type' => 'deleted_journal_entry'];
        }
        foreach ($entries->groupBy('linked_id') as $group) {
            $debit = round($group->where('direction', 'debit')->sum('amount_afn'), 2);
            $credit = round($group->where('direction', 'credit')->sum('amount_afn'), 2);
            if (abs($debit - $credit) > 0.02) {
                $issues[] = ['leg_id' => $legId, 'type' => 'unbalanced_journal', 'debit_afn' => $debit, 'credit_afn' => $credit];
            }
        }
        return $issues;
    }

    public function inspect(?int $branchId = null): array
    {
        $issues = [];
        $checked = 0;
        $positions = [];
        $legs = RemittanceLeg::query()
            ->with(['remittance', 'settlements'])
            ->when($branchId, fn ($query) => $query->where(fn ($q) => $q->where('debtor_branch_id', $branchId)->orWhere('creditor_branch_id', $branchId)))
            ->lazyById(200);

        foreach ($legs as $leg) {
            $checked++;
            $principal = (float) $leg->amount;
            $settled = round($leg->settlements->sum(fn ($row) => (float) $row->amount), 4);
            $outstanding = round($principal - $settled, 4);
            $currencyId = (int) ($leg->remittance?->currency_id ?? 0);
            $pairKey = implode(':', [(int) $leg->debtor_branch_id, (int) $leg->creditor_branch_id, $currencyId]);
            $positions[$pairKey] ??= [
                'debtor_branch_id' => (int) $leg->debtor_branch_id,
                'creditor_branch_id' => (int) $leg->creditor_branch_id,
                'currency_id' => $currencyId,
                'obligation' => 0.0,
                'settled' => 0.0,
                'outstanding' => 0.0,
            ];
            $positions[$pairKey]['obligation'] += $principal;
            $positions[$pairKey]['settled'] += $settled;
            $positions[$pairKey]['outstanding'] += $outstanding;
            if ($principal <= 0 || $settled < 0 || $settled > $principal + 0.00005) {
                $issues[] = ['leg_id' => $leg->id, 'type' => 'invalid_obligation', 'amount' => $principal, 'settled' => $settled];
            }
            if (! $leg->remittance || ! in_array($leg->remittance->status, ['approved', 'completed'], true)) {
                $issues[] = ['leg_id' => $leg->id, 'type' => 'unapproved_obligation'];
            } elseif (! Transaction::withTrashed()->where('module_name', 'remittance')->where('module_id', $leg->remittance_id)->exists()) {
                $issues[] = ['leg_id' => $leg->id, 'type' => 'missing_approval_journal'];
            }
            if ($leg->remittance && in_array($leg->remittance->status, ['approved', 'completed'], true)) {
                $issues = array_merge($issues, array_filter(
                    $this->journalIssues('remittance', (int) $leg->remittance_id, (int) $leg->id),
                    fn ($issue) => $issue['type'] !== 'missing_journal'
                ));
            }
            foreach ($leg->settlements as $settlement) {
                if ((float) $settlement->amount <= 0) {
                    $issues[] = ['leg_id' => $leg->id, 'settlement_id' => $settlement->id, 'type' => 'invalid_settlement'];
                }
                $posted = Transaction::withTrashed()->where('module_name', 'remittance_settlement')->where('module_id', $settlement->id)->exists();
                if (! $posted) {
                    $issues[] = ['leg_id' => $leg->id, 'settlement_id' => $settlement->id, 'type' => 'missing_settlement_journal'];
                } else {
                    foreach ($this->journalIssues('remittance_settlement', (int) $settlement->id, (int) $leg->id) as $issue) {
                        $issues[] = $issue + ['settlement_id' => $settlement->id];
                    }
                }
            }
        }

        $positions = array_values(array_map(function (array $position): array {
            foreach (['obligation', 'settled', 'outstanding'] as $field) {
                $position[$field] = round($position[$field], 4);
            }
            return $position;
        }, $positions));

        return ['legs_checked' => $checked, 'positions' => $positions, 'issues' => $issues, 'issue_count' => count($issues)];
    }
}
