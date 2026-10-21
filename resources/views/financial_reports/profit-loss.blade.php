@extends('layouts.layoutMaster')

@section('title', 'Profit & Loss')

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
    $netClass = ($statement['net_profit_loss'] ?? 0) < 0 ? 'dab-danger-amount' : 'dab-success-amount';
@endphp
<div class="row g-3 report-page">
    <div class="col-lg-2">@include('layouts.menu.financial-sidebar')</div>
    <div class="col-lg-10 fb-content">
        <div class="fb-main pt-3 dab-report-shell">
            <form method="GET" class="card dab-toolbar no-print mb-3">
                <div class="card-body row g-3 align-items-end">
                    <div class="col-xl-2 col-md-6"><label class="form-label">From</label><input type="date" name="from" value="{{ $from }}" class="form-control"></div>
                    <div class="col-xl-2 col-md-6"><label class="form-label">To</label><input type="date" name="to" value="{{ $to }}" class="form-control"></div>
                    <div class="col-xl-3 col-md-6"><label class="form-label">Branch</label><select name="branch_id" class="form-select"><option value="0" @selected(!$branchId)>All branches</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected((int) $branchId === (int) $branch->id)>{{ $branch->name }}</option>@endforeach</select></div>
                    <div class="col-xl-2 col-md-6"><label class="form-label">Frequency</label><select name="frequency" class="form-select"><option value="monthly" @selected($frequency === 'monthly')>Monthly</option><option value="quarterly" @selected($frequency === 'quarterly')>Quarterly</option><option value="annual" @selected($frequency === 'annual')>Annual</option></select></div>
                    <div class="col-xl-3 col-md-6 d-flex gap-2"><button class="btn btn-primary flex-grow-1"><i class="bx bx-filter-alt me-1"></i> Generate</button><button type="button" class="btn btn-outline-dark" onclick="window.print()"><i class="bx bx-printer"></i></button><button name="format" value="pdf" class="btn btn-outline-danger"><i class="bx bxs-file-pdf"></i></button><button name="format" value="xlsx" class="btn btn-outline-success"><i class="bx bx-spreadsheet"></i></button></div>
                </div>
            </form>

            <div class="dab-warning no-print">
                @include('financial_reports._warnings', ['report' => $report])
            </div>

            <div class="dab-sheet-wrap">
                <section class="dab-sheet">
                    <h1 class="dab-title">صورت حساب مفاد و ضرر</h1>

                    <table class="dab-meta">
                        <tr><td class="dab-meta-label">اسم شرکت صرافی و خدمات پولی</td><td class="dab-meta-value rtl-value">{{ $companyDisplay }}@if($selectedBranch)<span class="dab-branch-note">گزارش شعبه: {{ $selectedBranch->name }}</span>@endif</td></tr>
                        <tr><td class="dab-meta-label">تاریخ</td><td class="dab-meta-value">{{ $jalaliTo ?: $to }} <span class="dab-period-note">({{ $jalaliFrom ?: $from }} - {{ $jalaliTo ?: $to }})</span></td></tr>
                        <tr><td class="dab-meta-label">سعر</td><td class="dab-meta-value rtl-value">افغانی</td></tr>
                        <tr><td class="dab-meta-label">تناوب گزارش دهی</td><td class="dab-meta-value rtl-value">{{ $frequencyLabels[$frequency] ?? 'ربعوار' }}</td></tr>
                    </table>

                    <table class="dab-statement-table">
                        <thead><tr><th class="dab-code">کود</th><th class="dab-item">اقلام</th><th class="dab-amount">مبلغ</th></tr></thead>
                        <tbody>
                            <tr class="dab-section"><td class="dab-code">1</td><td class="dab-item">عواید</td><td class="dab-amount"></td></tr>
                            <tr><td class="dab-code">1.1</td><td class="dab-item">عواید ناشی از تبادله اسعار</td><td class="dab-amount">{{ $fmt($statement['exchange_income'] ?? 0) }}</td></tr>
                            <tr><td class="dab-code">1.2</td><td class="dab-item">عواید ناشی از کمیشن ها</td><td class="dab-amount">{{ $fmt($statement['commission_income'] ?? 0) }}</td></tr>
                            <tr><td class="dab-code">1.3</td><td class="dab-item">سایر عواید</td><td class="dab-amount">{{ $fmt($statement['other_income'] ?? 0) }}</td></tr>
                            <tr class="dab-total"><td class="dab-code">1.4</td><td class="dab-item">مجموع عواید ناخالص</td><td class="dab-amount">{{ $fmt($statement['gross_income'] ?? 0) }}</td></tr>
                            <tr><td class="dab-code">1.5</td><td class="dab-item">مالیه بر بازده (عواید) ناخالص</td><td class="dab-amount">{{ $fmt($statement['gross_revenue_tax'] ?? 0) }}</td></tr>
                            <tr class="dab-total"><td class="dab-code">1.6</td><td class="dab-item">عواید خالص</td><td class="dab-amount">{{ $fmt($statement['net_income'] ?? 0) }}</td></tr>

                            <tr class="dab-subsection"><td class="dab-code">2</td><td class="dab-item">مصارف عملیاتی</td><td class="dab-amount"></td></tr>
                            <tr><td class="dab-code">2.1</td><td class="dab-item">مصارف اداری</td><td class="dab-amount">{{ $fmt($statement['administrative_expenses'] ?? 0) }}</td></tr>
                            <tr><td class="dab-code">2.2</td><td class="dab-item">مصارف سوخت (گاز، تیل و غیره)</td><td class="dab-amount">{{ $fmt($statement['fuel_expenses'] ?? 0) }}</td></tr>
                            <tr><td class="dab-code">2.3</td><td class="dab-item">مصارف تیلفون/مخابراتی</td><td class="dab-amount">{{ $fmt($statement['communication_expenses'] ?? 0) }}</td></tr>
                            <tr><td class="dab-code">2.4</td><td class="dab-item">مصارف کرایه</td><td class="dab-amount">{{ $fmt($statement['rent_expenses'] ?? 0) }}</td></tr>
                            <tr><td class="dab-code">2.5</td><td class="dab-item">تکافل (بیمه)</td><td class="dab-amount">{{ $fmt($statement['insurance_expenses'] ?? 0) }}</td></tr>
                            <tr><td class="dab-code">2.6</td><td class="dab-item">فیس ها</td><td class="dab-amount">{{ $fmt($statement['fees'] ?? 0) }}</td></tr>
                            <tr><td class="dab-code">2.7</td><td class="dab-item">مصارف کارمندان (معاشات و سایر امتیازات)</td><td class="dab-amount">{{ $fmt($statement['employee_expenses'] ?? 0) }}</td></tr>
                            <tr><td class="dab-code">2.8</td><td class="dab-item">مصارف نیاز به ارزیابی</td><td class="dab-amount">{{ $fmt($statement['audit_expenses'] ?? 0) }}</td></tr>
                            <tr><td class="dab-code">2.9</td><td class="dab-item">پرداخت بل ها</td><td class="dab-amount">{{ $fmt($statement['electricity_expenses'] ?? 0) }}</td></tr>
                            <tr><td class="dab-code">2.10</td><td class="dab-item">سایر مصارف</td><td class="dab-amount">{{ $fmt($statement['other_expenses'] ?? 0) }}</td></tr>
                            <tr class="dab-total"><td class="dab-code">2.11</td><td class="dab-item">مجموع مصارف عملیاتی</td><td class="dab-amount">{{ $fmt($statement['operating_expenses'] ?? 0) }}</td></tr>

                            <tr class="dab-emphasis"><td class="dab-code">3</td><td class="dab-item">عواید قبل از وضع مالیه و استهلاک</td><td class="dab-amount dab-success-amount">{{ $fmt($statement['income_before_tax_depreciation'] ?? 0) }}</td></tr>
                            <tr><td class="dab-code">4</td><td class="dab-item">استهلاک</td><td class="dab-amount dab-success-amount">{{ $fmt($statement['depreciation'] ?? 0) }}</td></tr>
                            <tr class="dab-emphasis"><td class="dab-code">5</td><td class="dab-item">مجموع عواید قبل از وضع مالیه</td><td class="dab-amount">{{ $fmt($statement['income_before_tax'] ?? 0) }}</td></tr>
                            <tr><td class="dab-code">6</td><td class="dab-item">مالیه</td><td class="dab-amount">{{ $fmt($statement['tax'] ?? 0) }}</td></tr>
                            <tr class="dab-emphasis"><td class="dab-code">7</td><td class="dab-item">مفاد / ضرر خالص</td><td class="dab-amount {{ $netClass }}">{{ $fmt($statement['net_profit_loss'] ?? 0) }}</td></tr>
                        </tbody>
                    </table>

                    <div class="dab-signatures">
                        <div><div class="dab-signature-title">آمر مالی</div><div class="dab-signature-line">امضاء و تاریخ</div></div>
                        <div><div class="dab-signature-title">شخص مسئول</div><div class="dab-signature-line">امضاء و تاریخ</div></div>
                    </div>
                    <div class="dab-stamp">مهر / تایید شرکت</div>
                </section>
            </div>
        </div>
    </div>
</div>
@endsection
