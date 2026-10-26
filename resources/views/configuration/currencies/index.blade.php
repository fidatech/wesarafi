@extends('layouts.layoutMaster')

@section('title', 'Currencies')

@section('vendor-style')
@endsection

@section('page-style')
<style>
    #currencyCreateFormPanel,
    #currencyEditFormPanel { max-width: 1040px; flex: 0 0 auto; }
</style>
@endsection

@section('content')
<div class="row g-3">
    {{-- Sidebar --}}
    <div class="col-lg-2">@include('layouts.menu.configuration-sidebar')</div>
    {{-- Main Content --}}
    <div class="col-lg-10 fb-content">
        {{-- Sticky Header --}}


        {{-- Table --}}
        <div class="fb-main">
            <div class="fb-table-wrapper">
                <div class="fb-header">
            <div class="d-flex justify-content-between align-items-center">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="{{ route('dashboard') }}" class="text-primary text-decoration-none">
                                <i class="bx bx-home-alt me-1"></i> Dashboard
                            </a>
                        </li>
                        <li class="breadcrumb-item active">Currencies</li>
                    </ol>
                </nav>
                <button class="btn btn-primary"
                    data-wes-form-target="#currencyCreateFormPanel">
                    <i class="bi bi-plus-circle me-1"></i> Add Currency
                </button>
            </div>
        </div>
                <h4 class="card-title mb-3">Currencies</h4>
                <div class="table-responsive wes-institutional-table">
                    <table class="table-bordered table-hover table text-center" id="currencies-table" style="width: 100%;">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>Symbol</th>
                                <th>Country</th>
                                <th>Exchange Rate</th>
                                <th>Status</th>
                                <th>Is Default?</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>

            {{-- Inline Forms --}}
            @include('configuration.currencies.create')
            @include('configuration.currencies.edit')
        </div>
    </div>
</div>
@endsection

@section('vendor-script')
    <!-- jQuery (must be first) -->

    <!-- Bootstrap + DataTables -->
@endsection

