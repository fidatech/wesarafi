
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
              <input type="date" class="form-control" id="open_date" name="date" value="{{ now()->toDateString() }}">
            </div>
            <div class="col-md-4">
              <label class="form-label">Branch</label>
              <input type="number" class="form-control" id="open_branch_id" name="branch_id" value="{{ auth()->user()->branch_id ?? 1 }}">
            </div>
          </div>
          <div class="table-responsive">
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
              <input type="date" class="form-control" id="close_date" name="date" value="{{ now()->toDateString() }}">
            </div>
            <div class="col-md-4">
              <label class="form-label">Branch</label>
              <input type="number" class="form-control" id="close_branch_id" name="branch_id" value="{{ auth()->user()->branch_id ?? 1 }}">
            </div>
          </div>
          <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle" id="tblFxClose">
              <thead class="table-light">
                <tr><th>Currency</th><th>Rate (AFN per 1 unit)</th></tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
        </form>
        <small class="text-muted d-block">Closing will also create today’s valuation and Temporary FX P&L.</small>
      </div>
      <div class="modal-footer">
        <button class="btn btn-primary" id="btnSaveClose">Save Closing</button>
      </div>
    </div>
  </div>
</div>
