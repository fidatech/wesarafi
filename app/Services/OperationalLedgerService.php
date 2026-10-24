<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use RuntimeException;

/**
 * Backward-compatible facade for operational modules.
 * All journal writes are delegated to TransactionService so there is one
 * validation, AFN-conversion and balancing path across the application.
 */
class OperationalLedgerService
{
    /** @var array<string,int> */
    private static array $coaCache = [];

    public function post(array $header, array $lines): string
    {
        $entries = [];
        foreach ($lines as $line) {
            $entries[] = array_merge($header, $line, [
                'is_approved' => $header['is_approved'] ?? true,
                'approval_status' => $header['approval_status'] ?? 'approved',
            ]);
        }

        $posted = TransactionService::postMany($entries, $header['linked_id'] ?? null);

        return (string) $posted[0]->linked_id;
    }

    public function coa(string $code): int
    {
        if (! isset(self::$coaCache[$code])) {
            $id = ChartOfAccount::where('code', $code)->value('id');
            if (! $id) {
                throw new RuntimeException("Chart of account {$code} is missing.");
            }
            self::$coaCache[$code] = (int) $id;
        }

        return self::$coaCache[$code];
    }
}