@section('page-script')
    <script>
        const currencyMeta = {
            USD: {
                symbol: "$",
                country: "United States",
                flag: "us"
            },
            AFN: {
                symbol: "؋",
                country: "Afghanistan",
                flag: "af"
            },
            RMB: {
                symbol: "¥",
                country: "China",
                flag: "cn"
            },
            CNY: {
                symbol: "¥",
                country: "China",
                flag: "cn"
            },
            IRR: {
                symbol: "﷼",
                country: "Iran",
                flag: "ir"
            },
            PKR: {
                symbol: "₨",
                country: "Pakistan",
                flag: "pk"
            },
            EUR: {
                symbol: "€",
                country: "Eurozone",
                flag: "eu"
            },
            GBP: {
                symbol: "£",
                country: "United Kingdom",
                flag: "gb"
            },
            AED: {
                symbol: "د.إ",
                country: "United Arab Emirates",
                flag: "ae"
            },
            INR: {
                symbol: "₹",
                country: "India",
                flag: "in"
            },
            TRY: {
                symbol: "₺",
                country: "Turkey",
                flag: "tr"
            },
            CAD: {
                symbol: "$",
                country: "Canada",
                flag: "ca"
            },
            AUD: {
                symbol: "$",
                country: "Australia",
                flag: "au"
            },
            SAR: {
                symbol: "﷼",
                country: "Saudi Arabia",
                flag: "sa"
            },
            KWD: {
                symbol: "د.ك",
                country: "Kuwait",
                flag: "kw"
            },
            NPR: {
                symbol: "रू",
                country: "Nepal",
                flag: "np"
            },
            BDT: {
                symbol: "৳",
                country: "Bangladesh",
                flag: "bd"
            },
            JPY: {
                symbol: "¥",
                country: "Japan",
                flag: "jp"
            },
            CHF: {
                symbol: "Fr",
                country: "Switzerland",
                flag: "ch"
            },
            SEK: {
                symbol: "kr",
                country: "Sweden",
                flag: "se"
            },
            NOK: {
                symbol: "kr",
                country: "Norway",
                flag: "no"
            },
            DKK: {
                symbol: "kr",
                country: "Denmark",
                flag: "dk"
            },
            ZAR: {
                symbol: "R",
                country: "South Africa",
                flag: "za"
            },
            MYR: {
                symbol: "RM",
                country: "Malaysia",
                flag: "my"
            },
            SGD: {
                symbol: "$",
                country: "Singapore",
                flag: "sg"
            },
            THB: {
                symbol: "฿",
                country: "Thailand",
                flag: "th"
            },
            KRW: {
                symbol: "₩",
                country: "South Korea",
                flag: "kr"
            },
            IDR: {
                symbol: "Rp",
                country: "Indonesia",
                flag: "id"
            },
            EGP: {
                symbol: "£",
                country: "Egypt",
                flag: "eg"
            },
            HKD: {
                symbol: "$",
                country: "Hong Kong",
                flag: "hk"
            },
            BRL: {
                symbol: "R$",
                country: "Brazil",
                flag: "br"
            },
            MXN: {
                symbol: "$",
                country: "Mexico",
                flag: "mx"
            },
            RUB: {
                symbol: "₽",
                country: "Russia",
                flag: "ru"
            },
            ILS: {
                symbol: "₪",
                country: "Israel",
                flag: "il"
            },
            CZK: {
                symbol: "Kč",
                country: "Czech Republic",
                flag: "cz"
            },
            PLN: {
                symbol: "zł",
                country: "Poland",
                flag: "pl"
            },
            HUF: {
                symbol: "Ft",
                country: "Hungary",
                flag: "hu"
            },
            NGN: {
                symbol: "₦",
                country: "Nigeria",
                flag: "ng"
            },
            VND: {
                symbol: "₫",
                country: "Vietnam",
                flag: "vn"
            },
            TWD: {
                symbol: "$",
                country: "Taiwan",
                flag: "tw"
            },
            ARS: {
                symbol: "$",
                country: "Argentina",
                flag: "ar"
            },
            QAR: {
                symbol: "﷼",
                country: "Qatar",
                flag: "qa"
            },
            OMR: {
                symbol: "﷼",
                country: "Oman",
                flag: "om"
            },
            BHD: {
                symbol: ".د.ب",
                country: "Bahrain",
                flag: "bh"
            },
            LKR: {
                symbol: "Rs",
                country: "Sri Lanka",
                flag: "lk"
            },
            JOD: {
                symbol: "د.ا",
                country: "Jordan",
                flag: "jo"
            },
            DZD: {
                symbol: "دج",
                country: "Algeria",
                flag: "dz"
            },
            MAD: {
                symbol: "د.م.",
                country: "Morocco",
                flag: "ma"
            },
            LBP: {
                symbol: "ل.ل",
                country: "Lebanon",
                flag: "lb"
            },
            SDG: {
                symbol: "ج.س.",
                country: "Sudan",
                flag: "sd"
            },
            DOP: {
                symbol: "RD$",
                country: "Dominican Republic",
                flag: "do"
            },
            UYU: {
                symbol: "$U",
                country: "Uruguay",
                flag: "uy"
            },
            CLP: {
                symbol: "$",
                country: "Chile",
                flag: "cl"
            },
            PEN: {
                symbol: "S/",
                country: "Peru",
                flag: "pe"
            },
            COP: {
                symbol: "$",
                country: "Colombia",
                flag: "co"
            },
            KZT: {
                symbol: "₸",
                country: "Kazakhstan",
                flag: "kz"
            },
            AZN: {
                symbol: "₼",
                country: "Azerbaijan",
                flag: "az"
            },
            ALL: {
                symbol: "L",
                country: "Albania",
                flag: "al"
            },
            GEL: {
                symbol: "₾",
                country: "Georgia",
                flag: "ge"
            },
            XAF: {
                symbol: "FCFA",
                country: "Central Africa",
                flag: "cm"
            },
            XOF: {
                symbol: "CFA",
                country: "West Africa",
                flag: "sn"
            },
            ETB: {
                symbol: "Br",
                country: "Ethiopia",
                flag: "et"
            },
            TZS: {
                symbol: "TSh",
                country: "Tanzania",
                flag: "tz"
            },
            KES: {
                symbol: "KSh",
                country: "Kenya",
                flag: "ke"
            }
        };

        $(function() {
            const table = $('#currencies-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: '{{ route('configuration.currencies.data') }}',
                columns: [{
                        data: null,
                        render: (data, type, row, meta) => meta.row + 1
                    },
                    {
                        data: 'name_with_flag',
                        name: 'name'
                    },
                    {
                        data: 'symbol',
                        name: 'symbol'
                    },
                    {
                        data: 'country',
                        name: 'country'
                    },
                    {
                        data: 'exchange_rate',
                        name: 'exchange_rate'
                    },
                    {
                        data: 'status',
                        name: 'is_active'
                    },
                    {
                        data: 'is_default',
                        name: 'is_default',
                        render: data => data ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle-fill text-danger"></i>'
                    },
                    {
                        data: 'actions',
                        name: 'actions'
                    }
                ],
                order: [
                    [3, 'asc']
                ],
                pageLength: 10,
                searching: false,
                lengthChange: false,
                info: false,
                columnDefs: [{
                    targets: [1, 4, 5],
                    orderable: false
                }],
                responsive: true
            });


            $(document).on('click', '.btn-edit-currency', function() {
                const id = $(this).data('id');
                $.get(`/configuration/currencies/${id}/edit`, function(currency) {
                    $('#edit_currency_id').val(currency.id);
                    $('#edit_name').val(currency.name);
                    $('#edit_code').val(currency.code);
                    $('#edit_symbol').val(currency.symbol);
                    $('#edit_country').val(currency.country);
                    $('#edit_exchange_rate').val(currency.exchange_rate);
                    $('#edit_is_default').prop('checked', currency.is_default);
                    $('#edit_is_active').prop('checked', currency.is_active);

                    const flagPath = currency.flag ?? `/assets/flags/${currency.code.toLowerCase()}.svg`;
                    $('#edit_flag_preview').attr('src', '/' + flagPath);

                    // Auto-fill symbol/country from metadata if blank
                    const meta = currencyMeta[currency.code.toUpperCase()];
                    if (meta) {
                        if (!currency.symbol) $('#edit_symbol').val(meta.symbol);
                        if (!currency.country) $('#edit_country').val(meta.country);
                    }

                    $('#editCurrencyForm').attr('action', `/configuration/currencies/${currency.id}`);
                    wesOpenInlineForm('#currencyEditFormPanel');
                }).fail(function() {
                    alert('❌ Failed to load currency data.');
                });
            });

            $(document).on('click', '.btn-delete-currency', function() {
                const id = $(this).data('id');

                Swal.fire({
                    title: 'Are you sure?',
                    text: "This will permanently delete the currency!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, delete it!',
                    timer: 10000,
                    timerProgressBar: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: `/configuration/currencies/${id}`,
                            type: 'POST',
                            data: {
                                _method: 'DELETE',
                                _token: '{{ csrf_token() }}'
                            },
                            success: function(response) {
                                $('#currencies-table').DataTable().ajax.reload();
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Deleted!',
                                    text: 'Currency has been deleted.',
                                    timer: 3000,
                                    showConfirmButton: false
                                });
                            },
                            error: function(xhr) {
                                console.log(xhr.responseJSON.message);

                                Swal.fire({
                                    icon: 'error',
                                    title: 'Oops...',
                                    text: 'Something went wrong!',
                                    timer: 3000,
                                    showConfirmButton: false
                                });
                            }
                        });
                    }
                });
            });

            $('#currency-code').on('keyup change', function() {
                const code = this.value.trim().toUpperCase();
                const meta = currencyMeta[code];

                const flag = `/assets/flags/${code.substring(0, 2)}.svg`;
                $('#flag-preview').attr('src', flag);
                $('#flag-path').val(flag);

                if (meta) {
                    $('[name="symbol"]').val(meta.symbol);
                    $('[name="country"]').val(meta.country);
                }
            });
        });
    </script>
@endsection
