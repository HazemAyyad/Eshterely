@extends('layouts.admin')

@section('title', __('admin.wallet_refund_to_wallet_detail'))

@section('content')
<h4 class="py-4 mb-2">{{ __('admin.wallet_refund_to_wallet_detail') }} #{{ $refund->id }}</h4>

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
                <p><strong>{{ __('admin.user') }}:</strong> {{ $refund->user?->email ?? $refund->user?->phone ?? ('#'.$refund->user_id) }}</p>
                <p><strong>{{ __('admin.source') }}:</strong> {{ $refund->source_type }} #{{ $refund->source_id }}</p>
                <p><strong>{{ __('admin.amount') }}:</strong> {{ number_format((float) $refund->amount, 2) }} {{ $refund->currency }}</p>
                <p><strong>{{ __('admin.status') }}:</strong> {{ $refund->status }}</p>
                <p><strong>{{ __('admin.reason') }}:</strong></p>
                <div class="border rounded p-2 mb-2" style="white-space: pre-wrap;">{{ $refund->reason }}</div>
                <p><strong>{{ __('admin.created_at') }}:</strong> {{ $refund->created_at }}</p>
                @if($refund->reviewed_at)
                    <p><strong>{{ __('admin.reviewed_at') }}:</strong> {{ $refund->reviewed_at }}</p>
                @endif
                @if($refund->admin_notes)
                    <p><strong>{{ __('admin.admin_notes') }}:</strong></p>
                    <div class="border rounded p-2" style="white-space: pre-wrap;">{{ $refund->admin_notes }}</div>
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h5 class="mb-0">{{ __('admin.update_status') }}</h5></div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.wallet-refunds.update-status', $refund) }}">
                    @csrf
                    @method('PATCH')
                    <div class="mb-3">
                        <label class="form-label">{{ __('admin.status') }}</label>
                        <select name="status" class="form-select" required>
                            @foreach(\App\Models\WalletRefund::statuses() as $st)
                                <option value="{{ $st }}" @selected($refund->status === $st)>{{ $st }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('admin.admin_notes') }}</label>
                        <textarea name="admin_notes" class="form-control" rows="4">{{ old('admin_notes', $refund->admin_notes) }}</textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">{{ __('admin.save') }}</button>
                </form>
                <p class="small text-muted mt-2">Approving credits the user wallet and creates a <code>refund_in</code> transaction.</p>
            </div>
        </div>
    </div>
</div>

<a href="{{ route('admin.wallet-refunds.index') }}" class="btn btn-outline-secondary">{{ __('admin.back') }}</a>
@endsection
