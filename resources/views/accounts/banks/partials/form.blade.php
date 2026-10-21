<style>
    .custom-card {
        box-shadow: 0 0.5rem 1.6rem rgba(0, 0, 0, 0.08);
    }

    .custom-card .card-header {
        background: linear-gradient(135deg, #4567b7 0%, #6495ed 100%);
        color: white;
        border-radius: 1rem 1rem 0 0;
        font-size: 1rem;
        padding: 0.75rem 1rem;
        box-shadow: 0 0.5rem 1.6rem rgba(0, 0, 0, 0.08);
    }
</style>
{{-- Basic Info --}}
<div class="custom-card mb-4 mt-3">
    <div class="card-header fw-bold mb-3">Basic Info</div>
    <div class="card-body row g-3">
        <div class="col-md-4">
            <label class="form-label">Account Code</label>
            <input type="text" name="account_code" class="form-control" required readonly>
        </div>
        <div class="col-md-4">
            <label class="form-label">Bank Name</label>
            <input type="text" name="full_name" class="form-control" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">Bank Address</label>
            <textarea name="address" class="form-control" rows="1"></textarea>
        </div>
        <div class="col-md-4">
            <label class="form-label">Account Number</label>
            <input type="text" name="account_number" class="form-control">
        </div>
        <div class="col-md-4">
            <label class="form-label">Bank Category</label>
            <select name="account_sub_category_id" class="form-select" required>
                <option value="">Select bank category</option>
                @foreach($bankSubcategories as $bankCategory)
                    <option value="{{ $bankCategory->id }}">{{ $bankCategory->name_en }}</option>
                @endforeach
            </select>
        </div>

        <div class="col-md-4">
            <label class="form-label">Status</label>
            <select name="is_active" class="form-select">
                <option value="1">Active</option>
                <option value="0">Inactive</option>
            </select>
        </div>
    </div>
</div>

