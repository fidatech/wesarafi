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
      <label class="form-label">Full Name</label>
      <input type="text" name="full_name" class="form-control" required>
    </div>
    <div class="col-md-4">
      <label class="form-label">Father's Name</label>
      <input type="text" name="father_name" class="form-control">
    </div>
    <div class="col-md-4">
      <label class="form-label">Phone</label>
      <input type="text" name="phone" class="form-control">
    </div>
    <div class="col-md-4">
      <label class="form-label">Email</label>
      <input type="email" name="email" class="form-control">
    </div>
    <div class="col-md-4">
      <label class="form-label">Branch</label>
      <select class="form-select" required disabled>
        <option value="{{ Auth::user()->branch_id }}">{{ Auth::user()->branch->name }}</option>
      </select>
    </div>
  </div>
</div>

{{-- KYC Info --}}
<div class="custom-card mb-4">
  <div class="card-header fw-bold mb-3">KYC Information</div>
  <div class="card-body row g-3">
    <div class="col-md-4">
      <label class="form-label">Date of Birth</label>
      <input type="date" name="date_of_birth" class="form-control">
    </div>
    <div class="col-md-4">
      <label class="form-label">KYC Date</label>
      <input type="date" id="kyc_date" name="kyc_date" class="form-control" value="{{ \Carbon\Carbon::now()->addDays(1095)->format('Y-m-d') }}">
    </div>
    <div class="col-md-4">
      <label class="form-label">Nationality ID</label>
      <select name="nationality_id" class="form-select">
        <option value="">Select</option>
        <option value="1">Afghanistan</option>
        <option value="2">Iran</option>
        <option value="3">Pakistan</option>
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label">Occupation</label>
      <input type="text" name="occupation" class="form-control">
    </div>
    <div class="col-md-4">
      <label class="form-label">Monthly Income</label>
      <input type="number" name="monthly_income" class="form-control">
    </div>
    <div class="col-md-4">
      <label class="form-label">Address</label>
      <textarea name="address" class="form-control" rows="1"></textarea>
    </div>
  </div>
</div>

{{-- ID Info --}}
<div class="custom-card mb-4">
  <div class="card-header fw-bold mb-3">ID Information</div>
  <div class="card-body row g-3">
    <div class="col-md-4">
      <label class="form-label">ID Card Type</label>
      <select name="id_card_type" class="form-select">
        <option value="">Select</option>
        <option value="passport">Passport</option>
        <option value="etazkira">Etazkira (National ID)</option>
        <option value="paper_tazkira">Paper Tazkira (Old National ID)</option>
        <option value="other">Other</option>
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label">ID Card Number</label>
      <input type="text" name="id_card_number" class="form-control">
    </div>
    <div class="col-md-4">
      <label class="form-label">ID Card File</label>
      <input type="file" name="id_card_file" class="form-control">
    </div>
  </div>
</div>

{{-- Compliance Info --}}
{{-- Compliance Info --}}
<div class="custom-card mb-4">
  <div class="card-header fw-bold mb-3">Compliance</div>
  <div class="card-body row g-3">

    <div class="col-md-9 row g-3">
      <div class="col-md-6">
        <label class="form-label">Income Source</label>
        <select name="income_source" id="income_source" class="form-select">
          <option value="">Select</option>
          <option value="1">Salary</option>
          <option value="2">Business</option>
          <option value="3">Investment</option>
          <option value="4">Rent</option>
          <option value="5">Other</option>
        </select>
      </div>

      <div class="col-md-6">
        <label class="form-label">Purpose</label>
        <select name="purpose" id="purpose" class="form-select">
          <option value="">Select</option>
          <option value="exchange">Exchange</option>
          <option value="remittance">Remittance</option>
          <option value="both">Both</option>
        </select>
      </div>

      <div class="col-md-6">
        <label class="form-label">Geographical Area</label>
        <select name="geographical_area" id="geographical_area" class="form-select">
          <option value="">Select Geographical Area</option>
          <option value="central">Central Region</option>
          <option value="border">Border</option>
          <option value="beyond_border">Beyond Border</option>
        </select>
      </div>

      <div class="col-md-6">
        <label class="form-label">Distribution Channels</label>
        <select name="distribution_channels" id="distribution_channels" class="form-select">
          <option value="">Select</option>
          <option value="banks">Banks</option>
          <option value="non_banks">Non Banks</option>
        </select>
      </div>

      <div class="col-md-6">
        <label class="form-label">Transaction Volume</label>
        <select name="transaction_volume" id="transaction_volume" class="form-select">
          <option value="">Select</option>
          <option value="low">Low</option>
          <option value="moderate">Moderate</option>
          <option value="high">High</option>
        </select>
      </div>

      <div class="col-md-6">
        <label class="form-label">Risk Level</label>
        <select name="risk_level" id="danger" class="form-select">
          <option value="" selected>Select</option>
          <option value="low">Low</option>
          <option value="normal">Normal</option>
          <option value="high">High</option>
        </select>
      </div>

      <div class="col-md-6">
        <label class="form-label">Is Politically Exposed Person?</label><br>
        <input type="checkbox" name="is_pep" id="is_pep" value="1"> Yes
      </div>

      <div class="col-md-6">
        <label class="form-label" for="is_sanction_list_applied">Is Sanction List Applied? *</label><br>
        <input type="checkbox" name="is_sanction_list_applied" id="is_sanction_list_applied" value="1" checked required> Yes
      </div>
    </div>

    {{-- Risk Score Card --}}
    <div class="col-md-3 mt-9">
      <div class="card">
        <div class="card-header fw-bold">Risk Score</div>
        <div class="card-body mt-2">
          <div class="mb-3 d-flex justify-content-between"><span>Income Source</span><span id="incomeScore">0</span></div>
          <div class="mb-3 d-flex justify-content-between"><span>Purpose</span><span id="purposeScore">0</span></div>
          <div class="mb-3 d-flex justify-content-between"><span>Geographical Area</span><span id="geoScore">0</span></div>
          <div class="mb-3 d-flex justify-content-between"><span>Distribution Channels</span><span id="disScore">0</span></div>
          <div class="mb-3 d-flex justify-content-between"><span>Is Politically Exposed Person</span><span id="pepScore">0</span></div>
          <hr>
          <div class="mb-3 d-flex justify-content-between"><strong>Total</strong><strong id="totalScore">%0</strong></div>
        </div>
      </div>
    </div>
  </div>
</div>


{{-- Other Info --}}
<div class="custom-card mb-4">
  <div class="card-header fw-bold mb-3">Other Details</div>
  <div class="card-body row g-3">
    <div class="col-md-4">
      <label class="form-label">Profile Picture</label>
      <input type="file" name="profile_picture" class="form-control">
    </div>
    {{-- Profile Picture Preview --}}
<div class="col-md-2">
  <label class="form-label d-block">Preview</label>
  <img id="profile_preview" src="{{ asset('uploads/no_image.jpg') }}" alt="Profile" class="img-thumbnail" style="height: 60px; border-radius: 8px;">
</div>

    <div class="col-md-2">
      <label class="form-label">Status</label>
      <select name="is_active" class="form-select">
        <option value="1">Active</option>
        <option value="0">Inactive</option>
      </select>
    </div>
  </div>
</div>
