<div class="fb-inline-form-panel d-none" tabindex="-1" id="currencyCreateFormPanel">
    <div class="fb-inline-form-header">
        <h5 class="mb-0">Add Currency</h5>
        <button type="button" class="btn btn-label-secondary btn-sm" data-wes-form-close><i class="bx bx-arrow-back me-1"></i> Back to List</button>
    </div>
    <div class="fb-inline-form-body">
        <form method="POST" action="{{ route('configuration.currencies.store') }}">
            @csrf
            <div class="row g-3">
            <div class="col-sm-6 col-lg-4">
                <label class="form-label" for="currency-name">Name</label>
                <input type="text" name="name" id="currency-name" class="form-control" required>
            </div>

            <div class="col-sm-6 col-lg-4">
                <label class="form-label" for="currency-code">Currency Code</label>
                <input list="currency-codes" name="code" id="currency-code" class="form-control" placeholder="USD, AFN, RMB..." required>
                <datalist id="currency-codes">
                    @foreach ([
                        'USD', 'AFN', 'RMB', 'IRR', 'PKR', 'EUR', 'GBP', 'AED', 'INR', 'TRY',
                        'CAD', 'AUD', 'SAR', 'KWD', 'NPR', 'BDT', 'JPY', 'CHF', 'SEK', 'NOK',
                        'DKK', 'ZAR', 'MYR', 'SGD', 'THB', 'KRW', 'IDR', 'EGP', 'HKD', 'BRL',
                        'MXN', 'RUB', 'ILS', 'CZK', 'PLN', 'HUF', 'NGN', 'VND', 'TWD', 'ARS',
                        'QAR', 'OMR', 'BHD', 'LKR', 'JOD', 'DZD', 'MAD', 'LBP', 'SDG', 'DOP',
                        'UYU', 'CLP', 'PEN', 'COP', 'KZT', 'AZN', 'ALL', 'GEL', 'XAF', 'XOF',
                        'ETB', 'TZS', 'KES'
                    ] as $code)
                        <option value="{{ $code }}">{{ $code }}</option>
                    @endforeach
                </datalist>
            </div>

            <div class="col-sm-6 col-lg-4">
                <label class="form-label" for="currency-symbol">Symbol</label>
                <input type="text" name="symbol" id="currency-symbol" class="form-control">
            </div>

            <div class="col-sm-6 col-lg-4">
                <label class="form-label" for="currency-country">Country</label>
                <input type="text" name="country" id="currency-country" class="form-control">
            </div>

            <div class="col-sm-6 col-lg-4">
                <label class="form-label" for="currency-exchange-rate">Exchange Rate</label>
                <input type="number" step="0.000001" name="exchange_rate" id="currency-exchange-rate" class="form-control" required>
            </div>

            </div>

            <div class="d-flex align-items-center flex-wrap gap-3 mt-3">
            <div class="form-check mb-0">
                <input class="form-check-input" type="checkbox" name="is_default" id="is_default">
                <label class="form-check-label" for="is_default">Set as Default</label>
            </div>

            <div class="form-check mb-0">
                <input class="form-check-input" type="checkbox" name="is_active" id="is_active" checked>
                <label class="form-check-label" for="is_active">Active</label>
            </div>

            <input type="hidden" name="flag" id="flag-path">
            <img id="flag-preview" src="/assets/flags/unknown.svg" width="30" height="20" alt="Currency flag">

            </div>

            <div class="d-flex justify-content-end gap-2 border-top mt-3 pt-2">
                <button type="button" class="btn btn-outline-secondary" data-wes-form-close>Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>
