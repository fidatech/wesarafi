<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\Remittance;
use App\Models\Transaction;
use App\Models\User;
use App\Services\DabStatementService;
use App\Services\FinancialReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RemittanceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Branch $main;

    private Branch $turkey;

    private Branch $mazar;

    private User $mainUser;

    private User $mazarUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->travelTo(now()->setDate(2026, 9, 14));
        $this->main = Branch::where('is_main', true)->firstOrFail();
        $this->mainUser = User::where('branch_id', $this->main->id)->firstOrFail();
        $this->turkey = Branch::create(['name' => 'Turkey', 'type' => 'agent']);
        $this->mazar = Branch::create(['name' => 'Mazar', 'type' => 'dependent']);
        $this->mazarUser = User::factory()->create(['branch_id' => $this->mazar->id, 'status' => true]);
        $this->actingAs($this->mainUser);
    }

    private function payload(array $overrides = []): array
    {
        return array_replace(['direction' => 'transfer', 'routing_mode' => 'via_main', 'from_branch_id' => $this->turkey->id, 'to_branch_id' => $this->mazar->id,
            'sender_name' => 'Turkey Sender', 'sender_phone' => '0701111111', 'receiver_name' => 'Mazar Receiver', 'currency_id' => Currency::where('code', 'AFN')->firstOrFail()->id,
            'amount' => 10000, 'sender_branch_commission' => 0, 'bridge_commission' => 100, 'receiver_branch_commission' => 100, 'collection_method' => 'cash', 'date' => '2026-09-14'], $overrides);
    }

    private function store(array $overrides = []): Remittance
    {
        $this->post('/remittances', $this->payload($overrides))->assertRedirect();

        return Remittance::latest('id')->firstOrFail();
    }

    private function net(int $branch, string $code): float
    {
        $q = Transaction::where('branch_id', $branch)->whereHas('chartOfAccount', fn ($q) => $q->where('code', $code))->get();

        return $q->where('direction', 'debit')->sum('amount') - $q->where('direction', 'credit')->sum('amount');
    }

    public function test_interbranch_audit_detects_missing_settlement_journal_without_mutating_debt(): void
    {
        $r = $this->store();
        $this->post('/remittances/'.$r->id.'/approve')->assertRedirect();
        $leg = $r->legs()->firstOrFail();
        $clean = app(\App\Services\InterbranchSettlementAudit::class)->inspect($leg->debtor_branch_id);
        $this->assertSame(0, $clean['issue_count']);
        $position = collect($clean['positions'])->first(fn ($row) => $row['debtor_branch_id'] === (int) $leg->debtor_branch_id && $row['creditor_branch_id'] === (int) $leg->creditor_branch_id);
        $this->assertNotNull($position);
        $this->assertEqualsWithDelta((float) $leg->amount, $position['outstanding'], 0.0001);
        $leg->settlements()->create(['amount' => 10, 'date' => '2026-09-14', 'exchange_rate_afn' => 1, 'created_by' => $this->mainUser->id]);
        $result = app(\App\Services\InterbranchSettlementAudit::class)->inspect($leg->debtor_branch_id);
        $this->assertContains('missing_settlement_journal', array_column($result['issues'], 'type'));
        $this->assertEquals(10, $leg->settlements()->sum('amount'));
    }

    public function test_interbranch_audit_detects_unbalanced_remittance_journal(): void
    {
        $r = $this->store();
        $this->post('/remittances/'.$r->id.'/approve')->assertRedirect();
        $entry = \App\Models\Transaction::where('module_name', 'remittance')->where('module_id', $r->id)->firstOrFail();
        $entry->amount_afn = (float) $entry->amount_afn + 50;
        $entry->save();
        $result = app(\App\Services\InterbranchSettlementAudit::class)->inspect((int) $r->from_branch_id);
        $this->assertContains('unbalanced_journal', array_column($result['issues'], 'type'));
    }

    public function test_settlement_service_rejects_nonpositive_amount_without_posting(): void
    {
        $r = $this->store();
        $this->post('/remittances/'.$r->id.'/approve')->assertRedirect();
        $leg = $r->legs()->firstOrFail();
        $before = Transaction::count();
        try {
            app(\App\Services\RemittanceWorkflowService::class)->settle($leg, [
                'amount' => -10, 'date' => '2026-09-14', 'exchange_rate_afn' => 1,
            ]);
            $this->fail('Negative settlement must be rejected.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
        $this->assertSame($before, Transaction::count());
        $this->assertSame(0, $leg->settlements()->count());
    }

    public function test_repeated_remittance_approval_preserves_existing_journal_and_state(): void
    {
        $remittance = $this->store();
        $this->post('/remittances/'.$remittance->id.'/approve')->assertRedirect();
        $originalCount = Transaction::where('module_name', 'remittance')->where('module_id', $remittance->id)->count();
        $this->postJson('/remittances/'.$remittance->id.'/approve')->assertStatus(422);
        $this->assertSame('approved', $remittance->fresh()->status);
        $this->assertSame($originalCount, Transaction::where('module_name', 'remittance')->where('module_id', $remittance->id)->count());
        $this->assertCount(2, $remittance->legs);
    }

    public function test_bridge_debts_commission_settlement_and_receiver_payment(): void
    {
        $r = $this->store();
        $this->assertEquals(10200, $r->total_collected);
        $this->assertEquals($this->main->id, $r->bridge_branch_id);
        $this->assertDatabaseCount('transactions', 0);
        $createPage = $this->get('/remittances/create')->assertOk()->assertSee('Receiver gets')->assertDontSee('id="paymentModal"', false);
        if (!is_dir(base_path('tmp'))) mkdir(base_path('tmp'),0777,true);
        file_put_contents(base_path('tmp/remittance-create-test.html'), $createPage->getContent());
        $this->get('/remittances')->assertOk()->assertSee($r->reference)->assertDontSee('id="remittanceForm"', false);
        $this->post('/remittances/'.$r->id.'/approve')->assertRedirect();
        $this->assertEquals('approved', $r->fresh()->status);
        $this->assertCount(2, $r->legs);
        $this->assertEquals(10200, $this->net($this->turkey->id, '1000'));
        $this->assertEquals(-10200, $this->net($this->turkey->id, '2002'));
        $this->assertEquals(10200, $this->net($this->main->id, '1006'));
        $this->assertEquals(-10100, $this->net($this->main->id, '2002'));
        $this->assertEquals(-100, $this->net($this->main->id, '4001'));
        $this->assertEquals(10100, $this->net($this->mazar->id, '1006'));
        $this->assertEquals(-10000, $this->net($this->mazar->id, '2001'));
        $this->assertEquals(-100, $this->net($this->mazar->id, '4001'));
        foreach ([$this->turkey, $this->main, $this->mazar] as $branch) {
            $rows = Transaction::where('branch_id', $branch->id)->get();
            $this->assertEquals($rows->where('direction', 'debit')->sum('amount_afn'), $rows->where('direction', 'credit')->sum('amount_afn'));
            $s = app(DabStatementService::class)->balanceSheet(app(FinancialReportService::class)->balanceSheet('2026-09-14', $branch->id));
            $this->assertEquals(0, $s['difference']);
        }
        $count = Transaction::count();
        $this->postJson('/remittances/'.$r->id.'/approve')->assertStatus(422);
        $this->postJson('/remittances/'.$r->id.'/reject', ['reason' => 'test'])->assertStatus(422);
        $this->assertDatabaseCount('transactions', $count);
        $first = $r->legs->firstWhere('debtor_branch_id', $this->turkey->id);
        $second = $r->legs->firstWhere('debtor_branch_id', $this->main->id);
        $this->post('/remittances/'.$r->id.'/legs/'.$first->id.'/settle', ['amount' => 200, 'date' => '2026-09-14'])->assertRedirect();
        $this->assertEquals(10000, $first->outstanding);
        $this->postJson('/remittances/'.$r->id.'/legs/'.$first->id.'/settle', ['amount' => 10001, 'date' => '2026-09-14'])->assertStatus(422);
        $this->post('/remittances/'.$r->id.'/legs/'.$first->id.'/settle', ['amount' => 10000, 'date' => '2026-09-14'])->assertRedirect();
        $this->post('/remittances/'.$r->id.'/legs/'.$second->id.'/settle', ['amount' => 10100, 'date' => '2026-09-14'])->assertRedirect();
        $this->assertEquals(0, $first->outstanding);
        $this->assertEquals(0, $second->outstanding);
        $this->assertEquals(100, $this->net($this->main->id, '1000'));
        $this->assertEquals(0, $this->net($this->main->id, '1006'));
        $this->assertEquals(0, $this->net($this->main->id, '2002'));
        $this->postJson('/remittances/'.$r->id.'/complete', ['receiver_name' => 'Receiver'])->assertForbidden();
        $this->actingAs($this->mazarUser);
        $this->post('/remittances/'.$r->id.'/complete', ['receiver_name' => 'Verified Receiver'])->assertRedirect();
        $this->assertEquals(100, $this->net($this->mazar->id, '1000'));
        $this->assertEquals(0, $this->net($this->mazar->id, '2001'));
        $this->assertEquals('completed', $r->fresh()->status);
        $this->postJson('/remittances/'.$r->id.'/complete', ['receiver_name' => 'Again'])->assertStatus(422);
        $this->get('/remittances/'.$r->id)->assertOk()->assertSee('Settlement history');
        $this->get('/remittances/'.$r->id.'/slip')->assertOk()->assertSee('10,200.00');
        $pdf = $this->get('/remittances/'.$r->id.'/slip?pdf=1')->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        if (! is_dir(base_path('tmp/pdfs'))) {
            mkdir(base_path('tmp/pdfs'), 0777, true);
        }
        file_put_contents(base_path('tmp/pdfs/remittance-slip-test.pdf'), $pdf->getContent());
    }

    public function test_direct_transfer_and_auth_branch_rules_with_customer_autofill(): void
    {
        $turkeyUser = User::factory()->create(['branch_id' => $this->turkey->id, 'status' => true]);
        $sender = Account::create(['account_code' => 'TEST-SENDER', 'type' => 'customer', 'branch_id' => $this->turkey->id, 'full_name' => 'Authoritative Sender', 'phone' => '0791234567', 'father_name' => 'Father', 'id_card_number' => 'ID-123', 'is_active' => true]);
        $this->actingAs($turkeyUser);
        $r = $this->store(['routing_mode' => 'direct', 'bridge_commission' => 0, 'from_branch_id' => $this->main->id, 'sender_account_id' => $sender->id, 'sender_name' => 'Forged Name', 'sender_branch_commission' => 20, 'collection_method' => 'account']);
        $this->assertEquals($this->turkey->id, $r->from_branch_id);
        $this->assertNull($r->bridge_branch_id);
        $this->assertEquals('Authoritative Sender', $r->sender_name);
        $this->assertEquals('0791234567', $r->sender_phone);
        $this->getJson('/remittances/customers?branch_id='.$this->turkey->id.'&q=Authoritative')->assertOk()->assertJsonPath('results.0.tazkira', 'ID-123');
        $this->getJson('/remittances/customers?branch_id='.$this->mazar->id)->assertForbidden();
        $this->post('/remittances/'.$r->id.'/approve')->assertRedirect();
        $this->assertCount(1, $r->legs);
        $this->assertEquals(10120, $this->net($this->turkey->id, '1002'));
        $senderRows = Transaction::where('account_id', $sender->id)->get();
        $this->assertEquals(10120, $senderRows->where('direction', 'debit')->sum('amount') - $senderRows->where('direction', 'credit')->sum('amount'));
        $this->assertEquals(0, $this->net($this->turkey->id, '1000'));
        $out = $this->store(['direction' => 'outgoing', 'routing_mode' => 'direct', 'bridge_commission' => 0, 'from_branch_id' => $this->mazar->id, 'to_branch_id' => $this->main->id]);
        $this->assertEquals($this->turkey->id, $out->from_branch_id);
        $this->actingAs($this->mazarUser);
        $incoming = $this->store(['direction' => 'incoming', 'to_branch_id' => $this->main->id]);
        $this->assertEquals($this->mazar->id, $incoming->to_branch_id);
        $this->post('/remittances/'.$incoming->id.'/approve')->assertRedirect();
        $other = User::factory()->create(['branch_id' => Branch::create(['name' => 'Unrelated', 'type' => 'agent'])->id]);
        $this->actingAs($other);
        $this->get('/remittances/'.$r->id)->assertNotFound();
        $this->post('/remittances/'.$r->id.'/approve')->assertNotFound();
        $this->get('/remittances/'.$r->id.'/slip')->assertNotFound();
        $this->get('/remittances')->assertOk()->assertDontSee($r->reference);
    }

    public function test_commission_variants_and_direct_reverse_route(): void
    {
        foreach ([[100, 0], [0, 100], [0, 0]] as [$mainFee,$destFee]) {
            $r = $this->store(['bridge_commission' => $mainFee, 'receiver_branch_commission' => $destFee, 'sender_branch_commission' => 20]);
            $this->post('/remittances/'.$r->id.'/approve')->assertRedirect();
            $rows = Transaction::where('module_name', 'remittance')->where('module_id', $r->id)->whereHas('chartOfAccount', fn ($q) => $q->where('code', '4001'))->get();
            $this->assertEquals($mainFee, $rows->where('branch_id', $this->main->id)->sum('amount'));
            $this->assertEquals($destFee, $rows->where('branch_id', $this->mazar->id)->sum('amount'));
            $this->assertEquals(20, $rows->where('branch_id', $this->turkey->id)->sum('amount'));
            $this->assertEquals(10000 + $mainFee + $destFee, $r->legs()->where('debtor_branch_id', $this->turkey->id)->value('amount'));
        }
        User::factory()->create(['branch_id' => $this->turkey->id, 'status' => true]);
        $this->actingAs($this->mazarUser);
        $r = $this->store(['routing_mode' => 'direct', 'bridge_commission' => 0, 'to_branch_id' => $this->turkey->id]);
        $this->assertEquals($this->mazar->id, $r->from_branch_id);
        $this->post('/remittances/'.$r->id.'/approve')->assertRedirect();
        $this->assertEquals($this->turkey->id, $r->legs()->first()->creditor_branch_id);
        $this->turkey->update(['allows_direct_remittance' => false]);
        $this->postJson('/remittances', $this->payload(['routing_mode' => 'direct', 'bridge_commission' => 0, 'to_branch_id' => $this->turkey->id]))->assertUnprocessable()->assertJsonValidationErrors('routing_mode');
    }

    public function test_offline_branch_creation_and_authoritative_receiver_details(): void
    {
        $this->mainUser->assignRole('Admin');
        $this->postJson('/configuration/branches', ['name' => 'Offline Branch', 'type' => 'agent', 'allows_direct_remittance' => false])->assertOk();
        $branch = Branch::where('name', 'Offline Branch')->firstOrFail();
        $this->assertFalse($branch->hasRemittanceAccess());
        $this->assertNotNull($branch->treasuryAccount);
        $offline = $this->store(['to_branch_id' => $branch->id]);
        $this->post('/remittances/'.$offline->id.'/approve')->assertRedirect();
        $this->post('/remittances/'.$offline->id.'/complete', ['receiver_name' => 'Offline verified receiver'])->assertRedirect();
        $this->assertEquals('completed', $offline->fresh()->status);
        $this->assertDatabaseHas('accounts', ['branch_id' => $branch->id, 'type' => 'branch']);
        $receiver = Account::create(['account_code' => 'RECEIVER-TEST', 'full_name' => 'Existing Receiver', 'father_name' => 'Existing Father', 'phone' => '0705555555', 'type' => 'customer', 'branch_id' => $this->mazar->id, 'is_active' => true]);
        $r = $this->store(['receiver_account_id' => $receiver->id, 'receiver_name' => 'Forged receiver']);
        $this->assertSame('Existing Receiver', $r->receiver_name);
        $this->assertSame('Existing Father', $r->receiver_father_name);
        $this->postJson('/remittances', $this->payload(['sender_account_id' => $receiver->id]))->assertUnprocessable()->assertJsonValidationErrors('sender_account_id');
        $this->post('/remittances/'.$r->id.'/approve')->assertRedirect();
        $this->actingAs($this->mazarUser);
        $this->post('/remittances/'.$r->id.'/complete', ['receiver_name' => $receiver->full_name])->assertRedirect();
        $this->assertEquals(0, Transaction::where('account_id', $receiver->id)->whereHas('chartOfAccount', fn ($q) => $q->where('code', '2001'))->get()->sum(fn ($t) => $t->direction === 'credit' ? (float) $t->amount : -(float) $t->amount));
    }

    public function test_validation_rejection_and_atomic_posting(): void
    {
        $this->actingAs($this->mazarUser);
        $this->postJson('/remittances', $this->payload(['direction' => 'incoming', 'routing_mode' => 'direct', 'bridge_commission' => 0]))->assertStatus(422)->assertJsonValidationErrors('routing_mode');
        $this->actingAs($this->mainUser);
        $this->postJson('/remittances', $this->payload(['amount' => 0]))->assertStatus(422);
        $this->postJson('/remittances', $this->payload(['currency_id' => Currency::where('code', 'USD')->first()->id]))->assertStatus(422)->assertJsonValidationErrors('exchange_rate_afn');
        $r = $this->store(['currency_id' => Currency::where('code', 'USD')->first()->id, 'exchange_rate_afn' => 70, 'amount' => 100, 'bridge_commission' => 1, 'receiver_branch_commission' => 2]);
        $this->assertEquals(7000, $r->amount_afn);
        // A failure during posting rolls back legs, all journal lines, and status.
        Transaction::creating(function ($t) {
            if ($t->branch_id == $this->mazar->id) {
                throw new \RuntimeException('Simulated posting failure');
            }
        });
        $this->postJson('/remittances/'.$r->id.'/approve')->assertStatus(500);
        $this->assertEquals('pending', $r->fresh()->status);
        $this->assertDatabaseCount('remittance_legs', 0);
        $this->assertDatabaseCount('transactions', 0);
        Transaction::flushEventListeners();
        Transaction::clearBootedModels();
        $this->post('/remittances/'.$r->id.'/reject', ['reason' => 'Sender withdrew request'])->assertRedirect();
        $this->assertEquals('rejected', $r->fresh()->status);
        $this->postJson('/remittances/'.$r->id.'/approve')->assertStatus(422);
    }
}
