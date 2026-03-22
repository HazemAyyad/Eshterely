@extends('layouts.admin')

@section('title', $store->id ? 'تعديل متجر' : 'إضافة متجر')

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-header">
        <h5 class="mb-0">{{ $store->id ? __('admin.edit') : __('admin.add') }} {{ __('admin.featured_stores') }}</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ $store->id ? route('admin.config.featured-stores.update', $store) : route('admin.config.featured-stores.store') }}" class="ajax-submit-form" enctype="multipart/form-data">
            @if ($store->id) @method('PUT') @endif
            @csrf
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">store_slug</label>
                    <input type="text" name="store_slug" class="form-control" value="{{ old('store_slug', $store->store_slug) }}" required placeholder="amazon_us">
                    @error('store_slug')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">name</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name', $store->name) }}" required>
                    @error('name')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">country_code</label>
                    <input type="text" name="country_code" class="form-control" value="{{ old('country_code', $store->country_code) }}">
                    @error('country_code')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">store_url</label>
                    <input type="text" name="store_url" class="form-control" value="{{ old('store_url', $store->store_url) }}">
                    @error('store_url')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label class="form-label">description</label>
                    <textarea name="description" class="form-control" rows="2">{{ old('description', $store->description) }}</textarea>
                    @error('description')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label class="form-label">categories (comma separated)</label>
                    <input type="text" name="categories" class="form-control" value="{{ old('categories', $store->categories) }}" placeholder="Electronics,Fashion,Home,Beauty">
                    @error('categories')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('admin.logo') ?? 'رفع الشعار' }}</label>
                    @if(!empty($store->logo_url))
                        <div class="mb-2">
                            <img src="{{ str_starts_with($store->logo_url ?? '', 'http') ? $store->logo_url : asset('storage/' . $store->logo_url) }}" alt="Logo" class="img-thumbnail" style="max-height: 80px;">
                        </div>
                    @endif
                    <input type="file" name="logo" class="form-control" accept="image/jpeg,image/png,image/jpg,image/gif,image/webp">
                    @error('logo')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('admin.featured_store_visible_in_app') }}</label>
                    <div class="form-check form-switch mt-2">
                        <input type="checkbox" name="is_active" value="1" class="form-check-input" {{ old('is_active', $store->exists ? ($store->is_active ?? true) : true) ? 'checked' : '' }}>
                    </div>
                    <div class="form-text">{{ __('admin.featured_store_visible_in_app_help') }}</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">is_featured</label>
                    <div class="form-check form-switch mt-2">
                        <input type="checkbox" name="is_featured" value="1" class="form-check-input" {{ old('is_featured', $store->is_featured) ? 'checked' : '' }}>
                    </div>
                </div>
            </div>
            <div class="mt-4">
                <button type="submit" class="btn btn-primary">{{ __('admin.save') }}</button>
                <a href="{{ route('admin.config.featured-stores.index') }}" class="btn btn-outline-secondary">{{ __('admin.cancel') }}</a>
            </div>
        </form>
    </div>
</div>
@endsection

@include('admin.partials.ajax-form-script', ['redirect' => route('admin.config.featured-stores.index')])
