
{{-- Opening Modal --}}
<div class="modal fade" id="modalFxOpen" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Set Opening Rates</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="formFxOpen">
          @csrf
          <div class="row g-2 mb-3">
            <div class="col-md-4">
              <label class="form-label">Date</label>
              <input type="date" class="form-control" id="open_date" name="date" value="{{ now('Asia/Kabul')->toDateString() }}" readonly>
            </div>
            <div class="col-md-4">
              <label class="form-label">Branch</label>
              <select class="form-select" id="open_branch_id" name="branch_id" disabled>
                <option value="{{ auth()->user()->branch_id }}" selected>{{ auth()->user()->branch?->name ?? 'Unassigned branch' }}</option>
              </select>
            </div>
          </div>
          <div class="table-responsive wes-institutional-table">
            <table class="table table-sm table-bordered align-middle" id="tblFxOpen">
              <thead class="table-light">
                <tr><th>Currency</th><th>Rate (AFN per 1 unit)</th></tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button class="btn btn-primary" id="btnSaveOpen">Save Opening</button>
      </div>
    </div>
  </div>
</div>

{{-- Closing Modal --}}
<div class="modal fade" id="modalFxClose" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Set Closing Rates</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="formFxClose">
          @csrf
          <div class="row g-2 mb-3">
            <div class="col-md-4">
              <label class="form-label">Date</label>
              <input type="date" class="form-control" id="close_date" name="date" value="{{ now('Asia/Kabul')->toDateString() }}" readonly>
            </div>
            <div class="col-md-4">
              <label class="form-label">Branch</label>
              <select class="form-select" id="close_branch_id" name="branch_id" disabled>
                <option value="{{ auth()->user()->branch_id }}" selected>{{ auth()->user()->branch?->name ?? 'Unassigned branch' }}</option>
              </select>
            </div>
          </div>
          <div class="table-responsive wes-institutional-table">
            <table class="table table-sm table-bordered align-middle" id="tblFxClose">
              <thead class="table-light">
                <tr><th>Currency</th><th>Rate (AFN per 1 unit)</th></tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
        </form>
        <div class="mt-3 border rounded p-3">
          <h6 class="mb-2"><i class="bx bx-check-shield me-1"></i> Cash reconciliation</h6>
          <p class="text-muted small mb-2">Enter the independently counted physical cash for each currency. Differences must be resolved before closing.</p>
          <div id="fxCashCounts" class="row g-2"></div>
          <label for="fxClosingNotes" class="form-label mt-3">Closing notes (optional)</label>
          <textarea id="fxClosingNotes" class="form-control" rows="2" maxlength="2000"></textarea>
        </div>
        <small class="text-muted d-block">Closing will also create today’s valuation and Temporary FX P&L.</small>
      </div>
      <div class="modal-footer">
        <button class="btn btn-primary" id="btnSaveClose">Save Closing</button>
      </div>
    </div>
  </div>
</div>
