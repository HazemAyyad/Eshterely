@extends('layouts.admin')

@section('title', $page->id ? __('admin.edit') . ' - ' . __('admin.onboarding') : __('admin.add') . ' - ' . __('admin.onboarding'))

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-header">
        <h5 class="mb-0">{{ $page->id ? __('admin.edit') : __('admin.add') }} {{ __('admin.onboarding') }}</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ $page->id ? route('admin.config.onboarding.update', $page) : route('admin.config.onboarding.store') }}" class="ajax-submit-form" enctype="multipart/form-data">
            @if ($page->id) @method('PUT') @endif
            @csrf
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">sort_order</label>
                    <input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', $page->sort_order ?? 0) }}" min="0">
                    @error('sort_order')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('admin.image') ?? 'رفع الصورة' }}</label>
                    @if(!empty($page->image_url))
                        <div class="mb-2">
                            <img src="{{ str_starts_with($page->image_url ?? '', 'http') ? $page->image_url : asset('storage/' . $page->image_url) }}" alt="Page" class="img-thumbnail" style="max-height: 100px;">
                        </div>
                    @endif
                    <input type="file" name="image" class="form-control" accept="image/jpeg,image/png,image/jpg,image/gif,image/webp">
                    @error('image')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">title_en</label>
                    <input type="text" name="title_en" class="form-control" value="{{ old('title_en', $page->title_en) }}" required>
                    @error('title_en')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">title_ar</label>
                    <input type="text" name="title_ar" class="form-control" value="{{ old('title_ar', $page->title_ar) }}">
                    @error('title_ar')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label class="form-label">description_en</label>
                    <textarea name="description_en" class="form-control" rows="3">{{ old('description_en', $page->description_en) }}</textarea>
                    @error('description_en')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label class="form-label">description_ar</label>
                    <textarea name="description_ar" class="form-control" rows="3">{{ old('description_ar', $page->description_ar) }}</textarea>
                    @error('description_ar')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="mt-4">
                <button type="submit" class="btn btn-primary">{{ __('admin.save') }}</button>
                <a href="{{ route('admin.config.onboarding.index') }}" class="btn btn-outline-secondary">{{ __('admin.cancel') }}</a>
            </div>
        </form>
    </div>
</div>
@endsection

@include('admin.partials.ajax-form-script', ['redirect' => route('admin.config.onboarding.index')])
