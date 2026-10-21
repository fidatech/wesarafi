<style>
    .report-page { --report-primary: #1f4b7a; --report-ink: #17212b; --report-muted: #687789; }
    .report-header { background: linear-gradient(135deg, #173f68, #2b6ca3); color: #fff; border-radius: 14px; padding: 22px 24px; box-shadow: 0 8px 24px rgba(23, 63, 104, .18); }
    .report-header h4 { color: #fff; margin: 0; font-weight: 700; }
    .report-header p { color: rgba(255,255,255,.78); margin: 6px 0 0; }
    .report-filter-card, .report-card, .metric-card { border: 1px solid #e4e9ef; border-radius: 12px; box-shadow: 0 2px 10px rgba(31, 75, 122, .05); }
    .report-filter-card .card-body { padding: 16px 18px; }
    .metric-card .card-body { padding: 16px 18px; }
    .metric-label { color: var(--report-muted); font-size: .82rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; }
    .metric-value { color: var(--report-ink); font-size: 1.35rem; font-weight: 750; margin-top: 6px; font-variant-numeric: tabular-nums; }
    .metric-value.positive { color: #17804b; }
    .metric-value.negative { color: #b93838; }
    .report-table { margin-bottom: 0; font-size: .9rem; }
    .report-table thead th { position: sticky; top: 0; z-index: 2; background: #eef3f8; color: #31485f; border-color: #dce3ea; white-space: nowrap; }
    .report-table td, .report-table th { vertical-align: middle; border-color: #e4e9ef; padding: .72rem .75rem; }
    .report-table .group-row td { background: #f5f8fb; color: #264967; font-weight: 700; }
    .report-table .subtotal-row td { background: #fbfcfd; font-weight: 700; border-top: 2px solid #dce3ea; }
    .report-table .grand-total td { background: #173f68; color: #fff; font-weight: 750; border-color: #173f68; }
    .report-table .difference-row td { background: #fff5f5; color: #a52b2b; font-weight: 700; }
    .number-cell { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .code-cell { color: #748294; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; white-space: nowrap; }
    .negative-number { color: #b93838; }
    .calculated-badge { font-size: .68rem; background: #e8f2fb; color: #245b88; border: 1px solid #cfe3f3; border-radius: 999px; padding: 2px 7px; margin-left: 6px; }
    .report-table-wrap { overflow: auto; max-height: calc(100vh - 390px); border-radius: 10px; }
    .report-note { color: var(--report-muted); font-size: .82rem; }
    .print-only { display: none; }
    @media print {
        body { overflow: visible !important; background: #fff !important; }
        #layout-menu, #layout-navbar, .fb-sidebar, .no-print { display: none !important; }
        .fb-wrapper, .fb-content, .fb-main { display: block !important; height: auto !important; overflow: visible !important; padding: 0 !important; }
        .report-page { padding: 0 !important; }
        .report-header { background: #fff !important; color: #000 !important; box-shadow: none; border: 1px solid #bbb; }
        .report-header h4, .report-header p { color: #000 !important; }
        .report-table-wrap { max-height: none; overflow: visible; }
        .report-table thead th { position: static; }
        .print-only { display: block; }
        .card { box-shadow: none !important; }
    }
</style>
