@extends('layouts.layoutMaster')

@section('title', 'Balance Sheet')

@section('vendor-style')
    @include('financial_reports._dab-statement-styles')
@endsection

@section('content')
@php
    $fmt = function ($value) {
        $number = (float) ($value ?? 0);
        return abs($number - round($number)) < 0.005
            ? number_format($number, 0)
            : number_format($number, 2);
    };
    $frequencyLabels = ['monthly' => 'ماهوار', 'quarterly' => 'ربعوار', 'annual' => 'سالانه'];
    $companyName = $setting?->company_name ?: config('app.name', 'WeSarafi');
    $companyDisplay = $companyName;
@endphp
<div class="row g-3 report-page">
    <div class="col-lg-2">@include('layouts.menu.financial-sidebar')</div>
    <div class="col-lg-10 fb-content">
        <div class="fb-main pt-3 dab-report-shell">
            <form method="GET" class="card dab-toolbar no-print mb-3">
                <div class="card-body row g-3 align-items-end">
                    <div class="col-xl-3 col-md-6">
                        <label class="form-label">Reporting date</label>
                        <input type="date" name="date" value="{{ $date }}" class="form-control">
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <label class="form-label">Branch</label>
                        <select name="branch_id" class="form-select">
                            <option value="0" @selected(!$branchId)>All branches</option>
                            @foreach($branches as $branch)
                                <option value="{{ $branch->id }}" @selected((int) $branchId === (int) $branch->id)>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <label class="form-label">Reporting frequency</label>
                        <select name="frequency" class="form-select">
                            <option value="monthly" @selected($frequency === 'monthly')>Monthly / ماهوار</option>
                            <option value="quarterly" @selected($frequency === 'quarterly')>Quarterly / ربعوار</option>
                            <option value="annual" @selected($frequency === 'annual')>Annual / سالانه</option>
                        </select>
                    </div>
                    <div class="col-xl-3 col-md-6 d-flex gap-2">
                        <button class="btn btn-primary flex-grow-1"><i class="bx bx-filter-alt me-1"></i> Generate</button>
                        <button type="button" class="btn btn-outline-dark" onclick="window.print()"><i class="bx bx-printer"></i></button>
                        <button name="format" value="pdf" class="btn btn-outline-danger"><i class="bx bxs-file-pdf"></i></button>
                        <button name="format" value="xlsx" class="btn btn-outline-success"><i class="bx bx-spreadsheet"></i></button>
                    </div>
                </div>
            </form>

            <div class="dab-warning no-print">
                @include('financial_reports._warnings', ['report' => $report])
            </div>

            <div class="dab-sheet-wrap">
                <section class="dab-sheet">
                    <h1 class="dab-title">صورت حساب مالی (بلانس شیت)</h1>

                    <table class="dab-meta">
                        <tr>
                            <td class="dab-meta-label">اسم شرکت صرافی و خدمات پولی</td>
                            <td class="dab-meta-value rtl-value">
                                {{ $companyDisplay }}
                                @if($selectedBranch)<span class="dab-branch-note">گزارش شعبه: {{ $selectedBranch->name }}</span>@endif
                            </td>
                        </tr>
                        <tr><td class="dab-meta-label">تاریخ</td><td class="dab-meta-value">{{ $jalaliDate ?: $date }}</td></tr>
                        <tr><td class="dab-meta-label">سعر</td><td class="dab-meta-value rtl-value">افغانی</td></tr>
                        <tr><td class="dab-meta-label">تناوب گزارش دهی</td><td class="dab-meta-value rtl-value">{{ $frequencyLabels[$frequency] ?? 'ربعوار' }}</td></tr>
                    </table>

                    <table class="dab-statement-table">
                        <thead>
                            <tr><th class="dab-code">کود</th><th class="dab-item">اقلام</th><th class="dab-amount">مبلغ</th></tr>
                        </thead>
                        <tbody>
                            <tr class="dab-section"><td class="dab-code"></td><td class="dab-item">دارایی ها</td><td class="dab-amount"></td></tr>
                            <tr class="dab-subsection"><td class="dab-code">1</td><td class="dab-item">دارایی های جاری</td><td class="dab-amount"></td></tr>
                            <tr><td class="dab-code">1.1</td><td class="dab-item">پول نقد</td><td class="dab-amount">{{ $fmt($statement['current_assets']['cash'] ?? 0) }}</td></tr>
                            <tr><td class="dab-code">1.2</td><td class="dab-item">پول نقد در بانک</td><td class="dab-amount">{{ $fmt($statement['current_assets']['cash_bank'] ?? 0) }}</td></tr>
                            <tr><td class="dab-code">1.3</td><td class="dab-item">حسابات قابل حصول</td><td class="dab-amount">{{ $fmt($statement['current_assets']['receivables'] ?? 0) }}</td></tr>
                            <tr><td class="dab-code">1.4</td><td class="dab-item">طلبات بالای د افغانستان بانک</td><td class="dab-amount">{{ $fmt($statement['current_assets']['claims_dab'] ?? 0) }}</td></tr>
                            <tr><td class="dab-code">1.5</td><td class="dab-item">پیش پرداخت ها</td><td class="dab-amount">{{ $fmt($statement['current_assets']['prepayments'] ?? 0) }}</td></tr>
                            <tr><td class="dab-code">1.6</td><td class="dab-item">سایر دارایی های جاری</td><td class="dab-amount">{{ $fmt($statement['current_assets']['other_current'] ?? 0) }}</td></tr>
                            <tr class="dab-total"><td class="dab-code">1.7</td><td class="dab-item">مجموع دارایی های جاری</td><td class="dab-amount">{{ $fmt($statement['current_assets']['total'] ?? 0) }}</td></tr>

                            <tr class="dab-subsection"><td class="dab-code">1.8</td><td class="dab-item">دارایی های ثابت</td><td class="dab-amount"></td></tr>
                            <tr><td class="dab-code"></td><td class="dab-item">دارایی های ملموس</td><td class="dab-amount">{{ $fmt($statement['fixed_assets']['tangible'] ?? 0) }}</td></tr>
                            <tr><td class="dab-code">2</td><td class="dab-item">کاهش استهلاکات دارایی های ملموس</td><td class="dab-amount">{{ $fmt($statement['fixed_assets']['accumulated_depreciation'] ?? 0) }}</td></tr>
                            <tr class="dab-total"><td class="dab-code">2.1</td><td class="dab-item">مجموع دارایی های ملموس</td><td class="dab-amount">{{ $fmt($statement['fixed_assets']['tangible_total'] ?? 0) }}</td></tr>
                            <tr><td class="dab-code">2.2</td><td class="dab-item">دارایی های غیر ملموس</td><td class="dab-amount">{{ $fmt($statement['fixed_assets']['intangible'] ?? 0) }}</td></tr>
                            <tr><td class="dab-code">2.3</td><td class="dab-item">کاهش استهلاکات دارایی های غیر ملموس</td><td class="dab-amount">{{ $fmt($statement['fixed_assets']['accumulated_amortization'] ?? 0) }}</td></tr>
                            <tr class="dab-total"><td class="dab-code">2.4</td><td class="dab-item">مجموع دارایی های غیر ملموس</td><td class="dab-amount">{{ $fmt($statement['fixed_assets']['intangible_total'] ?? 0) }}</td></tr>
                            <tr class="dab-total"><td class="dab-code">2.5</td><td class="dab-item">مجموع دارایی های ثابت</td><td class="dab-amount">{{ $fmt($statement['fixed_assets']['total'] ?? 0) }}</td></tr>
                            <tr class="dab-total"><td class="dab-code">2.6</td><td class="dab-item">مجموع دارایی ها</td><td class="dab-amount">{{ $fmt($statement['total_assets'] ?? 0) }}</td></tr>

                            <tr class="dab-grand-total"><td class="dab-code">2.7</td><td class="dab-item">بدهی ها + سرمایه</td><td class="dab-amount"></td></tr>
                            <tr class="dab-subsection"><td class="dab-code"></td><td class="dab-item">بدهی های جاری</td><td class="dab-amount"></td></tr>
                            <tr><td class="dab-code">3</td><td class="dab-item">بل های قابل پرداخت</td><td class="dab-amount">{{ $fmt($statement['liabilities']['bills_payable'] ?? 0) }}</td></tr>
                            <tr><td class="dab-code"></td><td class="dab-item">حسابات قابل پرداخت</td><td class="dab-amount">{{ $fmt($statement['liabilities']['accounts_payable'] ?? 0) }}</td></tr>
                            <tr><td class="dab-code">4</td><td class="dab-item">مالیات قابل پرداخت</td><td class="dab-amount">{{ $fmt($statement['liabilities']['tax_payable'] ?? 0) }}</td></tr>
                            <tr><td class="dab-code">5</td><td class="dab-item">سایر بدهی ها</td><td class="dab-amount">{{ $fmt($statement['liabilities']['other_liabilities'] ?? 0) }}</td></tr>
                            <tr class="dab-total"><td class="dab-code">5.1</td><td class="dab-item">مجموع بدهی ها</td><td class="dab-amount">{{ $fmt($statement['liabilities']['total'] ?? 0) }}</td></tr>

                            <tr class="dab-subsection"><td class="dab-code">5.2</td><td class="dab-item">سرمایه</td><td class="dab-amount"></td></tr>
                            <tr><td class="dab-code">5.3</td><td class="dab-item">مجموع سرمایه سهم شرکاء</td><td class="dab-amount">{{ $fmt($statement['equity']['share_capital'] ?? 0) }}</td></tr>
                            <tr><td class="dab-code">5.4</td><td class="dab-item">مفاد / ضرر سال گذشته</td><td class="dab-amount">{{ $fmt($statement['equity']['retained_earnings'] ?? 0) }}</td></tr>
                            <tr><td class="dab-code">5.5</td><td class="dab-item">مفاد / ضرر ربع فعلی</td><td class="dab-amount">{{ $fmt($statement['equity']['current_profit_loss'] ?? 0) }}</td></tr>
                            <tr class="dab-total"><td class="dab-code">6</td><td class="dab-item">مجموع سرمایه</td><td class="dab-amount">{{ $fmt($statement['equity']['total'] ?? 0) }}</td></tr>
                            <tr class="dab-grand-total"><td class="dab-code"></td><td class="dab-item">مجموع بدهی ها و سرمایه</td><td class="dab-amount">{{ $fmt($statement['liabilities_equity'] ?? 0) }}</td></tr>
                        </tbody>
                    </table>
                </section>
            </div>
        </div>
    </div>
</div>
@endsection
