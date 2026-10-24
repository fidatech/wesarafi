<!doctype html><html><head><meta charset="utf-8"><style>body{font-family:DejaVu Sans,sans-serif;font-size:10px;color:#263345}h1{font-size:18px}h2{font-size:12px}table{border-collapse:collapse;width:100%;margin:8px 0 16px}td,th{border:1px solid #cbd5e1;padding:5px;word-wrap:break-word}th{background:#eef2f6;text-align:left}tr{page-break-inside:avoid}.muted{color:#64748b}</style></head><body><h1>{{ $title }}</h1><p class="muted">{{ $context['from'] }} — {{ $context['to'] }} · {{ count($rows) }} results
@if(in_array($report, ['cumulative','large','rapid'])) · Threshold {{ number_format($context['threshold'],2) }} AFN 
@endif 
@if(in_array($report, ['inactive','kyc'])) · Review period {{ $context['days'] }} days 
@endif 
@if($report === 'rapid') · Window {{ $context['window_days'] }} days; outflow ≥ 80% of inflow 
@endif</p>
@foreach($warnings as $warning)<p>{{ $warning }}</p>
@endforeach 
@forelse($rows as $item)<h2>Result {{ $loop->iteration }}</h2><table><thead><tr>
@foreach($item['cells'] as $label=>$value)<th>{{ $label }}</th>
@endforeach</tr></thead><tbody><tr>
@foreach($item['cells'] as $value)<td>{{ is_float($value) ? number_format($value,2) : $value }}</td>
@endforeach</tr></tbody></table>
@if($item['details'])
@include('monitoring.transaction-details',['details'=>$item['details']])
@endif 
@empty<p>No matching results.</p>
@endforelse</body></html>
