<div class="fb-sidebar">
    <h6 class="text-muted ps-4">{{ __('Remittances') }}</h6>
    @foreach ([['New Remittance', 'remittances.create', []], ['All', 'remittances.index', []], ['Outgoing', 'remittances.direction', ['outgoing']], ['Incoming', 'remittances.direction', ['incoming']], ['Branch Transfers', 'remittances.direction', ['transfer']], ['Pending', 'remittances.status', ['pending']], ['Rejected', 'remittances.status', ['rejected']]] as $i)
        <button onclick="window.location='{{ route($i[1], $i[2]) }}'"><i class="bx bx-mail-send me-2"></i>{{ __($i[0]) }}</button>
    @endforeach
</div>
