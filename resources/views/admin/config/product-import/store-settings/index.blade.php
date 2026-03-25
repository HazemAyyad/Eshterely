@extends('layouts.admin')

@section('title', 'Product Import — Store Settings')

@section('content')
@if (session('success'))
    <div class="alert alert-success alert-dismissible">{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="card border-0 shadow-sm">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h5 class="mb-0">Product Import — Store Settings</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Store</th>
                        <th>Enabled</th>
                        <th>AI Extraction</th>
                        <th>Playwright</th>
                        <th>Paid Scraper</th>
                        <th>Min. Confidence</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($settings as $s)
                    <tr>
                        <td>
                            <span class="fw-semibold">{{ $s->display_name ?? $s->store_key }}</span>
                            <small class="text-muted d-block">{{ $s->store_key }}</small>
                        </td>
                        <td>
                            @if ($s->is_enabled)
                                <span class="badge bg-success">Yes</span>
                            @else
                                <span class="badge bg-secondary">No</span>
                            @endif
                        </td>
                        <td>
                            @if ($s->allow_ai_extraction)
                                <span class="badge bg-info text-dark">Yes</span>
                            @else
                                <span class="badge bg-light text-muted">No</span>
                            @endif
                        </td>
                        <td>
                            @if ($s->allow_playwright_fallback)
                                <span class="badge bg-warning text-dark">Yes</span>
                            @else
                                <span class="badge bg-light text-muted">No</span>
                            @endif
                        </td>
                        <td>
                            @if ($s->allow_paid_fallback)
                                <span class="badge bg-danger">Yes</span>
                            @else
                                <span class="badge bg-light text-muted">No</span>
                            @endif
                        </td>
                        <td>{{ $s->minimum_confidence }}</td>
                        <td>
                            <a href="{{ route('admin.config.product-import.store-settings.edit', $s) }}"
                               class="btn btn-sm btn-outline-primary">Edit</a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
