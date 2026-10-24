<?php

namespace App\Services;

use App\Models\FxRate;

class DailyBalanceState
{
    public static function forBranch(int $branchId, ?string $date = null): array
    {
        $date ??= now('Asia/Kabul')->toDateString();
        $query = FxRate::query()->whereDate('as_of', $date)->where('branch_id', $branchId);
        $opened = (clone $query)->where('type', 'open')->exists();
        $closed = (clone $query)->where('type', 'close')->exists();

        return [
            'date' => $date,
            'branch_id' => $branchId,
            'opened' => $opened,
            'closed' => $closed,
            'can_open' => !$opened && !$closed,
            'can_close' => $opened && !$closed,
        ];
    }
}
