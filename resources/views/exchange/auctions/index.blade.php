@extends('layouts.layoutMaster')

@section('title', 'Auctions')

@section('vendor-style')
@endsection

@section('content')
    <div class="row g-3">
        <div class="col-lg-2">@include('layouts.menu.exchange-sidebar')</div>
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
                            <li class="breadcrumb-item active">Auctions</li>
                        </ol>
                    </nav>
                    <button class="btn btn-primary" id="toggleCreate">
                        <i class="bx bx-plus me-1"></i> New Auction Trade
                    </button>
                </div>
            </div>

            <div class="fb-main">
                <div class="card mb-4" id="createCard" style="display:none;">
                    <div class="card-header text-dark py-2">
                        <h4 class="mb-0"><i class="bx bx-transfer me-2"></i> New Auction Trade</h4>
                    </div>
                    <div class="card-body">
                        <form id="auctionForm" method="POST">
                            @csrf
                            <input type="hidden" name="id" id="auction_id">
                            <input type="hidden" name="_method" id="form_method" value="POST">
                            @include('exchange.auctions.partials.form')
                            <div class="d-flex justify-content-end mt-3">
                                <button type="submit" class="btn btn-primary" id="submitBtn">
                                    <i class="bx bx-save me-2"></i> Save
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="fb-table-wrapper" id="tableCard">
                    <h4 class="card-title mb-3">Auction Trades</h4>
                    <div class="table-responsive wes-institutional-table">
                        <table class="table-sm table-hover table-bordered nowrap table align-middle" id="auctionTable">
                            <thead class="text-center">
                                <tr>
                                    <th>#</th>
                                    <th>Type</th>
                                    <th>Branch</th>
                                    <th>Counterparty</th>
                                    <th>Summary</th>
                                    <th>Lines</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>

                        </table>
                    </div>
                    <small class="text-muted d-block mt-2">Rule: All purchases from DAB must be fully sold the same day.</small>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('vendor-script')
@endsection

