@extends('layouts.admin')

@section('title', __('admin.wallet_withdrawal_detail'))

@section('content')
<h4 class="py-4 mb-2">{{ __('admin.wallet_withdrawal_detail') }} #{{ $withdrawal->id }}</h4>

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
                <p><strong>{{ __('admin.user') }}:</strong> {{ $withdrawal->user?->email ?? $withdrawal->user?->phone ?? ('#'.$withdrawal->user_id) }}</p>
                <p><strong>{{ __('admin.amount') }} (gross):</strong> {{ number_format((float) $withdrawal->amount, 2) }}</p>
                <p><strong>Fee %:</strong> {{ number_format((float) $withdrawal->fee_percent, 4) }}</p>
                <p><strong>Fee amount:</strong> {{ number_format((float) $withdrawal->fee_amount, 2) }}</p>
                <p><strong>Net to bank:</strong> {{ number_format((float) $withdrawal->net_amount, 2) }}</p>
                <p><strong>{{ __('admin.status') }}:</strong> {{ $withdrawal->status }}</p>
                <p><strong>IBAN:</strong> <code>{{ $withdrawal->iban }}</code></p>
                <p><strong>{{ __('admin.bank_name') }}:</strong> {{ $withdrawal->bank_name }}</p>
                <p><strong>{{ __('admin.country') }}:</strong> {{ $withdrawal->country }}</p>
                @if($withdrawal->note)
                    <p><strong>Note:</strong></p>
                    <div class="border rounded p-2 mb-2" style="white-space: pre-wrap;">{{ $withdrawal->note }}</div>
                @endif
                <p><strong>{{ __('admin.created_at') }}:</strong> {{ $withdrawal->created_at }}</p>
                @if($withdrawal->reviewed_at)
                    <p><strong>{{ __('admin.reviewed_at') }}:</strong> {{ $withdrawal->reviewed_at }}</p>
                @endif
                @if($withdrawal->transferred_at)
                    <p><strong>{{ __('admin.transferred_at') }}:</strong> {{ $withdrawal->transferred_at }}</p>
                @endif
                @if($withdrawal->transfer_proof)
                    <p><strong>Transfer proof:</strong>
                        <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($withdrawal->transfer_proof) }}" target="_blank">View file</a>
                    </p>
                @endif
                @if($withdrawal->admin_notes)
                    <p><strong>{{ __('admin.admin_notes') }}:</strong></p>
                    <div class="border rounded p-2" style="white-space: pre-wrap;">{{ $withdrawal->admin_notes }}</div>
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h5 class="mb-0">{{ __('admin.update_status') }}</h5></div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.wallet-withdrawals.update-status', $withdrawal) }}" enctype="multipart/form-data">
                    @csrf
                    @method('PATCH')
                    <div class="mb-3">
                        <label class="form-label">{{ __('admin.status') }}</label>
                        <select name="status" class="form-select" required>
                            @foreach(\App\Models\WalletWithdrawal::statuses() as $st)
                                <option value="{{ $st }}" @selected($withdrawal->status === $st)>{{ $st }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Transfer proof (required for transferred)</label>
                        <input type="file" name="transfer_proof" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('admin.admin_notes') }}</label>
                        <textarea name="admin_notes" class="form-control" rows="4">{{ old('admin_notes', $withdrawal->admin_notes) }}</textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">{{ __('admin.save') }}</button>
                </form>
                <p class="small text-muted mt-2">Approve before transfer. Marking <strong>transferred</strong> deducts the gross amount from the wallet and requires proof.</p>
            </div>
        </div>
    </div>
</div>

<a href="{{ route('admin.wallet-withdrawals.index') }}" class="btn btn-outline-secondary">{{ __('admin.back') }}</a>
@endsection
