@extends('layouts.admin')

@section('title', __('admin.purchase_assistant_title') . ' #' . $req->id)

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 py-4 mb-2">
    <h4 class="mb-0">
        <span class="badge bg-label-info me-2">Purchase Assistant</span>
        #{{ $req->id }}
    </h4>
    <a href="{{ route('admin.purchase-assistant.index') }}" class="btn btn-sm btn-outline-secondary">{{ __('admin.back') }}</a>
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible">{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header"><strong>{{ __('admin.customer') }}</strong></div>
            <div class="card-body">
                <p class="mb-1"><strong>{{ __('admin.user_name') }}:</strong> {{ $req->user?->full_name ?? $req->user?->name ?? '-' }}</p>
                <p class="mb-1"><strong>Email:</strong> {{ $req->user?->email ?? '-' }}</p>
                <p class="mb-0"><strong>{{ __('admin.status') }}:</strong> {{ $req->status }}</p>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header"><strong>{{ __('admin.source_order') }}</strong></div>
            <div class="card-body">
                @if($req->converted_order_id)
                    <a href="{{ route('admin.orders.show', $req->converted_order_id) }}">{{ __('admin.order_number') }} #{{ $req->converted_order_id }}</a>
                @else
                    <span class="text-muted">—</span>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mt-4">
    <div class="card-header"><strong>{{ __('admin.request_details') }}</strong></div>
    <div class="card-body">
        <p class="mb-2"><strong>URL:</strong> <a href="{{ $req->source_url }}" target="_blank" rel="noopener">{{ $req->source_url }}</a></p>
        <p class="mb-2"><strong>{{ __('admin.title') }}:</strong> {{ $req->title ?? '—' }}</p>
        <p class="mb-2"><strong>{{ __('admin.details') }}:</strong></p>
        <pre class="bg-light p-3 rounded small mb-2" style="white-space: pre-wrap;">{{ $req->details ?? '—' }}</pre>
        <p class="mb-1"><strong>{{ __('admin.quantity') }}:</strong> {{ $req->quantity }}</p>
        <p class="mb-1"><strong>Variant:</strong> {{ $req->variant_details ?? '—' }}</p>
        <p class="mb-0"><strong>{{ __('admin.estimated_price') }}:</strong> {{ $req->customer_estimated_price !== null ? number_format((float) $req->customer_estimated_price, 2) : '—' }} {{ $req->currency ?? '' }}</p>
    </div>
</div>

<form method="POST" action="{{ route('admin.purchase-assistant.update', $req) }}" class="card border-0 shadow-sm mt-4">
    @csrf
    @method('PATCH')
    <div class="card-header"><strong>{{ __('admin.admin') }}</strong></div>
    <div class="card-body row g-3">
        <div class="col-md-6">
            <label class="form-label">{{ __('admin.product_price') }}</label>
            <input type="number" step="0.01" min="0" name="admin_product_price" class="form-control"
                   value="{{ old('admin_product_price', $req->admin_product_price) }}">
        </div>
        <div class="col-md-6">
            <label class="form-label">{{ __('admin.service_fee') }}</label>
            <input type="number" step="0.01" min="0" name="admin_service_fee" class="form-control"
                   value="{{ old('admin_service_fee', $req->admin_service_fee) }}">
        </div>
        <div class="col-12">
            <label class="form-label">{{ __('admin.notes') }}</label>
            <textarea name="admin_notes" class="form-control" rows="3">{{ old('admin_notes', $req->admin_notes) }}</textarea>
        </div>
        <div class="col-md-6">
            <label class="form-label">{{ __('admin.status') }}</label>
            <select name="status" class="form-select">
                @foreach(\App\Models\PurchaseAssistantRequest::statuses() as $st)
                    <option value="{{ $st }}" @selected(old('status', $req->status) === $st)>{{ $st }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="card-footer d-flex flex-wrap gap-2">
        <button type="submit" name="action" value="save" class="btn btn-primary">{{ __('admin.save') }}</button>
        <button type="submit" name="action" value="ready_for_payment" class="btn btn-success"
                onclick="return confirm({{ json_encode(__('admin.purchase_assistant_confirm_payment')) }});">
            {{ __('admin.purchase_assistant_ready_payment') }}
        </button>
    </div>
</form>
@endsection
