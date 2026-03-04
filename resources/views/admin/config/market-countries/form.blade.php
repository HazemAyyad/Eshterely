@extends('layouts.admin')

@section('title', $country->id ? 'تعديل دولة' : 'إضافة دولة')

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-header">
        <h5 class="mb-0">{{ $country->id ? __('admin.edit') : __('admin.add') }} {{ __('admin.market_countries') }}</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ $country->id ? route('admin.config.market-countries.update', $country) : route('admin.config.market-countries.store') }}" class="ajax-submit-form">
            @if ($country->id) @method('PUT') @endif
            @csrf
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">code</label>
                    <input type="text" name="code" class="form-control" value="{{ old('code', $country->code) }}" required>
                    @error('code')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">name</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name', $country->name) }}" required>
                    @error('name')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">flag_emoji</label>
                    <input type="text" name="flag_emoji" class="form-control" value="{{ old('flag_emoji', $country->flag_emoji) }}" placeholder="🇺🇸">
                    @error('flag_emoji')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">is_featured</label>
                    <div class="form-check form-switch mt-2">
                        <input type="checkbox" name="is_featured" value="1" class="form-check-input" {{ old('is_featured', $country->is_featured) ? 'checked' : '' }}>
                    </div>
                </div>
            </div>
            <div class="mt-4">
                <button type="submit" class="btn btn-primary">{{ __('admin.save') }}</button>
                <a href="{{ route('admin.config.market-countries.index') }}" class="btn btn-outline-secondary">{{ __('admin.cancel') }}</a>
            </div>
        </form>
    </div>
</div>
@endsection

@include('admin.partials.ajax-form-script', ['redirect' => route('admin.config.market-countries.index')])
