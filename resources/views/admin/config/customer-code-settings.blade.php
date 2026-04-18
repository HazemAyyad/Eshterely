@extends('layouts.admin')

@section('title', 'Customer code format')

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
        <h5 class="mb-0">Customer code format</h5>
    </div>
    <div class="card-body">
        <p class="text-muted small">
            Controls how <strong>new</strong> customer codes are generated (e.g. <code>ESH00001</code>).
            Existing codes are never changed when you update these settings.
        </p>
        <form method="POST" action="{{ route('admin.config.customer-code-settings.update') }}" class="ajax-submit-form">
            @method('PATCH')
            @csrf
            <div class="mb-3">
                <label class="form-label" for="prefix">Prefix</label>
                <input type="text" name="prefix" id="prefix" class="form-control" maxlength="16"
                       value="{{ old('prefix', $prefix) }}" required pattern="[A-Za-z][A-Za-z0-9]*"
                       placeholder="ESH">
                <small class="text-muted">Letters/digits; must start with a letter.</small>
                @error('prefix')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label" for="numeric_padding">Numeric padding</label>
                <input type="number" name="numeric_padding" id="numeric_padding" class="form-control"
                       min="1" max="12" value="{{ old('numeric_padding', $numeric_padding) }}" required>
                <small class="text-muted">Number of digits (e.g. 5 → 00001).</small>
                @error('numeric_padding')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
            <button type="submit" class="btn btn-primary">{{ __('admin.save') }}</button>
        </form>
    </div>
</div>
@endsection

@include('admin.partials.ajax-form-script', ['redirect' => route('admin.config.customer-code-settings.edit')])
