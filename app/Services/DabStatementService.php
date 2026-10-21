<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DabStatementService
{
    public function balanceSheet(array $report): array
    {
        $assets = $this->flatten($report['assets'] ?? []);
        $liabilities = $this->flatten($report['liabilities'] ?? []);
        $equity = $this->flatten($report['equity'] ?? []);

        $currentAssets = [
            'cash' => $this->codes($assets, ['1000']),
            'cash_bank' => $this->codes($assets, ['1001']),
            'receivables' => $this->codes($assets, ['1002', '1006']),
            'claims_dab' => $this->codes($assets, ['1008']),
            'prepayments' => $this->codes($assets, ['1003']),
            'other_current' => $this->codes($assets, ['1004', '1005', '1007', '1009']),
        ];

        $fixedAssets = [
            'tangible' => $this->codes($assets, ['1100', '1010', '1011', '1012', '1013', '1014', '1015']),
            'accumulated_depreciation' => $this->codes($assets, ['1199']),
            'intangible' => $this->codes($assets, ['1200', '1020', '1021', '1022']),
            'accumulated_amortization' => $this->codes($assets, ['1299']),
        ];

        $currentAssets['total'] = $this->sum($currentAssets);
        $fixedAssets['tangible_total'] = $fixedAssets['tangible'] + $fixedAssets['accumulated_depreciation'];
        $fixedAssets['intangible_total'] = $fixedAssets['intangible'] + $fixedAssets['accumulated_amortization'];
        $fixedAssets['total'] = $fixedAssets['tangible_total'] + $fixedAssets['intangible_total'];

        $liabilityLines = [
            'bills_payable' => $this->codes($liabilities, ['2101']),
            'accounts_payable' => $this->codes($liabilities, ['2000', '2001', '2002', '2003']),
            'tax_payable' => $this->codes($liabilities, ['2004']),
            'other_liabilities' => $this->codes($liabilities, ['2005', '2100', '2102']),
        ];
        $liabilityLines['total'] = (float) data_get($report, 'liabilities.total.total', $this->sum($liabilityLines));

        $equityLines = [
            'share_capital' => $this->codes($equity, ['3000', '3003', '3004']),
            'retained_earnings' => $this->codes($equity, ['3001']),
            'current_profit_loss' => $this->codes($equity, ['3002', '3002-CALC']),
        ];
        $equityLines['total'] = (float) data_get($report, 'equity.total.total', $this->sum($equityLines));

        return [
            'current_assets' => $this->zeroes($currentAssets),
            'fixed_assets' => $this->zeroes($fixedAssets),
            'total_assets' => (float) data_get($report, 'assets.total.total', $currentAssets['total'] + $fixedAssets['total']),
            'liabilities' => $this->zeroes($liabilityLines),
            'equity' => $this->zeroes($equityLines),
            'liabilities_equity' => (float) data_get($report, 'liabilities_equity.total', $liabilityLines['total'] + $equityLines['total']),
            'difference' => (float) data_get($report, 'difference.total', 0),
        ];
    }

    public function profitLoss(array $report): array
    {
        $income = $this->flatten($report['income'] ?? []);
        $expenses = $this->flatten($report['expenses'] ?? []);

        $exchangeIncome = $this->codes($income, ['4005', 'FX-UNREALIZED-GAIN'])
            - $this->codes($expenses, ['5016', 'FX-UNREALIZED-LOSS']);
        $commissionIncome = $this->codes($income, ['4000', '4001', '4002']);
        $otherIncome = $this->unclassified($income, ['4000', '4001', '4002', '4005', 'FX-UNREALIZED-GAIN']);

        $grossIncome = $exchangeIncome + $commissionIncome + $otherIncome;
        $grossRevenueTax = $this->matching($expenses, ['gross revenue tax', 'business receipts tax', 'turnover tax', 'عواید ناخالص'], ['5022']);
        $netIncome = $grossIncome - $grossRevenueTax;

        $classified = collect();

        $administrative = $this->take($expenses, $classified, ['5004', '5006', '5008'], ['administrative', 'office supplies', 'maintenance', 'software', 'marketing']);
        $fuel = $this->take($expenses, $classified, [], ['fuel', 'gas', 'oil', 'سوخت', 'تیل']);
        $communication = $this->take($expenses, $classified, ['5003'], ['communication', 'telephone', 'internet']);
        $rent = $this->take($expenses, $classified, ['5001'], ['rent', 'کرایه']);
        $insurance = $this->take($expenses, $classified, ['5005'], ['insurance', 'بیمه']);
        $fees = $this->take($expenses, $classified, ['5007', '5009'], ['fee', 'bank charge', 'legal', 'professional']);
        $staff = $this->take($expenses, $classified, ['5000', '5010', '5011', '5012'], ['salary', 'wage', 'employee', 'payroll', 'bonus', 'provident']);
        $audit = $this->take($expenses, $classified, [], ['audit', 'ارزیابی']);
        $electricity = $this->take($expenses, $classified, ['5002'], ['electricity', 'utility', 'برق']);

        // These are displayed separately and must not be included in operating expenses.
        $depreciation = $this->take($expenses, $classified, ['5013', '5014'], ['depreciation', 'amortization', 'استهلاک']);
        $incomeTax = $this->take($expenses, $classified, ['5015'], ['income tax', 'مالیه']);
        $this->markMatching($expenses, $classified, ['5016', 'FX-UNREALIZED-LOSS', '5022'], ['gross revenue tax', 'business receipts tax', 'turnover tax']);

        $otherExpenses = $expenses
            ->reject(fn (array $row) => $classified->contains($row['code']))
            ->sum('amount');

        $operatingExpenses = $administrative + $fuel + $communication + $rent + $insurance
            + $fees + $staff + $audit + $electricity + $otherExpenses;
        $incomeBeforeTaxAndDepreciation = $netIncome - $operatingExpenses;
        $incomeBeforeTax = $incomeBeforeTaxAndDepreciation - $depreciation;
        $netProfitLoss = $incomeBeforeTax - $incomeTax;

        return $this->zeroes([
            'exchange_income' => $exchangeIncome,
            'commission_income' => $commissionIncome,
            'other_income' => $otherIncome,
            'gross_income' => $grossIncome,
            'gross_revenue_tax' => $grossRevenueTax,
            'net_income' => $netIncome,
            'administrative_expenses' => $administrative,
            'fuel_expenses' => $fuel,
            'communication_expenses' => $communication,
            'rent_expenses' => $rent,
            'insurance_expenses' => $insurance,
            'fees' => $fees,
            'employee_expenses' => $staff,
            'audit_expenses' => $audit,
            'electricity_expenses' => $electricity,
            'other_expenses' => $otherExpenses,
            'operating_expenses' => $operatingExpenses,
            'income_before_tax_depreciation' => $incomeBeforeTaxAndDepreciation,
            'depreciation' => $depreciation,
            'income_before_tax' => $incomeBeforeTax,
            'tax' => $incomeTax,
            'net_profit_loss' => $netProfitLoss,
        ]);
    }

    private function flatten(array $section): Collection
    {
        return collect($section['groups'] ?? [])
            ->flatMap(fn (array $group) => $group['rows'] ?? [])
            ->map(function (array $row) {
                return [
                    'code' => (string) ($row['code'] ?? ''),
                    'name' => (string) ($row['name'] ?? ''),
                    'amount' => (float) data_get($row, 'columns.total', 0),
                ];
            })
            ->values();
    }

    private function codes(Collection $rows, array $codes): float
    {
        return (float) $rows->whereIn('code', $codes)->sum('amount');
    }

    private function unclassified(Collection $rows, array $classifiedCodes): float
    {
        return (float) $rows->reject(fn (array $row) => in_array($row['code'], $classifiedCodes, true))->sum('amount');
    }

    private function matching(Collection $rows, array $keywords, array $codes = []): float
    {
        return (float) $rows->filter(function (array $row) use ($keywords, $codes) {
            if (in_array($row['code'], $codes, true)) {
                return true;
            }

            $name = Str::lower($row['name']);
            return collect($keywords)->contains(fn (string $keyword) => Str::contains($name, Str::lower($keyword)));
        })->sum('amount');
    }

    private function take(Collection $rows, Collection $classified, array $codes, array $keywords): float
    {
        $matched = $rows->filter(function (array $row) use ($codes, $keywords) {
            if (in_array($row['code'], $codes, true)) {
                return true;
            }

            $name = Str::lower($row['name']);
            return collect($keywords)->contains(fn (string $keyword) => Str::contains($name, Str::lower($keyword)));
        });

        $matched->each(fn (array $row) => $classified->push($row['code']));
        return (float) $matched->sum('amount');
    }

    private function markMatching(Collection $rows, Collection $classified, array $codes, array $keywords): void
    {
        $rows->filter(function (array $row) use ($codes, $keywords) {
            if (in_array($row['code'], $codes, true)) {
                return true;
            }

            $name = Str::lower($row['name']);
            return collect($keywords)->contains(fn (string $keyword) => Str::contains($name, Str::lower($keyword)));
        })->each(fn (array $row) => $classified->push($row['code']));
    }

    private function sum(array $values): float
    {
        return (float) collect($values)->filter(fn ($value, $key) => $key !== 'total')->sum();
    }

    private function zeroes(array $values): array
    {
        return collect($values)->map(function ($value) {
            if ($value === null) {
                return 0.0;
            }

            return is_numeric($value) ? (float) $value : $value;
        })->all();
    }
}
