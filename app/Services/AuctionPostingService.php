<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Auction;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use Illuminate\Support\Facades\Auth;

class AuctionPostingService
{
    /**
     * Build a balanced historical-AFN journal for a DAB/customer FX auction.
     * Purchases capitalize incoming FX at consideration paid. Sales remove FX
     * at moving-average carrying cost and recognize realized gain/loss.
     */
    public static function entries(Auction $auction, int $cashCoaId): array
    {
        $auction->loadMissing('lines.giveCurrency', 'lines.getCurrency');

        $entries = [];
        $inventory = [];
        $baseCode = config('dab_reports.base_currency', 'AFN');
        $baseCurrencyId = (int) Currency::where('code', $baseCode)->value('id');
        $gainCoaId = (int) ChartOfAccount::where('code', '4005')->value('id');
        $lossCoaId = (int) ChartOfAccount::where('code', '5016')->value('id');
        $description = $auction->description ?: ucfirst(str_replace('_', ' ', $auction->type));
        $date = $auction->date?->toDateString() ?: (string) $auction->date;

        if (! $baseCurrencyId || ! $gainCoaId || ! $lossCoaId) {
            throw new \RuntimeException('AFN currency, FX Gain (4005), or FX Loss (5016) is missing. Run the required seeders first.');
        }

        $loadPosition = function (int $currencyId) use (&$inventory, $auction, $date): array {
            if (! isset($inventory[$currencyId])) {
                $inventory[$currencyId] = FxInventoryCostService::position(
                    (int) $auction->branch_id,
                    $currencyId,
                    $date,
                    'auction',
                    (int) $auction->id
                );
            }

            return $inventory[$currencyId];
        };

        $common = [
            'branch_id' => $auction->branch_id,
            'account_id' => $auction->account_id,
            'module_name' => 'auction',
            'module_id' => $auction->id,
            'date' => $auction->date,
            'created_by' => $auction->created_by ?: Auth::id(),
            'accountable_type' => Account::class,
            'accountable_id' => $auction->account_id,
        ];

        foreach ($auction->lines as $line) {
            $giveAmount = (float) $line->give_amount;
            $getAmount = (float) $line->get_amount;
            $giveIsBase = (int) $line->give_currency_id === $baseCurrencyId;
            $getIsBase = (int) $line->get_currency_id === $baseCurrencyId;

            if ($giveIsBase) {
                $considerationAfn = round($giveAmount, 2);

                $entries[] = array_merge($common, [
                    'direction' => 'credit',
                    'chart_of_account_id' => $cashCoaId,
                    'currency_id' => $line->give_currency_id,
                    'amount' => $giveAmount,
                    'exchange_rate_afn' => 1,
                    'amount_afn' => $considerationAfn,
                    'description' => $description . ' (AFN paid)',
                ]);

                $entries[] = array_merge($common, [
                    'direction' => 'debit',
                    'chart_of_account_id' => $cashCoaId,
                    'currency_id' => $line->get_currency_id,
                    'amount' => $getAmount,
                    'exchange_rate_afn' => round($considerationAfn / $getAmount, 8),
                    'amount_afn' => $considerationAfn,
                    'description' => $description . ' (Foreign currency received)',
                ]);

                if (! $getIsBase) {
                    $position = $loadPosition((int) $line->get_currency_id);
                    $inventory[(int) $line->get_currency_id] = self::addInventory($position, $getAmount, $considerationAfn);
                }

                continue;
            }

            $position = $loadPosition((int) $line->give_currency_id);
            if ($position['units'] + 0.0001 < $giveAmount || $position['average_rate_afn'] <= 0) {
                $code = $line->giveCurrency?->code ?? (string) $line->give_currency_id;
                throw new \RuntimeException(
                    'Insufficient historical ' . $code . ' cash inventory. Available: '
                    . number_format($position['units'], 4) . ', required: ' . number_format($giveAmount, 4) . '.'
                );
            }

            $costAfn = round($giveAmount * $position['average_rate_afn'], 2);
            $inventory[(int) $line->give_currency_id] = self::removeInventory($position, $giveAmount, $costAfn);

            $entries[] = array_merge($common, [
                'direction' => 'credit',
                'chart_of_account_id' => $cashCoaId,
                'currency_id' => $line->give_currency_id,
                'amount' => $giveAmount,
                'exchange_rate_afn' => round($costAfn / $giveAmount, 8),
                'amount_afn' => $costAfn,
                'description' => $description . ' (Foreign currency sold at historical cost)',
            ]);

            if ($getIsBase) {
                $proceedsAfn = round($getAmount, 2);
                $entries[] = array_merge($common, [
                    'direction' => 'debit',
                    'chart_of_account_id' => $cashCoaId,
                    'currency_id' => $line->get_currency_id,
                    'amount' => $getAmount,
                    'exchange_rate_afn' => 1,
                    'amount_afn' => $proceedsAfn,
                    'description' => $description . ' (AFN received)',
                ]);

                $difference = round($proceedsAfn - $costAfn, 2);
                if ($difference > 0) {
                    $entries[] = array_merge($common, [
                        'direction' => 'credit',
                        'chart_of_account_id' => $gainCoaId,
                        'currency_id' => $baseCurrencyId,
                        'amount' => $difference,
                        'exchange_rate_afn' => 1,
                        'amount_afn' => $difference,
                        'description' => $description . ' (Realized FX gain)',
                    ]);
                } elseif ($difference < 0) {
                    $loss = abs($difference);
                    $entries[] = array_merge($common, [
                        'direction' => 'debit',
                        'chart_of_account_id' => $lossCoaId,
                        'currency_id' => $baseCurrencyId,
                        'amount' => $loss,
                        'exchange_rate_afn' => 1,
                        'amount_afn' => $loss,
                        'description' => $description . ' (Realized FX loss)',
                    ]);
                }

                continue;
            }

            $entries[] = array_merge($common, [
                'direction' => 'debit',
                'chart_of_account_id' => $cashCoaId,
                'currency_id' => $line->get_currency_id,
                'amount' => $getAmount,
                'exchange_rate_afn' => round($costAfn / $getAmount, 8),
                'amount_afn' => $costAfn,
                'description' => $description . ' (Cross-currency received)',
            ]);

            $getPosition = $loadPosition((int) $line->get_currency_id);
            $inventory[(int) $line->get_currency_id] = self::addInventory($getPosition, $getAmount, $costAfn);
        }

        return $entries;
    }

    private static function addInventory(array $position, float $units, float $carryingAfn): array
    {
        $newUnits = (float) $position['units'] + $units;
        $newCarrying = (float) $position['carrying_afn'] + $carryingAfn;

        return [
            'units' => round($newUnits, 4),
            'carrying_afn' => round($newCarrying, 2),
            'average_rate_afn' => $newUnits > 0 ? round($newCarrying / $newUnits, 8) : 0.0,
        ];
    }

    private static function removeInventory(array $position, float $units, float $carryingAfn): array
    {
        $newUnits = (float) $position['units'] - $units;
        $newCarrying = (float) $position['carrying_afn'] - $carryingAfn;

        if (abs($newUnits) < 0.0001) {
            $newUnits = 0.0;
            $newCarrying = 0.0;
        }

        return [
            'units' => round($newUnits, 4),
            'carrying_afn' => round($newCarrying, 2),
            'average_rate_afn' => $newUnits > 0 ? round($newCarrying / $newUnits, 8) : 0.0,
        ];
    }
}
