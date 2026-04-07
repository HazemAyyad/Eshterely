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
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header"><h5 class="mb-0">{{ __('admin.receive_item_title') }}</h5></div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.warehouse.receive', $orderLineItem) }}">
            @csrf
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">{{ __('admin.date') }} (received_at)</label>
                    <input type="datetime-local" name="received_at" class="form-control" value="{{ old('received_at', now()->format('Y-m-d\TH:i')) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('admin.weight_kg') }}</label>
                    <input type="number" step="0.0001" min="0" name="received_weight" class="form-control" value="{{ old('received_weight') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('admin.additional_fee') }}</label>
                    <input type="number" step="0.01" min="0" name="additional_fee_amount" class="form-control" value="{{ old('additional_fee_amount', '0') }}">
                </div>
                <div class="col-12"><span class="text-muted small">{{ __('admin.dims_lwh') }}</span></div>
                <div class="col-md-3">
                    <label class="form-label">L</label>
                    <input type="number" step="0.0001" min="0" name="received_length" class="form-control" value="{{ old('received_length') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">W</label>
                    <input type="number" step="0.0001" min="0" name="received_width" class="form-control" value="{{ old('received_width') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">H</label>
                    <input type="number" step="0.0001" min="0" name="received_height" class="form-control" value="{{ old('received_height') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">{{ __('admin.special_handling') }}</label>
                    <input type="text" name="special_handling_type" class="form-control" maxlength="50" value="{{ old('special_handling_type') }}">
                </div>
                <div class="col-12">
                    <label class="form-label">{{ __('admin.images_urls_help') }}</label>
                    <textarea name="images_text" class="form-control" rows="3" placeholder="https://...">{{ old('images_text') }}</textarea>
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
