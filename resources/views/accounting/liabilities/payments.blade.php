@if ($liability->payments->count())
    <div class="table-responsive wes-institutional-table">
        <table class="table-sm table-hover table-bordered table align-middle">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Liability ID</th>
                    <th>Amount</th>
                    <th>Currency</th>
                    <th>Account</th>
                    <th>Description</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($liability->payments as $payment)
                    <tr>
                        <td>{{ $payment->date }}</td>
                        <td>{{ $payment->liability->id ?? '-' }}</td>
                        <td>{{ number_format($payment->amount, 2) }}</td>
                        <td>
                            <img src="{{ asset($payment->currency->flag) }}" width="20" class="me-1">
                            {{ $payment->currency->code }}
                        </td>
                        <td>{{ $payment->account->full_name ?? '-' }}</td>
                        <td>{{ $payment->description ?? '-' }}</td>
                        <td>
                            <button class="btn btn-warning btn-sm" onclick="editPayment({{ $payment->id }})">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                            <button class="btn btn-danger btn-sm" onclick="deletePayment({{ $payment->id }})">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@else
    <p class="text-muted">No payments found.</p>
@endif
