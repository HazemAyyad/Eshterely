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
                <div class="col-12 mt-3">
                    <h6 class="text-muted">{{ __('admin.shipping_fallback_defaults_section') }}</h6>
                    <p class="small text-muted">{{ __('admin.shipping_fallback_defaults_help') }}</p>
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('admin.shipping_default_weight') }}</label>
                    <input type="text" name="shipping_default_weight" class="form-control" value="{{ old('shipping_default_weight', $values['shipping_default_weight'] ?? '0.5') }}" placeholder="0.5">
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('admin.shipping_default_weight_unit') }}</label>
                    <select name="shipping_default_weight_unit" class="form-select">
                        <option value="kg" {{ old('shipping_default_weight_unit', $values['shipping_default_weight_unit'] ?? 'kg') === 'kg' ? 'selected' : '' }}>kg</option>
                        <option value="lb" {{ old('shipping_default_weight_unit', $values['shipping_default_weight_unit'] ?? 'kg') === 'lb' ? 'selected' : '' }}>lb</option>
                    </select>
                </div>
                <div class="col-md-4"></div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('admin.shipping_default_length') }}</label>
                    <input type="text" name="shipping_default_length" class="form-control" value="{{ old('shipping_default_length', $values['shipping_default_length'] ?? '10') }}" placeholder="10">
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('admin.shipping_default_width') }}</label>
                    <input type="text" name="shipping_default_width" class="form-control" value="{{ old('shipping_default_width', $values['shipping_default_width'] ?? '10') }}" placeholder="10">
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('admin.shipping_default_height') }}</label>
                    <input type="text" name="shipping_default_height" class="form-control" value="{{ old('shipping_default_height', $values['shipping_default_height'] ?? '10') }}" placeholder="10">
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('admin.shipping_default_dimension_unit') }}</label>
                    <select name="shipping_default_dimension_unit" class="form-select">
                        <option value="cm" {{ old('shipping_default_dimension_unit', $values['shipping_default_dimension_unit'] ?? 'cm') === 'cm' ? 'selected' : '' }}>cm</option>
                        <option value="in" {{ old('shipping_default_dimension_unit', $values['shipping_default_dimension_unit'] ?? 'cm') === 'in' ? 'selected' : '' }}>in</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('admin.order_number_prefix') }}</label>
                    <input type="text" name="order_number_prefix" class="form-control" value="{{ old('order_number_prefix', $values['order_number_prefix'] ?? 'ZY') }}" placeholder="ZY" maxlength="20">
                    <small class="text-muted">{{ __('admin.order_number_prefix_help') }}</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('admin.app_fee_percent') }}</label>
                    <input type="text" name="app_fee_percent" class="form-control" value="{{ old('app_fee_percent', $values['app_fee_percent'] ?? '0') }}" placeholder="0">
                    <small class="text-muted">{{ __('admin.app_fee_percent_help') }}</small>
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