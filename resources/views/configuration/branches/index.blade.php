@extends('layouts.layoutMaster')

@section('title', 'Branches')

@section('vendor-style')
@endsection

@section('page-style')
<style>
    #branchForm { max-width: 1040px; flex: 0 0 auto; }
</style>
@endsection

@section('content')
<div class="row g-3">
    {{-- Sidebar --}}
    <div class="col-lg-2">@include('layouts.menu.configuration-sidebar')</div>
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
                        <li class="breadcrumb-item active">Branches</li>
                    </ol>
                </nav>
                <button class="btn btn-primary" data-wes-form-target="#branchForm" onclick="$('#formTitle').text('Add Branch'); $('#branchFormSubmit')[0].reset(); $('#branch_id').val(''); $('.create-user-group').show(); $('#userForm').show(); $('#branchFormSubmit select').trigger('change');">
                    <i class="bx bx-plus me-1"></i> Add Branch
                </button>
            </div>
        </div>

        {{-- Filter + Table --}}
        <div class="fb-main">
            <div class="fb-table-wrapper">
                <h4 class="card-title mb-3">Branches</h4>

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <div class="input-group">
                            <span class="input-group-text"><i class="bx bx-filter"></i></span>
                            <select id="filterType" class="form-select">
                                <option value="">Branch Type</option>
                                <option value="independent">Independent</option>
                                <option value="dependent">Dependent</option>
                                <option value="agent">Agent</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="input-group">
                            <span class="input-group-text"><i class="bx bx-map"></i></span>
                            <select id="filterProvince" class="form-select">
                                <option value="">Province</option>
                                @foreach ($provinces as $province)
                                <option value="{{ $province->name_en }}">{{ $province->name_en }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="input-group">
                            <span class="input-group-text"><i class="bx bx-search"></i></span>
                            <input type="text" id="searchBox" class="form-control" placeholder="Search name or phone...">
                        </div>
                    </div>
                </div>

                <div class="table-responsive wes-institutional-table">
                    <table class="table-hover table-borderless table align-middle" id="branches-table" style="width: 100%;">
                        <thead class="table-light sticky-top text-center">
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>Province</th>
                                <th>Phone</th>
                                <th>Type</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>

            {{-- Inline form --}}
            @include('configuration.branches.form')
        </div>
    </div>
</div>
@endsection

@section('vendor-script')
@endsection

@section('page-script')
<script>
    let table;
    $(function() {
        table = $('#branches-table').DataTable({
            processing: true
            , serverSide: true
            , searching: false
            , lengthChange: false
            , pageLength: 10
            , ajax: {
                url: "{{ route('configuration.branches.data') }}"
                , data: function(d) {
                    d.search = {
                        value: $('#searchBox').val()
                    };
                    d.type = $('#filterType').val();
                    d.province = $('#filterProvince').val();
                }
            }
            , columns: [{
                    data: 'DT_RowIndex'
                    , name: 'DT_RowIndex'
                    , className: 'text-center'
                    , orderable: false
                    , searchable: false
                }
                , {
                    data: 'name'
                    , name: 'name'
                }
                , {
                    data: 'province'
                    , name: 'province'
                }
                , {
                    data: 'phone'
                    , name: 'phone'
                }
                , {
                    data: 'type'
                    , name: 'type'
                    , className: 'text-center'
                    , render: function(data) {
                        const badgeMap = {
                            'independent': {
                                text: 'Independent'
                                , gradient: 'background: linear-gradient(to right, #1e88e5, #42a5f5); color: white;'
                            }
                            , 'dependent': {
                                text: 'Dependent'
                                , gradient: 'background: linear-gradient(to right, #ffa726, #fb8c00); color: white;'
                            }
                            , 'agent': {
                                text: 'Agent'
                                , gradient: 'background: linear-gradient(to right, #66bb6a, #43a047); color: white;'
                            }
                        };

                        const badge = badgeMap[data] || {
                            text: data
                            , gradient: 'background: linear-gradient(to right, #90a4ae, #78909c); color: white;'
                        };

                        return `
                                <span class="badge border-0 rounded-pill px-3 py-2 shadow-sm text-capitalize"
                                    style="${badge.gradient} font-size: 0.75rem;">
                                    ${badge.text}
                                </span>
                            `;
                    }
                }
                , {
                    data: 'actions'
                    , name: 'actions'
                    , orderable: false
                    , searchable: false
                    , className: 'text-center'
                }
            ]
        });

        $('#filterType, #filterProvince').on('change', function() {
            table.ajax.reload();
        });

        // Debounce server-side search; filters still apply immediately.
        let searchTimer;
        $('#searchBox').on('input', function() {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => table.ajax.reload(), 300);
        });
    });

</script>
<script>
    function editBranch(id) {
        $('#formTitle').text('Edit Branch');
        $('#branchFormSubmit')[0].reset();
        $('.is-invalid').removeClass('is-invalid');
        $('.invalid-feedback').text('');

        // Disable user creation block on edit
        $('.create-user-group').hide();

        $.get('/configuration/branches/' + id + '/edit', function(res) {
            $('#branch_id').val(res.id);
            $('#name').val(res.name);
            $('#province_id').val(res.province_id);
            $('#phone').val(res.phone);
            $('#address').val(res.address);
            $('#type').val(res.type);
            $('#userForm').hide();
            $('#allows_direct_remittance').val(res.allows_direct_remittance ? '1' : '0').trigger('change');
            wesOpenInlineForm('#branchForm');
        });
    }



    $('#branchFormSubmit').on('submit', function(e) {
        e.preventDefault();
        $('.is-invalid').removeClass('is-invalid');
        $('.invalid-feedback').text('');

        let id = $('#branch_id').val();
        let url = id ? `/configuration/branches/${id}` : `{{ route('configuration.branches.store') }}`;
        let method = id ? 'PUT' : 'POST';

        $.ajax({
            url: url
            , type: method
            , data: $(this).serialize()
            , success: function(res) {
                wesCloseInlineForm('#branchForm');

                Swal.fire({
                    icon: 'success'
                    , title: res.message
                    , width: '30rem', // or '500px'
                    position: 'center'
                    , showConfirmButton: false
                    , allowOutsideClick: false
                    , allowEscapeKey: false
                    , backdrop: true
                    , timer: 2000
                    , customClass: {
                        popup: 'swal2-shadow'
                    }
                }).then(() => {
                    table.ajax.reload(null, false);
                });

            }
            , error: function(xhr) {
                if (xhr.status === 422) {
                    console.log(xhr.responseJSON.errors);

                    let errors = xhr.responseJSON.errors;
                    for (let field in errors) {
                        $('#' + field).addClass('is-invalid');
                        $('#error-' + field).text(errors[field][0]);
                    }
                }
            }
        });
    });


    function confirmDelete(id) {
        Swal.fire({
            title: 'Are you sure?'
            , text: 'This action cannot be undone.'
            , icon: 'warning'
            , showCancelButton: true
            , confirmButtonColor: '#dc3545'
            , cancelButtonColor: '#6c757d'
            , confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: `/configuration/branches/${id}`
                    , type: 'DELETE'
                    , headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                    , success: function(res) {
                        table.ajax.reload(null, false);
                        showSuccess(res.message);
                    }
                    , error: function(err) {
                        showError('Failed to delete branch.');
                    }
                });
            }
        });
    }

</script>
@endsection

