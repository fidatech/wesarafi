@php
    use App\Models\Liability;
    use App\Models\LiabilityPayment;
@endphp

<div class="row mb-3">
    @foreach ($currencies as $currency)
        @php
            $total = Liability::where('currency_id', $currency->id)->sum('amount');
            $paid = LiabilityPayment::where('currency_id', $currency->id)->sum('amount');
            $remaining = $total - $paid;
        @endphp

        <div class="col-md-4">
            <div class="card bg-primary text-white shadow">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-bold">Currency:</span>
                        <span class="fw-bold">
                            <img src="{{ asset($currency->flag) }}" alt="{{ $currency->code }} Flag" width="20" height="14" class="me-1"
                                style="object-fit: cover; border-radius: 2px;">
                            {{ $currency->code }}
                        </span>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span>Total</span>
                        <span>{{ number_format($total, 2) }} {{ $currency->code }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span>Paid</span>
                        <span>{{ number_format($paid, 2) }} {{ $currency->code }}</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Remaining</span>
                        <span>{{ number_format($remaining, 2) }} {{ $currency->code }}</span>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>
