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

    public function test_approval_list_hides_entire_mixed_branch_group(): void
    {
        $branch = Branch::firstOrFail();
        $other = Branch::whereKeyNot($branch->id)->firstOrFail();
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $user->assignRole('Admin');
        $mixed = $this->entry($branch->id);
        $foreign = $mixed->replicate();
        $foreign->branch_id = $other->id;
        $foreign->direction = 'credit';
        $foreign->save();
        $visible = $this->entry($branch->id);
        $this->actingAs($user)->get('/journal/approvals')->assertOk()
            ->assertDontSee($mixed->linked_id)
            ->assertSee($visible->linked_id);
    }

    public function test_approval_list_hides_partially_finalized_group(): void
    {
        $branch = Branch::firstOrFail();
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $user->assignRole('Admin');
        $pending = $this->entry($branch->id);
        $finalized = $pending->replicate();
        $finalized->direction = 'credit';
        $finalized->approval_status = 'approved';
        $finalized->is_approved = true;
        $finalized->save();
        $visible = $this->entry($branch->id);
        $this->actingAs($user)->get('/journal/approvals')->assertOk()
            ->assertDontSee($pending->linked_id)
            ->assertSee($visible->linked_id);
    }

    public function test_balanced_pending_journal_approval_records_reviewer_on_every_line(): void
    {
        $branch = Branch::firstOrFail();
        $reviewer = User::factory()->create(['branch_id' => $branch->id]);
        $reviewer->assignRole('Admin');
        $debit = $this->entry($branch->id);
        $credit = $debit->replicate();
        $credit->direction = 'credit';
        $credit->save();

        $this->actingAs($reviewer)->post('/journal/approvals/'.$debit->linked_id.'/approve')->assertRedirect();
        foreach ([$debit, $credit] as $line) {
            $line->refresh();
            $this->assertSame('approved', $line->approval_status);
            $this->assertTrue((bool) $line->is_approved);
            $this->assertEquals($reviewer->id, $line->approved_by);
            $this->assertNotNull($line->approved_at);
            $this->assertNull($line->rejection_reason);
        }
        $this->assertEquals($debit->approved_at, $credit->approved_at);
    }

    public function test_rejecting_pending_group_records_reason_on_every_line(): void
    {
        $branch = Branch::firstOrFail();
        $reviewer = User::factory()->create(['branch_id' => $branch->id]);
        $reviewer->assignRole('Admin');
        $debit = $this->entry($branch->id);
        $credit = $debit->replicate();
        $credit->direction = 'credit';
        $credit->save();

        $this->actingAs($reviewer)->post('/journal/approvals/'.$debit->linked_id.'/reject', ['reason' => 'Incorrect supporting documents'])->assertRedirect();
        foreach ([$debit, $credit] as $line) {
            $line->refresh();
            $this->assertSame('rejected', $line->approval_status);
            $this->assertFalse((bool) $line->is_approved);
            $this->assertSame('Incorrect supporting documents', $line->rejection_reason);
        }
    }

    public function test_approval_list_hides_other_branch_group_even_for_view_only_user(): void
    {
        $branch = Branch::firstOrFail();
        $other = Branch::whereKeyNot($branch->id)->firstOrFail();
        $viewer = User::factory()->create(['branch_id' => $branch->id]);
        $viewer->givePermissionTo('view journal');
        $foreign = $this->entry($other->id);
        $visible = $this->entry($branch->id);

        $this->actingAs($viewer)->get('/journal/approvals')->assertOk()
            ->assertDontSee($foreign->linked_id)
            ->assertSee($visible->linked_id);
    }

    public function test_rejection_clears_stale_approval_metadata_from_pending_entries(): void
    {
        $branch = Branch::firstOrFail();
        $reviewer = User::factory()->create(['branch_id' => $branch->id]);
        $reviewer->assignRole('Admin');
        $entry = $this->entry($branch->id);
        $entry->update(['approved_by' => $reviewer->id, 'approved_at' => now()]);
        $this->actingAs($reviewer)->post('/journal/approvals/'.$entry->linked_id.'/reject', ['reason' => 'Invalid document'])->assertRedirect();
        $entry->refresh();
        $this->assertSame('rejected', $entry->approval_status);
        $this->assertNull($entry->approved_by);
        $this->assertNull($entry->approved_at);
        $this->assertSame('Invalid document', $entry->rejection_reason);
    }

    public function test_balanced_zero_value_journal_cannot_be_approved(): void
    {
        $branch = Branch::firstOrFail();
        $reviewer = User::factory()->create(['branch_id' => $branch->id]);
        $reviewer->assignRole('Admin');
        $debit = $this->entry($branch->id);
        $debit->update(['amount' => 0, 'amount_afn' => 0]);
        $credit = $debit->replicate();
        $credit->direction = 'credit';
        $credit->save();

        $this->actingAs($reviewer)->post('/journal/approvals/'.$debit->linked_id.'/approve')->assertStatus(422);
        $this->assertSame('pending', $debit->fresh()->approval_status);
        $this->assertSame('pending', $credit->fresh()->approval_status);
    }

    public function test_negative_amount_journal_cannot_be_approved(): void
    {
        $branch = Branch::firstOrFail();
        $reviewer = User::factory()->create(['branch_id' => $branch->id]);
        $reviewer->assignRole('Admin');
        $debit = $this->entry($branch->id);
        $debit->update(['amount' => -100, 'amount_afn' => -100]);
        $credit = $debit->replicate();
        $credit->direction = 'credit';
        $credit->save();
        $this->actingAs($reviewer)->post('/journal/approvals/'.$debit->linked_id.'/approve')->assertStatus(422);
        $this->assertSame('pending', $debit->fresh()->approval_status);
        $this->assertSame('pending', $credit->fresh()->approval_status);
    }

    public function test_positive_original_amount_with_zero_base_amount_cannot_be_approved(): void
    {
        $branch = Branch::firstOrFail();
        $reviewer = User::factory()->create(['branch_id' => $branch->id]);
        $reviewer->assignRole('Admin');
        $debit = $this->entry($branch->id);
        $debit->update(['amount_afn' => 0]);
        $credit = $debit->replicate();
        $credit->direction = 'credit';
        $credit->save();
        $this->actingAs($reviewer)->post('/journal/approvals/'.$debit->linked_id.'/approve')->assertStatus(422);
        $this->assertSame('pending', $debit->fresh()->approval_status);
        $this->assertSame('pending', $credit->fresh()->approval_status);
    }

    public function test_view_only_journal_user_cannot_see_approval_or_rejection_actions(): void
    {
        $branch = Branch::firstOrFail();
        $viewer = User::factory()->create(['branch_id' => $branch->id]);
        $viewer->givePermissionTo('view journal');
        $entry = $this->entry($branch->id);

        $this->actingAs($viewer)->get('/journal/approvals')->assertOk()
            ->assertSee($entry->linked_id)
            ->assertDontSee('Approve group')
            ->assertDontSee('Rejection reason');
    }

    public function test_journal_editor_sees_approval_and_rejection_actions(): void
    {
        $branch = Branch::firstOrFail();
        $editor = User::factory()->create(['branch_id' => $branch->id]);
        $editor->givePermissionTo(['view journal', 'edit journal']);
        $entry = $this->entry($branch->id);
        $credit = $entry->replicate();
        $credit->direction = 'credit';
        $credit->save();

        $this->actingAs($editor)->get('/journal/approvals')->assertOk()
            ->assertSee($entry->linked_id)
            ->assertSee('Approve group')
            ->assertSee('Rejection reason');
    }

    public function test_rejection_form_has_accessible_label_and_mobile_friendly_layout(): void
    {
        $branch = Branch::firstOrFail();
        $reviewer = User::factory()->create(['branch_id' => $branch->id]);
        $reviewer->assignRole('Admin');
        $entry = $this->entry($branch->id);

        $this->actingAs($reviewer)->get('/journal/approvals')->assertOk()
            ->assertSee('for="reject-reason-'.$entry->linked_id.'"', false)
            ->assertSee('id="reject-reason-'.$entry->linked_id.'"', false)
            ->assertSee('maxlength="1000"', false)
            ->assertSee('d-flex flex-wrap gap-2', false);
    }

    public function test_approval_list_shows_base_currency_amounts_alongside_original_amounts(): void
    {
        $branch = Branch::firstOrFail();
        $reviewer = User::factory()->create(['branch_id' => $branch->id]);
        $reviewer->assignRole('Admin');
        $entry = $this->entry($branch->id);
        $entry->update(['amount' => 75, 'amount_afn' => 5250]);

        $this->actingAs($reviewer)->get('/journal/approvals')->assertOk()
            ->assertSee('Debit (AFN)')
            ->assertSee('Credit (AFN)')
            ->assertSee('75.00')
            ->assertSee('5,250.00');
    }

    public function test_approval_list_shows_group_afn_totals(): void
    {
        $branch = Branch::firstOrFail();
        $reviewer = User::factory()->create(['branch_id' => $branch->id]);
        $reviewer->assignRole('Admin');
        $debit = $this->entry($branch->id);
        $credit = $debit->replicate();
        $credit->direction = 'credit';
        $credit->save();

        $this->actingAs($reviewer)->get('/journal/approvals')->assertOk()
            ->assertSee('AFN totals')
            ->assertSee('100.00');
    }

    public function test_approval_list_flags_unbalanced_group_afn_difference(): void
    {
        $branch = Branch::firstOrFail();
        $reviewer = User::factory()->create(['branch_id' => $branch->id]);
        $reviewer->assignRole('Admin');
        $debit = $this->entry($branch->id);
        $credit = $debit->replicate();
        $credit->direction = 'credit';
        $credit->amount_afn = 75;
        $credit->save();

        $this->actingAs($reviewer)->get('/journal/approvals')->assertOk()
            ->assertSee('AFN difference: 25.00 (Unbalanced)');
    }

    public function test_unbalanced_group_shows_rejection_but_not_approval_action(): void
    {
        $branch = Branch::firstOrFail();
        $reviewer = User::factory()->create(['branch_id' => $branch->id]);
        $reviewer->assignRole('Admin');
        $debit = $this->entry($branch->id);
        $credit = $debit->replicate();
        $credit->direction = 'credit';
        $credit->amount_afn = 50;
        $credit->save();

        $this->actingAs($reviewer)->get('/journal/approvals')->assertOk()
            ->assertSee('Not eligible for approval')
            ->assertDontSee('Approve group')
            ->assertSee('Rejection reason');
    }

    public function test_balanced_two_line_group_keeps_approval_action(): void
    {
        $branch = Branch::firstOrFail();
        $reviewer = User::factory()->create(['branch_id' => $branch->id]);
        $reviewer->assignRole('Admin');
        $debit = $this->entry($branch->id);
        $credit = $debit->replicate();
        $credit->direction = 'credit';
        $credit->save();

        $this->actingAs($reviewer)->get('/journal/approvals')->assertOk()
            ->assertSee('Approve group')
            ->assertDontSee('Not eligible for approval');
    }

    public function test_group_with_soft_deleted_line_is_hidden_and_cannot_be_approved_or_rejected(): void
    {
        $branch = Branch::firstOrFail();
        $reviewer = User::factory()->create(['branch_id' => $branch->id]);
        $reviewer->assignRole('Admin');
        $debit = $this->entry($branch->id);
        $credit = $debit->replicate();
        $credit->direction = 'credit';
        $credit->save();
        $credit->delete();

        $this->actingAs($reviewer)->get('/journal/approvals')->assertOk()
            ->assertDontSee($debit->linked_id);
        $this->post('/journal/approvals/'.$debit->linked_id.'/approve')->assertStatus(422);
        $this->post('/journal/approvals/'.$debit->linked_id.'/reject', ['reason' => 'Incomplete'])->assertStatus(422);
        $this->assertSame('pending', $debit->fresh()->approval_status);
        $this->assertSame('pending', $credit->fresh()->approval_status);
    }

    public function test_other_branch_soft_deleted_journal_does_not_disclose_group_state(): void
    {
        $branch = Branch::firstOrFail();
        $other = Branch::whereKeyNot($branch->id)->firstOrFail();
        $reviewer = User::factory()->create(['branch_id' => $branch->id]);
        $reviewer->assignRole('Admin');
        $foreign = $this->entry($other->id);
        $foreign->delete();

        $this->actingAs($reviewer)->post('/journal/approvals/'.$foreign->linked_id.'/approve')->assertForbidden();
        $this->post('/journal/approvals/'.$foreign->linked_id.'/reject', ['reason' => 'Not mine'])->assertForbidden();
        $this->assertSame('pending', $foreign->fresh()->approval_status);
    }

    public function test_approval_rolls_back_when_one_journal_line_belongs_to_closed_business_day(): void
    {
        $branch = Branch::firstOrFail();
        $reviewer = User::factory()->create(['branch_id' => $branch->id]);
        $reviewer->assignRole('Admin');
        $debit = $this->entry($branch->id);
        $credit = $debit->replicate();
        $credit->direction = 'credit';
        $credit->date = now('Asia/Kabul')->subDay()->toDateString();
        $credit->save();
        \App\Models\BusinessDay::create([
            'branch_id' => $branch->id,
            'business_date' => $credit->date->toDateString(),
            'status' => 'closed',
        ]);

        $this->actingAs($reviewer)->post('/journal/approvals/'.$debit->linked_id.'/approve')
            ->assertSessionHasErrors('business_day');
        $this->assertSame('pending', $debit->fresh()->approval_status);
        $this->assertSame('pending', $credit->fresh()->approval_status);
        $this->assertNull($debit->fresh()->approved_by);
        $this->assertNull($credit->fresh()->approved_by);
    }

    public function test_rejection_rolls_back_when_one_journal_line_belongs_to_closed_business_day(): void
    {
        $branch = Branch::firstOrFail();
        $reviewer = User::factory()->create(['branch_id' => $branch->id]);
        $reviewer->assignRole('Admin');
        $debit = $this->entry($branch->id);
        $credit = $debit->replicate();
        $credit->direction = 'credit';
        $credit->date = now('Asia/Kabul')->subDay()->toDateString();
        $credit->save();
        \App\Models\BusinessDay::create([
            'branch_id' => $branch->id,
            'business_date' => $credit->date->toDateString(),
            'status' => 'closed',
        ]);

        $this->actingAs($reviewer)->post('/journal/approvals/'.$debit->linked_id.'/reject', ['reason' => 'Incorrect entry'])
            ->assertSessionHasErrors('business_day');
        $this->assertSame('pending', $debit->fresh()->approval_status);
        $this->assertSame('pending', $credit->fresh()->approval_status);
        $this->assertNull($debit->fresh()->rejection_reason);
        $this->assertNull($credit->fresh()->rejection_reason);
    }

    public function test_three_line_approval_rolls_back_after_earlier_lines_are_updated(): void
    {
        $branch = Branch::firstOrFail();
        $reviewer = User::factory()->create(['branch_id' => $branch->id]);
        $reviewer->assignRole('Admin');
        $debit = $this->entry($branch->id);
        $debit->update(['amount' => 150, 'amount_afn' => 150]);
        $credit = $debit->replicate();
        $credit->direction = 'credit';
        $credit->amount = 100;
        $credit->amount_afn = 100;
        $credit->save();
        $closedLine = $credit->replicate();
        $closedLine->amount = 50;
        $closedLine->amount_afn = 50;
        $closedLine->date = now('Asia/Kabul')->subDay()->toDateString();
        $closedLine->save();
        \App\Models\BusinessDay::create([
            'branch_id' => $branch->id,
            'business_date' => $closedLine->date->toDateString(),
            'status' => 'closed',
        ]);

        $this->actingAs($reviewer)->post('/journal/approvals/'.$debit->linked_id.'/approve')
            ->assertSessionHasErrors('business_day');
        foreach ([$debit, $credit, $closedLine] as $line) {
            $this->assertSame('pending', $line->fresh()->approval_status);
            $this->assertFalse((bool) $line->fresh()->is_approved);
            $this->assertNull($line->fresh()->approved_by);
        }
    }

    public function test_three_line_rejection_rolls_back_after_earlier_lines_are_updated(): void
    {
        $branch = Branch::firstOrFail();
        $reviewer = User::factory()->create(['branch_id' => $branch->id]);
        $reviewer->assignRole('Admin');
        $debit = $this->entry($branch->id);
        $debit->update(['amount' => 150, 'amount_afn' => 150]);
        $credit = $debit->replicate();
        $credit->direction = 'credit';
        $credit->amount = 100;
        $credit->amount_afn = 100;
        $credit->save();
        $closedLine = $credit->replicate();
        $closedLine->amount = 50;
        $closedLine->amount_afn = 50;
        $closedLine->date = now('Asia/Kabul')->subDay()->toDateString();
        $closedLine->save();
        \App\Models\BusinessDay::create([
            'branch_id' => $branch->id,
            'business_date' => $closedLine->date->toDateString(),
            'status' => 'closed',
        ]);

        $this->actingAs($reviewer)->post('/journal/approvals/'.$debit->linked_id.'/reject', ['reason' => 'Incorrect entry'])
            ->assertSessionHasErrors('business_day');
        foreach ([$debit, $credit, $closedLine] as $line) {
            $this->assertSame('pending', $line->fresh()->approval_status);
            $this->assertFalse((bool) $line->fresh()->is_approved);
            $this->assertNull($line->fresh()->rejection_reason);
        }
    }

    public function test_closed_day_journal_group_shows_no_review_actions(): void
    {
        $branch = Branch::firstOrFail();
        $reviewer = User::factory()->create(['branch_id' => $branch->id]);
        $reviewer->assignRole('Admin');
        $debit = $this->entry($branch->id);
        $credit = $debit->replicate();
        $credit->direction = 'credit';
        $credit->save();
        \App\Models\BusinessDay::create([
            'branch_id' => $branch->id,
            'business_date' => $debit->date->toDateString(),
            'status' => 'closed',
        ]);

        $this->actingAs($reviewer)->get('/journal/approvals')->assertOk()
            ->assertSee($debit->linked_id)
            ->assertSee('Contains a closed business day')
            ->assertDontSee('Approve group')
            ->assertDontSee('Rejection reason');
    }

    public function test_mixed_date_closed_group_does_not_hide_actions_for_unrelated_open_group(): void
    {
        $branch = Branch::firstOrFail();
        $reviewer = User::factory()->create(['branch_id' => $branch->id]);
        $reviewer->assignRole('Admin');

        $closedDebit = $this->entry($branch->id);
        $closedCredit = $closedDebit->replicate();
        $closedCredit->direction = 'credit';
        $closedCredit->date = now('Asia/Kabul')->subDay()->toDateString();
        $closedCredit->save();
        \App\Models\BusinessDay::create([
            'branch_id' => $branch->id,
            'business_date' => $closedCredit->date->toDateString(),
            'status' => 'closed',
        ]);

        $openDebit = $this->entry($branch->id);
        $openCredit = $openDebit->replicate();
        $openCredit->direction = 'credit';
        $openCredit->save();

        $response = $this->actingAs($reviewer)->get('/journal/approvals')->assertOk();
        $response->assertSee($closedDebit->linked_id)
            ->assertSee($openDebit->linked_id)
            ->assertSee('Contains a closed business day');
        $sections = explode('<div class="border rounded p-3 mb-3">', $response->getContent());
        $closedSection = collect($sections)->first(fn ($section) => str_contains($section, $closedDebit->linked_id));
        $openSection = collect($sections)->first(fn ($section) => str_contains($section, $openDebit->linked_id));
        $this->assertNotNull($closedSection);
        $this->assertNotNull($openSection);
        $this->assertStringNotContainsString('Approve group', $closedSection);
        $this->assertStringNotContainsString('Rejection reason', $closedSection);
        $this->assertStringContainsString('Approve group', $openSection);
        $this->assertStringContainsString('Rejection reason', $openSection);
    }

    public function test_legacy_fx_close_hides_journal_review_actions_without_business_day_row(): void
    {
        $branch = Branch::firstOrFail();
        $reviewer = User::factory()->create(['branch_id' => $branch->id]);
        $reviewer->assignRole('Admin');
        $debit = $this->entry($branch->id);
        $credit = $debit->replicate();
        $credit->direction = 'credit';
        $credit->save();
        \App\Models\FxRate::create([
            'branch_id' => $branch->id,
            'as_of' => $debit->date->toDateString(),
            'type' => 'close',
            'currency_id' => Currency::where('code', 'AFN')->value('id'),
            'rate_afn_per_unit' => 1,
        ]);

        $this->actingAs($reviewer)->get('/journal/approvals')->assertOk()
            ->assertSee($debit->linked_id)
            ->assertSee('Contains a closed business day')
            ->assertDontSee('Approve group')
            ->assertDontSee('Rejection reason');
    }

    public function test_legacy_fx_close_only_disables_review_for_its_own_branch(): void
    {
        $branch = Branch::firstOrFail();
        $other = Branch::whereKeyNot($branch->id)->firstOrFail();
        $reviewer = User::factory()->create(['branch_id' => $branch->id]);
        $reviewer->assignRole('Admin');

        $debit = $this->entry($branch->id);
        $credit = $debit->replicate();
        $credit->direction = 'credit';
        $credit->save();
        \App\Models\FxRate::create([
            'branch_id' => $other->id,
            'as_of' => $debit->date->toDateString(),
            'type' => 'close',
            'currency_id' => Currency::where('code', 'AFN')->value('id'),
            'rate_afn_per_unit' => 1,
        ]);

        $this->actingAs($reviewer)->get('/journal/approvals')->assertOk()
            ->assertSee($debit->linked_id)
            ->assertDontSee('Contains a closed business day')
            ->assertSee('Approve group')
            ->assertSee('Rejection reason');
    }

    public function test_super_admin_cannot_review_mixed_branch_group_with_closed_day(): void
    {
        $branch = Branch::firstOrFail();
        $other = Branch::whereKeyNot($branch->id)->firstOrFail();
        $superAdmin = User::factory()->create(['branch_id' => null]);
        $superAdmin->assignRole('Super Admin');
        $debit = $this->entry($branch->id);
        $credit = $debit->replicate();
        $credit->branch_id = $other->id;
        $credit->direction = 'credit';
        $credit->save();
        \App\Models\BusinessDay::create([
            'branch_id' => $other->id,
            'business_date' => $credit->date->toDateString(),
            'status' => 'closed',
        ]);

        $this->actingAs($superAdmin)->get('/journal/approvals')->assertOk()
            ->assertSee($debit->linked_id)
            ->assertSee('Contains a closed business day')
            ->assertDontSee('Approve group')
            ->assertDontSee('Rejection reason');

        $this->post('/journal/approvals/'.$debit->linked_id.'/approve')
            ->assertSessionHasErrors('business_day');
        $this->post('/journal/approvals/'.$debit->linked_id.'/reject', ['reason' => 'Invalid journal'])
            ->assertSessionHasErrors('business_day');
        $this->assertSame('pending', $debit->fresh()->approval_status);
        $this->assertSame('pending', $credit->fresh()->approval_status);
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
