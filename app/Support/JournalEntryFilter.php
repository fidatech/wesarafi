<?php

namespace App\Support;

final class JournalEntryFilter
{
    public static function apply($query, string $scope = 'primary'): void
    {
        $cash = static function ($q) {
            $q->where('transactions.is_safe', true)
                ->orWhereIn('transactions.chart_of_account_id', function ($coa) {
                    $coa->select('id')->from('chart_of_accounts')->where('code', '1000');
                });
        };
        $branch = static function ($q) {
            $q->whereIn('transactions.account_id', function ($accounts) {
                $accounts->select('id')->from('accounts')->where('type', 'branch');
            })->orWhereIn('transactions.chart_of_account_id', function ($coa) {
                $coa->select('id')->from('chart_of_accounts')->whereIn('code', ['1006', '2002']);
            });
        };

        if ($scope === 'all') {
            return;
        }
        if ($scope === 'cash') {
            $query->where($cash);
        } elseif ($scope === 'branch') {
            $query->where($branch);
        } else {
            // Exclude by ID rather than NOT IN on nullable account/COA columns.
            $query->whereNotIn('transactions.id', function ($internal) use ($cash, $branch) {
                $internal->select('transactions.id')->from('transactions')
                    ->where($cash)->orWhere($branch);
            });
        }
    }
}
