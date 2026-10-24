<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AccountSubCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['Capital', 'سرمایه', 'سرمایه', 'safe'],
            ['Assets', 'دارایی', 'شتمني', 'safe'],
            ['Liabilities', 'بدهی', 'پور', 'safe'],
            ['Expenses', 'مصارف', 'مصارف', 'safe'],
            ['Exchange Reserve', 'ذخیره تبادله', 'د تبادلې ذخیره', 'safe'],
            ['Remittance', 'حواله', 'حواله', 'safe'],
            ['Regular Customer', 'مشتری معمولی', 'معمولي مشتری', 'customer'],
            ['Corporate Customer', 'مشتری حقوقی', 'حقوقي مشتری', 'customer'],
            ['Temporary Customer', 'مشتری موقتی', 'موقتي مشتری', 'customer'],
            ['Domestic Bank', 'بانک داخلی', 'کورنی بانک', 'bank'],
            ['International Bank', 'بانک خارجی', 'بهرنی بانک', 'bank'],
            ['Branch Account', 'حساب شعبه', 'د څانګې حساب', 'branch'],
            ['Street Vendor', 'دست فروش', 'دست فروش', 'customer'],
        ];

        foreach ($categories as [$nameEn, $nameFa, $namePs, $type]) {
            DB::table('account_sub_categories')->updateOrInsert(
                ['name_en' => $nameEn, 'type' => $type],
                [
                    'name_fa' => $nameFa,
                    'name_ps' => $namePs,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $branch = Branch::first();
        $user = User::first();
        $branchCategoryId = DB::table('account_sub_categories')
            ->where('name_en', 'Branch Account')
            ->where('type', 'branch')
            ->value('id');

        if ($branch && $branchCategoryId) {
            Account::firstOrCreate(
                ['account_code' => 'BRANCH-' . sprintf('%03d', $branch->id)],
                [
                    'full_name' => $branch->name,
                    'email' => $user?->email,
                    'phone' => $branch->phone,
                    'address' => $branch->address,
                    'branch_id' => $branch->id,
                    'account_sub_category_id' => $branchCategoryId,
                    'type' => 'branch',
                ]
            );
        }
    }
}
