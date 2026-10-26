@extends('layouts.layoutMaster')

@section('title', 'Expenses')

@section('vendor-style')
@endsection

@section('content')
    <div class="row g-3">
        <div class="col-lg-2">@include('layouts.menu.accounting-sidebar')</div>
        <div class="col-lg-10 fb-content">

            <div class="fb-main">
                <div class="card">
                <div class="fb-header d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Expenses</h5>
                <button class="btn btn-primary" data-wes-form-target="#expenseFormPanel" onclick="resetExpenseForm()">
                    <i class="bi bi-plus-circle me-1"></i> Add Expense
                </button>
            </div>
                    <div class="card-body">
                        <div class="row mb-3" id="expenseSummary">
                            <div class="col-md-4 col-sm-6 mb-2">
                                <div class="card bg-secondary text-white shadow-sm">
                                    <div class="card-body p-2">
                                        <small class="text-white">Total Expense</small>
                                        <h5 class="fw-bold mb-0"><span id="sumTotal">0</span></h5>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 col-sm-6 mb-2">
                                <div class="card bg-success text-white shadow-sm">
                                    <div class="card-body p-2">
                                        <small class="text-white">Total Paid</small>
                                        <h5 class="fw-bold mb-0"><span id="sumPaid">0</span></h5>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 col-sm-6 mb-2">
                                <div class="card bg-danger text-white shadow-sm">
                                    <div class="card-body p-2">
                                        <small class="text-white">Remaining</small>
                                        <h5 class="fw-bold mb-0"><span id="sumRemaining">0</span></h5>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive wes-institutional-table">
                            <table class="table-sm table-bordered nowrap table-hover table" id="expenseTable" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>COA</th>
                                        <th>Account</th>
                                        <th>Amount</th>
                                        <th>Paid</th>
                                        <th>Remaining</th>
                                        <th>Currency</th>
                                        <th>Payment Type</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="fb-inline-form-panel d-none" tabindex="-1" id="expenseFormPanel">
        <div class="fb-inline-form-header">
            <h5 class="mb-0">Add Expense</h5>
            <button type="button" class="btn btn-label-secondary btn-sm" data-wes-form-close><i class="bx bx-arrow-back me-1"></i> Back to List</button>
        </div>
        <form id="expenseForm" class="fb-inline-form-body" method="POST">
            @csrf
            <input type="hidden" name="id" id="expense_id">
            <input type="hidden" name="branch_id" value="{{ Auth::user()->branch_id }}">
            <div class="mb-2">
                <label>Expense Type (COA)</label>
                <select name="chart_of_account_id" class="form-select" required>
                    @foreach ($chartOfAccounts as $coa)
                        <option value="{{ $coa->id }}">{{ $coa->account_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-2">
                <label>Currency</label>
                <select name="currency_id" class="form-select" required>
                    @foreach ($currencies as $cur)
                        <option value="{{ $cur->id }}">{{ $cur->code }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-2">
                <label>Amount</label>
                <input type="number" step="0.01" name="amount" class="form-control" required>
            </div>
            <div class="mb-2">
                <label>Payment Type</label>
                <select name="payment_type" class="form-select" id="paymentType" required>
                    <option value="cash">Paid at once</option>
                    <option value="credit">Unpaid / Partial</option>
                </select>
            </div>
            <div class="mb-2" id="accountSection">
                <label>Paid From Account</label>
                <select name="account_id" class="form-select">
                    @foreach ($accounts as $acc)
                        <option value="{{ $acc->id }}">{{ $acc->full_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="d-none mb-2" id="dueDateSection">
                <label>Due Date</label>
                <input type="date" name="due_date" class="form-control">
            </div>
            <div class="mb-2">
                <label>Date</label>
                <input type="date" name="date" class="form-control" required>
            </div>
            <div class="mb-2">
                <label>Description</label>
                <textarea name="description" class="form-control"></textarea>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-success">Save</button>
            </div>
        </form>
    </div>

    <!-- Payment Modal -->
    <div class="modal fade" id="paymentModal" tabindex="-1">
        <div class="modal-dialog">
            <form id="paymentForm" class="modal-content">
                @csrf
                <input type="hidden" name="expense_id" id="payment_expense_id">
                <input type="hidden" name="currency_id" id="payment_currency_id">
                <input type="hidden" name="branch_id" value="{{ auth()->user()->branch_id }}">
                <div class="modal-header">
                    <h5 class="modal-title">Make Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-2">
                    <div class="col-md-12">
                        <label>Account</label>
                        <select name="account_id" class="form-select" required>
                            @foreach ($accounts as $acc)
                                <option value="{{ $acc->id }}">{{ $acc->full_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label>Amount</label>
                        <input type="number" step="0.01" name="amount" class="form-control" id="payment_amount" required>
                    </div>
                    <div class="col-md-6">
                        <label>Date</label>
                        <input type="date" name="date" class="form-control" value="{{ now()->toDateString() }}" required>
                    </div>
                    <div class="col-md-12">
                        <label>Note</label>
                        <input type="text" name="note" class="form-control" placeholder="Optional">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success">Submit Payment</button>
                </div>
            </form>
        </div>
    </div>

@endsection

@section('vendor-script')
@endsection

@section('page-script')
    <script>
        window.resetExpenseForm = function () {
            const form = document.getElementById('expenseForm');
            form.reset();
            $('#expense_id').val('');
            $(form).find('select').trigger('change');
            $('#paymentType').trigger('change');
        };

        $(document).ready(function() {
            const table = $('#expenseTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: "{{ route('accounting.expenses.data') }}",
                drawCallback: function(settings) {
                    let api = this.api();
                    let total = 0,
                        paid = 0,
                        remaining = 0;

                    api.rows().data().each(function(row) {
                        total += parseFloat(row.amount || 0);
                        paid += parseFloat(row.paid || 0);
                        remaining += parseFloat(row.remaining || 0);
                    });

                    $('#sumTotal').text(total.toFixed(2));
                    $('#sumPaid').text(paid.toFixed(2));
                    $('#sumRemaining').text(remaining.toFixed(2));
                },
                columns: [{
                        data: 'DT_RowIndex',
                        name: 'DT_RowIndex',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'chart_of_account',
                        name: 'chart_of_account'
                    },
                    {
                        data: 'account',
                        name: 'account'
                    },
                    {
                        data: 'amount',
                        name: 'amount'
                    },
                    {
                        data: 'paid',
                        name: 'paid'
                    },
                    {
                        data: 'remaining',
                        name: 'remaining'
                    },
                    {
                        data: 'currency',
                        name: 'currency'
                    },
                    {
                        data: 'payment_type',
                        name: 'payment_type'
                    },
                    {
                        data: 'payment_status',
                        name: 'payment_status'
                    },
                    {
                        data: 'date',
                        name: 'date'
                    },
                    {
                        data: 'actions',
                        name: 'actions',
                        orderable: false,
                        searchable: false
                    }
                ]
            });

            // Toggle fields
            $('#paymentType').on('change', function() {
                let type = $(this).val();
                $('#accountSection').toggleClass('d-none', type === 'credit');
                $('#dueDateSection').toggleClass('d-none', type === 'cash');
            });

            // Submit via AJAX
            $('#expenseForm').on('submit', function(e) {
                e.preventDefault();
                let form = $(this);
                let id = $('#expense_id').val();
                let url = id ? `{{ url('accounting/expenses') }}/${id}/update` : `{{ route('accounting.expenses.store') }}`;
                $.ajax({
                    url: url,
                    method: 'POST',
                    data: form.serialize(),
                    success: function(res) {
                        Swal.fire({
                            icon: 'success',
                            title: res.message,
                            timer: 1000
                        });
                        wesCloseInlineForm('#expenseFormPanel');
                        form[0].reset();
                        table.ajax.reload();
                    },
                    error: function(xhr) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: xhr.responseJSON.message
                        });
                    }
                });
            });

            // Edit
            $('#expenseTable').on('click', '.editBtn', function() {
                let id = $(this).data('id');
                $.get(`{{ url('accounting/expenses') }}/${id}/edit`, function(data) {
                    $('#expense_id').val(data.id);
                    $('select[name="chart_of_account_id"]').val(data.chart_of_account_id);
                    $('select[name="currency_id"]').val(data.currency_id);
                    $('input[name="amount"]').val(data.amount);
                    $('select[name="payment_type"]').val(data.payment_type).trigger('change');
                    $('select[name="account_id"]').val(data.account_id);
                    $('input[name="due_date"]').val(data.due_date);
                    $('input[name="date"]').val(data.date);
                    $('textarea[name="description"]').val(data.description);
                    wesOpenInlineForm('#expenseFormPanel');
                });
            });

            // Delete
            $('#expenseTable').on('click', '.deleteBtn', function() {
                let id = $(this).data('id');
                Swal.fire({
                    title: 'Are you sure?',
                    showCancelButton: true,
                    confirmButtonText: 'Delete',
                }).then(result => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: `{{ url('accounting/expenses') }}/${id}`,
                            type: 'DELETE',
                            data: {
                                _token: "{{ csrf_token() }}"
                            },
                            success: function(res) {
                                Swal.fire({
                                    icon: 'success',
                                    title: res.message,
                                    timer: 1000
                                });
                                table.ajax.reload();
                            }
                        });
                    }
                });
            });

            // Open Pay Modal
            $('#expenseTable').on('click', '.payBtn', function() {
                const expenseId = $(this).data('id');
                const total = parseFloat($(this).data('amount'));
                const paid = parseFloat($(this).data('paid'));
                const due = (total - paid).toFixed(2);

                $('#payment_expense_id').val(expenseId);
                $('#payment_amount').val(due);

                // You may fetch currency from the row or API if needed
                $.get(`{{ url('accounting/expenses') }}/${expenseId}/edit`, function(data) {
                    $('#payment_currency_id').val(data.currency_id);
                    $('#paymentModal').modal('show');
                });
            });

            // Submit Payment
            $('#paymentForm').on('submit', function(e) {
                e.preventDefault();
                $.ajax({
                    url: "{{ route('accounting.expense_payments.store') }}",
                    type: 'POST',
                    data: $(this).serialize(),
                    success: function(res) {
                        Swal.fire({
                            icon: 'success',
                            title: res.message,
                            timer: 1000
                        });
                        $('#paymentModal').modal('hide');
                        $('#paymentForm')[0].reset();
                        table.ajax.reload();
                    },
                    error: function(xhr) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: xhr.responseJSON.message
                        });
                    }
                });
            });

        });
    </script>
@endsection
