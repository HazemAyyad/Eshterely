@extends('layouts.admin')

@section('title', $banner->id ? 'تعديل بانر' : 'إضافة بانر')

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-header">
        <h5 class="mb-0">{{ $banner->id ? __('admin.edit') : __('admin.add') }} {{ __('admin.promo_banners') }}</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ $banner->id ? route('admin.config.promo-banners.update', $banner) : route('admin.config.promo-banners.store') }}" class="ajax-submit-form" enctype="multipart/form-data">
            @if ($banner->id) @method('PUT') @endif
            @csrf
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">label</label>
                    <input type="text" name="label" class="form-control" value="{{ old('label', $banner->label) }}">
                    @error('label')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">title</label>
                    <input type="text" name="title" class="form-control" value="{{ old('title', $banner->title) }}">
                    @error('title')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">cta_text</label>
                    <input type="text" name="cta_text" class="form-control" value="{{ old('cta_text', $banner->cta_text) }}">
                    @error('cta_text')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">sort_order</label>
                    <input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', $banner->sort_order ?? 0) }}" min="0">
                    @error('sort_order')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label class="form-label">{{ __('admin.image') ?? 'رفع الصورة' }}</label>
                    @if(!empty($banner->image_url))
                        <div class="mb-2">
                            <img src="{{ str_starts_with($banner->image_url, 'http') ? $banner->image_url : asset('storage/' . $banner->image_url) }}" alt="Banner" class="img-thumbnail" style="max-height: 120px;">
                        </div>
                    @endif
                    <input type="file" name="image" class="form-control" accept="image/jpeg,image/png,image/jpg,image/gif,image/webp">
                    @error('image')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">deep_link</label>
                    <input type="text" name="deep_link" class="form-control" value="{{ old('deep_link', $banner->deep_link) }}">
                    @error('deep_link')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">is_active</label>
                    <div class="form-check form-switch mt-2">
                        <input type="checkbox" name="is_active" value="1" class="form-check-input" {{ old('is_active', $banner->is_active ?? true) ? 'checked' : '' }}>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">start_at</label>
                    <input type="datetime-local" name="start_at" class="form-control" value="{{ old('start_at', $banner->start_at?->format('Y-m-d\TH:i')) }}">
                    @error('start_at')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">end_at</label>
                    <input type="datetime-local" name="end_at" class="form-control" value="{{ old('end_at', $banner->end_at?->format('Y-m-d\TH:i')) }}">
                    @error('end_at')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="mt-4">
                <button type="submit" class="btn btn-primary">{{ __('admin.save') }}</button>
                <a href="{{ route('admin.config.promo-banners.index') }}" class="btn btn-outline-secondary">{{ __('admin.cancel') }}</a>
            </div>
        </form>
    </div>
</div>
@endsection

@include('admin.partials.ajax-form-script', ['redirect' => route('admin.config.promo-banners.index')])
