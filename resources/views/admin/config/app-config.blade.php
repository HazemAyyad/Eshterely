@extends('layouts.admin')

@section('title', 'إعدادات التطبيق')

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
        <h5 class="mb-0">إعدادات التطبيق (API ووضع التطوير)</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.config.app-config') }}" class="ajax-submit-form">
            @method('PATCH')
            @csrf
            <div class="mb-4">
                <label class="form-label">رابط الـ API (API Base URL)</label>
                <input type="url" name="api_base_url" id="api_base_url" class="form-control"
                       value="{{ old('api_base_url', $config['api_base_url'] ?? '') }}"
                       placeholder="https://eshterely.duosparktech.com">
                <small class="text-muted">اتركه فارغاً لاستخدام العنوان الافتراضي في التطبيق.</small>
                @error('api_base_url')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
            <div class="mb-4">
                <div class="form-check form-switch">
                    <input type="hidden" name="development_mode" value="0">
                    <input type="checkbox" class="form-check-input" name="development_mode" id="development_mode" value="1"
                           {{ ($config['development_mode'] ?? false) ? 'checked' : '' }}>
                    <label class="form-check-label" for="development_mode">تفعيل وضع التطوير</label>
                </div>
                <small class="text-muted">عند التفعيل يظهر في التطبيق بانر "وضع التطوير" وشاشة التطوير.</small>
            </div>
            <div class="mt-4">
                <button type="submit" class="btn btn-primary">{{ __('admin.save') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection

@include('admin.partials.ajax-form-script', ['redirect' => route('admin.config.app-config')])
