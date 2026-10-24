<?php

namespace App\Services;

use App\Models\BusinessDay;
use App\Models\FxRate;

class DailyBalanceState
{
    public static function forBranch(int $branchId, ?string $date = null): array
    {
        $date ??= now('Asia/Kabul')->toDateString();
        $day = BusinessDay::query()->where('branch_id', $branchId)->whereDate('business_date', $date)->first();
        // Preserve legacy rate entries without silently reopening previously closed days.
        $legacy = FxRate::query()->where('branch_id', $branchId)->whereDate('as_of', $date);
        $legacyOpen = (clone $legacy)->where('type', 'open')->exists();
        $legacyClose = (clone $legacy)->where('type', 'close')->exists();
        $status = $day?->status ?? ($legacyClose ? 'closed' : ($legacyOpen ? 'open' : 'not_opened'));

        return [
            'date' => $date,
            'branch_id' => $branchId,
            'status' => $status,
            'opened' => in_array($status, ['open', 'closing', 'closed'], true),
            'closed' => $status === 'closed',
            'can_open' => $status === 'not_opened',
            'can_close' => $status === 'open',
        ];
    }
}
