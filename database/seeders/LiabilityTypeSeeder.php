<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LiabilityTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $liability_types = [
            ['name_en' => 'Account Payable',     'name_fa' => 'بل قابل پرداخت',    'name_ps' => 'د تادیې حسابونه'],
            ['name_en' => 'Tax Payable',         'name_fa' => 'مالیه قابل پرداخت',    'name_ps' => 'د مالیې تادیه'],
            ['name_en' => 'Pre Sale Contract',   'name_fa' => 'قرارداد پیش فروش', 'name_ps' => 'د مخکې پلور قرارداد'],
        ];

        foreach ($liability_types as $liability_type) {
            \App\Models\LiabilityType::create($liability_type);
        }
    }
}
