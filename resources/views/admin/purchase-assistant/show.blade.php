@extends('layouts.admin')

@php
    use App\Models\PurchaseAssistantRequest;
    use App\Support\AdminPurchaseAssistantDataTable;
    use App\Support\PurchaseAssistantStoreDisplayName;
    $storeLabel = $req->store_display_name ?: PurchaseAssistantStoreDisplayName::fromHost($req->source_domain);
@endphp

@section('title', __('admin.purchase_assistant_title') . ' #' . $req->id)

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 py-4 mb-2">
    <div>
        <h4 class="mb-1">
            <span class="badge bg-label-info me-2">Purchase Assistant</span>
            #{{ $req->id }}
        </h4>
        <p class="mb-0 text-muted small">Review pricing, status, and linked order.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('admin.purchase-assistant.index') }}" class="btn btn-sm btn-outline-secondary">{{ __('admin.back') }}</a>
    </div>
</div>

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

{{-- 1. Customer --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>{{ __('admin.customer') }}</strong>
        @if($req->user)
            <a href="{{ route('admin.users.show', $req->user) }}" class="btn btn-sm btn-label-primary">{{ __('admin.details') }}</a>
        @endif
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <div class="small text-muted mb-1">{{ __('admin.user_name') }}</div>
                <div class="fw-semibold">{{ $req->user?->full_name ?? $req->user?->name ?? '—' }}</div>
            </div>
            <div class="col-md-6">
                <div class="small text-muted mb-1">Email</div>
                <div>{{ $req->user?->email ?? '—' }}</div>
            </div>
            @if($req->user?->phone)
                <div class="col-md-6">
                    <div class="small text-muted mb-1">Phone</div>
                    <div>{{ $req->user->phone }}</div>
                </div>
            @endif
        </div>
    </div>
</div>

<div class="row g-4">
    {{-- 2. Product / request --}}
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header"><strong>{{ __('admin.request_details') }}</strong></div>
            <div class="card-body">
                @if(is_array($req->image_paths) && count($req->image_paths) > 0)
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        @foreach($req->image_paths as $path)
                            @if(!empty($path))
                                @php
                                    $src = \Illuminate\Support\Str::startsWith($path, ['http://', 'https://']) ? $path : url($path);
                                @endphp
                                <a href="{{ $src }}" target="_blank" rel="noopener" class="d-inline-block border rounded overflow-hidden" style="max-width:140px;">
                                    <img src="{{ $src }}" alt="" class="img-fluid" style="max-height:120px;object-fit:cover;">
                                </a>
                            @endif
                        @endforeach
                    </div>
                @endif

                <dl class="row mb-0">
                    <dt class="col-sm-4 text-muted small">{{ __('admin.title') }}</dt>
                    <dd class="col-sm-8 mb-2">{{ $req->title ?: '—' }}</dd>

                    <dt class="col-sm-4 text-muted small">Store</dt>
                    <dd class="col-sm-8 mb-2">{{ $storeLabel }}</dd>

                    <dt class="col-sm-4 text-muted small">Product link</dt>
                    <dd class="col-sm-8 mb-2">
                        <a href="{{ $req->source_url }}" target="_blank" rel="noopener" class="btn btn-sm btn-icon btn-label-secondary" title="Open URL">
                            <i class="icon-base ti tabler-external-link icon-18px"></i>
                        </a>
                    </dd>

                    <dt class="col-sm-4 text-muted small">{{ __('admin.quantity') }}</dt>
                    <dd class="col-sm-8 mb-2">{{ $req->quantity }}</dd>

                    <dt class="col-sm-4 text-muted small">Variant</dt>
                    <dd class="col-sm-8 mb-2">{{ $req->variant_details ?: '—' }}</dd>

                    <dt class="col-sm-4 text-muted small">{{ __('admin.details') }}</dt>
                    <dd class="col-sm-8 mb-2">
                        @if($req->details)
                            <div class="bg-label-secondary bg-opacity-10 rounded p-3 small" style="white-space: pre-wrap;">{{ $req->details }}</div>
                        @else
                            —
                        @endif
                    </dd>

                    <dt class="col-sm-4 text-muted small">{{ __('admin.estimated_price') }}</dt>
                    <dd class="col-sm-8 mb-0">
                        @if($req->customer_estimated_price !== null)
                            <span class="fw-semibold">{{ number_format((float) $req->customer_estimated_price, 2) }}</span> {{ $req->currency ?? 'USD' }}
                        @else
                            —
                        @endif
                    </dd>
                </dl>
            </div>
        </div>
    </div>

    {{-- Status + order + timeline --}}
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header"><strong>Status &amp; order</strong></div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="small text-muted mb-1">{{ __('admin.status') }}</div>
                    {!! AdminPurchaseAssistantDataTable::statusBadge($req) !!}
                </div>
                <div class="mb-0">
                    <div class="small text-muted mb-1">{{ __('admin.source_order') }}</div>
                    @if($req->converted_order_id)
                        <a href="{{ route('admin.orders.show', $req->converted_order_id) }}" class="fw-semibold">{{ __('admin.order_number') }} #{{ $req->converted_order_id }}</a>
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header"><strong>Timeline</strong></div>
            <div class="card-body small">
                <div class="mb-2"><span class="text-muted">Created:</span> {{ $req->created_at?->format('Y-m-d H:i') ?? '—' }}</div>
                <div class="mb-0"><span class="text-muted">Updated:</span> {{ $req->updated_at?->format('Y-m-d H:i') ?? '—' }}</div>
            </div>
        </div>
    </div>
</div>

{{-- 3. Admin review --}}
<form method="POST" action="{{ route('admin.purchase-assistant.update', $req) }}" class="card border-0 shadow-sm mt-4">
    @csrf
    @method('PATCH')
    <div class="card-header"><strong>{{ __('admin.admin') }}</strong></div>
    <div class="card-body row g-3">
        <div class="col-12">
            <div class="alert alert-info border-0 mb-0" role="status">
                <div class="fw-semibold mb-1">{{ __('admin.purchase_assistant_workflow_title') }}</div>
                <ol class="mb-0 ps-3 small">
                    <li>{{ __('admin.purchase_assistant_workflow_step_price') }}</li>
                    <li>{{ __('admin.purchase_assistant_workflow_step_fee') }}</li>
                    <li>{{ __('admin.purchase_assistant_workflow_step_status') }}</li>
                    <li>{{ __('admin.purchase_assistant_workflow_step_notify') }}</li>
                </ol>
            </div>
        </div>
        <div class="col-md-6">
            <label class="form-label">{{ __('admin.product_price') }}</label>
            <input type="number" step="0.01" min="0" name="admin_product_price" class="form-control"
                   value="{{ old('admin_product_price', $req->admin_product_price) }}">
        </div>
        <div class="col-md-6">
            <label class="form-label">{{ __('admin.service_fee') }}</label>
            <input type="number" step="0.01" min="0" name="admin_service_fee" class="form-control"
                   value="{{ old('admin_service_fee', $req->admin_service_fee) }}">
        </div>
        <div class="col-12">
            <label class="form-label">{{ __('admin.notes') }}</label>
            <textarea name="admin_notes" class="form-control" rows="3">{{ old('admin_notes', $req->admin_notes) }}</textarea>
        </div>
        <div class="col-md-6">
            <label class="form-label">{{ __('admin.status') }}</label>
            <select name="status" class="form-select @error('status') is-invalid @enderror">
                @foreach(PurchaseAssistantRequest::statuses() as $st)
                    <option value="{{ $st }}" @selected(old('status', $req->status) === $st)>{{ $st }}</option>
                @endforeach
            </select>
            @error('status')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>
    </div>
    <div class="card-footer d-flex flex-wrap gap-2 align-items-center">
        <button type="submit" class="btn btn-primary">{{ __('admin.save') }}</button>
        <span class="text-muted small ms-auto">{{ __('admin.purchase_assistant_save_footer_hint') }}</span>
    </div>
</form>
@endsection
