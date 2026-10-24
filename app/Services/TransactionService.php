<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Currency;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class TransactionService
{
    /** @var array<int,string|null> Request-local currency code cache. */
    private static array $currencyCodeCache = [];

    public static function repost(string $module, int $moduleId, callable $dataCallback): void
    {
        DB::transaction(function () use ($module, $moduleId, $dataCallback) {
            $before = Transaction::where('module_name', $module)->where('module_id', $moduleId)->get()->map(fn ($row) => \App\Support\TransactionAudit::snapshot($row))->all();
            self::delete($module, $moduleId);

            $data = $dataCallback();
            $entries = isset($data[0]) ? $data : [$data];

            $posted = self::postMany(array_map(
                fn (array $entry) => array_merge($entry, [
                    'module_name' => $module,
                    'module_id' => $moduleId,
                ]),
                $entries
            ));
            if ($before) {
                \App\Support\TransactionAudit::record($before, array_map(fn ($row) => \App\Support\TransactionAudit::snapshot($row), $posted), 'Replaced '.$module.' #'.$moduleId);
            }
        });
    }

    /**
     * Post a balanced set of journal lines under one linked_id.
     *
     * @return array<int, Transaction>
     */
    public static function postMany(array $entries, ?string $linkedId = null): array
    {
        if (count($entries) < 2) {
            throw new InvalidArgumentException('A journal group must contain at least one debit and one credit line.');
        }

        return DB::transaction(function () use ($entries, $linkedId) {
            $linkedId ??= (string) Str::uuid();
            if (Transaction::withTrashed()->where('linked_id', $linkedId)->exists()) {
                throw new InvalidArgumentException('Journal reference already exists; duplicate posting prevented.');
            }
            $prepared = [];
            $debitAfn = 0.0;
            $creditAfn = 0.0;

            foreach ($entries as $entry) {
                $entry['linked_id'] = $linkedId;
                $entry = self::prepare($entry);
                $prepared[] = $entry;

                if ($entry['direction'] === 'debit') {
                    $debitAfn += $entry['amount_afn'];
                } else {
                    $creditAfn += $entry['amount_afn'];
                }
            }

            if (abs($debitAfn - $creditAfn) > 0.02) {
                throw new InvalidArgumentException(
                    'Unbalanced journal group. AFN debit '.number_format($debitAfn, 2)
                    .' does not equal AFN credit '.number_format($creditAfn, 2).'.'
                );
            }

            // Insert the already-normalized lines. Do not resolve rates or module
            // linked IDs again for each row; large journals remain fast and atomic.
            return array_map(fn (array $entry) => Transaction::create($entry), $prepared);
        });
    }

    public static function post(array $data): Transaction
    {
        return DB::transaction(function () use ($data) {
            if (empty($data['linked_id'])) {
                $data['linked_id'] = self::existingLinkedId($data) ?? (string) Str::uuid();
            }

            return Transaction::create(self::prepare($data));
        });
    }

    public static function delete(string $moduleName, int $moduleId): void
    {
        Transaction::where('module_name', $moduleName)
            ->where('module_id', $moduleId)
            ->get(['id', 'deleted_by'])
            ->each(function (Transaction $transaction) {
                $transaction->deleted_by = Auth::id();
                $transaction->save();
                $transaction->delete();
            });
    }

    private static function prepare(array $data): array
    {
        foreach (['branch_id', 'chart_of_account_id', 'currency_id', 'amount', 'direction'] as $key) {
            if (! array_key_exists($key, $data) || $data[$key] === null || $data[$key] === '') {
                throw new InvalidArgumentException("Missing required transaction field: {$key}");
            }
        }

        if (! in_array($data['direction'], ['debit', 'credit'], true)) {
            throw new InvalidArgumentException('Transaction direction must be debit or credit.');
        }

        $date = isset($data['date'])
            ? date('Y-m-d', strtotime((string) $data['date']))
            : now()->toDateString();
        $amount = round((float) $data['amount'], ($data['module_name'] ?? null) === 'customer_exchange' ? 8 : 4);
        if ($amount <= 0) {
            throw new InvalidArgumentException('Transaction amounts must be greater than zero.');
        }

        $rate = self::resolveAfnRate($data, $date);
        $amountAfn = array_key_exists('amount_afn', $data)
            ? round((float) $data['amount_afn'], 2)
            : round($amount * $rate, 2);
        if ($amountAfn <= 0) {
            throw new InvalidArgumentException('AFN-equivalent transaction amounts must be greater than zero.');
        }

        $accountId = $data['account_id'] ?? (
            ($data['accountable_type'] ?? null) === Account::class
                ? ($data['accountable_id'] ?? null)
                : null
        );

        $isApproved = (bool) ($data['is_approved'] ?? true);
        $approvalStatus = $data['approval_status'] ?? ($isApproved ? 'approved' : 'pending');

        return [
            'linked_id' => $data['linked_id'] ?? (string) Str::uuid(),
            'branch_id' => (int) $data['branch_id'],
            'date' => $date,
            'chart_of_account_id' => (int) $data['chart_of_account_id'],
            'currency_id' => (int) $data['currency_id'],
            'account_id' => $accountId,
            'amount' => $amount,
            'exchange_rate_afn' => $rate,
            'amount_afn' => $amountAfn,
            'direction' => $data['direction'],
            'description' => $data['description'] ?? null,
            'module_name' => $data['module_name'] ?? null,
            'module_id' => $data['module_id'] ?? null,
            'accountable_type' => $data['accountable_type'] ?? null,
            'accountable_id' => $data['accountable_id'] ?? null,
            'transaction_type_id' => $data['transaction_type_id'] ?? null,
            'is_approved' => $isApproved,
            'is_safe' => (bool) ($data['is_safe'] ?? false),
            'created_by' => $data['created_by'] ?? Auth::id(),
            'approval_status' => $approvalStatus,
            'approved_by' => $data['approved_by'] ?? ($isApproved ? Auth::id() : null),
            'approved_at' => $data['approved_at'] ?? ($isApproved ? now() : null),
            'rejection_reason' => $data['rejection_reason'] ?? null,
            'is_suspicious' => (bool) ($data['is_suspicious'] ?? false),
            'suspicious_reason' => $data['suspicious_reason'] ?? null,
        ];
    }

    private static function existingLinkedId(array $data): ?string
    {
        if (empty($data['module_name']) || empty($data['module_id'])) {
            return null;
        }

        return Transaction::where('module_name', $data['module_name'])
            ->where('module_id', $data['module_id'])
            ->whereNotNull('linked_id')
            ->value('linked_id');
    }

    private static function resolveAfnRate(array $data, string $date): float
    {
        if (isset($data['exchange_rate_afn']) && (float) $data['exchange_rate_afn'] > 0) {
            return round((float) $data['exchange_rate_afn'], 8);
        }

        $currencyId = (int) $data['currency_id'];
        if (! array_key_exists($currencyId, self::$currencyCodeCache)) {
            self::$currencyCodeCache[$currencyId] = Currency::whereKey($currencyId)->value('code');
        }

        if (self::$currencyCodeCache[$currencyId] === config('dab_reports.base_currency', 'AFN')) {
            return 1.0;
        }

        return round(DailyValuationService::effectiveRate(
            $date,
            'close',
            $currencyId,
            isset($data['branch_id']) ? (int) $data['branch_id'] : null
        ), 8);
    }
}
