<div class="row g-3">
    {{-- Branch --}}
    <div class="col-md-4">
        <label class="form-label">Branch <span class="text-danger">*</span></label>
        <select name="branch_id" id="capitalBranch" class="form-select" required>
            <option value="">Select Branch</option>
            @foreach ($branches as $branch)
                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
            @endforeach
        </select>
    </div>

    {{-- Shareholder --}}
    <div class="col-md-4">
        <label class="form-label">Shareholder <span class="text-danger">*</span></label>
        <select name="shareholder_id" id="capitalShareholder" class="form-select" required>
            <option value="">Select Shareholder</option>
            <option value="__new_shareholder__">＋ Add new shareholder</option>
            @foreach ($shareholders as $sh)
                <option value="{{ $sh->id }}" data-share-percentage="{{ $sh->share_percentage }}">{{ $sh->name }}</option>
            @endforeach
        </select>
    </div>

    {{-- Account --}}
    <div class="col-md-4">
        <label class="form-label">Account <span class="text-danger">*</span></label>
        <select name="account_id" id="capitalAccount" class="form-select" required>
            <option value="">Select Account</option>
            @foreach ($accounts as $acc)
                <option value="{{ $acc->id }}" data-branch-id="{{ $acc->branch_id }}">{{ $acc->full_name }}</option>
            @endforeach
        </select>
    </div>

    {{-- Currency --}}
    <div class="col-md-4">
        <label class="form-label">Currency <span class="text-danger">*</span></label>
        <select name="currency_id" class="form-select" required>
            <option value="">Select Currency</option>
            @foreach ($currencies as $currency)
                <option value="{{ $currency->id }}">{{ $currency->code }}</option>
            @endforeach
        </select>
    </div>

    {{-- Amount --}}
    <div class="col-md-4">
        <label class="form-label">Amount <span class="text-danger">*</span></label>
        <input type="number" step="0.01" name="amount" class="form-control" required>
    </div>

    {{-- Share Percentage --}}
    <div class="col-md-4">
        <label class="form-label">Share % <span class="text-danger">*</span></label>
        <input type="number" step="0.01" name="share_percentage" class="form-control" required>
    </div>

    {{-- Date --}}
    <div class="col-md-4">
        <label class="form-label">Date <span class="text-danger">*</span></label>
        <input type="date" name="date" class="form-control" value="{{ now()->toDateString() }}" required>
    </div>

    {{-- Description --}}
    <div class="col-md-8">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control" rows="2"></textarea>
    </div>
</div>
