@extends('layouts.layoutMaster')

@section('title', 'Branch Treasury Journal')

@section('vendor-style')
@endsection

@section('content')
    <div class="row g-3">
        <div class="col-lg-2">@include('layouts.menu.journal-sidebar')</div>
        <div class="col-lg-10 fb-content">
            <div class="fb-header d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Branch Treasury Journal</h5>
                <a href="#" class="btn btn-outline-danger" id="exportPDF">
                    <i class="bx bx-download"></i> Export PDF
                </a>

            </div>

            <div class="fb-main">
                <div id="summaryCards"></div>

                <div class="card">
                    <div class="card-body">
                        <div class="row g-2 mb-3">
                            <div class="col-md-2">
                                <select id="filterEntryScope" class="form-select" aria-label="Journal entries" required>
                                    <option value="cash">Safe / Treasury</option>
                                    <option value="primary">Other ledger entries</option>
                                    <option value="branch">Branch accounts only</option>
                                    <option value="all">All ledger entries</option>
                                </select>
                            </div>
                            <div class="col-md-2"><select id="filterBranch" class="form-select"><option value="">Current / all branch</option>@foreach($branches as $branch)<option value="{{$branch->id}}" @selected(auth()->user()->branch_id==$branch->id)>{{$branch->name}}</option>@endforeach</select></div><div class="col-md-2">
                                <select id="filterAccount" class="select2 form-select">
                                    <option value="">All Linked Accounts</option>
                                    @foreach ($accounts as $acc)
                                        <option value="{{ $acc->id }}">{{ $acc->full_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select id="filterCurrency" class="select2 form-select">
                                    <option value="">All Currencies</option>
                                    @foreach ($currencies as $curr)
                                        <option value="{{ $curr->id }}">{{ $curr->code }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select id="filterType" class="select2 form-select">
                                    <option value="">All Types</option>
                                    @foreach ($types as $t)
                                        <option value="{{ $t->id }}">{{ $t->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <input type="text" id="filterDate" value="{{now()->toDateString()}} - {{now()->toDateString()}}" class="form-control" placeholder="Date Range">
                            </div>
                        </div>

                        <table id="transactionsTable" class="table-bordered table-striped table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Branch / Account</th>
                                    <th>Linked Account</th>
                                    <th>Type</th>
                                    <th>COA</th>
                                    <th>Currency</th>
                                    <th>Debit / Money In</th>
                                    <th>Credit / Money Out</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('vendor-script')
@endsection

@section('page-script')
    <script>
        $(function() {

            const table = $('#transactionsTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{{ route('journal.transactions.data') }}',
                    data: function(d) {
                        d.entry_scope = $('#filterEntryScope').val();
                        d.branch_id = $('#filterBranch').val();
                        d.account_id = $('#filterAccount').val();
                        d.currency_id = $('#filterCurrency').val();
                        d.type_id = $('#filterType').val();
                        d.date_range = $('#filterDate').val();
                    }
                },
                columns: [{
                        data: 'date',
                        name: 'date'
                    },
                    {
                        data: 'account',
                        name: 'account.full_name',
                        orderable: false
                    },
                    { data: 'linked_account', orderable: false, searchable: false },
                    {
                        data: 'type',
                        name: 'transactionType.name'
                    },
                    {
                        data: 'coa',
                        name: 'chartOfAccount.account_name'
                    },
                    {
                        data: 'currency',
                        name: 'currency.code'
                    },
                    {
                        data: 'money_in', name: 'amount', searchable: false
                    },
                    {
                        data: 'money_out', name: 'amount', searchable: false
                    },
                    {
                        data: 'description',
                        name: 'description'
                    }
                ]
            });

            function loadSummaryCards() {
                $.ajax({
                    url: '{{ route('journal.transactions.summary') }}',
                    data: {
                        entry_scope: $('#filterEntryScope').val(),
                        branch_id: $('#filterBranch').val(),
                        account_id: $('#filterAccount').val(),
                        currency_id: $('#filterCurrency').val(),
                        type_id: $('#filterType').val(),
                        date_range: $('#filterDate').val(),
                    },
                    success: function(data) {
                        $('#summaryCards').html(data);
                    }
                });
            }

            $('#filterEntryScope, #filterBranch, #filterAccount, #filterCurrency, #filterType').on('change', function() {
                table.ajax.reload();
                loadSummaryCards();
            });

            flatpickr('#filterDate', {
                mode: 'range',
                dateFormat: 'Y-m-d',
                conjunction: ' - ',
                defaultDate: [new Date(), new Date()],
                onClose: function(selectedDates, dateStr, instance) {
                    if (selectedDates.length === 2) {
                        const format = (date) => instance.formatDate(date, 'Y-m-d');
                        $('#filterDate').val(format(selectedDates[0]) + ' - ' + format(selectedDates[1]));
                        table.ajax.reload(null, false);
                        loadSummaryCards();
                    }
                }
            });

            loadSummaryCards();

            $('#exportPDF').click(function() {
                const params = new URLSearchParams({
                    entry_scope: $('#filterEntryScope').val(),
                        branch_id: $('#filterBranch').val(),
                        account_id: $('#filterAccount').val(),
                    currency_id: $('#filterCurrency').val(),
                    type_id: $('#filterType').val(),
                    date_range: $('#filterDate').val()
                });

                window.open('{{ route('journal.transactions.export') }}' + '?' + params.toString(), '_blank');
            });

        });
    </script>
@endsection
