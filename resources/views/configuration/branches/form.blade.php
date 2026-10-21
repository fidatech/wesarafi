<div class="fb-inline-form-panel d-none" tabindex="-1" id="branchForm">
    <div class="fb-inline-form-header">
        <h5 class="mb-0" id="formTitle">Add Branch</h5>
        <button type="button" class="btn btn-label-secondary btn-sm" data-wes-form-close><i class="bx bx-arrow-back me-1"></i> Back to List</button>
    </div>
    <div class="fb-inline-form-body">
        <form id="branchFormSubmit">
            @csrf
            <input type="hidden" name="id" id="branch_id">

            <div class="row g-3">
                <div class="col-sm-6 col-lg-4">
                    <label class="form-label" for="name">Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="name" id="name">
                    <div class="invalid-feedback" id="error-name"></div>
                </div>
                <div class="col-sm-6 col-lg-4">
                    <label class="form-label" for="type">Type <span class="text-danger">*</span></label>
                    <select class="form-select" name="type" id="type">
                        <option value="">-- Select Type --</option>
                        <option value="independent">Independent</option>
                        <option value="dependent">Dependent</option>
                        <option value="agent">Agent</option>
                    </select>
                    <div class="invalid-feedback" id="error-type"></div>
                </div>
                <div class="col-sm-6 col-lg-4">
                    <label class="form-label" for="province_id">Province</label>
                    <select class="form-select" name="province_id" id="province_id">
                        <option value="">-- Select Province --</option>
                        @foreach ($provinces as $province)
                            <option value="{{ $province->id }}">{{ $province->name_en }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-lg-4">
                    <label class="form-label" for="phone">Phone</label>
                    <input type="text" class="form-control" name="phone" id="phone">
                </div>
                <div class="col-sm-6 col-lg-8">
                    <label class="form-label" for="address">Address</label>
                    <textarea class="form-control" name="address" id="address" rows="1"></textarea>
                </div>
            </div>

            <div class="mt-3"><label class="form-label" for="allows_direct_remittance">Remittance access</label><select id="allows_direct_remittance" name="allows_direct_remittance" class="form-select"><option value="1">Allow direct transfers when the branch has an active user</option><option value="0">Route through Main Branch</option></select></div>
            <fieldset class="border-top mt-3 pt-3" id="userForm">
                <legend class="float-none w-auto fs-6 fw-semibold mb-2">Create Branch User (optional)</legend><p class="text-muted">Leave all three fields empty for an offline branch. Its remittances will be coordinated through Main Branch.</p>
                <div class="row g-3">
                    <div class="col-sm-6 col-lg-4">
                        <label class="form-label" for="user_name">Username</label>
                        <input type="text" class="form-control" name="user_name" id="user_name" placeholder="Username">
                        <div class="invalid-feedback" id="error-user_name"></div>
                    </div>
                    <div class="col-sm-6 col-lg-4">
                        <label class="form-label" for="user_email">Email</label>
                        <input type="email" class="form-control" name="user_email" id="user_email" placeholder="Email">
                        <div class="invalid-feedback" id="error-user_email"></div>
                    </div>
                    <div class="col-sm-6 col-lg-4">
                        <label class="form-label" for="user_password">Password</label>
                        <input type="password" class="form-control" name="user_password" id="user_password" placeholder="Password">
                        <div class="invalid-feedback" id="error-user_password"></div>
                    </div>
                </div>
            </fieldset>

            <div class="d-flex justify-content-end gap-2 border-top mt-3 pt-2">
                <button type="button" class="btn btn-outline-secondary" data-wes-form-close>Cancel</button>
                <button type="submit" class="btn btn-success">Save Branch</button>
            </div>
        </form>
    </div>
</div>
