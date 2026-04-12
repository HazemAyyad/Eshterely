@extends('layouts.admin')

@section('title', __('admin.wallet_topup_request_detail'))

@section('content')
<h4 class="py-4 mb-2">{{ __('admin.wallet_topup_request_detail') }} #{{ $req->id }}</h4>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="row">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">{{ __('admin.request_details') }}</h5></div>
            <div class="card-body">
                <p><strong>{{ __('admin.user') }}:</strong> {{ $req->user?->email ?? $req->user?->phone ?? ('#'.$req->user_id) }}</p>
                <p><strong>Method:</strong> {{ $req->method }}</p>
                <p><strong>{{ __('admin.amount') }}:</strong> {{ number_format((float) $req->amount, 2) }} {{ $req->currency }}</p>
                <p><strong>{{ __('admin.status') }}:</strong> {{ $req->status }}</p>
                @if($req->reference)
                    <p><strong>Reference:</strong> {{ $req->reference }}</p>
                @endif
                @if($req->sender_name)
                    <p><strong>Sender name:</strong> {{ $req->sender_name }}</p>
                @endif
                @if($req->sender_email)
                    <p><strong>Sender email:</strong> {{ $req->sender_email }}</p>
                @endif
                @if($req->sender_phone)
                    <p><strong>Sender phone:</strong> {{ $req->sender_phone }}</p>
                @endif
                @if($req->bank_name)
                    <p><strong>Bank:</strong> {{ $req->bank_name }}</p>
                @endif
                @if($req->notes)
                    <p><strong>User notes:</strong></p>
                    <div class="border rounded p-2 mb-2" style="white-space: pre-wrap;">{{ $req->notes }}</div>
                @endif
                @if($req->proof_file)
                    <p><strong>Proof:</strong>
                        <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($req->proof_file) }}" target="_blank" rel="noopener">View file</a>
                    </p>
                @endif
                <p><strong>{{ __('admin.created_at') }}:</strong> {{ $req->created_at }}</p>
                @if($req->reviewed_at)
                    <p><strong>{{ __('admin.reviewed_at') }}:</strong> {{ $req->reviewed_at }}</p>
                @endif
                @if($req->approved_at)
                    <p><strong>Approved at:</strong> {{ $req->approved_at }}</p>
                @endif
                @if($req->admin_notes)
                    <p><strong>{{ __('admin.admin_notes') }}:</strong></p>
                    <div class="border rounded p-2" style="white-space: pre-wrap;">{{ $req->admin_notes }}</div>
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h5 class="mb-0">{{ __('admin.update_status') }}</h5></div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.wallet-topup-requests.update-status', $req) }}">
                    @csrf
                    @method('PATCH')
                    <div class="mb-3">
                        <label class="form-label">{{ __('admin.status') }}</label>
                        <select name="status" class="form-select" required>
                            @foreach(['pending','under_review','approved','rejected'] as $st)
                                <option value="{{ $st }}" @selected($req->status === $st)>{{ $st }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('admin.admin_notes') }}</label>
                        <textarea name="admin_notes" class="form-control" rows="4">{{ old('admin_notes', $req->admin_notes) }}</textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">{{ __('admin.save') }}</button>
                </form>
                <p class="small text-muted mt-2">Approving credits the wallet once. Rejecting does not change the balance.</p>
            </div>
        </div>
    </div>
</div>

<a href="{{ route('admin.wallet-topup-requests.index') }}" class="btn btn-outline-secondary">{{ __('admin.back') }}</a>
@endsection
