@extends('layouts.admin')

@section('title', 'تحرير الشاشة الافتتاحية')

@section('content')
@if (session('success'))
    <div class="alert alert-success alert-dismissible">{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="card border-0 shadow-sm">
    <div class="card-header">
        <h5 class="mb-0">{{ __('admin.splash') }}</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.config.splash') }}" class="ajax-submit-form" enctype="multipart/form-data">
            @method('PATCH')
            @csrf
            <div class="mb-4">
                <label class="form-label">{{ __('admin.logo') ?? 'رفع الشعار' }}</label>
                @if(!empty($splash->logo_url))
                    <div class="mb-2">
                        <img src="{{ str_starts_with($splash->logo_url, 'http') ? $splash->logo_url : asset('storage/' . $splash->logo_url) }}" alt="Logo" class="img-thumbnail" style="max-height: 100px;">
                    </div>
                @endif
                <input type="file" name="logo" class="form-control" accept="image/jpeg,image/png,image/jpg,image/gif,image/webp">
                @error('logo')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">title_en</label>
                    <input type="text" name="title_en" class="form-control" value="{{ old('title_en', $splash->title_en) }}">
                    @error('title_en')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">title_ar</label>
                    <input type="text" name="title_ar" class="form-control" value="{{ old('title_ar', $splash->title_ar) }}">
                    @error('title_ar')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">subtitle_en</label>
                    <input type="text" name="subtitle_en" class="form-control" value="{{ old('subtitle_en', $splash->subtitle_en) }}">
                    @error('subtitle_en')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">subtitle_ar</label>
                    <input type="text" name="subtitle_ar" class="form-control" value="{{ old('subtitle_ar', $splash->subtitle_ar) }}">
                    @error('subtitle_ar')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">progress_text_en</label>
                    <input type="text" name="progress_text_en" class="form-control" value="{{ old('progress_text_en', $splash->progress_text_en) }}">
                    @error('progress_text_en')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">progress_text_ar</label>
                    <input type="text" name="progress_text_ar" class="form-control" value="{{ old('progress_text_ar', $splash->progress_text_ar) }}">
                    @error('progress_text_ar')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="mt-4">
                <button type="submit" class="btn btn-primary">{{ __('admin.save') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection

@include('admin.partials.ajax-form-script', ['redirect' => route('admin.config.splash')])
