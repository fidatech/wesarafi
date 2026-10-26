@extends('layouts.layoutMaster')

@section('title', 'Capital')

@section('vendor-style')
@endsection

@section('content')
    <div class="row g-3">
        <div class="col-lg-2">@include('layouts.menu.accounting-sidebar')</div>
        <div class="col-lg-10 fb-content">


            <div class="fb-main">
                <div class="card mb-4" id="createCard" style="display: none;">
                    <div class="card-header text-dark py-2">
                        <h4 class="mb-0"><i class="bx bx-plus-circle me-2"></i> Add Capital</h4>
                    </div>
                    <div class="card-body">
                        <form id="capitalForm" method="POST">
                            @csrf
                            <input type="hidden" name="id" id="capital_id">
                            <input type="hidden" name="_method" id="form_method" value="POST">
                            @include('accounting.capital.partials.form')
                            <div class="d-flex justify-content-end mt-3">
                                <button type="submit" class="btn btn-primary" id="submitBtn">
                                    <i class="bx bx-save me-2"></i> Save
                                </button>
                            </div>
                        </form>

                    </div>
                </div>

                <div class="fb-table-wrapper" id="tableCard">
                <div class="fb-header">
                <div class="d-flex justify-content-between align-items-center">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item">
                                <a href="{{ route('dashboard') }}" class="text-primary text-decoration-none">
                                    <i class="bx bx-home-alt me-1"></i> Dashboard
                                </a>
                            </li>
                            <li class="breadcrumb-item active">Capital</li>
                        </ol>
                    </nav>
                    <button class="btn btn-primary" id="toggleCreate">
                        <i class="bx bx-plus me-1"></i> Add Capital
                    </button>
                </div>
            </div>
                    <h4 class="card-title mb-3">Capital Entries</h4>
                    <div class="table-responsive wes-institutional-table">
                        <table class="table-sm table-hover table-bordered nowrap table align-middle" id="capitalTable">
                            <thead class="text-center">
                                <tr>
                                    <th>#</th>
                                    <th>Shareholder</th>
                                    <th>Amount</th>
                                    <th>Currency</th>
                                    <th>Branch</th>
                                    <th>Percentage</th>
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
@include('accounting.capital.partials.shareholder-modal')
@endsection

@section('vendor-script')
@endsection

