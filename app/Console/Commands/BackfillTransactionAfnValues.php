<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Currency;
use App\Models\Transaction;
use App\Services\DailyValuationService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class BackfillTransactionAfnValues extends Command
{
    protected $signature = 'wesarafi:backfill-afn
                            {--branch= : Restrict to one branch ID}
                            {--dry-run : Count and calculate without saving}';

    protected $description = 'Backfill historical AFN exchange rates and AFN amounts for legacy transaction lines.';

    public function handle(): int
    {
        $branchId = $this->option('branch') ? (int) $this->option('branch') : null;
        $dryRun = (bool) $this->option('dry-run');
        $currencyCodes = Currency::pluck('code', 'id');
        $baseCode = config('dab_reports.base_currency', 'AFN');
        $processed = 0;
        $failed = 0;

        $query = Transaction::withTrashed()
            ->where(function ($query) {
                $query->whereNull('exchange_rate_afn')
                    ->orWhereNull('amount_afn')
                    ->orWhereNull('account_id')
                    ->orWhereNull('linked_id');
            })
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId));

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->info('No legacy transaction lines require backfilling.');
            return self::SUCCESS;
        }

        $this->info(($dryRun ? 'Dry run: ' : '') . "Processing {$total} legacy transaction line(s)…");
        $moduleLinkedIds = [];
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->orderBy('id')->chunkById(500, function ($transactions) use (
            &$processed,
            &$failed,
            $bar,
            $currencyCodes,
            $baseCode,
            $dryRun,
            &$moduleLinkedIds
        ) {
            foreach ($transactions as $transaction) {
                try {
                    $currencyCode = $currencyCodes[$transaction->currency_id] ?? null;
                    if (! $currencyCode) {
                        throw new \RuntimeException("Currency ID {$transaction->currency_id} was not found.");
                    }

                    $date = $transaction->date?->toDateString() ?: (string) $transaction->date;
                    $rate = $currencyCode === $baseCode
                        ? 1.0
                        : DailyValuationService::effectiveRate(
                            $date,
                            'close',
                            (int) $transaction->currency_id,
                            (int) $transaction->branch_id
                        );

                    $moduleKey = null;
                    if ($transaction->module_name && $transaction->module_id && ! $transaction->linked_id) {
                        $moduleKey = $transaction->module_name . ':' . $transaction->module_id;
                        $moduleLinkedIds[$moduleKey] ??= Transaction::withTrashed()
                            ->where('module_name', $transaction->module_name)
                            ->where('module_id', $transaction->module_id)
                            ->whereNotNull('linked_id')
                            ->value('linked_id') ?: (string) Str::uuid();
                    }

                    if (! $dryRun) {
                        $transaction->exchange_rate_afn ??= round((float) $rate, 8);
                        $transaction->amount_afn ??= round((float) $transaction->amount * (float) $rate, 2);

                        if (! $transaction->account_id && $transaction->accountable_type === Account::class) {
                            $transaction->account_id = $transaction->accountable_id;
                        }

                        if (! $transaction->linked_id && $moduleKey !== null) {
                            $transaction->linked_id = $moduleLinkedIds[$moduleKey];
                        }

                        $transaction->saveQuietly();
                    }

                    $processed++;
                } catch (\Throwable $exception) {
                    $failed++;
                    $this->newLine();
                    $this->error("Transaction #{$transaction->id}: {$exception->getMessage()}");
                } finally {
                    $bar->advance();
                }
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info("Calculated: {$processed}; failed: {$failed}." . ($dryRun ? ' No records were changed.' : ''));

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
