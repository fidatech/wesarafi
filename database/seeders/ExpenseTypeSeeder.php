<?php

namespace Database\Seeders;

use App\Models\ExpenseType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ExpenseTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $expense_types = [
            ['name_en' => 'Administrative', 'name_fa' => 'اداری', 'name_ps' => 'اداری'],
            ['name_en' => 'Consumption', 'name_fa' => 'استهلاک', 'name_ps' => 'استهلاک'],
            ['name_en' => 'Telecommunication', 'name_fa' => 'ارتباطی', 'name_ps' => 'ارتباطی'],
            ['name_en' => 'Rent', 'name_fa' => 'کرایه', 'name_ps' => 'کرایه'],
            ['name_en' => 'Internet', 'name_fa' => 'اینترنت', 'name_ps' => 'اینترنت'],
            ['name_en' => 'Electricity', 'name_fa' => 'برق', 'name_ps' => 'برق'],
            ['name_en' => 'Insurance', 'name_fa' => 'بیمه', 'name_ps' => 'بیمه'],
            ['name_en' => 'Salary', 'name_fa' => ' معاش', 'name_ps' => 'معاش'],
            ['name_en' => 'Other', 'name_fa' => 'دیگر', 'name_ps' => 'نور'],
        ];

        foreach ($expense_types as $expense_type) {
            ExpenseType::create($expense_type);
        }
    }
}
