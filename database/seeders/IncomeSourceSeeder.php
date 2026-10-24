<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class IncomeSourceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $income_sources = [
            ['name_en' => 'Salary',         'name_fa' => 'معاش',             'name_ps' => 'معاش', 'level' => 1],
            ['name_en' => 'Business',       'name_fa' => 'کسب و کار',         'name_ps' => 'سوداګري', 'level' => 3],
            ['name_en' => 'Investment',     'name_fa' => 'سرمایه‌گذاری',      'name_ps' => 'سرمایه‌ګذاري', 'level' => 2],
            ['name_en' => 'Other',          'name_fa' => 'سایر',              'name_ps' => 'نور عواید', 'level' => 3],
            ['name_en' => 'Rent',           'name_fa' => 'کرایه',            'name_ps' => 'کرایه', 'level' => 2],
            ['name_en' => 'Real Estate',    'name_fa' => 'ملکیت',             'name_ps' => 'ملکيت', 'level' => 3],
            ['name_en' => 'Accountant',     'name_fa' => 'حسابدار',           'name_ps' => 'حسابدار', 'level' => 3],
            ['name_en' => 'Lawyer',         'name_fa' => 'وکیل',              'name_ps' => 'وکیل', 'level' => 3],
            ['name_en' => 'Trading',        'name_fa' => 'تجارت',            'name_ps' => 'تجارت', 'level' => 3],
            ['name_en' => 'Manufacturing',  'name_fa' => 'تولیدات',           'name_ps' => 'تولیدات', 'level' => 3],
        ];

        foreach ($income_sources as $income_source) {
            \App\Models\IncomeSource::create($income_source);
        }
    }
}
