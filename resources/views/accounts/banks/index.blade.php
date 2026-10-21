@extends('layouts.layoutMaster')

@section('title', 'Banks')

@section('vendor-style')

@endsection

@section('content')
    <div class="row g-3">
        {{-- Sidebar --}}
        <div class="col-lg-2">@include('layouts.menu.accounts-sidebar')</div>
        {{-- Main Content --}}
        <div class="col-lg-10 fb-content">
            {{-- Sticky Header --}}
            <div class="fb-header">
                <div class="d-flex justify-content-between align-items-center">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item">
                                <a href="{{ route('dashboard') }}" class="text-primary text-decoration-none">
                                    <i class="bx bx-home-alt me-1"></i> Dashboard
                                </a>
                            </li>
                            <li class="breadcrumb-item active">Banks</li>
                        </ol>
                    </nav>

                    <button class="btn btn-primary" id="toggleCreate">
                        <i class="bx bx-user-plus me-1"></i> Add Bank
                    </button>
                </div>
            </div>

            {{-- Scrollable Table Area --}}
            <div class="fb-main">
                {{-- Create Form --}}
                <div class="card mb-4" id="createCard" style="display: none;">
                    <div class="card-header text-dark py-2">
                        <h4 class="mb-0"><i class="bx bx-user-plus me-2"></i> Create Bank</h4>
                    </div>
                    <div class="card-body">
                        <form id="customerForm" action="{{ route('accounts.banks.store') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="id" id="customer_id">
                            <input type="hidden" name="customer_type" value="bank">
                            @include('accounts.banks.partials.form')
                            <div class="d-flex justify-content-end mt-3">
                                <button type="submit" class="btn btn-primary" id="submitBtn"><i class="bx bx-save bx-xl me-2"></i> Save Bank</button>
                            </div>
                        </form>
                    </div>
                </div>

                {{-- Table + Pagination --}}
                <div class="fb-table-wrapper" id="tableCard">
                    <h4 class="card-title mb-3">Banks List</h4>
                    <div class="table-responsive">
                        <table class="table-sm table-hover table-bordered table align-middle nowrap" id="customersTable">
                            <thead class="text-center">
                                <tr>
                                    <th style="width: 3%">ID</th>
                                    <th style="width: 8%">Code</th>
                                    <th style="width: 11%">Bank Name</th>
                                    <th style="width: 11%">Bank Address</th>
                                    <th style="width: 11%">Account Number</th>
                                    <th style="width: 11%">Balances by currency</th>
                                    <th style="width: 5%">Status</th>
                                    <th style="width: 17%">Actions</th>
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
        $(document).ready(function() {

            $('#customersTable').DataTable({
                processing: true,
                serverSide: true,
                searching: false,
                lengthChange: false,
                ajax: '{{ route('accounts.banks.data') }}',
                columns: [{ data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
                    {
                        data: 'account_code'
                    },
                    {
                        data: 'full_name'
                    },
                    {
                        data: 'address'
                    },
                    {
                        data: 'account_number'
                    },
                    {
                        data: 'balance',
                        name: 'balance', orderable: false, searchable: false
                    },
                    {
                        data: 'is_active',
                        render: d => d ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>'
                    },
                    {
                        data: 'actions',
                        orderable: false,
                        searchable: false
                    }
                ]
            });

            $('#toggleCreate').click(function() {
                const isCreateVisible = $('#createCard').is(':visible');
                $('#customerForm .is-invalid').removeClass('is-invalid');

                if (isCreateVisible) {
                    $('#createCard').stop(true, true).slideUp(200);
                    $('#tableCard').stop(true, true).slideDown(200);
                    $(this).html('<i class="bx bx-plus me-2"></i> Add Customer');
                } else {
                    $('#createCard').stop(true, true).slideDown(200);
                    $('#tableCard').stop(true, true).slideUp(200);
                    const $scroller = $('.fb-main:visible');
                    if ($scroller.length) $scroller.stop(true).animate({ scrollTop: 0 }, 180);
                    $(this).html('<i class="bx bx-table me-2"></i> Show Table');

                    // Wait until createCard is fully shown before running updateScores
                    setTimeout(() => {
                        $('#income_source, #geographical_area, #purpose, #distribution_channels, #is_pep').off('.riskScore').on('change.riskScore input.riskScore', updateScores);
                        updateScores();
                    }, 350); // allow time for animation
                }
            });




            $.get("{{ route('accounts.banks.count') }}", function(data) {
                $('input[name="account_code"]').val('BANK-' + ('000' + (parseInt(data) + 1)).slice(-3));
            });

            $('#customerForm').on('submit', function(e) {
                e.preventDefault();

                let $form = $(this);
                let $btn = $('#submitBtn');
                $form.find('.is-invalid').removeClass('is-invalid');

                // Disable the button
                $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Saving...');

                let formData = new FormData(this);

                $.ajax({
                    url: $form.attr('action'),
                    method: 'POST',
                    timeout: 120000,
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        // Reset form or show success
                        Swal.fire({
                            icon: 'success',
                            title: 'Bank saved!',
                            timer: 1500,
                            showConfirmButton: false
                        });

                        $form[0].reset();
                        $('#customersTable').DataTable().ajax.reload();

                        $('#createCard').slideUp(300);
                        $('#tableCard').slideDown(300);
                        $('#toggleCreate').html('<i class="bx bx-plus me-2"></i> Add Bank');
                    },
                    error: function(xhr) {
                        // Show validation errors
                        if (xhr.status === 422) {
                            let errors = xhr.responseJSON.errors;
                            console.log(errors);

                            $.each(errors, function(key, msg) {
                                $(`[name="${key}"]`).addClass('is-invalid');
                            });
                            const $firstInvalid = $form.find('.is-invalid').first();
                            if ($firstInvalid.length) {
                                const $scroller = $firstInvalid.closest('.fb-main');
                                if ($scroller.length) {
                                    $scroller.stop(true).animate({
                                        scrollTop: Math.max(0, $scroller.scrollTop() + $firstInvalid.offset().top - $scroller.offset().top - 120)
                                    }, 220);
                                }
                                setTimeout(() => $firstInvalid.trigger('focus'), 250);
                            }
                            Swal.fire({
                                icon: 'error',
                                title: 'Validation Error',
                                text: 'Please check your input.',
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: xhr.responseJSON.message,
                            });
                        }
                    },
                    complete: function() {
                        $btn.prop('disabled', false).html('Save Bank');
                    }
                });
            });

        });
    </script>
    <script>
        function viewCustomer(id) {
            $.get(`/accounts/banks/${id}`, function(data) {
                Swal.fire({
                    title: 'Bank Details',
                    html: `
                        <strong>Bank Name:</strong> ${data.full_name}<br>
                        <strong>Bank Address:</strong> ${data.address}<br>
                        <strong>Account Number:</strong> ${data.account_number}<br>
                        <strong>Bank Type:</strong> ${data.type}<br>
                        <strong>Branch:</strong> ${data.branch_name}<br>
                        <strong>Account Code:</strong> ${data.account_code}
                    `,
                    icon: 'info',
                });
            });
        }

        editCustomer = function(id) {
            $.get(`/accounts/banks/${id}/edit`, function(data) {
                for (let key in data) {
                    const $el = $(`[name="${key}"]`);

                    if ($el.attr('type') === 'file') continue;

                    if ($el.attr('type') === 'checkbox') {
                        $el.prop('checked', !!data[key]);
                        continue;
                    }

                    if ($el.is('select')) {
                        $el.val(data[key]).trigger('change');
                        continue;
                    }

                    $el.val(data[key]);
                }

                $('#customer_id').val(data.id);

                // ✅ Profile Image Preview
                const profileUrl = data.profile_picture ?
                    `{{ asset('/${data.profile_picture}') }}` :
                    `uploads/no_image.jpg`;

                $('#profile_preview').attr('src', profileUrl);

                // ✅ ID Card File Download
                const idFile = data.id_card_file;
                const idFileUrl = idFile ? `{{ asset('/${idFile}') }}` : null;

                if (idFileUrl) {
                    $('#id_card_download')
                        .attr('href', idFileUrl)
                        .removeClass('disabled')
                        .show();
                } else {
                    $('#id_card_download')
                        .attr('href', '#')
                        .addClass('disabled')
                        .hide();
                }

                $('#createCard').slideDown(300);
                $('#tableCard').slideUp(300);
                $('#toggleCreate').html('<i class="bx bx-table me-2"></i> Show Table');
            });
        }



        function confirmDelete(id) {
            Swal.fire({
                title: 'Are you sure?',
                text: 'This will delete the customer permanently!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: `/accounts/banks/${id}`,
                        type: 'DELETE',
                        data: {
                            _token: $('meta[name="csrf-token"]').attr('content')
                        },
                        success: function() {
                            Swal.fire('Deleted!', 'Bank has been deleted.', 'success');
                            $('#customersTable').DataTable().ajax.reload();
                        },
                        error: function(xhr) {
                            Swal.fire('Error', JSON.parse(xhr.responseText).message, 'error');
                            // Swal.fire('Error', 'Something went wrong.', 'error');
                        }
                    });
                }
            });
        }
    </script>
@endsection
