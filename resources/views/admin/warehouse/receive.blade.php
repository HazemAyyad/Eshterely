@extends('layouts.admin')

@section('title', __('admin.receive_item_title'))

@section('content')
@php
    use App\Support\AdminFulfillmentLabels;
    $p = AdminFulfillmentLabels::lineItemFulfillment($orderLineItem->fulfillment_status);
@endphp

<h4 class="py-4 mb-2">{{ __('admin.receive_item_title') }}</h4>

@if (session('success'))
    <div class="alert alert-success alert-dismissible">{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if (session('error'))
    <div class="alert alert-danger alert-dismissible">{{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <p class="mb-1"><strong>{{ __('admin.product') }}:</strong> {{ $orderLineItem->name }}</p>
        <p class="mb-1"><strong>{{ __('admin.store') }}:</strong> {{ $orderLineItem->store_name ?? '—' }}</p>
        <p class="mb-1"><strong>{{ __('admin.procurement_status') }}:</strong> <span class="badge bg-{{ $p['badge'] }}">{{ $p['label'] }}</span></p>
        <p class="mb-0"><strong>{{ __('admin.order_number') }}:</strong>
            <a href="{{ route('admin.orders.show', $orderLineItem->shipment->order_id) }}">{{ $orderLineItem->shipment->order->order_number ?? '—' }}</a>
        </p>
        <p class="small text-muted mb-0 mt-2">{{ __('admin.receive_full_page_fallback_note') }}</p>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header"><h5 class="mb-0">{{ __('admin.receive_item_title') }}</h5></div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.warehouse.receive', $orderLineItem) }}" enctype="multipart/form-data">
            @csrf
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">{{ __('admin.date') }} (received_at)</label>
                    <input type="datetime-local" name="received_at" class="form-control" value="{{ old('received_at', now()->format('Y-m-d\TH:i')) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('admin.weight_lb') }}</label>
                    <input type="number" step="0.0001" min="0" name="received_weight" class="form-control" value="{{ old('received_weight') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label d-flex align-items-center gap-1">
                        {{ __('admin.additional_fee') }}
                        <span class="text-muted" tabindex="0" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ __('admin.tooltip_additional_fee') }}"><i class="ti tabler-help-circle"></i></span>
                    </label>
                    <input type="number" step="0.01" min="0" name="additional_fee_amount" class="form-control" value="{{ old('additional_fee_amount', '0') }}">
                </div>
                <div class="col-12"><span class="text-muted small">{{ __('admin.receive_dimensions_hint_in') }}</span></div>
                <div class="col-md-3">
                    <label class="form-label">{{ __('admin.length_in') }}</label>
                    <input type="number" step="0.0001" min="0" name="received_length" class="form-control" value="{{ old('received_length') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">{{ __('admin.width_in') }}</label>
                    <input type="number" step="0.0001" min="0" name="received_width" class="form-control" value="{{ old('received_width') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">{{ __('admin.height_in') }}</label>
                    <input type="number" step="0.0001" min="0" name="received_height" class="form-control" value="{{ old('received_height') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label d-flex align-items-center gap-1">
                        {{ __('admin.special_handling') }}
                        <span class="text-muted" tabindex="0" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ __('admin.tooltip_special_handling') }}"><i class="ti tabler-help-circle"></i></span>
                    </label>
                    <input type="text" name="special_handling_type" class="form-control" maxlength="50" value="{{ old('special_handling_type') }}">
                </div>
                <div class="col-12">
                    <label class="form-label">{{ __('admin.receipt_images_upload') }}</label>
                    <input type="file" name="receipt_images[]" class="form-control" accept="image/*" multiple>
                    <div class="form-text">{{ __('admin.receipt_images_upload_help') }}</div>
                </div>
                <div class="col-12">
                    <label class="form-label">{{ __('admin.images_urls_optional') }}</label>
                    <textarea name="images_text" class="form-control" rows="2" placeholder="https://...">{{ old('images_text') }}</textarea>
                    <div class="form-text">{{ __('admin.images_urls_optional_help') }}</div>
                </div>
                <div class="col-12">
                    <label class="form-label">{{ __('admin.notes') }} / condition</label>
                    <textarea name="condition_notes" class="form-control" rows="2" maxlength="2000">{{ old('condition_notes') }}</textarea>
                </div>
            </div>
            @if ($errors->any())
                <div class="alert alert-danger mt-3 mb-0">{{ $errors->first() }}</div>
            @endif
            <div class="mt-3">
                <button type="submit" class="btn btn-primary">{{ __('admin.save') }}</button>
                <a href="{{ route('admin.warehouse.index') }}" class="btn btn-outline-secondary">{{ __('admin.back') }}</a>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function(el) {
        new bootstrap.Tooltip(el);
    });
});
</script>
@endpush
