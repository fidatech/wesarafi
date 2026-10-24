<div class="fb-inline-form-panel d-none" tabindex="-1" id="userForm">
    <div class="fb-inline-form-header">
        <h5 class="mb-0" id="formTitle">Add User</h5>
        <button type="button" class="btn btn-label-secondary btn-sm" data-wes-form-close><i class="bx bx-arrow-back me-1"></i> Back to List</button>
    </div>
    <div class="fb-inline-form-body">
        <form id="userFormSubmit" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="id" id="user_id">

            <div class="row g-3">
                <div class="col-sm-6 col-lg-4">
                    <label for="name" class="form-label">Full Name</label>
                    <input type="text" class="form-control" name="name" id="name">
                    <div class="invalid-feedback" id="error-name"></div>
                </div>

                <div class="col-sm-6 col-lg-4">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" name="username" id="username">
                    <div class="invalid-feedback" id="error-username"></div>
                </div>

                <div class="col-sm-6 col-lg-4">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" name="email" id="email">
                    <div class="invalid-feedback" id="error-email"></div>
                </div>

                <div class="col-sm-6 col-lg-4">
                    <label for="branch_id" class="form-label">Branch</label>
                    <select class="form-select" name="branch_id" id="branch_id">
                        <option value="">Select Branch</option>
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                        @endforeach
                    </select>
                    <div class="invalid-feedback" id="error-branch_id"></div>
                </div>

                <div class="col-sm-6 col-lg-4">
                    <label for="role" class="form-label">Role</label>
                    <select class="form-select" name="role" id="role">
                        <option value="">Select Role</option>
                        @foreach($roles as $role)
                            <option value="{{ $role->name }}">{{ ucfirst($role->name) }}</option>
                        @endforeach
                    </select>
                    <div class="invalid-feedback" id="error-role"></div>
                </div>

                <div class="col-sm-6 col-lg-4">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" name="status" id="status">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                    <div class="invalid-feedback" id="error-status"></div>
                </div>
            </div>

            <div class="row g-3 border-top mt-3 pt-2 align-items-start">
            <div class="col-sm-6 col-lg-4 password-block">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" name="password" id="password" autocomplete="new-password">
                <div class="invalid-feedback" id="error-password"></div>
            </div>

            <div class="col-sm-6 col-lg-8">
                <label for="image" class="form-label">User Image</label>
                <div class="d-flex align-items-start gap-2">
                    <img id="previewImage" src="{{ asset('uploads/no_image.jpg') }}" class="rounded border" width="40" height="40" style="object-fit: cover;" alt="User photo preview">
                    <div class="flex-grow-1" style="min-width: 0;">
                        <input type="file" class="form-control" name="image" id="image" accept="image/*">
                        <div class="invalid-feedback" id="error-image"></div>
                    </div>
                </div>
            </div>

            </div>

            <div class="d-flex justify-content-end gap-2 border-top mt-3 pt-2">
                <button type="button" class="btn btn-outline-secondary" data-wes-form-close>Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="bx bx-check me-1"></i> Save User</button>
            </div>
        </form>
    </div>
</div>
