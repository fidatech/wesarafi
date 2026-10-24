<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ProvinceSeeder::class,
            RolesAndAdminSeeder::class,
            DesignationSeeder::class,
            ExpenseTypeSeeder::class,
            LiabilityTypeSeeder::class,
            IncomeSourceSeeder::class,
            NationalitySeeder::class,
            AccountSubCategorySeeder::class,
            ChartOfAccountsSeeder::class,
            TransactionTypeSeeder::class,
            TreasuryDemoSeeder::class,
        ]);
    }
}
