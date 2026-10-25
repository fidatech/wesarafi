<?php

namespace Tests\Feature\Security;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FinancialBranchAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function entry(int $branchId): Transaction
    {
        return Transaction::create([
            'linked_id' => (string) Str::uuid(), 'branch_id' => $branchId,
            'chart_of_account_id' => ChartOfAccount::where('code', '1000')->value('id'),
            'currency_id' => Currency::where('code', 'AFN')->value('id'),
            'date' => now()->toDateString(), 'amount' => 100, 'amount_afn' => 100,
            'direction' => 'debit', 'created_by' => User::firstOrFail()->id,
            'approval_status' => 'pending', 'is_approved' => false,
        ]);
    }

    public function test_unprivileged_user_cannot_approve_or_flag_journals(): void
    {
        $branch = Branch::firstOrFail();
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $entry = $this->entry($branch->id);
        $this->actingAs($user);
        $this->post('/journal/approvals/'.$entry->linked_id.'/approve')->assertForbidden();
        $this->post('/monitoring/'.$entry->id.'/flag', ['reason' => 'Suspicious'])->assertForbidden();
        $this->assertSame('pending', $entry->fresh()->approval_status);
        $this->assertFalse((bool) $entry->fresh()->is_suspicious);
    }

    public function test_branch_user_cannot_view_other_branch_financial_statements(): void
    {
        $branch = Branch::firstOrFail();
        $other = Branch::whereKeyNot($branch->id)->firstOrFail();
        $this->actingAs(User::factory()->create(['branch_id' => $branch->id]));
        foreach (['balance-sheet', 'trial-balance', 'profit-loss'] as $report) {
            $this->get('/financial-reports/'.$report.'?branch_id='.$other->id)->assertForbidden();
        }
    }

    public function test_unbalanced_pending_journal_cannot_be_approved_but_can_be_rejected(): void
    {
        $branch = Branch::firstOrFail();
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $user->assignRole('Admin');
        $entry = $this->entry($branch->id);
        $second = $entry->replicate();
        $second->direction = 'credit';
        $second->amount = 50;
        $second->amount_afn = 50;
        $second->save();

        $this->actingAs($user);
        $this->post('/journal/approvals/'.$entry->linked_id.'/approve')->assertStatus(422);
        $this->assertSame('pending', $entry->fresh()->approval_status);
        $this->assertSame('pending', $second->fresh()->approval_status);
        $this->post('/journal/approvals/'.$entry->linked_id.'/reject', ['reason' => 'Unbalanced entries'])->assertRedirect();
        $this->assertSame('rejected', $entry->fresh()->approval_status);
        $this->assertSame('rejected', $second->fresh()->approval_status);
    }

    public function test_admin_cannot_reject_another_branch_journal(): void
    {
        $branch = Branch::firstOrFail();
        $other = Branch::whereKeyNot($branch->id)->firstOrFail();
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $user->assignRole('Admin');
        $entry = $this->entry($other->id);
        $this->actingAs($user);
        $this->post('/journal/approvals/'.$entry->linked_id.'/reject', ['reason' => 'Cross-branch attempt'])->assertForbidden();
        $this->assertSame('pending', $entry->fresh()->approval_status);
        $this->assertNull($entry->fresh()->rejection_reason);
    }

    public function test_admin_cannot_approve_mixed_branch_journal_group(): void
    {
        $branch = Branch::firstOrFail();
        $other = Branch::whereKeyNot($branch->id)->firstOrFail();
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $user->assignRole('Admin');
        $entry = $this->entry($branch->id);
        $otherEntry = $entry->replicate();
        $otherEntry->branch_id = $other->id;
        $otherEntry->direction = 'credit';
        $otherEntry->save();
        $this->actingAs($user);
        $this->post('/journal/approvals/'.$entry->linked_id.'/approve')->assertForbidden();
        $this->assertSame('pending', $entry->fresh()->approval_status);
        $this->assertSame('pending', $otherEntry->fresh()->approval_status);
    }

    public function test_admin_cannot_reject_mixed_branch_journal_group(): void
    {
        $branch = Branch::firstOrFail();
        $other = Branch::whereKeyNot($branch->id)->firstOrFail();
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $user->assignRole('Admin');
        $entry = $this->entry($branch->id);
        $otherEntry = $entry->replicate();
        $otherEntry->branch_id = $other->id;
        $otherEntry->direction = 'credit';
        $otherEntry->save();
        $this->actingAs($user);
        $this->post('/journal/approvals/'.$entry->linked_id.'/reject', ['reason' => 'Mixed branch attempt'])->assertForbidden();
        $this->assertSame('pending', $entry->fresh()->approval_status);
        $this->assertSame('pending', $otherEntry->fresh()->approval_status);
    }

    public function test_approved_journal_cannot_be_approved_or_rejected_again(): void
    {
        $branch = Branch::firstOrFail();
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $user->assignRole('Admin');
        $entry = $this->entry($branch->id);
        $entry->update(['approval_status' => 'approved', 'is_approved' => true]);
        $this->actingAs($user);
        $this->post('/journal/approvals/'.$entry->linked_id.'/approve')->assertStatus(422);
        $this->post('/journal/approvals/'.$entry->linked_id.'/reject', ['reason' => 'Duplicate review'])->assertStatus(422);
        $this->assertSame('approved', $entry->fresh()->approval_status);
        $this->assertTrue((bool) $entry->fresh()->is_approved);
    }

    public function test_rejected_journal_cannot_be_reapproved_or_rejected_again(): void
    {
        $branch = Branch::firstOrFail();
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $user->assignRole('Admin');
        $entry = $this->entry($branch->id);
        $entry->update(['approval_status' => 'rejected', 'rejection_reason' => 'Original rejection']);
        $this->actingAs($user);
        $this->post('/journal/approvals/'.$entry->linked_id.'/approve')->assertStatus(422);
        $this->post('/journal/approvals/'.$entry->linked_id.'/reject', ['reason' => 'Second rejection'])->assertStatus(422);
        $this->assertSame('rejected', $entry->fresh()->approval_status);
        $this->assertSame('Original rejection', $entry->fresh()->rejection_reason);
    }

    public function test_admin_without_branch_cannot_review_journals(): void
    {
        $branch = Branch::firstOrFail();
        $user = User::factory()->create(['branch_id' => null]);
        $user->assignRole('Admin');
        $entry = $this->entry($branch->id);
        $this->actingAs($user);
        $this->post('/journal/approvals/'.$entry->linked_id.'/approve')->assertForbidden();
        $this->post('/journal/approvals/'.$entry->linked_id.'/reject', ['reason' => 'No branch'])->assertForbidden();
        $this->assertSame('pending', $entry->fresh()->approval_status);
    }

    public function test_admin_cannot_review_group_with_already_finalized_entry(): void
    {
        $branch = Branch::firstOrFail();
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $user->assignRole('Admin');
        $entry = $this->entry($branch->id);
        $finalized = $entry->replicate();
        $finalized->direction = 'credit';
        $finalized->approval_status = 'approved';
        $finalized->is_approved = true;
        $finalized->save();
        $this->actingAs($user);
        $this->post('/journal/approvals/'.$entry->linked_id.'/approve')->assertStatus(422);
        $this->post('/journal/approvals/'.$entry->linked_id.'/reject', ['reason' => 'Mixed status'])->assertStatus(422);
        $this->assertSame('pending', $entry->fresh()->approval_status);
        $this->assertSame('approved', $finalized->fresh()->approval_status);
    }

    public function test_admin_cannot_approve_single_line_journal(): void
    {
        $branch = Branch::firstOrFail();
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $user->assignRole('Admin');
        $entry = $this->entry($branch->id);
        $this->actingAs($user);
        $this->post('/journal/approvals/'.$entry->linked_id.'/approve')->assertStatus(422);
        $this->assertSame('pending', $entry->fresh()->approval_status);
    }

    public function test_rejection_requires_reason_and_keeps_pending_journal_unchanged(): void
    {
        $branch = Branch::firstOrFail();
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $user->assignRole('Admin');
        $entry = $this->entry($branch->id);
        $this->actingAs($user);
        $this->post('/journal/approvals/'.$entry->linked_id.'/reject', ['reason' => ''])->assertSessionHasErrors('reason');
        $this->assertSame('pending', $entry->fresh()->approval_status);
        $this->assertNull($entry->fresh()->rejection_reason);
    }

    public function test_missing_journal_group_cannot_be_approved_or_rejected(): void
    {
        $branch = Branch::firstOrFail();
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $user->assignRole('Admin');
        $this->actingAs($user);
        $missing = (string) Str::uuid();
        $this->post('/journal/approvals/'.$missing.'/approve')->assertNotFound();
        $this->post('/journal/approvals/'.$missing.'/reject', ['reason' => 'Not found'])->assertNotFound();
    }

    public function test_admin_with_branch_assignment_cannot_approve_other_branch_journal(): void
    {
        $branch = Branch::firstOrFail();
        $other = Branch::whereKeyNot($branch->id)->firstOrFail();
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $user->assignRole('Admin');
        $entry = $this->entry($other->id);
        $this->actingAs($user);
        $this->post('/journal/approvals/'.$entry->linked_id.'/approve')->assertForbidden();
        $this->assertSame('pending', $entry->fresh()->approval_status);
    }
}
