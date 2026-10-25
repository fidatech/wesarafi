<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class FinancialAuditVerifierTest extends TestCase
{
    use RefreshDatabase;

    public function test_verifier_accepts_export_and_rejects_invalid_branch(): void
    {
        $this->seed();
        $user = User::firstOrFail();
        $user->assignRole('Super Admin');
        $branch = (int) ($user->branch_id ?: 1);
        $this->actingAs($user)->get(route('financial_reports.historical-integrity.export', ['branch_id' => $branch]))->assertOk();
        $this->artisan('wesarafi:verify-financial-audit')->assertExitCode(0);
        $this->artisan('wesarafi:verify-financial-audit', ['--branch' => 'invalid'])->assertExitCode(1);
    }

    public function test_verifier_detects_malformed_export_event(): void
    {
        $this->seed();
        $user = User::firstOrFail();
        $user->assignRole('Super Admin');
        $branch = (int) ($user->branch_id ?: 1);
        $this->actingAs($user)->get(route('financial_reports.historical-integrity.export', ['branch_id' => $branch]))->assertOk();
        $event = Activity::where('log_name', 'financial-audit')->latest('id')->firstOrFail();
        $event->properties = ['branch_id' => $branch, 'action' => 'invalid'];
        $event->save();
        $this->artisan('wesarafi:verify-financial-audit')->assertExitCode(1);
    }
}
