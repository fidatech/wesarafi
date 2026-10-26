<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}" class="layout-navbar-fixed layout-menu-fixed layout-wide" data-bs-theme="light"
    data-template="horizontal-menu-template-no-customizer">

    <head>
        <meta charset="utf-8" />
        <title>@yield('title', 'WeSarafi')</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon" />
        <meta name="csrf-token" content="{{ csrf_token() }}">
        {{-- Vite --}}
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @yield('vendor-style')
        @yield('page-style')
    </head>

    <body class="wes-compact">

        <div class="layout-wrapper layout-navbar-full layout-horizontal layout-without-menu">

            <div class="layout-container">

                @include('layouts.navbar')

                <div class="layout-page{{ request()->routeIs('dashboard') ? ' dashboard-layout-page' : '' }}">

                    @include('layouts.menubar')
                    <div class="content-wrapper">
                        {{-- ✅ Optional Breadcrumb (Remove if not needed) --}}
                        {{-- @include('layouts.breadcrumb') --}}

                        <div class="container-fluid flex-grow-1 container-p-y">
                @php($businessDayView = auth()->check() ? \App\Services\DailyBalanceState::forBranch((int) (auth()->user()->branch_id ?: 1)) : null)
                @if($businessDayView && $businessDayView['status'] === 'not_opened')
                    <div class="alert alert-info wes-business-day-notice" role="status"><i class="bx bx-info-circle me-2"></i><strong>Business day opening required.</strong> Financial transactions are blocked until today’s opening rates are saved.
                        @if(auth()->user()->hasRole('Super Admin') || auth()->user()->can('manage business day'))
                            <button type="button" class="btn btn-primary btn-sm ms-2" id="btnOpenBusinessDayRequired">Open business day</button>
                        @else
                            <span class="d-block small mt-1">Contact your branch manager or an authorized administrator to open the day.</span>
                        @endif</div>
                @endif
                @if($businessDayView && $businessDayView['closed'])
                    <div class="alert alert-warning wes-business-day-notice" role="status"><i class="bx bx-lock-alt me-2"></i><strong>Business day closed.</strong> All data changes are locked for this branch until 12:00 AM Kabul time. Reports remain available.</div>
                @endif


                            @hasSection('content')
                                @yield('content')
                            @else
                                {{-- For Livewire views --}}
                                {{ $slot ?? '' }}
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <script>
            // Place branch notices inside the actual module workspace, after its sidebar.
            // Other pages keep the notice in the normal content container.
            document.querySelectorAll('.wes-business-day-notice').forEach((notice) => {
                const page = notice.closest('.container-p-y');
                const row = page?.querySelector(':scope > .row');
                const workspace = row && Array.from(row.children).find((column) =>
                    column.matches('[class*="col-"]') && !column.querySelector('.fb-sidebar')
                );
                if (workspace) workspace.prepend(notice);
            });
        </script>
        @include('layouts.partials.fx-modals')

        {{-- Page scripts are held inert until the Vite module has initialized its UI globals. --}}
        <template id="wesDeferredScripts">
            @yield('vendor-script')
            @yield('page-script')
        </template>
        <script>
            window.addEventListener('wes:app-ready', async function () {
                const template = document.getElementById('wesDeferredScripts');
                if (!template) return;

                const scripts = Array.from(template.content.querySelectorAll('script'));
                for (const original of scripts) {
                    await new Promise((resolve) => {
                        const script = document.createElement('script');
                        for (const attribute of original.attributes) {
                            script.setAttribute(attribute.name, attribute.value);
                        }
                        if (original.src) {
                            script.onload = resolve;
                            script.onerror = resolve;
                            script.src = original.src;
                            document.body.appendChild(script);
                        } else {
                            script.textContent = original.textContent;
                            document.body.appendChild(script);
                            resolve();
                        }
                    });
                }
                template.remove();
            }, { once: true });
        </script>
        <script>
            // Roll the business day over at midnight in Asia/Kabul, even for an open tab.
            (() => {
                const kabulDate = () => new Intl.DateTimeFormat('en-CA', {
                    timeZone: 'Asia/Kabul', year: 'numeric', month: '2-digit', day: '2-digit'
                }).format(new Date());
                let currentDay = kabulDate();
                setInterval(() => {
                    const day = kabulDate();
                    if (day !== currentDay) window.location.reload();
                }, 30000);
            })();
        </script>
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                const sidebarToggle = document.getElementById("sidebarToggle");
                const sidebar = document.querySelector(".fb-sidebar");

                if (sidebarToggle && sidebar) {
                    sidebarToggle.addEventListener("click", function() {
                        sidebar.classList.toggle("show");
                    });
                }
            });
        </script>

        <script>
            window.addEventListener('wes:app-ready', function () {
                (function() {
                    const csrftoken = '{{ csrf_token() }}';

                function loadMeta(date, branchId, cb) {
                    $.get('{{ route('fx.rates.meta') }}', {
                        date,
                        branch_id: branchId
                    }, cb);
                }

                function fillTable($tbody, items, useField) {
                    $tbody.empty();
                    items.forEach(row => {
                        const disabled = row.is_base ? 'disabled' : '';
                        const val = row[useField].toFixed ? row[useField].toFixed(6) : row[useField];
                        $tbody.append(`
        <tr>
          <td style="width:35%"><strong>${row.code}</strong>${row.is_base ? ' <span class="badge bg-secondary ms-1">Base</span>' : ''}</td>
          <td>
            <input type="number" step="0.000001" class="form-control rate-input" data-cid="${row.id}" value="${row.is_base ? 1 : val}" ${disabled}>
          </td>
        </tr>
      `);
                    });
                }

                // OPEN
                $('#btnOpenBusinessDayRequired').on('click', function() { $('#btnFxOpen').trigger('click'); });

                $('#btnFxOpen').on('click', function() {
                    const d = $('#open_date').val() || (new Date()).toISOString().slice(0, 10);
                    const b = $('#open_branch_id').val() || 1;
                    loadMeta(d, b, res => {
                        fillTable($('#tblFxOpen tbody'), res.currencies, 'open');
                        new bootstrap.Modal(document.getElementById('modalFxOpen')).show();
                    });
                });

                $('#btnSaveOpen').on('click', function() {
                    const date = $('#open_date').val();
                    const branch_id = $('#open_branch_id').val();
                    const rates = {};
                    $('#tblFxOpen .rate-input').each(function() {
                        rates[$(this).data('cid')] = parseFloat($(this).val() || 0);
                    });
                    $.ajax({
                        url: '{{ route('fx.rates.open') }}',
                        method: 'POST',
                        data: {
                            _token: csrftoken,
                            date,
                            branch_id,
                            rates
                        },
                        success: res => {
                            Swal.fire({
                                icon: 'success',
                                title: 'Saved',
                                text: res.message,
                                timer: 1200,
                                showConfirmButton: false
                            });
                            bootstrap.Modal.getInstance(document.getElementById('modalFxOpen')).hide();
                            setTimeout(() => location.reload(), 1250);
                        },
                        error: xhr => {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: xhr.responseJSON?.message || 'Failed'
                            });
                        }
                    });
                });

                // CLOSE
                $('#btnFxClose').on('click', function() {
                    const d = $('#close_date').val() || (new Date()).toISOString().slice(0, 10);
                    const b = $('#close_branch_id').val() || 1;
                    loadMeta(d, b, res => {
                        fillTable($('#tblFxClose tbody'), res.currencies, 'close'); // default to effective close
                        const $counts = $('#fxCashCounts').empty();
                        const cash = res.reconciliation?.cash_book_by_currency || {};
                        const currencyCodes = Object.fromEntries(res.currencies.map(c => [String(c.id), c.code]));
                        Object.entries(cash).forEach(([id, amount]) => {
                            const $cell = $('<div class="col-md-6"></div>');
                            $('<label class="form-label"></label>').text(`${currencyCodes[id] || id} — ledger: ${Number(amount).toLocaleString()}`).appendTo($cell);
                            $('<input type="number" step="0.01" min="0" class="form-control fx-cash-count" required>')
                                .attr('data-cid', id).attr('placeholder', 'Physical cash counted').appendTo($cell);
                            $counts.append($cell);
                        });
                        if (res.reconciliation?.pending_approvals > 0) {
                            $counts.prepend($('<div class="col-12 text-danger small"></div>').text(`${res.reconciliation.pending_approvals} transactions require approval before closing.`));
                        }
                        new bootstrap.Modal(document.getElementById('modalFxClose')).show();
                    });
                });

                $('#btnSaveClose').on('click', function() {
                    const date = $('#close_date').val();
                    const branch_id = $('#close_branch_id').val();
                    const rates = {};
                    $('#tblFxClose .rate-input').each(function() {
                        rates[$(this).data('cid')] = parseFloat($(this).val() || 0);
                    });
                    const cash_counts = {};
                    let missingCount = false;
                    $('#fxCashCounts .fx-cash-count').each(function() {
                        if ($(this).val() === '') missingCount = true;
                        cash_counts[$(this).data('cid')] = $(this).val();
                    });
                    if (missingCount) {
                        Swal.fire({icon:'warning', title:'Cash count required', text:'Count each currency before closing.'});
                        return;
                    }
                    $.ajax({
                        url: '{{ route('fx.rates.close') }}',
                        method: 'POST',
                        data: {
                            _token: csrftoken,
                            date,
                            branch_id,
                            rates,
                            cash_counts,
                            closing_notes: $('#fxClosingNotes').val()
                        },
                        success: res => {
                            const v = res.valuation || {};
                            Swal.fire({
                                icon: 'success',
                                title: 'Closing saved',
                                html: `Realized: <b>${(v.realized||0).toLocaleString()}</b> AFN<br>
                Unrealized: <b>${(v.unrealized||0).toLocaleString()}</b> AFN<br>
                Temporary P&L: <b>${(v.temp_pnl||0).toLocaleString()}</b> AFN`,
                            }).then(() => location.reload()); // refresh so Balance Sheet picks up Temp P&L
                            bootstrap.Modal.getInstance(document.getElementById('modalFxClose')).hide();
                        },
                        error: xhr => {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: xhr.responseJSON?.message || 'Failed'
                            });
                        }
                    });
                });
                })();
            }, { once: true });
        </script>


        <script>
            window.addEventListener('wes:app-ready', function () {
            $(document).ready(function() {
                const $btn = $('#scrollToTopBtn');

                function toggleScrollButton() {
                    const innerScroll = $('.fb-main:visible').scrollTop() || 0;
                    const windowScroll = $(window).scrollTop() || 0;
                    (Math.max(innerScroll, windowScroll) > 200) ? $btn.fadeIn() : $btn.fadeOut();
                }

                $('.fb-main').on('scroll', toggleScrollButton);
                $(window).on('scroll', toggleScrollButton);

                $btn.on('click', function() {
                    $('.fb-main').stop(true).animate({ scrollTop: 0 }, 350);
                    $('html, body').stop(true).animate({ scrollTop: 0 }, 350);
                });
            });

            window.showSuccess = function(message = 'Success!', timer = 1500) {
                Swal.fire({
                    icon: 'success',
                    title: message,
                    width: '30rem',
                    position: 'center',
                    showConfirmButton: false,
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    backdrop: true,
                    timer: timer,
                    customClass: { popup: 'swal2-shadow' }
                });
            };

            window.showError = function(message = 'Something went wrong!') {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: message,
                    width: '30rem',
                    position: 'center',
                    showConfirmButton: true,
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    backdrop: true,
                    customClass: { popup: 'swal2-shadow' },
                    confirmButtonColor: '#dc3545'
                });
            };

            window.showConfirm = function(message, onConfirm) {
                Swal.fire({
                    title: 'Are you sure?',
                    text: message,
                    icon: 'warning',
                    width: '30rem',
                    position: 'center',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    backdrop: true,
                    customClass: { popup: 'swal2-shadow' },
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, confirm!'
                }).then((result) => {
                    if (result.isConfirmed && typeof onConfirm === 'function') {
                        onConfirm();
                    }
                });
            };
            }, { once: true });
        </script>

        {{-- Page-specific scripts (dashboard chart and organizational controls). --}}
        @stack('scripts')

        <button id="scrollToTopBtn" type="button" title="Back to Top" aria-label="Back to top">
            <i class="bx bx-up-arrow-alt"></i>
        </button>
    </body>
</html>