@section('page-script')
    <script>
        let isSubmitting = false;

        $(function() {
            const table = $('#auctionTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: '{{ route('exchange.auctions.data') }}',
                columns: [{
                        data: 'DT_RowIndex',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'type_badge',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'branch'
                    },
                    {
                        data: 'counterparty'
                    },
                    {
                        data: 'summary',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'lines_count'
                    },
                    {
                        data: 'date'
                    },
                    {
                        data: 'actions',
                        orderable: false,
                        searchable: false
                    }
                ]
            });

            // Toggle
            $('#toggleCreate').on('click', function() {
                const v = $('#createCard').is(':visible');
                if (v) {
                    $('#createCard').slideUp();
                    $('#tableCard').slideDown();
                    $('#auctionForm')[0].reset();
                    $('#auction_id').val('');
                    $('#form_method').val('POST');
                    $(this).html('<i class="bx bx-plus me-1"></i> New Auction Trade');
                } else {
                    $('#createCard').slideDown();
                    $('#tableCard').slideUp();
                    $(this).html('<i class="bx bx-table me-1"></i> Show Table');
                }
            });

            // Auto calc AFN = rate * FX
            $('[name="fx_amount"], [name="rate_afn_per_fx"]').on('input', function() {
                const fx = parseFloat($('[name="fx_amount"]').val() || 0);
                const r = parseFloat($('[name="rate_afn_per_fx"]').val() || 0);
                $('[name="afn_amount"]').val((fx * r).toFixed(2));
            });

            // Submit
            $('#auctionForm').on('submit', function(e) {
                e.preventDefault();
                if (isSubmitting) return;
                isSubmitting = true;

                // collect lines from the table
                const lines = [];
                $('#linesTable tbody tr').each(function() {
                    const $tr = $(this);
                    const giveC = $tr.find('[name="lines[][give_currency_id]"]').val();
                    const giveA = $tr.find('[name="lines[][give_amount]"]').val();
                    const getC = $tr.find('[name="lines[][get_currency_id]"]').val();
                    const rate = $tr.find('[name="lines[][rate_get_per_give]"]').val();
                    const getA = $tr.find('[name="lines[][get_amount]"]').val();
                    const note = $tr.find('[name="lines[][note]"]').val?.() || null;

                    // keep only complete rows
                    if (giveC && getC && giveA && getA) {
                        lines.push({
                            give_currency_id: giveC,
                            give_amount: parseFloat(giveA),
                            get_currency_id: getC,
                            rate_get_per_give: rate ? parseFloat(rate) : null,
                            get_amount: parseFloat(getA),
                            note: note
                        });
                    }
                });

                if (lines.length === 0) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Add at least one line',
                        timer: 1200,
                        showConfirmButton: false
                    });
                    isSubmitting = false;
                    return;
                }

                // put JSON into hidden input so it’s posted
                $('#lines_json').val(JSON.stringify(lines));

                const id = $('#auction_id').val();
                const url = id ? `/exchange/auctions/${id}` : `{{ route('exchange.auctions.store') }}`;
                $('#form_method').val(id ? 'PUT' : 'POST');

                const btn = $('#submitBtn').prop('disabled', true).html('<i class="bx bx-loader bx-spin me-2"></i> Saving...');

                $.ajax({
                    url,
                    type: 'POST',
                    data: $(this).serialize(),
                    success: res => {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success',
                            text: res.message,
                            timer: 1200,
                            showConfirmButton: false
                        });
                        $('#auctionForm')[0].reset();
                        $('#auction_id').val('');
                        $('#form_method').val('POST');
                        $('#linesTable tbody').empty(); // clear lines
                        // add a fresh blank row if you want (call addRow())
                        table.ajax.reload();
                        if ($('#createCard').is(':visible')) $('#toggleCreate').click();
                    },
                    error: xhr => {
                        const msg = xhr.responseJSON?.message || 'Validation failed';
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: msg
                        });
                    },
                    complete: () => {
                        isSubmitting = false;
                        btn.prop('disabled', false).html('<i class="bx bx-save me-2"></i> Save');
                    }
                });
            });


            // Edit
            $(document).on('click', '.editAuction', function() {
                const id = $(this).data('id');
                $.get(`/exchange/auctions/${id}`, function(d) {
                    $('#auction_id').val(d.id);
                    $('[name="type"]').val(d.type);
                    $('[name="branch_id"]').val(d.branch_id);
                    $('[name="account_id"]').val(d.account_id);
                    $('[name="date"]').val(d.date ? new Date(d.date).toISOString().slice(0, 10) : '');
                    $('[name="description"]').val(d.description || '');

                    // rebuild lines
                    const tbody = document.querySelector('#linesTable tbody');
                    tbody.innerHTML = '';
                    (d.lines || []).forEach(l => {
                        // call addRow with data (function defined in the form partial)
                        // If not accessible, you can manually create rows like in addRow()
                    });
                    if (!$('#createCard').is(':visible')) $('#toggleCreate').click();
                });
            });

            // Delete
            $(document).on('click', '.deleteAuction', function() {
                const id = $(this).data('id');
                Swal.fire({
                    title: 'Are you sure?',
                    text: 'This will delete the auction and its transactions.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    confirmButtonText: 'Yes, delete'
                }).then(r => {
                    if (!r.isConfirmed) return;
                    $.ajax({
                        url: `/exchange/auctions/${id}`,
                        type: 'DELETE',
                        data: {
                            _token: '{{ csrf_token() }}'
                        },
                        success: res => {
                            Swal.fire({
                                icon: 'success',
                                title: res.message,
                                timer: 1200,
                                showConfirmButton: false
                            });
                            table.ajax.reload();
                        },
                        error: () => Swal.fire('Error', 'Something went wrong', 'error')
                    })
                });
            });

            // Type UI hints
            $('[name="type"]').on('change', function() {
                const t = $(this).val();
                // For purchase_from_dab, fx is incoming, AFN is outgoing.
                // For sells, fx is outgoing, AFN is incoming.
                $('#typeHelp').text(
                    t === 'purchase_from_dab' ?
                    'You pay AFN to DAB and receive USD/EUR.' :
                    'You receive AFN and give USD/EUR.'
                );
            }).trigger('change');
        });
    </script>
@endsection
