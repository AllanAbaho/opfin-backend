@extends('layouts.app')

@section('title', 'Loan Applications')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Loan Applications</h1>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Loan Product</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Applied At</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($loanApplications as $application)
                            <tr>
                                <td>{{ $application->id }}</td>
                                <td>{{ $application->user->name ?? 'N/A' }}</td>
                                <td>{{ $application->loanProduct->name ?? 'N/A' }}</td>
                                <td>{{ number_format($application->amount, 2) }}</td>
                                <td>
                                    <span
                                        class="badge 
                                    @if ($application->status == 'approved') bg-success
                                    @elseif($application->status == 'rejected') bg-danger
                                    @elseif($application->status == 'pending') bg-warning
                                    @else bg-secondary @endif">
                                        {{ ucfirst($application->status) }}
                                    </span>
                                </td>
                                <td>{{ $application->created_at->format('M d, Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center">No loan applications found</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
