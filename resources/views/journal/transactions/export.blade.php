<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Branch Treasury Journal Export</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #000; padding: 6px; text-align: left; }
        th { background-color: #f5f5f5; }
    </style>
</head>
<body>
    <h2>Branch Treasury Journal</h2>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Branch / Account</th>
                <th>Linked Account</th>
                <th>Type</th>
                <th>COA</th>
                <th>Currency</th>
                <th>Debit / Money In</th><th>Credit / Money Out</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($transactions as $txn)
                <tr>
                    <td>{{ $txn->date?->format('Y-m-d') }}</td>
                    <td>{{ $txn->account?->full_name ?? $txn->branch?->name ?? '—' }}</td>
                    <td>{{ $txn->journalLinkedAccounts() }}</td>
                    <td>{{ $txn->transactionType?->name ?? ucfirst($txn->direction) }}</td>
                    <td>{{ $txn->chartOfAccount?->account_name }}</td>
                    <td>{{ $txn->currency?->code }}</td>
                    <td>{{ $txn->direction === 'debit' ? number_format($txn->amount, 2) : '—' }}</td><td>{{ $txn->direction === 'credit' ? number_format($txn->amount, 2) : '—' }}</td>
                    <td>{{ $txn->description }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
