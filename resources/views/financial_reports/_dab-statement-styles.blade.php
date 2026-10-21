<style>
    .dab-report-shell { padding-bottom: 2rem; }
    .dab-toolbar { border: 1px solid #e1e7ee; border-radius: 12px; box-shadow: 0 3px 14px rgba(31, 62, 94, .06); }
    .dab-sheet-wrap { overflow-x: auto; padding: 14px 0 28px; }
    .dab-sheet {
        width: min(100%, 1180px);
        min-width: 850px;
        margin: 0 auto;
        padding: 18px 24px 30px;
        background: #fff;
        color: #111;
        direction: rtl;
        font-family: Tahoma, Arial, sans-serif;
        font-size: 15px;
    }
    .dab-title { margin: 0 0 14px; text-align: center; color: #111; font-size: 25px; font-weight: 800; text-decoration: underline; }
    .dab-meta, .dab-statement-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    .dab-meta { margin-bottom: 16px; }
    .dab-meta td { border: 1px solid #111; height: 31px; padding: 3px 10px; }
    .dab-meta-label { width: 50%; text-align: right; font-weight: 800; font-size: 17px; }
    .dab-meta-value { width: 50%; text-align: left; direction: ltr; font-weight: 700; }
    .dab-meta-value.rtl-value { text-align: right; direction: rtl; }
    .dab-branch-note { display: block; margin-top: 2px; color: #555; font-size: 11px; font-weight: 500; }
    .dab-statement-table th, .dab-statement-table td { border: 1px solid #111; min-height: 29px; padding: 3px 8px; vertical-align: middle; }
    .dab-statement-table thead th { background: #fff; color: #111; text-transform: none; letter-spacing: 0; font-size: 17px; font-weight: 800; }
    .dab-code { width: 8%; text-align: center; direction: ltr; font-weight: 600; }
    .dab-item { width: 54%; text-align: right; }
    .dab-amount { width: 38%; text-align: center; direction: ltr; font-variant-numeric: tabular-nums; }
    .dab-section td { font-size: 20px; font-weight: 800; }
    .dab-subsection td { background: #efefef; font-size: 19px; font-weight: 800; }
    .dab-total td { font-size: 18px; font-weight: 800; }
    .dab-grand-total td { background: #efefef; font-size: 19px; font-weight: 900; }
    .dab-emphasis td { font-size: 18px; font-weight: 900; }
    .dab-success-amount { color: #078d38; font-weight: 900; }
    .dab-danger-amount { color: #b4232c; font-weight: 900; }
    .dab-signatures { display: grid; grid-template-columns: 1fr 1fr; gap: 80px; margin-top: 8px; text-align: center; }
    .dab-signature-title { font-size: 17px; font-weight: 800; text-decoration: underline; }
    .dab-signature-line { margin-top: 58px; padding-top: 7px; border-top: 1px solid #222; color: #777; }
    .dab-stamp { width: 48%; margin: 48px 0 0 auto; padding-top: 7px; border-top: 1px solid #222; text-align: right; color: #777; }
    .dab-period-note { color: #555; font-size: 11px; font-weight: 500; }
    .dab-warning { width: min(100%, 1180px); margin: 0 auto 12px; }

    @media (max-width: 991.98px) {
        .dab-sheet { padding-inline: 16px; }
    }

    @media print {
        @page { size: A4 portrait; margin: 8mm; }
        html, body { width: auto !important; height: auto !important; overflow: visible !important; background: #fff !important; }
        #layout-navbar, #layout-menu, .fb-sidebar, .no-print, #scrollToTopBtn { display: none !important; }
        .layout-wrapper, .layout-container, .layout-page, .content-wrapper, .container-fluid,
        .fb-wrapper, .fb-content, .fb-main, .dab-report-shell, .dab-sheet-wrap {
            display: block !important;
            width: auto !important;
            height: auto !important;
            min-height: 0 !important;
            overflow: visible !important;
            margin: 0 !important;
            padding: 0 !important;
        }
        .dab-sheet { width: 100% !important; min-width: 0 !important; padding: 0 !important; font-size: 12px; }
        .dab-title { font-size: 20px; margin-bottom: 8px; }
        .dab-meta { margin-bottom: 10px; }
        .dab-meta td { height: 24px; padding: 2px 7px; }
        .dab-meta-label, .dab-statement-table thead th { font-size: 13px; }
        .dab-statement-table th, .dab-statement-table td { padding: 2px 6px; height: 22px; }
        .dab-section td, .dab-subsection td, .dab-total td, .dab-grand-total td, .dab-emphasis td { font-size: 13px; }
        .dab-signatures { margin-top: 5px; }
        .dab-signature-title { font-size: 13px; }
        .dab-signature-line { margin-top: 35px; }
        .dab-stamp { margin-top: 30px; }
        .card { border: 0 !important; box-shadow: none !important; }
    }
</style>
