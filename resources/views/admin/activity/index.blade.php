@extends('layouts.admin')

@section('title', 'Activity log')

@section('content')
<h4 class="py-4 mb-2">User activity (global)</h4>

<form method="get" class="card border-0 shadow-sm mb-3 p-3">
    <div class="row g-2 align-items-end">
        <div class="col-md-2">
            <label class="form-label small mb-0">User ID</label>
            <input type="number" name="user_id" class="form-control form-control-sm" value="{{ $filters['user_id'] ?? '' }}" placeholder="e.g. 12">
        </div>
        <div class="col-md-3">
            <label class="form-label small mb-0">Action type</label>
            <select name="action_type" class="form-select form-select-sm">
                <option value="">— Any —</option>
                @foreach($actionTypes as $t)
                    <option value="{{ $t }}" @selected(($filters['action_type'] ?? '') === $t)>{{ $t }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-0">From</label>
            <input type="date" name="date_from" class="form-control form-control-sm" value="{{ $filters['date_from'] ?? '' }}">
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-0">To</label>
            <input type="date" name="date_to" class="form-control form-control-sm" value="{{ $filters['date_to'] ?? '' }}">
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-sm btn-primary">Filter</button>
            <a href="{{ route('admin.activity.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
        </div>
    </div>
</form>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>When</th>
                    <th>User</th>
                    <th>Type</th>
                    <th>Title</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                @forelse($activities as $a)
                    <tr>
                        <td class="text-nowrap small">{{ $a->created_at?->format('Y-m-d H:i:s') }}</td>
                        <td>
                            @if($a->user)
                                <a href="{{ route('admin.users.show', $a->user) }}">#{{ $a->user_id }}</a>
                                <div class="small text-muted">{{ $a->user->phone ?? $a->user->email ?? '—' }}</div>
                            @else
                                #{{ $a->user_id }}
                            @endif
                        </td>
                        <td><code class="small">{{ $a->action_type }}</code></td>
                        <td>{{ $a->title }}</td>
                        <td class="small">
                            @if($a->description)
                                <div>{{ $a->description }}</div>
                            @endif
                            @if(!empty($a->meta) && is_array($a->meta))
                                <pre class="mb-0 mt-1 small bg-light p-2 rounded" style="max-width:420px;white-space:pre-wrap;">{{ json_encode($a->meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                            @endif
                            @if($a->ip_address)
                                <div class="text-muted">IP: {{ $a->ip_address }}</div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">No activity yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-body border-top">
        {{ $activities->links() }}
    </div>
</div>
@endsection
