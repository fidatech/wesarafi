<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Branch;
use App\Models\BranchCapitalTransfer;
use App\Models\ChartOfAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BranchCapitalService
{
    public function create(array $data, ?BranchCapitalTransfer $advance = null): BranchCapitalTransfer
    {
        return DB::transaction(function () use ($data, $advance) {
            if ($advance) {
                $advance = BranchCapitalTransfer::lockForUpdate()->findOrFail($advance->id);
                if ($advance->advance_id || (float) $data['amount'] > $advance->outstanding) {
                    throw ValidationException::withMessages(['amount' => 'Repayment cannot exceed the outstanding amount.']);
                }
                if ($data['date'] < $advance->date->toDateString()) {
                    throw ValidationException::withMessages(['date' => 'Repayment cannot be dated before the advance.']);
                }
                $data['source_branch_id'] = $advance->destination_branch_id;
                $data['destination_branch_id'] = $advance->source_branch_id;
                $data['currency_id'] = $advance->currency_id;
                $data['advance_id'] = $advance->id;
            }
            $transfer = BranchCapitalTransfer::create($data + ['created_by' => auth()->id()]);
            $cash = ChartOfAccount::where('code', '1000')->firstOrFail();
            $receivable = ChartOfAccount::where('code', '1006')->where('type', 'asset')->firstOrFail();
            $payable = ChartOfAccount::where('code', '2002')->where('type', 'liability')->firstOrFail();
            $source = Branch::findOrFail($transfer->source_branch_id);
            $destination = Branch::findOrFail($transfer->destination_branch_id);
            $sourceCounterparty = $this->counterparty($source, $destination);
            $destinationCounterparty = $this->counterparty($destination, $source);
            $common = ['currency_id' => $transfer->currency_id, 'date' => $transfer->date->toDateString(),
                'amount' => $transfer->amount, 'exchange_rate_afn' => $transfer->exchange_rate_afn,
                'module_name' => 'branch_capital', 'module_id' => $transfer->id,
                'description' => ($advance ? 'Branch capital repayment' : 'Branch capital shared').' #'.$transfer->id.': '.$source->name.' → '.$destination->name.($transfer->description ? ' — '.$transfer->description : '')];
            TransactionService::postMany([
                $common + ['branch_id' => $source->id, 'account_id' => $source->ensureTreasuryAccount()->id, 'chart_of_account_id' => $cash->id, 'direction' => 'credit'],
                $common + ['branch_id' => $source->id, 'account_id' => $sourceCounterparty->id, 'chart_of_account_id' => $advance ? $payable->id : $receivable->id, 'direction' => 'debit'],
                $common + ['branch_id' => $destination->id, 'account_id' => $destination->ensureTreasuryAccount()->id, 'chart_of_account_id' => $cash->id, 'direction' => 'debit'],
                $common + ['branch_id' => $destination->id, 'account_id' => $destinationCounterparty->id, 'chart_of_account_id' => $advance ? $receivable->id : $payable->id, 'direction' => 'credit'],
            ]);

            return $transfer;
        });
    }

    private function counterparty(Branch $owner, Branch $other): Account
    {
        return Account::firstOrCreate(['account_code' => 'INTERBRANCH-'.$owner->id.'-'.$other->id],
            ['branch_id' => $owner->id, 'full_name' => $other->name.' — Interbranch', 'type' => 'branch', 'is_active' => true]);
    }
}
