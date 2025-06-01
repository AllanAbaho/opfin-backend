@extends('layouts.app')

@section('title', 'Loans')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Loans</h1>
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
                            <th>Repayment Amount</th>
                            <th>Duration</th>
                            <th>Status</th>
                            <th>Disbursed At</th>
                            <th>Repayment Start</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($loans as $loan)
                            <tr>
                                <td>{{ $loan->id }}</td>
                                <td>{{ $loan->user->name ?? 'N/A' }}</td>
                                <td>{{ $loan->loanProduct->name ?? 'N/A' }}</td>
                                <td>{{ $loan->amount }}</td>
                                <td>{{ $loan->repayment_amount }}</td>
                                <td>{{ $loan->duration }} days</td>
                                <td>
                                    <span
                                        class="badge 
                                        @switch($loan->status)
                                            @case('approved') bg-success @break
                                            @case('rejected') bg-danger @break
                                            @case('pending') bg-warning @break
                                            @case('disbursed') bg-info @break
                                            @case('paid') bg-primary @break
                                            @default bg-secondary
                                        @endswitch">
                                        {{ ucfirst($loan->status) }}
                                    </span>
                                </td>
                                <td>
                                    @if ($loan->disbursed_at)
                                        {{ $loan->disbursed_at }}
                                    @else
                                        N/A
                                    @endif
                                </td>
                                <td>
                                    @if ($loan->repayment_start_date)
                                        {{ $loan->repayment_start_date }}
                                    @else
                                        N/A
                                    @endif
                                </td>

                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center">No loans found</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($loans->hasPages())
                <div class="d-flex justify-content-center mt-4">
                    {{ $loans->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
