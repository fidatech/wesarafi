@extends('layouts.layoutMaster')

@section('title', 'Liabilities')

@section('vendor-style')
@endsection

@section('content')
    <div class="row g-3">
        <div class="col-lg-2">@include('layouts.menu.accounting-sidebar')</div>
        <div class="col-lg-10 fb-content">
            <div class="fb-header">
                <div class="d-flex justify-content-between align-items-center">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item">
                                <a href="{{ route('dashboard') }}" class="text-primary text-decoration-none">
                                    <i class="bx bx-home-alt me-1"></i> Dashboard
                                </a>
                            </li>
                            <li class="breadcrumb-item active">Liabilities</li>
                        </ol>
                    </nav>
                    <button class="btn btn-primary" data-wes-form-target="#liabilityFormPanel" onclick="resetLiabilityForm()">
                        <i class="bx bx-plus me-1"></i> Add Liability
                    </button>
                </div>
            </div>

            <div class="fb-main">
                <div class="card">
                    <div class="card-body">
                        <div id="summaryCardsContainer">
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-3">
                                <select id="filterBranch" class="form-select">
                                    <option value="">All Branches</option>
                                    @foreach ($branches as $b)
                                        <option value="{{ $b->id }}">{{ $b->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select id="filterCurrency" class="form-select">
                                    <option value="">All Currencies</option>
                                    @foreach ($currencies as $c)
                                        <option value="{{ $c->id }}">{{ $c->code }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="table-responsive wes-institutional-table">
                            <table class="table-sm table-hover table-bordered nowrap table align-middle" id="liabilityTable">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Account</th>
                                        <th>Currency</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Branch</th>
                                        <th>Date</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Inline Form -->
    <div class="fb-inline-form-panel d-none" tabindex="-1" id="liabilityFormPanel">
        <div class="fb-inline-form-header">
            <h5 class="mb-0">Liability Form</h5>
            <button type="button" class="btn btn-label-secondary btn-sm" data-wes-form-close><i class="bx bx-arrow-back me-1"></i> Back to List</button>
        </div>
        <form id="liabilityForm" class="fb-inline-form-body">
            @csrf
            <input type="hidden" id="liability_id">
            <div class="mb-2">
                <label>Branch</label>
                <select name="branch_id" class="form-select" required>
                    <option value="">Select Branch</option>
                    @foreach ($branches as $b)
                        <option value="{{ $b->id }}">{{ $b->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-2">
                <label>Currency</label>
                <select name="currency_id" class="form-select" required>
                    <option value="">Select Currency</option>
                    @foreach ($currencies as $c)
                        <option value="{{ $c->id }}">{{ $c->code }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-2">
                <label>Chart of Account</label>
                <select name="chart_of_account_id" class="form-select" required>
                    <option value="">Select Chart of Account</option>
                    @foreach (App\Models\ChartOfAccount::where('type', 'liability')->get() as $coa)
                        <option value="{{ $coa->id }}">[{{ $coa->code }}] - {{ $coa->account_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-2">
                <label>Amount</label>
                <input type="number" step="0.01" name="amount" class="form-control" required>
            </div>
            <div class="mb-2">
                <label>Date</label>
                <input type="date" name="date" class="form-control" value="{{ now()->toDateString() }}" required>
            </div>
            <div class="mb-2">
                <label>Description</label>
                <textarea name="description" class="form-control" rows="2"></textarea>
            </div>
            <div class="d-grid">
                <button type="submit" class="btn btn-success">Save</button>
            </div>
        </form>
    </div>

    <!-- Payment Modal -->
    <div class="modal fade" id="paymentModal" tabindex="-1">
        <div class="modal-dialog">
            <form id="paymentForm" class="modal-content">
                @csrf
                <input type="hidden" name="liability_id" id="payment_liability_id">

                <div class="modal-header">
                    <h5 class="modal-title">Make Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body row g-2">
                    <div class="col-md-12">
                        <label>Account</label>
                        <select name="account_id" class="form-select" required>
                            @foreach (\App\Models\Account::where('type', 'branch')->where('branch_id', Auth::user()->branch_id)->get() as $acc)
                                <option value="{{ $acc->id }}">{{ $acc->full_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label>Currency</label>
                        <select name="currency_id" class="form-select" required>
                            @foreach (\App\Models\Currency::all() as $cur)
                                <option value="{{ $cur->id }}">{{ $cur->code }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label>Amount</label>
                        <input type="number" name="amount" class="form-control" step="0.01" required>
                    </div>

                    <div class="col-md-6">
                        <label>Date</label>
                        <input type="date" name="date" class="form-control" required>
                    </div>

                    <div class="col-md-6">
                        <label>Description</label>
                        <input type="text" name="description" class="form-control">
                    </div>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-primary" type="submit">Save</button>
                </div>
            </form>
        </div>
    </div>


    <!-- Payment History Modal -->
    <div class="modal fade" id="paymentHistoryModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Payment History</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="paymentHistoryContent">
                    Loading...
                </div>
            </div>
        </div>
    </div>

@endsection

@section('vendor-script')
@endsection

@section('page-script')

    <script>
        window.resetLiabilityForm = function () {
            const form = document.getElementById('liabilityForm');
            form.reset();
            $('#liability_id').val('');
            $(form).find('select').trigger('change');
        };
        function loadLiabilitySummaryCards() {
            $.get('{{ route('accounting.liabilities.summary') }}', function (res) {
                if (res.success) {
                    $('#summaryCardsContainer').html(res.html);
                }
            });
        }

        $(document).ready(function () {
            loadLiabilitySummaryCards();
        });

        function viewPayments(id) {
            $.get(`/accounting/liabilities/${id}/payments`, function(html) {
                $('#paymentHistoryContent').html(html);
                const modal = new bootstrap.Modal(document.getElementById('paymentHistoryModal'));
                modal.show();
            });
        }

        function editPayment(id) {
            $.get(`/accounting/liability-payments/${id}/edit`, function(res) {
                if (res.success) {
                    const p = res.payment;
                    $('#paymentForm').attr('data-id', p.id);
                    $('#payment_liability_id').val(p.liability_id);
                    $('#paymentForm [name=account_id]').val(p.account_id).trigger('change');
                    $('#paymentForm [name=currency_id]').val(p.currency_id);
                    $('#paymentForm [name=amount]').val(p.amount);
                    $('#paymentForm [name=date]').val(p.date);
                    $('#paymentForm [name=description]').val(p.description);

                    // Hide the payment history modal if it's open
                    const historyModalEl = document.getElementById('paymentHistoryModal');
                    const historyModal = bootstrap.Modal.getInstance(historyModalEl) || bootstrap.Modal.getOrCreateInstance(historyModalEl);
                    historyModal.hide();

                    // Show the payment modal after a short delay
                    setTimeout(() => {
                        const paymentModal = new bootstrap.Modal(document.getElementById('paymentModal'));
                        paymentModal.show();
                    }, 400); // allow hide animation to complete
                }
            });
        }


        function deletePayment(id) {
            const historyModalEl = document.getElementById('paymentHistoryModal');
            const historyModal = bootstrap.Modal.getInstance(historyModalEl) || bootstrap.Modal.getOrCreateInstance(historyModalEl);
            historyModal.hide();
            Swal.fire({
                icon: 'warning',
                title: 'Are you sure?',
                text: 'This will delete the payment.',
                showCancelButton: true,
                confirmButtonText: 'Yes, delete it'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: `/accounting/liability-payments/${id}`,
                        type: 'DELETE',
                        data: {
                            _token: '{{ csrf_token() }}'
                        },
                        success: function(res) {
                            if (res.success) {
                                table.ajax.reload();
                                Swal.fire({
                                    icon: 'success',
                                    title: res.message,
                                    timer: 1500,
                                    showConfirmButton: false
                                });
                                loadLiabilitySummaryCards();
                            } else {
                                Swal.fire('Error', res.message, 'error');
                            }
                        }
                    });
                }
            });
        }



        const table = $('#liabilityTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: "{{ route('accounting.liabilities.data') }}",
                type: 'POST',
                data: d => {
                    d._token = '{{ csrf_token() }}';
                    d.branch_id = $('#filterBranch').val();
                    d.currency_id = $('#filterCurrency').val();
                }
            },
            columns: [{
                    data: 'DT_RowIndex',
                    name: 'DT_RowIndex',
                    orderable: false,
                    searchable: false,
                    className: 'text-center'
                },
                {
                    data: 'account',
                    name: 'account'
                },
                {
                    data: 'currency',
                    name: 'currency'
                },
                {
                    data: 'amount',
                    name: 'amount'
                },
                {
                    data: 'status',
                    name: 'status'
                },
                {
                    data: 'branch',
                    name: 'branch'
                },
                {
                    data: 'date',
                    name: 'date'
                },
                {
                    data: 'action',
                    name: 'action',
                    orderable: false,
                    searchable: false
                }
            ]
        });

        $('#filterBranch, #filterCurrency').on('change', () => table.ajax.reload());

        $('#liabilityForm').submit(function(e) {
            e.preventDefault();
            const id = $('#liability_id').val();
            const url = id ? `/accounting/liabilities/${id}/update` : "{{ route('accounting.liabilities.store') }}";
            const formData = $(this).serialize();

            $.post(url, formData, function(res) {
                if (res.success) {
                    wesCloseInlineForm('#liabilityFormPanel');

                    table.ajax.reload();
                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: res.message,
                        timer: 1500,
                        showConfirmButton: false
                    });
                    loadLiabilitySummaryCards();
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            });
        });

        function editLiability(id) {
            $.get(`/accounting/liabilities/${id}/edit`, function(data) {
                $('#liability_id').val(data.id);
                $('#liabilityForm').find('[name=branch_id]').val(data.branch_id);
                $('#liabilityForm').find('[name=account_id]').val(data.account_id);
                $('#liabilityForm').find('[name=currency_id]').val(data.currency_id);
                $('#liabilityForm').find('[name=chart_of_account_id]').val(data.chart_of_account_id);
                $('#liabilityForm').find('[name=amount]').val(data.amount);
                $('#liabilityForm').find('[name=date]').val(data.date);
                $('#liabilityForm').find('[name=description]').val(data.description);
                wesOpenInlineForm('#liabilityFormPanel');
            });
        }

        function deleteLiability(id) {
            Swal.fire({
                icon: 'warning',
                title: 'Are you sure?',
                text: 'This will delete the liability.',
                showCancelButton: true,
                confirmButtonText: 'Yes, delete it'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: `/accounting/liabilities/${id}`,
                        type: 'DELETE',
                        data: {
                            _token: '{{ csrf_token() }}'
                        },
                        success: function(res) {
                            if (res.success) {
                                table.ajax.reload();
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Success',
                                    text: res.message,
                                    timer: 1500,
                                    showConfirmButton: false
                                });
                                loadLiabilitySummaryCards();
                            } else {
                                Swal.fire('Error', res.message, 'error');
                            }
                        }
                    });
                }
            });
        }

        // Handle payment form submit
        $('#paymentForm').submit(function(e) {
            e.preventDefault();
            const formData = $(this).serialize();
            const id = $(this).attr('data-id');

            const url = id ?
                `/accounting/liability-payments/${id}/update` :
                @json(route('accounting.liability-payments.store'));


            $.post(url, formData, function(res) {
                if (res.success) {
                    const modalEl = document.getElementById('paymentModal');
                    const modalInstance = bootstrap.Modal.getInstance(modalEl);
                    modalInstance.hide();

                    $('#paymentForm')[0].reset();
                    $('#paymentForm').removeAttr('data-id');
                    table.ajax.reload();

                    Swal.fire({
                        icon: 'success',
                        title: res.message,
                        timer: 1500,
                        showConfirmButton: false,
                    });
                    loadLiabilitySummaryCards();
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            });
        });




        // Trigger payment modal
        function makePayment(liabilityId) {
            $('#paymentForm')[0].reset();
            $('#paymentForm').removeAttr('data-id');
            $('#payment_liability_id').val(liabilityId);

            const historyModalEl = document.getElementById('paymentHistoryModal');
            const paymentModalEl = document.getElementById('paymentModal');

            // Close history modal safely
            const historyModal = bootstrap.Modal.getInstance(historyModalEl) || bootstrap.Modal.getOrCreateInstance(historyModalEl);
            historyModal.hide();

            // Ensure hide animation is done before showing new modal
            setTimeout(() => {
                const paymentModal = bootstrap.Modal.getOrCreateInstance(paymentModalEl);
                paymentModal.show();
            }, 400); // Adjust this if your modal fade transition is longer/shorter
        }
    </script>
@endsection
