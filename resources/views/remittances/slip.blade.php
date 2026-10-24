<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ in_array(app()->getLocale(), ['fa','ps']) ? 'rtl' : 'ltr' }}">
<head><meta charset="utf-8"><title>{{ $remittance->reference }}</title>
<style>
@page { margin: 22px; }
body { font-family: DejaVu Sans,sans-serif; font-size: 10px; color: #243247; margin: 0; }
.slip { max-width: 620px; margin: auto; border: 1px solid #d5deea; }
.head { padding: 14px 16px; background: #1d5ca9; color: white; }
.reference { font-size: 17px; font-weight: bold; margin-top: 5px; }
.body { padding: 12px 16px; }
.grid { width: 100%; border-collapse: collapse; }
.grid td { padding: 7px 4px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
.label { color: #64748b; width: 42%; }
.notice { margin-top: 12px; padding: 10px; background: #f3f6fa; font-size: 9px; }
.actions { text-align: center; margin: 18px; }
@media print { .actions { display: none; } }
</style></head><body>
<div class="slip"><div class="head"><div>{{ config('app.name','WeSarafi') }}</div><div class="reference">{{ $remittance->reference }}</div></div><div class="body"><table class="grid">
<tr><td class="label">{{ __('Date / status') }}</td><td>{{ $remittance->date->toDateString() }} · {{ ucfirst($remittance->status) }}</td></tr>
<tr><td class="label">{{ __('Sender') }}</td><td>{{ $remittance->sender_name }}</td></tr>
<tr><td class="label">{{ __('Receiver') }}</td><td><strong>{{ $remittance->receiver_name }}</strong><br>{{ $remittance->receiver_phone }}</td></tr>
<tr><td class="label">{{ __('Receiver gets') }}</td><td><strong>{{ number_format($remittance->amount,2) }} {{ $remittance->currency?->code }}</strong></td></tr>
<tr><td class="label">{{ __('Total commission') }}</td><td>{{ number_format($remittance->commission,2) }} {{ $remittance->currency?->code }}</td></tr>
<tr><td class="label">{{ in_array($remittance->status,['approved','completed']) ? 'Total collected from sender' : 'Total to collect from sender' }}</td><td><strong>{{ number_format($remittance->total_collected,2) }} {{ $remittance->currency?->code }}</strong></td></tr>
<tr><td class="label">{{ __('Route') }}</td><td>{{ $remittance->fromBranch?->name }} →
@if($remittance->bridgeBranch)
{{ $remittance->bridgeBranch->name }} →
@endif
{{ $remittance->toBranch?->name }}</td></tr>
<tr><td class="label">{{ __('Paying branch contact') }}</td><td>{{ $remittance->toBranch?->address ?: '—' }}<br>{{ $remittance->toBranch?->phone }}</td></tr>
</table><div class="notice">{{ __('The receiver must present this reference and a valid ID at the paying branch. This instruction is payable only after approval. Payout status is shown above.') }}</div></div></div>
@unless(request()->boolean('pdf'))
<div class="actions"><button onclick="window.print()">{{ __('Print') }}</button> <a href="{{ route('remittances.slip', [$remittance, 'pdf'=>1]) }}">{{ __('Download PDF') }}</a></div>
@endunless
</body></html>
