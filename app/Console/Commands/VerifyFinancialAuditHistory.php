<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Activitylog\Models\Activity;

class VerifyFinancialAuditHistory extends Command
{
    protected $signature = 'wesarafi:verify-financial-audit {--branch= : Inspect one branch}';
    protected $description = 'Read-only validation of financial export audit event fields.';

    public function handle(): int
    {
        $branch = $this->option('branch');
        if ($branch !== null && (! ctype_digit((string) $branch) || (int) $branch < 1)) {
            $this->error('Branch must be a positive integer.');
            return self::FAILURE;
        }
        $checked = 0;
        $invalid = [];
        $events = Activity::query()->where('log_name', 'financial-audit')
            ->where('description', 'Historical closing integrity report export requested')
            ->when($branch !== null, fn ($q) => $q->where('properties->branch_id', (int) $branch))
            ->orderBy('id')->cursor();
        foreach ($events as $event) {
            $checked++;
            $props = $event->properties;
            $branchId = $props?->get('branch_id');
            $requestedAt = $props?->get('requested_at');
            if (! $event->causer_id || ! is_numeric($branchId) || (int) $branchId < 1
                || $props?->get('action') !== 'historical_integrity_csv_export'
                || ! is_string($requestedAt) || ! strtotime($requestedAt) || ! $event->created_at) {
                $invalid[] = $event->id;
            }
        }
        $this->table(['Check', 'Count'], [['Export requests inspected', $checked], ['Malformed events', count($invalid)]]);
        if ($invalid) {
            $this->error('Malformed event IDs: '.implode(', ', array_slice($invalid, 0, 30)));
            return self::FAILURE;
        }
        $this->info('Required event fields are valid. This does not prove that events were not altered or deleted.');
        return self::SUCCESS;
    }
}
