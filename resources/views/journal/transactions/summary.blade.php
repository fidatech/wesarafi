<div class="row">
  @foreach ($summary as $currency => $totals)
    <div class="col-md-4 mb-3">
      <div class="card shadow-sm wes-journal-summary">
        <div class="card-body">
          <h6 class="text-muted mb-1">{{ $currency }}</h6>
          <div class="fs-5 mb-2"><strong>{{ $isTreasury ? 'Current treasury balance:' : 'Current balance:' }}</strong> {{ ($isTreasury ? number_format($totals['current_balance'], 2) : \App\Support\LedgerBalance::format($totals['current_balance'])) }}</div>
          <div><strong class="text-success">{{ $isTreasury ? 'Money out:' : 'Credit:' }}</strong> {{ number_format($totals['credit'], 2) }}</div>
          <div><strong class="text-body">{{ $isTreasury ? 'Money in:' : 'Debit:' }}</strong> {{ number_format($totals['debit'], 2) }}</div>
          <div><strong class="text-primary">{{ $isTreasury ? 'Net movement:' : 'Balance:' }}</strong> {{ ($isTreasury ? number_format($totals['balance'], 2) : \App\Support\LedgerBalance::format($totals['balance'])) }}</div>
        </div>
      </div>
    </div>
  @endforeach
</div>
