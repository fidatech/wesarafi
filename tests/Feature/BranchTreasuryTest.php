<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Currency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchTreasuryTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_branch_gets_one_treasury_account(): void
    {
        $branch = Branch::create(['name' => 'Test Branch', 'type' => 'independent']);
        $treasury = $branch->treasuryAccount()->firstOrFail();
        $this->assertSame('Test Branch Treasury', $treasury->full_name);
        $this->assertSame($treasury->id, $branch->ensureTreasuryAccount()->id);
        $this->assertSame(1, Account::where('branch_id', $branch->id)->where('type', 'safe')->count());
    }

    public function test_seeded_baseline_has_currencies_branches_treasuries_and_demo_accounts(): void
    {
        $this->seed();
        $this->assertSame(2, Branch::count());
        $this->assertSame(2, Account::where('type', 'safe')->count());
        $this->assertSame(2, Currency::count());
        $this->assertSame(['AFN'], Currency::where('is_default', true)->pluck('code')->all());
        $this->assertSame(8, Account::where('type', 'customer')->count());
        $this->assertSame(2, Account::where('type', 'bank')->count());
        foreach (Branch::all() as $branch) {
            $this->assertNotNull($branch->treasuryAccount);
        }
    }
}
