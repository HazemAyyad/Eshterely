@extends('layouts.admin')

@section('title', 'Activity — user #'.$user->id)

@section('content')
<h4 class="py-4 mb-2">
    Activity for
    <a href="{{ route('admin.users.show', $user) }}">user #{{ $user->id }}</a>
    @if($user->customer_code)
        <span class="badge bg-label-secondary font-monospace">{{ $user->customer_code }}</span>
    @endif
</h4>

<p class="text-muted small mb-3">
    <a href="{{ route('admin.activity.index') }}">← Global activity log</a>
</p>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>When</th>
                    <th>Type</th>
                    <th>Title</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                @forelse($activities as $a)
                    <tr>
                        <td class="text-nowrap small">{{ $a->created_at?->format('Y-m-d H:i:s') }}</td>
                        <td><code class="small">{{ $a->action_type }}</code></td>
                        <td>{{ $a->title }}</td>
                        <td class="small">
                            @if($a->description)
                                <div>{{ $a->description }}</div>
                            @endif
                            @if(!empty($a->meta) && is_array($a->meta))
                                <pre class="mb-0 mt-1 small bg-light p-2 rounded" style="max-width:420px;white-space:pre-wrap;">{{ json_encode($a->meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted py-4">No activity for this user.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-body border-top">
        {{ $activities->links() }}
    </div>
</div>
@endsection
