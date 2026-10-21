<?php

namespace App\Console\Commands;

use App\Models\Auction;
use App\Models\ChartOfAccount;
use App\Models\Transaction;
use App\Services\AuctionPostingService;
use App\Services\TransactionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RebuildAuctionJournals extends Command
{
    protected $signature = 'wesarafi:rebuild-auction-journals
                            {--branch= : Restrict to one branch ID}
                            {--dry-run : Show how many auctions would be rebuilt}';

    protected $description = 'Rebuild auction journals chronologically using moving-average FX cost and realized gain/loss.';

    public function handle(): int
    {
        $branchId = $this->option('branch') ? (int) $this->option('branch') : null;
        $dryRun = (bool) $this->option('dry-run');

        $auctions = Auction::query()
            ->with(['lines.giveCurrency', 'lines.getCurrency'])
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        if ($auctions->isEmpty()) {
            $this->info('No auctions were found.');
            return self::SUCCESS;
        }

        $this->table(['Scope', 'Count'], [
            ['Auctions', number_format($auctions->count())],
            ['Auction lines', number_format($auctions->sum(fn (Auction $auction) => $auction->lines->count()))],
            ['Branch', $branchId ?: 'All branches'],
        ]);

        if ($dryRun) {
            $this->warn('Dry run only: no journal lines were changed.');
            return self::SUCCESS;
        }

        $cashCoaId = (int) ChartOfAccount::where('code', '1000')->value('id');
        if (! $cashCoaId) {
            $this->error('COA code 1000 (Cash on Hand) was not found.');
            return self::FAILURE;
        }

        $bar = $this->output->createProgressBar($auctions->count());
        $bar->start();

        try {
            DB::transaction(function () use ($auctions, $branchId, $cashCoaId, $bar) {
                Transaction::query()
                    ->where('module_name', 'auction')
                    ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
                    ->get()
                    ->each(fn (Transaction $transaction) => $transaction->delete());

                foreach ($auctions as $auction) {
                    TransactionService::postMany(
                        AuctionPostingService::entries($auction, $cashCoaId)
                    );
                    $bar->advance();
                }
            });
        } catch (\Throwable $exception) {
            $bar->finish();
            $this->newLine(2);
            $this->error('Auction rebuild failed and was rolled back: ' . $exception->getMessage());

            return self::FAILURE;
        }

        $bar->finish();
        $this->newLine(2);
        $this->info('Auction journals rebuilt successfully. Run wesarafi:audit-journal next.');

        return self::SUCCESS;
    }
}
