@extends('layouts.admin')

@section('title', 'Import Settings — ' . ($setting->display_name ?? $setting->store_key))

@section('content')
@if (session('success'))
    <div class="alert alert-success alert-dismissible">{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="card border-0 shadow-sm">
    <div class="card-header d-flex align-items-center gap-2">
        <a href="{{ route('admin.config.product-import.store-settings.index') }}" class="btn btn-sm btn-outline-secondary">← Back</a>
        <h5 class="mb-0">{{ $setting->display_name ?? $setting->store_key }}</h5>
        <small class="text-muted">store_key: {{ $setting->store_key }}</small>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.config.product-import.store-settings.update', $setting) }}">
            @method('PATCH')
            @csrf

            <div class="row g-4">
                {{-- Basic --}}
                <div class="col-md-6">
                    <label class="form-label">Display Name</label>
                    <input type="text" name="display_name" class="form-control"
                           value="{{ old('display_name', $setting->display_name) }}">
                </div>
                <div class="col-md-3 d-flex align-items-center">
                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input" type="checkbox" name="is_enabled" id="is_enabled" value="1"
                               {{ $setting->is_enabled ? 'checked' : '' }}>
                        <label class="form-check-label" for="is_enabled">Store Enabled</label>
                    </div>
                </div>
                <div class="col-md-3 d-flex align-items-center">
                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input" type="checkbox" name="requires_manual_review_for_missing_specs"
                               id="requires_manual_review" value="1"
                               {{ $setting->requires_manual_review_for_missing_specs ? 'checked' : '' }}>
                        <label class="form-check-label" for="requires_manual_review">Force Review on Missing Specs</label>
                    </div>
                </div>

                {{-- Attempt Order --}}
                <div class="col-12">
                    <label class="form-label">Attempt Order <small class="text-muted">(comma-separated)</small></label>
                    <input type="text" name="attempt_order" class="form-control font-monospace"
                           value="{{ old('attempt_order', implode(', ', $setting->attemptOrder())) }}">
                    <small class="text-muted">
                        Available: structured_data, json_ld, open_graph, direct_html, ai_extraction, playwright, paid_scraper
                    </small>
                </div>

                {{-- Free providers --}}
                <div class="col-md-4">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="free_attempts_enabled" id="free_attempts" value="1"
                               {{ $setting->free_attempts_enabled ? 'checked' : '' }}>
                        <label class="form-check-label" for="free_attempts">Free Providers Enabled</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="allow_ai_extraction" id="allow_ai" value="1"
                               {{ $setting->allow_ai_extraction ? 'checked' : '' }}>
                        <label class="form-check-label" for="allow_ai">Allow AI Extraction (OpenAI)</label>
                    </div>
                </div>

                {{-- Playwright --}}
                <div class="col-md-4">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="allow_playwright_fallback" id="allow_pw" value="1"
                               {{ $setting->allow_playwright_fallback ? 'checked' : '' }}>
                        <label class="form-check-label" for="allow_pw">Allow Playwright Fallback</label>
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Playwright Priority</label>
                    <input type="number" name="playwright_priority" class="form-control"
                           value="{{ old('playwright_priority', $setting->playwright_priority) }}" min="1" max="10">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Playwright Timeout (s)</label>
                    <input type="number" name="playwright_timeout_seconds" class="form-control"
                           value="{{ old('playwright_timeout_seconds', $setting->playwright_timeout_seconds) }}" min="5" max="120">
                </div>

                {{-- Paid --}}
                <div class="col-md-4">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="allow_paid_fallback" id="allow_paid" value="1"
                               {{ $setting->allow_paid_fallback ? 'checked' : '' }}>
                        <label class="form-check-label" for="allow_paid">Allow Paid Scraper Fallback</label>
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Paid Provider</label>
                    <input type="text" name="paid_provider" class="form-control"
                           value="{{ old('paid_provider', $setting->paid_provider) }}" placeholder="scraperapi">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Paid Provider Priority</label>
                    <input type="number" name="paid_provider_priority" class="form-control"
                           value="{{ old('paid_provider_priority', $setting->paid_provider_priority) }}" min="1" max="10">
                </div>

                {{-- Misc --}}
                <div class="col-md-3">
                    <label class="form-label">Max Retries</label>
                    <input type="number" name="max_retries" class="form-control"
                           value="{{ old('max_retries', $setting->max_retries) }}" min="1" max="5">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Timeout (s)</label>
                    <input type="number" name="timeout_seconds" class="form-control"
                           value="{{ old('timeout_seconds', $setting->timeout_seconds) }}" min="5" max="120">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Minimum Confidence (0–1)</label>
                    <input type="text" name="minimum_confidence" class="form-control"
                           value="{{ old('minimum_confidence', $setting->minimum_confidence) }}" placeholder="0.5">
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
