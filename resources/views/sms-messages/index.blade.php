@extends('layouts.app')

@section('title', 'SMS Messages')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>SMS Messages</h1>

    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Recipient</th>
                            <th>Message</th>
                            <th>Status</th>
                            <th>Sent At</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($smsMessages as $sms)
                            <tr>
                                <td>{{ $sms->id }}</td>
                                <td>{{ $sms->to }}</td>
                                <td class="text-truncate" style="max-width: 300px;" title="{{ $sms->message }}">
                                    {{ Str::limit($sms->message, 50) }}
                                </td>
                                <td>
                                    <span
                                        class="badge 
                                        @switch($sms->status)
                                            @case('sent') bg-success @break
                                            @case('failed') bg-danger @break
                                            @case('queued') bg-warning @break
                                            @case('delivered') bg-info @break
                                            @default bg-secondary
                                        @endswitch">
                                        {{ ucfirst($sms->status) }}
                                    </span>
                                </td>
                                <td>
                                    {{ $sms->created_at->format('M d, Y H:i') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center">No SMS messages found</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($smsMessages->hasPages())
                <div class="d-flex justify-content-center mt-4">
                    {{ $smsMessages->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
