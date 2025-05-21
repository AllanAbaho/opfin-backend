@extends('layouts.app')

@section('title', 'Users')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Users</h1>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Created At</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($users as $user)
                            <tr>
                                <td>{{ $user->id }}</td>
                                <td>
                                    <div class="d-flex align-items-center">

                                        <div>
                                            <h6 class="mb-0">{{ $user->name }}</h6>
                                            <small class="text-muted">{{ $user->email ?? 'N/A' }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td>{{ $user->phone ?? 'N/A' }}</td>
                                <td>
                                    <span
                                        class="badge 
                                    @if ($user->is_admin) bg-success
                                    @elseif($user->role == 'manager') bg-primary
                                    @elseif($user->role == 'agent') bg-info
                                    @else bg-secondary @endif">
                                        @if ($user->is_admin)
                                            Admin
                                        @else
                                            {{ ucfirst($user->role) }}
                                        @endif
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-success">
                                        Active
                                    </span>
                                </td>
                                <td>{{ $user->created_at->format('M d, Y') }}</td>

                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center">No users found</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
