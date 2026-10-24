<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransactionTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            'credit' => ['income', 'liability', 'equity'],
            'debit' => ['asset', 'expense'],
        ];

        foreach (DB::table('chart_of_accounts')->get() as $coa) {
            $direction = collect($types)
                ->filter(fn (array $groups) => in_array($coa->type, $groups, true))
                ->keys()
                ->first();

            if (! $direction) {
                Log::warning("COA type '{$coa->type}' is not mapped for '{$coa->account_name}'.");
                continue;
            }

            DB::table('transaction_types')->updateOrInsert(
                ['default_coa_id' => $coa->id, 'name' => $coa->account_name],
                [
                    'category' => $coa->category,
                    'direction' => $direction,
                    'is_editable' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
