@extends('layouts.admin')

@section('title', __('admin.shipping_settings'))

@section('content')
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

<div class="card border-0 shadow-sm">
    <div class="card-header">
        <h5 class="mb-0">{{ __('admin.shipping_settings') }}</h5>
    </div>
    <div class="card-body">
        <p class="text-muted small mb-4">{{ __('admin.shipping_settings_help') }}</p>
        <form method="POST" action="{{ route('admin.config.shipping-settings.update') }}" class="ajax-submit-form">
            @method('PATCH')
            @csrf
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">{{ __('admin.shipping_volumetric_divisor') }}</label>
                    <input type="text" name="volumetric_divisor" class="form-control" value="{{ old('volumetric_divisor', $values['volumetric_divisor'] ?? '5000') }}" placeholder="5000">
                    <small class="text-muted">{{ __('admin.shipping_volumetric_divisor_help') }}</small>
                    @error('volumetric_divisor')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('admin.shipping_default_currency') }}</label>
                    <input type="text" name="default_currency" class="form-control" value="{{ old('default_currency', $values['default_currency'] ?? 'USD') }}" placeholder="USD" maxlength="10">
                    @error('default_currency')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('admin.shipping_default_markup_percent') }}</label>
                    <input type="text" name="default_markup_percent" class="form-control" value="{{ old('default_markup_percent', $values['default_markup_percent'] ?? '0') }}" placeholder="0">
                    @error('default_markup_percent')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('admin.shipping_min_charge') }}</label>
                    <input type="text" name="min_shipping_charge" class="form-control" value="{{ old('min_shipping_charge', $values['min_shipping_charge'] ?? '0') }}" placeholder="0">
                    @error('min_shipping_charge')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('admin.shipping_warehouse_handling_fee') }}</label>
                    <input type="text" name="warehouse_handling_fee" class="form-control" value="{{ old('warehouse_handling_fee', $values['warehouse_handling_fee'] ?? '0') }}" placeholder="0">
                    @error('warehouse_handling_fee')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('admin.shipping_multi_package_percent') }}</label>
                    <input type="text" name="multi_package_percent" class="form-control" value="{{ old('multi_package_percent', $values['multi_package_percent'] ?? '0') }}" placeholder="0">
                    @error('multi_package_percent')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('admin.shipping_carrier_discount_dhl') }}</label>
                    <input type="text" name="carrier_discount_dhl" class="form-control" value="{{ old('carrier_discount_dhl', $values['carrier_discount_dhl'] ?? '0') }}" placeholder="0">
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('admin.shipping_carrier_discount_ups') }}</label>
                    <input type="text" name="carrier_discount_ups" class="form-control" value="{{ old('carrier_discount_ups', $values['carrier_discount_ups'] ?? '0') }}" placeholder="0">
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('admin.shipping_carrier_discount_fedex') }}</label>
                    <input type="text" name="carrier_discount_fedex" class="form-control" value="{{ old('carrier_discount_fedex', $values['carrier_discount_fedex'] ?? '0') }}" placeholder="0">
                </div>
                <div class="col-md-12">
                    <label class="form-label">{{ __('admin.shipping_rounding_strategy') }}</label>
                    <select name="rounding_strategy" class="form-select">
                        @foreach($roundingOptions as $optVal => $optLabel)
                            <option value="{{ $optVal }}" {{ old('rounding_strategy', $values['rounding_strategy'] ?? '') == $optVal ? 'selected' : '' }}>{{ $optLabel }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="mt-4">
                <button type="submit" class="btn btn-primary">{{ __('admin.save') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection

@include('admin.partials.ajax-form-script', ['redirect' => route('admin.config.shipping-settings.edit')])