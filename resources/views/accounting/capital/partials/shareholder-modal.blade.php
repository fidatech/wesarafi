<div class="modal fade" id="capitalShareholderModal" tabindex="-1" aria-labelledby="capitalShareholderModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form id="capitalShareholderForm" action="{{ route('hr.shareholders.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="capitalShareholderModalTitle">Add Shareholder</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger d-none" role="alert" id="capitalShareholderErrors"></div>
                    <div class="row g-3">
                        @foreach(['name' => 'Name', 'father_name' => "Father’s name", 'phone' => 'Phone', 'email' => 'Email', 'national_id' => 'National ID'] as $field => $label)
                            <div class="col-md-4"><label class="form-label" for="newShareholder_{{ $field }}">{{ $label }}{{ $field === 'name' ? ' *' : '' }}</label><input id="newShareholder_{{ $field }}" name="{{ $field }}" type="{{ $field === 'email' ? 'email' : 'text' }}" class="form-control" @required($field === 'name')></div>
                        @endforeach
                        <div class="col-md-4"><label for="newShareholder_joining_date" class="form-label">Joining date</label><input type="date" id="newShareholder_joining_date" name="joining_date" class="form-control" value="{{ now()->toDateString() }}"></div>
                        <div class="col-md-4"><label for="newShareholder_share_percentage" class="form-label">Share %</label><input type="number" id="newShareholder_share_percentage" name="share_percentage" class="form-control" min="0" max="100" step="0.01"></div>
                        <div class="col-md-4"><label for="newShareholder_profile_picture" class="form-label">Profile picture</label><input type="file" id="newShareholder_profile_picture" name="profile_picture" class="form-control" accept="image/*"></div>
                        <div class="col-md-4"><label for="newShareholder_is_active" class="form-label">Status</label><select id="newShareholder_is_active" name="is_active" class="form-select" required><option value="1" selected>Active</option><option value="0">Inactive</option></select></div>
                        <div class="col-12"><label for="newShareholder_address" class="form-label">Address</label><textarea id="newShareholder_address" name="address" rows="2" class="form-control"></textarea></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="saveCapitalShareholder">Save Shareholder</button>
                </div>
            </form>
        </div>
    </div>
</div>
