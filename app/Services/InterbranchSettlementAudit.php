<?php

namespace App\Services;

use App\Models\Account;
use App\Models\ChartOfAccount;
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

    private function ledgerBalance(int $owner, int $other, int $currencyId, string $coaCode): float
    {
        $accountId = Account::query()->where('account_code', 'INTERBRANCH-'.$owner.'-'.$other)
            ->where('branch_id', $owner)->value('id');
        $coaId = ChartOfAccount::query()->where('code', $coaCode)->value('id');
        if (! $accountId || ! $coaId) {
            return 0.0;
        }
        $rows = Transaction::query()->where('branch_id', $owner)->where('account_id', $accountId)
            ->where('currency_id', $currencyId)->where('chart_of_account_id', $coaId)->get(['direction', 'amount']);
        $signed = $rows->sum(fn ($row) => (float) $row->amount * ($row->direction === 'debit' ? 1 : -1));
        return round($coaCode === '2002' ? -$signed : $signed, 4);
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

        // Ledger-only branch/currency pairs must not disappear simply because no leg exists.
        // Aggregate the same active transaction rows used by ledgerBalance; never repair balances here.
        $ledgerRows = Transaction::query()
            ->join('accounts', 'transactions.account_id', '=', 'accounts.id')
            ->join('chart_of_accounts', 'transactions.chart_of_account_id', '=', 'chart_of_accounts.id')
            ->where('accounts.account_code', 'like', 'INTERBRANCH-%')
            ->whereIn('chart_of_accounts.code', ['2002', '1006'])
            ->select('transactions.branch_id', 'transactions.currency_id', 'transactions.direction',
                'transactions.amount', 'accounts.account_code', 'chart_of_accounts.code as coa_code')
            ->cursor();
        $orphanBalances = [];
        foreach ($ledgerRows as $row) {
            if (! preg_match('/^INTERBRANCH-(\d+)-(\d+)$/', $row->account_code, $matches)) {
                continue;
            }
            $owner = (int) $matches[1];
            $other = (int) $matches[2];
            if ($owner !== (int) $row->branch_id || $owner === $other) {
                continue;
            }
            if ($branchId !== null && $branchId !== $owner && $branchId !== $other) {
                continue;
            }
            $debtor = $row->coa_code === '2002' ? $owner : $other;
            $creditor = $row->coa_code === '2002' ? $other : $owner;
            $key = implode(':', [$debtor, $creditor, (int) $row->currency_id]);
            if (isset($positions[$key])) {
                continue;
            }
            $field = $row->coa_code === '2002' ? 'payable_ledger' : 'receivable_ledger';
            $orphanBalances[$key] ??= [
                'debtor_branch_id' => $debtor, 'creditor_branch_id' => $creditor,
                'currency_id' => (int) $row->currency_id,
                'payable_ledger' => 0.0, 'receivable_ledger' => 0.0,
            ];
            $sign = $row->direction === 'debit' ? 1 : -1;
            $orphanBalances[$key][$field] += (float) $row->amount * $sign * ($field === 'payable_ledger' ? -1 : 1);
        }
        foreach ($orphanBalances as $orphan) {
            foreach (['payable_ledger', 'receivable_ledger'] as $field) {
                $balance = round($orphan[$field], 4);
                if (abs($balance) > 0.0001) {
                    $issues[] = ['leg_id' => null, 'type' => 'orphan_interbranch_'.$field,
                        'debtor_branch_id' => $orphan['debtor_branch_id'],
                        'creditor_branch_id' => $orphan['creditor_branch_id'],
                        'currency_id' => $orphan['currency_id'], 'actual' => $balance];
                }
            }
        }

        $positions = array_values(array_map(function (array $position): array {
            foreach (['obligation', 'settled', 'outstanding'] as $field) {
                $position[$field] = round($position[$field], 4);
            }
            return $position;
        }, $positions));

        foreach ($positions as &$position) {
            $debtor = $position['debtor_branch_id'];
            $creditor = $position['creditor_branch_id'];
            $currency = $position['currency_id'];
            $expected = $position['outstanding'];
            $position['payable_ledger'] = $this->ledgerBalance($debtor, $creditor, $currency, '2002');
            $position['receivable_ledger'] = $this->ledgerBalance($creditor, $debtor, $currency, '1006');
            foreach (['payable_ledger', 'receivable_ledger'] as $field) {
                if (abs($position[$field] - $expected) > 0.0001) {
                    $issues[] = ['leg_id' => null, 'type' => 'interbranch_'.$field.'_mismatch',
                        'debtor_branch_id' => $debtor, 'creditor_branch_id' => $creditor,
                        'currency_id' => $currency, 'expected' => $expected, 'actual' => $position[$field]];
                }
            }
        }
        unset($position);

        return ['legs_checked' => $checked, 'positions' => $positions, 'issues' => $issues, 'issue_count' => count($issues)];
    }
}
