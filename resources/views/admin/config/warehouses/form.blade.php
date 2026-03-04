@extends('layouts.admin')

@section('title', $warehouse->id ? 'تعديل مستودع' : 'إضافة مستودع')

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-header">
        <h5 class="mb-0">{{ $warehouse->id ? __('admin.edit') : __('admin.add') }} {{ __('admin.warehouses') }}</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ $warehouse->id ? route('admin.config.warehouses.update', $warehouse) : route('admin.config.warehouses.store') }}" class="ajax-submit-form">
            @if ($warehouse->id) @method('PUT') @endif
            @csrf
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">slug</label>
                    <input type="text" name="slug" class="form-control" value="{{ old('slug', $warehouse->slug) }}" required placeholder="delaware_us">
                    @error('slug')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">label</label>
                    <input type="text" name="label" class="form-control" value="{{ old('label', $warehouse->label) }}" required>
                    @error('label')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">country_code</label>
                    <input type="text" name="country_code" class="form-control" value="{{ old('country_code', $warehouse->country_code) }}">
                    @error('country_code')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">is_active</label>
                    <div class="form-check form-switch mt-2">
                        <input type="checkbox" name="is_active" value="1" class="form-check-input" {{ old('is_active', $warehouse->is_active ?? true) ? 'checked' : '' }}>
                    </div>
                </div>
            </div>
            <div class="mt-4">
                <button type="submit" class="btn btn-primary">{{ __('admin.save') }}</button>
                <a href="{{ route('admin.config.warehouses.index') }}" class="btn btn-outline-secondary">{{ __('admin.cancel') }}</a>
            </div>
        </form>
    </div>
</div>
@endsection

@include('admin.partials.ajax-form-script', ['redirect' => route('admin.config.warehouses.index')])
