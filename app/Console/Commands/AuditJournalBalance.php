<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditJournalBalance extends Command
{
    protected $signature = 'wesarafi:audit-journal
                            {--branch= : Restrict to one branch ID}
                            {--tolerance=0.50 : Maximum accepted AFN difference per linked journal}';

    protected $description = 'Audit linked journal groups for missing AFN values and unequal AFN debits and credits.';

    public function handle(): int
    {
        $branchId = $this->option('branch') ? (int) $this->option('branch') : null;
        $tolerance = max(0, (float) $this->option('tolerance'));

        $base = Transaction::query()
            ->where('is_approved', true)
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId));

        $missingLinked = (clone $base)->whereNull('linked_id')->count();
        $missingAfn = (clone $base)
            ->where(fn ($query) => $query->whereNull('amount_afn')->orWhereNull('exchange_rate_afn'))
            ->count();

        $imbalanced = DB::table('transactions')
            ->select(
                'linked_id',
                DB::raw("SUM(CASE WHEN direction = 'debit' THEN COALESCE(amount_afn, 0) ELSE 0 END) AS debit_afn"),
                DB::raw("SUM(CASE WHEN direction = 'credit' THEN COALESCE(amount_afn, 0) ELSE 0 END) AS credit_afn")
            )
            ->whereNull('deleted_at')
            ->where('is_approved', true)
            ->whereNotNull('linked_id')
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->groupBy('linked_id')
            ->havingRaw('ABS(SUM(CASE WHEN direction = \'debit\' THEN COALESCE(amount_afn, 0) ELSE -COALESCE(amount_afn, 0) END)) > ?', [$tolerance])
            ->orderByDesc(DB::raw('ABS(SUM(CASE WHEN direction = \'debit\' THEN COALESCE(amount_afn, 0) ELSE -COALESCE(amount_afn, 0) END))'))
            ->limit(100)
            ->get();

        $this->table(
            ['Check', 'Result'],
            [
                ['Approved lines without linked_id', number_format($missingLinked)],
                ['Approved lines missing historical AFN values', number_format($missingAfn)],
                ["Imbalanced linked groups (first 100, tolerance {$tolerance} AFN)", number_format($imbalanced->count())],
            ]
        );

        if ($imbalanced->isNotEmpty()) {
            $this->newLine();
            $this->table(
                ['Linked ID', 'Debit AFN', 'Credit AFN', 'Difference'],
                $imbalanced->map(fn ($row) => [
                    $row->linked_id,
                    number_format((float) $row->debit_afn, 2),
                    number_format((float) $row->credit_afn, 2),
                    number_format((float) $row->debit_afn - (float) $row->credit_afn, 2),
                ])->all()
            );
        }

        if ($missingLinked || $missingAfn || $imbalanced->isNotEmpty()) {
            $this->warn('Journal cleanup is required before treating regulatory totals as final.');
            return self::FAILURE;
        }

        $this->info('Journal integrity checks passed.');
        return self::SUCCESS;
    }
}
