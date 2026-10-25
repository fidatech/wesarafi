<div class="row g-3">
    <input type="hidden" name="lines_json" id="lines_json">

  <div class="col-md-4">
    <label class="form-label">Type <span class="text-danger">*</span></label>
    <select name="type" class="form-select" required>
      <option value="purchase_from_dab">Purchase from DAB</option>
      <option value="sell_to_customer">Sell to Customer</option>
      <option value="sell_to_dab">Sell to DAB</option>
    </select>
  </div>

  <div class="col-md-4">
    <label class="form-label">Branch <span class="text-danger">*</span></label>
    <select name="branch_id" class="form-select" required>
      <option value="">Select Branch</option>
      @foreach($branches as $b)
        <option value="{{ $b->id }}">{{ $b->name }}</option>
      @endforeach
    </select>
  </div>

  <div class="col-md-4">
    <label class="form-label">Counterparty Account <span class="text-danger">*</span></label>
    <select name="account_id" class="form-select" required>
      <option value="">Select Account</option>
      @foreach($accounts as $acc)
        <option value="{{ $acc->id }}">{{ $acc->full_name ?? $acc->name }}</option>
      @endforeach
    </select>
    <small class="text-muted">DAB for bank auctions; corporate customer for sells.</small>
  </div>

  <div class="col-12">
    <label class="form-label d-flex justify-content-between align-items-center">
      <span>Lines (Give → Get)</span>
      <button type="button" class="btn btn-sm btn-outline-primary" id="addLine"><i class="bx bx-plus"></i> Add Line</button>
    </label>

    <div class="table-responsive wes-institutional-table">
      <table class="table table-sm table-bordered align-middle" id="linesTable">
        <thead class="table-light text-center">
          <tr>
            <th style="width:22%">Give Currency</th>
            <th style="width:18%">Give Amount</th>
            <th style="width:22%">Get Currency</th>
            <th style="width:18%">Rate (Get / 1 Give)</th>
            <th style="width:18%">Get Amount</th>
            <th style="width:2%"></th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>

    <small class="text-muted">Example: For AFN→USD, set Give=AFN, Get=USD. For USD→AFN, set Give=USD, Get=AFN. Mix as needed.</small>
  </div>

  <div class="col-md-4">
    <label class="form-label">Date <span class="text-danger">*</span></label>
    <input type="date" name="date" class="form-control" value="{{ now()->toDateString() }}" required>
  </div>

  <div class="col-md-8">
    <label class="form-label">Description</label>
    <textarea name="description" class="form-control" rows="2" placeholder="Auction reference, notes, etc."></textarea>
  </div>
</div>

<script>
(function(){
  const currencies = @json($currencies->map(fn($c)=>['id'=>$c->id,'code'=>$c->code]));
  const tbody = document.querySelector('#linesTable tbody');

  function currencySelect(name){
    const sel = document.createElement('select');
    sel.className = 'form-select';
    sel.name = name;
    sel.required = true;
    sel.innerHTML = `<option value="">Select</option>` + currencies.map(c=>`<option value="${c.id}">${c.code}</option>`).join('');
    return sel;
  }
  function input(name, step){
    const i = document.createElement('input');
    i.type = 'number'; i.step = step; i.name = name; i.className = 'form-control'; i.required = true;
    return i;
  }

  function addRow(data={}){
    const tr = document.createElement('tr');

    const tdGiveC = document.createElement('td'); const giveC = currencySelect('lines[][give_currency_id]'); tdGiveC.appendChild(giveC);
    const tdGiveA = document.createElement('td'); const giveA = input('lines[][give_amount]', '0.0001'); tdGiveA.appendChild(giveA);

    const tdGetC  = document.createElement('td'); const getC  = currencySelect('lines[][get_currency_id]');  tdGetC.appendChild(getC);
    const tdRate  = document.createElement('td'); const rate  = input('lines[][rate_get_per_give]', '0.000001'); tdRate.appendChild(rate);
    const tdGetA  = document.createElement('td'); const getA  = input('lines[][get_amount]', '0.0001'); tdGetA.appendChild(getA);

    const tdDel = document.createElement('td'); tdDel.className='text-center';
    tdDel.innerHTML = `<button type="button" class="btn btn-sm btn-outline-danger"><i class="bx bx-x"></i></button>`;

    tr.append(tdGiveC, tdGiveA, tdGetC, tdRate, tdGetA, tdDel);
    tbody.appendChild(tr);

    // prefill
    if (data.give_currency_id) giveC.value = data.give_currency_id;
    if (data.give_amount)      giveA.value = data.give_amount;
    if (data.get_currency_id)  getC.value  = data.get_currency_id;
    if (data.rate_get_per_give)rate.value  = data.rate_get_per_give;
    if (data.get_amount)       getA.value  = data.get_amount;

    // auto-calc get_amount from give_amount*rate; if user edits get_amount, keep it
    function recalc(){ const ga = parseFloat(giveA.value||0), r = parseFloat(rate.value||0); if(ga>0 && r>0){ getA.value = (ga*r).toFixed(4); } }
    giveA.addEventListener('input', recalc);
    rate.addEventListener('input', recalc);

    tdDel.querySelector('button').addEventListener('click', ()=> tr.remove());
  }

  // initial row
  addRow();

  document.getElementById('addLine').addEventListener('click', ()=> addRow());

  // When editing: the edit JS will GET the auction and you can rebuild rows accordingly (see page-script in index)
})();
</script>
