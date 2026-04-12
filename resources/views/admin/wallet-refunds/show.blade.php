@extends('layouts.admin')

@section('title', __('admin.wallet_refund_detail'))

@section('content')
<h4 class="py-4 mb-2">{{ __('admin.wallet_refund_detail') }} #{{ $requestModel->id }}</h4>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="row">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">{{ __('admin.request_details') }}</h5></div>
            <div class="card-body">
                <p><strong>{{ __('admin.user') }}:</strong> {{ $requestModel->user?->email ?? $requestModel->user?->phone ?? ('#'.$requestModel->user_id) }}</p>
                <p><strong>{{ __('admin.amount') }}:</strong> {{ number_format((float) $requestModel->amount, 2) }} {{ $requestModel->currency }}</p>
                <p><strong>{{ __('admin.status') }}:</strong> {{ $requestModel->status }}</p>
                <p><strong>{{ __('admin.reason') }}:</strong></p>
                <div class="border rounded p-2 mb-2" style="white-space: pre-wrap;">{{ $requestModel->reason }}</div>
                <p><strong>IBAN:</strong> <code>{{ $requestModel->iban }}</code></p>
                <p><strong>{{ __('admin.bank_name') }}:</strong> {{ $requestModel->bank_name }}</p>
                <p><strong>{{ __('admin.country') }}:</strong> {{ $requestModel->country }}</p>
                <p><strong>{{ __('admin.created_at') }}:</strong> {{ $requestModel->created_at }}</p>
                @if($requestModel->reviewed_at)
                    <p><strong>{{ __('admin.reviewed_at') }}:</strong> {{ $requestModel->reviewed_at }}</p>
                @endif
                @if($requestModel->processed_at)
                    <p><strong>{{ __('admin.processed_at') }}:</strong> {{ $requestModel->processed_at }}</p>
                @endif
                @if($requestModel->transferred_at)
                    <p><strong>{{ __('admin.transferred_at') }}:</strong> {{ $requestModel->transferred_at }}</p>
                @endif
                @if($requestModel->admin_notes)
                    <p><strong>{{ __('admin.admin_notes') }}:</strong></p>
                    <div class="border rounded p-2" style="white-space: pre-wrap;">{{ $requestModel->admin_notes }}</div>
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h5 class="mb-0">{{ __('admin.update_status') }}</h5></div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.wallet-refunds.update-status', $requestModel) }}">
                    @csrf
                    @method('PATCH')
                    <div class="mb-3">
                        <label class="form-label">{{ __('admin.status') }}</label>
                        <select name="status" class="form-select" required>
                            @foreach(\App\Models\WalletRefundRequest::statuses() as $st)
                                <option value="{{ $st }}" @selected($requestModel->status === $st)>{{ $st }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('admin.admin_notes') }}</label>
                        <textarea name="admin_notes" class="form-control" rows="4">{{ old('admin_notes', $requestModel->admin_notes) }}</textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">{{ __('admin.save') }}</button>
                </form>
            </div>
        </div>
    </div>
</div>

<a href="{{ route('admin.wallet-refunds.index') }}" class="btn btn-outline-secondary">{{ __('admin.back') }}</a>
@endsection
