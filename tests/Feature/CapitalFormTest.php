<?php

namespace Tests\Feature;

use App\Models\{Account, Branch, Currency, Shareholder, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CapitalFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_capital_form_contains_branch_mapping_and_shareholder_modal(): void
    {
        $this->seed();
        $this->actingAs(User::first())->withoutMiddleware();
        $response = $this->get('/accounting/capital')->assertOk();
        foreach (Branch::all() as $branch) {
            $account = Account::where('branch_id', $branch->id)->where('type', 'branch')->firstOrFail();
            $response->assertSee('value="'.$account->id.'" data-branch-id="'.$branch->id.'"', false);
        }
        $response->assertSee('Add new shareholder')->assertSee('id="capitalShareholderModal"', false);
        if (!is_dir(base_path('tmp'))) mkdir(base_path('tmp'), 0777, true);
        preg_match_all('/<script\b[^>]*>(.*?)<\/script>/s', $response->getContent(), $scripts);
        file_put_contents(base_path('tmp/capital-scripts.js'), implode("\n", $scripts[1]));
    }

    public function test_shareholder_modal_endpoint_returns_created_option_and_validates_input(): void
    {
        $this->seed();
        $this->actingAs(User::first())->withoutMiddleware();
        $response = $this->postJson('/hr/shareholders', [
            'name' => 'New Capital Shareholder', 'father_name' => 'Father', 'phone' => '0700000000',
            'email' => 'shareholder@example.com', 'address' => 'Kabul', 'share_percentage' => 12.5,
            'joining_date' => '2026-09-13', 'national_id' => 'ID-123', 'is_active' => 0,
            'profile_picture' => \Illuminate\Http\UploadedFile::fake()->image('profile.png'),
        ]);
        $response->assertOk()->assertJsonPath('shareholder.name', 'New Capital Shareholder');
        $this->assertDatabaseHas('shareholders', ['id' => $response->json('shareholder.id'), 'name' => 'New Capital Shareholder']);
        $shareholder = Shareholder::findOrFail($response->json('shareholder.id'));
        $this->assertEquals(12.5, $response->json('shareholder.share_percentage'));
        $this->assertFalse($shareholder->is_active);
        $this->assertSame('ID-123', $shareholder->national_id);
        $this->assertFileExists(public_path($shareholder->profile_picture));
        unlink(public_path($shareholder->profile_picture));
        $this->postJson('/hr/shareholders', ['email' => 'invalid'])->assertUnprocessable()->assertJsonValidationErrors(['name', 'email']);
    }

    public function test_capital_rejects_an_account_from_another_branch(): void
    {
        $this->seed();
        $this->actingAs(User::first())->withoutMiddleware();
        $shareholder = Shareholder::create(['name' => 'Test Shareholder']);
        $branches = Branch::orderBy('id')->get();
        $account = Account::where('type', 'branch')->where('branch_id', $branches[1]->id)->firstOrFail();
        $this->postJson('/accounting/capital', [
            'shareholder_id' => $shareholder->id, 'branch_id' => $branches[0]->id,
            'account_id' => $account->id, 'currency_id' => Currency::first()->id,
            'amount' => 1000, 'share_percentage' => 10, 'date' => '2026-09-13',
        ])->assertUnprocessable()->assertJsonValidationErrors('account_id');
        $this->assertDatabaseCount('capitals', 0);
    }
}