@section('page-script')
<script>
    let isSubmitting = false;

    $(document).ready(function () {
        const $capitalForm = $('#capitalForm');
        const $branch = $('#capitalBranch');
        const $account = $('#capitalAccount');
        const $shareholder = $('#capitalShareholder');
        const accountOptions = $account.find('option[value!=""]').map(function () {
            return {id: this.value, text: this.text, branch: this.dataset.branchId};
        }).get();
        function syncBranchAccount(preferredAccount = null) {
            const options = accountOptions.filter(option => option.branch === $branch.val());
            $account.empty().append(new Option(options.length ? 'Select Account' : 'No branch account available', ''));
            options.forEach(option => $account.append(new Option(option.text, option.id)));
            const selected = options.find(option => option.id === String(preferredAccount)) || options[0];
            $account.val(selected?.id || '').trigger('change.select2');
        }
        $branch.on('change.capitalAccount', () => syncBranchAccount());
        $capitalForm.on('reset.capitalAccount', () => setTimeout(() => syncBranchAccount(), 0));
        syncBranchAccount();

        window.initializeSelect2($capitalForm[0]);
        if ($shareholder.hasClass('select2-hidden-accessible')) $shareholder.select2('destroy');
        const defaultMatcher = $.fn.select2.defaults.defaults.matcher;
        $shareholder.select2({
            width: '100%', dir: document.documentElement.dir || 'ltr',
            matcher: (params, data) => data.id === '__new_shareholder__' ? data : defaultMatcher(params, data)
        });
        $shareholder.on('change.capitalShare', function () {
            const percentage = this.selectedOptions[0]?.dataset.sharePercentage ?? '';
            $capitalForm.find('[name="share_percentage"]').val(percentage);
        });
        const shareholderModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('capitalShareholderModal'));
        $shareholder.on('select2:selecting', function (event) {
            if (event.params.args.data.id !== '__new_shareholder__') return;
            event.preventDefault();
            $shareholder.select2('close');
            $('#capitalShareholderForm')[0].reset();
            $('#capitalShareholderErrors').empty().addClass('d-none');
            shareholderModal.show();
        });
        $('#capitalShareholderModal').on('shown.bs.modal', () => $('#newShareholder_name').trigger('focus'));
        $('#capitalShareholderForm').on('submit', function (event) {
            event.preventDefault();
            const $button = $('#saveCapitalShareholder');
            if ($button.prop('disabled') || !this.reportValidity()) return;
            $button.prop('disabled', true).text('Saving…');
            $('#capitalShareholderErrors').empty().addClass('d-none');
            $.ajax({url: this.action, method: 'POST', data: new FormData(this), processData: false, contentType: false,
                success: function (response) {
                    const shareholder = response.shareholder;
                    const option = new Option(shareholder.name, shareholder.id, false, true);
                    option.dataset.sharePercentage = shareholder.share_percentage ?? '';
                    $shareholder.append(option).trigger('change');
                    shareholderModal.hide();
                },
                error: function (xhr) {
                    const errors = xhr.responseJSON?.errors;
                    $('#capitalShareholderErrors').text(errors ? Object.values(errors).flat().join(' ') : (xhr.responseJSON?.message || 'Unable to save shareholder. Please try again.')).removeClass('d-none');
                },
                complete: () => $button.prop('disabled', false).text('Save Shareholder')
            });
        });
        const table = $('#capitalTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: '{{ route('accounting.capital.data') }}',
            columns: [
                { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
                { data: 'shareholder' },
                { data: 'amount' },
                { data: 'currency' },
                { data: 'branch' },
                { data: 'share_percentage' },
                { data: 'date' },
                { data: 'actions', orderable: false, searchable: false }
            ]
        });

        // Toggle form
        $('#toggleCreate').click(function () {
            const isVisible = $('#createCard').is(':visible');

            if (isVisible) {
                $('#createCard').slideUp();
                $('#tableCard').slideDown();
                $('#capitalForm')[0].reset();
                $('#capital_id').val('');
                $('#form_method').val('POST');
                $(this).html('<i class="bx bx-plus me-1"></i> Add Capital');
            } else {
                $('#createCard').slideDown();
                $('#tableCard').slideUp();
                $(this).html('<i class="bx bx-table me-1"></i> Show Table');
            }
        });

        // Submit (Add + Edit)
        $('#capitalForm').on('submit', function (e) {
            e.preventDefault();
            if (isSubmitting) return;
            isSubmitting = true;

            const form = $(this);
            const submitBtn = $('#submitBtn');

            const id = $('#capital_id').val();
            const isEdit = id !== '';
            const url = isEdit ? `/accounting/capital/${id}` : `{{ route('accounting.capital.store') }}`;

            $('#form_method').val(isEdit ? 'PUT' : 'POST');

            submitBtn.prop('disabled', true).html('<i class="bx bx-loader bx-spin me-2"></i> Saving...');

            $.ajax({
                url: url,
                type: 'POST',
                data: form.serialize(),
                success: function (res) {
                    if (res.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success',
                            text: res.message,
                            timer: 1500,
                            showConfirmButton: false
                        });

                        form[0].reset();
                        $('#capital_id').val('');
                        $('#form_method').val('POST');
                        table.ajax.reload();

                        if ($('#createCard').is(':visible')) {
                            $('#toggleCreate').click();
                        }
                    }
                },
                error: function (xhr) {
                    const msg = xhr.responseJSON?.message || 'Something went wrong!';
                    Swal.fire({ icon: 'error', title: 'Error', text: msg });
                },
                complete: function () {
                    isSubmitting = false;
                    submitBtn.prop('disabled', false).html('<i class="bx bx-save me-2"></i> Save');
                }
            });
        });

        // Edit
        $(document).on('click', '.editCapital', function () {
            let id = $(this).data('id');
            $.get(`/accounting/capital/${id}`, function (data) {
                $('#capital_id').val(data.id);
                $shareholder.val(data.shareholder_id).trigger('change.select2');
                $branch.val(String(data.branch_id)).trigger('change.select2');
                syncBranchAccount(data.account_id);

                $capitalForm.find('[name="currency_id"]').val(data.currency_id).trigger('change.select2');
                $capitalForm.find('[name="amount"]').val(data.amount);
                $capitalForm.find('[name="share_percentage"]').val(data.share_percentage);
                $capitalForm.find('[name="date"]').val(data.date ? new Date(data.date).toISOString().slice(0, 10) : '');
                $capitalForm.find('[name="description"]').val(data.description);
                $('#form_method').val('PATCH');
                if (!$('#createCard').is(':visible')) $('#toggleCreate').click();
            });
        });

        // Delete
        $(document).on('click', '.deleteCapital', function () {
            let id = $(this).data('id');
            Swal.fire({
                title: 'Are you sure?',
                text: "This will delete the capital and its transaction.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                confirmButtonText: 'Yes, delete it!',
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: `/accounting/capital/${id}`,
                        type: 'DELETE',
                        data: { _token: '{{ csrf_token() }}' },
                        success: function (res) {
                            Swal.fire({
                                icon: 'success',
                                title: res.message,
                                timer: 2000,
                                showConfirmButton: false
                            });
                            table.ajax.reload();
                        },
                        error: function () {
                            Swal.fire('Error', 'Something went wrong!', 'error');
                        }
                    });
                }
            });
        });
    });
</script>

@endsection
