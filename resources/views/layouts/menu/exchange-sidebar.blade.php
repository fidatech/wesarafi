<div class="fb-sidebar">
    <h6 class="text-muted ps-4">{{ __('Exchange Menu') }}</h6>
    <button onclick="location='{{route('exchange.customers.index')}}'"><i class="bx bx-transfer me-2"></i>{{ __('Exchanges') }}</button>
    <button onclick="location='{{route('exchange.rates.index')}}'"><i class="bx bx-line-chart me-2"></i>{{ __('Exchange Rates') }}</button>
    <button onclick="location='{{route('exchange.auctions.index')}}'"><i class="bx bx-cart-download me-2"></i>{{ __('Auction Purchases') }}</button>
</div>
