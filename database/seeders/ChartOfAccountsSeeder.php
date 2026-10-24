<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ChartOfAccountsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $accounts = [
            // ASSETS
            ['1000', 'Assets', 'Current Assets', 'Cash on Hand', 'asset'],
            ['1001', 'Assets', 'Current Assets', 'Cash in Bank', 'asset'],
            ['1002', 'Assets', 'Current Assets', 'Accounts Receivable', 'asset'],
            ['1003', 'Assets', 'Current Assets', 'Prepaid Expenses', 'asset'],
            ['1004', 'Assets', 'Current Assets', 'Short-Term Investments', 'asset'],
            ['1005', 'Assets', 'Current Assets', 'Inventory', 'asset'],
            ['1006', 'Assets', 'Current Assets', 'Due from Branches', 'asset'],
            ['1007', 'Assets', 'Current Assets', 'Input Tax Receivable', 'asset'],
            ['1008', 'Assets', 'Current Assets', 'Claims on Da Afghanistan Bank', 'asset'],
            ['1009', 'Assets', 'Current Assets', 'Other Current Assets', 'asset'],

            // Group total placeholder codes for fixed assets
            ['1100', 'Assets', 'Fixed Assets', 'Tangible Fixed Assets', 'asset'],
            ['1199', 'Assets', 'Fixed Assets', 'Accumulated Depreciation', 'asset'],
            ['1200', 'Assets', 'Intangible Assets', 'Intangible Assets', 'asset'],
            ['1299', 'Assets', 'Intangible Assets', 'Accumulated Amortization', 'asset'],

            ['1010', 'Assets', 'Fixed Assets', 'Furniture and Fixtures', 'asset'],
            ['1011', 'Assets', 'Fixed Assets', 'Office Equipment', 'asset'],
            ['1012', 'Assets', 'Fixed Assets', 'Vehicles', 'asset'],
            ['1013', 'Assets', 'Fixed Assets', 'Buildings', 'asset'],
            ['1014', 'Assets', 'Fixed Assets', 'Land', 'asset'],
            ['1015', 'Assets', 'Fixed Assets', 'Other Tangible Assets', 'asset'],
            ['1020', 'Assets', 'Intangible Assets', 'Software Licenses', 'asset'],
            ['1021', 'Assets', 'Intangible Assets', 'Patents', 'asset'],
            ['1022', 'Assets', 'Intangible Assets', 'Goodwill', 'asset'],

            // LIABILITIES
            ['2000', 'Liabilities', 'Current Liabilities', 'Accounts Payable', 'liability'],
            ['2001', 'Liabilities', 'Current Liabilities', 'Customer Amanat (Payable)', 'liability'],
            ['2002', 'Liabilities', 'Current Liabilities', 'Due to Branches', 'liability'],
            ['2003', 'Liabilities', 'Current Liabilities', 'Accrued Expenses', 'liability'],
            ['2004', 'Liabilities', 'Current Liabilities', 'Tax Payable', 'liability'],
            ['2005', 'Liabilities', 'Current Liabilities', 'Deferred Revenue', 'liability'],
            ['2100', 'Liabilities', 'Long-Term Liabilities', 'Loans Payable', 'liability'],
            ['2101', 'Liabilities', 'Long-Term Liabilities', 'Bonds Payable', 'liability'],
            ['2102', 'Liabilities', 'Long-Term Liabilities', 'Deferred Tax Liability', 'liability'],

            // EQUITY
            ['3000', 'Equity', "Owner's Equity", 'Owner Capital', 'equity'],
            ['3001', 'Equity', "Owner's Equity", 'Retained Earnings', 'equity'],
            ['3002', 'Equity', "Owner's Equity", 'Current Year Profit/Loss', 'equity'], // reused
            ['3003', 'Equity', "Owner's Equity", 'Share Capital', 'equity'],
            ['3004', 'Equity', "Owner's Equity", 'Revaluation Reserve', 'equity'],

            // INCOME
            ['4000', 'Income', 'Operating Income', 'Exchange Commission Income', 'income'],
            ['4001', 'Income', 'Operating Income', 'Remittance Income', 'income'],
            ['4002', 'Income', 'Operating Income', 'Service Fees', 'income'],
            ['4003', 'Income', 'Operating Income', 'Interest Income', 'income'],
            ['4004', 'Income', 'Operating Income', 'Sales Revenue', 'income'],
            ['4005', 'Income', 'Other Income', 'Foreign Exchange Gain', 'income'],
            ['4006', 'Income', 'Other Income', 'Asset Sale Gain', 'income'],
            ['4007', 'Income', 'Other Income', 'Investment Income', 'income'],

            // EXPENSES
            ['5000', 'Expenses', 'Operating Expenses', 'Salaries & Wages', 'expense'],
            ['5001', 'Expenses', 'Operating Expenses', 'Office Rent', 'expense'],
            ['5002', 'Expenses', 'Operating Expenses', 'Utilities', 'expense'],
            ['5003', 'Expenses', 'Operating Expenses', 'Communication Expense', 'expense'],
            ['5004', 'Expenses', 'Operating Expenses', 'Marketing Expense', 'expense'],
            ['5005', 'Expenses', 'Operating Expenses', 'Insurance Expense', 'expense'],
            ['5006', 'Expenses', 'Operating Expenses', 'Maintenance & Repairs', 'expense'],
            ['5007', 'Expenses', 'Operating Expenses', 'Bank Charges', 'expense'],
            ['5008', 'Expenses', 'Operating Expenses', 'Software & Licensing', 'expense'],
            ['5009', 'Expenses', 'Operating Expenses', 'Legal and Professional Fees', 'expense'],
            ['5010', 'Expenses', 'Payroll Expenses', 'Employee Bonus', 'expense'],
            ['5011', 'Expenses', 'Payroll Expenses', 'Provident Fund Employer Share', 'expense'],
            ['5012', 'Expenses', 'Payroll Expenses', 'Payroll Tax', 'expense'],
            ['5013', 'Expenses', 'Other Expenses', 'Depreciation', 'expense'],
            ['5014', 'Expenses', 'Other Expenses', 'Amortization', 'expense'],
            ['5015', 'Expenses', 'Tax Expenses', 'Income Tax', 'expense'],
            ['5016', 'Expenses', 'Other Expenses', 'Foreign Exchange Loss', 'expense'],
        ];

        foreach ($accounts as [$code, $category, $subcategory, $name, $type]) {
            DB::table('chart_of_accounts')->updateOrInsert(
                ['code' => $code],
                [
                    'category'     => $category,
                    'subcategory'  => $subcategory,
                    'account_name' => $name,
                    'type'         => $type,
                    'is_active'    => true,
                    'updated_at'   => now(),
                    'created_at'   => now(),
                ]
            );
        }
    }

}
