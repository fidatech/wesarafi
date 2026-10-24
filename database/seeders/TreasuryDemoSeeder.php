<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\AccountSubCategory;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Database\Seeder;

class TreasuryDemoSeeder extends Seeder
{
    public function run(): void
    {
        Currency::query()->update(['is_default' => false]);
        Currency::updateOrCreate(['code' => 'AFN'], [
            'name' => 'Afghani', 'symbol' => '؋', 'country' => 'Afghanistan',
            'flag' => '/assets/flags/af.svg', 'exchange_rate' => 1, 'is_default' => true, 'is_active' => true,
        ]);
        Currency::updateOrCreate(['code' => 'USD'], [
            'name' => 'US Dollar', 'symbol' => '$', 'country' => 'United States',
            // Demo rate only; configure the operational rate before posting USD transactions.
            'flag' => '/assets/flags/us.svg', 'exchange_rate' => 70, 'is_default' => false, 'is_active' => true,
        ]);
        $main = Branch::where('name', 'Main Branch')->firstOrFail();
        // Legacy feature tests require a two-branch fixture. Never seed sample
        // customers or an automatic login into a real installation.
        $testing = app()->runningUnitTests();
        $branches = [$main];
        if ($testing) {
            $branches[] = Branch::firstOrCreate(['name' => 'Kabul Branch'], [
                'province_id' => $main->province_id, 'type' => 'dependent', 'address' => 'Kabul',
            ]);
            if (!User::query()->exists()) {
                User::factory()->create(['branch_id' => $main->id, 'status' => true]);
            }
        }
        foreach ($branches as $branch) {
            $branch->ensureTreasuryAccount();
            Account::firstOrCreate(['account_code' => sprintf('BRANCH-%03d', $branch->id)], [
                'full_name' => $branch->name, 'branch_id' => $branch->id, 'type' => 'branch',
                'account_sub_category_id' => AccountSubCategory::where('name_en', 'Branch Account')->value('id'),
                'is_active' => true,
            ]);
            if ($testing) {
                foreach ([
                    ['REG', 'Regular Customer', 'customer', 'Ahmad Sample'],
                    ['REG2', 'Regular Customer', 'customer', 'Farid Sample'],
                    ['CORP', 'Corporate Customer', 'customer', 'Sample Trading Company'],
                    ['BANK', 'Domestic Bank', 'bank', 'Sample Bank'],
                    ['VENDOR', 'Street Vendor', 'customer', 'Sample Street Vendor'],
                ] as [$code, $category, $type, $name]) {
                    Account::updateOrCreate(['account_code' => $code.'-'.$branch->id], [
                        'full_name' => $name.' — '.$branch->name, 'type' => $type,
                        'branch_id' => $branch->id,
                        'account_sub_category_id' => AccountSubCategory::where('name_en', $category)->value('id'),
                        'is_active' => true, 'is_sanction_list_applied' => true, 'risk_level' => 'low',
                        'business_name' => $code === 'CORP' ? 'Sample Trading Company' : null,
                    ]);
                }
            }
        }
    }
}
