@extends('layouts.app')

@section('title', 'Transactions')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Transactions</h1>
        <div>
            <a href="#" class="btn btn-outline-primary">
                <i class="bi bi-download"></i> Export
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Institution</th>
                            <th>Reference</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($transactions as $transaction)
                            <tr>
                                <td>{{ $transaction->id }}</td>
                                <td>{{ $transaction->user->name ?? 'N/A' }}</td>
                                <td>
                                    {{ $transaction->type }}
                                </td>
                                <td>{{ number_format($transaction->amount, 2) }}</td>

                                <td>{{ $transaction->institution->name ?? 'N/A' }}</td>
                                <td>
                                    <small class="text-muted">{{ $transaction->reference }}</small>
                                </td>
                                <td>
                                    <span
                                        class="badge 
                                    @if ($transaction->status == 'Completed') bg-success
                                    @elseif($transaction->status == 'Approved') bg-primary
                                    @elseif($transaction->status == 'Failed') bg-danger
                                    @elseif($transaction->status == 'Pending') bg-warning
                                    @else bg-secondary @endif">
                                        {{ ucfirst($transaction->status) }}
                                    </span>
                                </td>
                                <td>{{ $transaction->created_at->format('M d, Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center">No transactions found</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>


        </div>
    </div>
@endsection
