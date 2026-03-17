@extends('layouts.admin')

@section('title', $promo->id ? __('admin.edit') . ' ' . __('admin.promo_codes') : __('admin.add') . ' ' . __('admin.promo_codes'))

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-header">
        <h5 class="mb-0">{{ $promo->id ? __('admin.edit') : __('admin.add') }} {{ __('admin.promo_codes') }}</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ $promo->id ? route('admin.config.promo-codes.update', $promo) : route('admin.config.promo-codes.store') }}" class="ajax-submit-form">
            @if ($promo->id) @method('PUT') @endif
            @csrf
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">{{ __('admin.code') }}</label>
                    <input type="text" name="code" class="form-control text-uppercase" value="{{ old('code', $promo->code) }}" placeholder="SAVE10">
                    @error('code')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Description</label>
                    <input type="text" name="description" class="form-control" value="{{ old('description', $promo->description) }}" placeholder="Seasonal promo">
                    @error('description')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Discount type</label>
                    <select name="discount_type" class="form-select">
                        <option value="percent" @selected(old('discount_type', $promo->discount_type) === 'percent')>Percent</option>
                        <option value="fixed" @selected(old('discount_type', $promo->discount_type) === 'fixed')>Fixed</option>
                    </select>
                    @error('discount_type')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Discount value</label>
                    <input type="number" step="0.01" min="0" name="discount_value" class="form-control" value="{{ old('discount_value', $promo->discount_value ?? 0) }}">
                    @error('discount_value')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Minimum order amount</label>
                    <input type="number" step="0.01" min="0" name="min_order_amount" class="form-control" value="{{ old('min_order_amount', $promo->min_order_amount) }}">
                    @error('min_order_amount')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Maximum discount amount</label>
                    <input type="number" step="0.01" min="0" name="max_discount_amount" class="form-control" value="{{ old('max_discount_amount', $promo->max_discount_amount) }}">
                    @error('max_discount_amount')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Max usage total</label>
                    <input type="number" min="1" name="max_usage_total" class="form-control" value="{{ old('max_usage_total', $promo->max_usage_total) }}">
                    @error('max_usage_total')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Max usage per user</label>
                    <input type="number" min="1" name="max_usage_per_user" class="form-control" value="{{ old('max_usage_per_user', $promo->max_usage_per_user) }}">
                    @error('max_usage_per_user')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Starts at</label>
                    <input type="datetime-local" name="starts_at" class="form-control" value="{{ old('starts_at', $promo->starts_at?->format('Y-m-d\TH:i')) }}">
                    @error('starts_at')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Ends at</label>
                    <input type="datetime-local" name="ends_at" class="form-control" value="{{ old('ends_at', $promo->ends_at?->format('Y-m-d\TH:i')) }}">
                    @error('ends_at')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('admin.status') }}</label>
                    <div class="form-check form-switch mt-2">
                        <input type="checkbox" name="is_active" value="1" class="form-check-input" {{ old('is_active', $promo->is_active ?? true) ? 'checked' : '' }}>
                    </div>
                </div>
            </div>
            <div class="mt-4">
                <button type="submit" class="btn btn-primary">{{ __('admin.save') }}</button>
                <a href="{{ route('admin.config.promo-codes.index') }}" class="btn btn-outline-secondary">{{ __('admin.cancel') }}</a>
            </div>
        </form>
    </div>
</div>
@endsection

@include('admin.partials.ajax-form-script', ['redirect' => route('admin.config.promo-codes.index')])
