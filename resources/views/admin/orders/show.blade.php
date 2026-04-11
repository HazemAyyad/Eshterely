@extends('layouts.admin')

@section('title', __('admin.order_number') . ' ' . $order->order_number)

@section('content')
@php
    $exec = $executionStatus ?? \App\Support\OrderExecutionStatus::AWAITING_PAYMENT;
    $execBadge = \App\Support\OrderExecutionStatus::badgeClass($exec);
    $isAwaitingReview = $exec === \App\Support\OrderExecutionStatus::AWAITING_REVIEW;
    $showReviewComplete = $order->reviewed_at && ! $isAwaitingReview;
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 py-4 mb-2">
    <h4 class="mb-0">{{ __('admin.order_number') }} {{ $order->order_number }}</h4>
    <div class="d-flex flex-wrap gap-2 align-items-center">
        @if(!empty($canCancelOrder))
        <form method="POST" action="{{ route('admin.orders.update-status', $order) }}" class="ajax-submit-form d-inline" onsubmit="return confirm({{ json_encode(__('admin.confirm_cancel_order')) }});">
            @csrf
            @method('PATCH')
            <input type="hidden" name="status" value="{{ \App\Models\Order::STATUS_CANCELLED }}">
            <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('admin.cancel_order') }}</button>
        </form>
        @endif
        <a href="{{ route('admin.warehouse.index') }}" class="btn btn-sm btn-outline-secondary">{{ __('admin.link_warehouse') }}</a>
        <a href="{{ route('admin.shipments.index') }}" class="btn btn-sm btn-outline-secondary">{{ __('admin.link_shipments') }}</a>
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

@include('admin.orders.partials.order-customer')

{{-- 1) Review: first required admin action --}}
@if($isAwaitingReview)
<div class="card border-warning border-3 shadow-sm mb-4">
    <div class="card-body p-4">
        <div class="d-flex flex-wrap align-items-start gap-3">
            <div class="flex-grow-1" style="min-width: 240px;">
                <div class="text-uppercase small text-warning fw-semibold mb-1">{{ __('admin.review_step_title') }}</div>
                <h5 class="mb-2">{{ __('admin.review_required_card_title') }}</h5>
                <p class="mb-2 text-body-secondary">{{ __('admin.review_required_card_intro') }}</p>
                <p class="small text-muted mb-0">{{ __('admin.review_required_next_steps') }}</p>
            </div>
        </div>
        <hr class="my-3">
        <form method="POST" action="{{ route('admin.orders.review', $order) }}" class="ajax-submit-form">
            @csrf
            @method('PATCH')
            <div class="mb-3">
                <label class="form-label fw-semibold">{{ __('admin.notes_review_comments') }}</label>
                <textarea name="admin_notes" class="form-control" rows="3" maxlength="5000" placeholder="{{ __('admin.checkout_review_gate_help') }}">{{ old('admin_notes', $order->admin_notes) }}</textarea>
            </div>
            <button type="submit" class="btn btn-primary btn-lg px-4">{{ __('admin.mark_reviewed_btn') }}</button>
        </form>
    </div>
</div>
@elseif($showReviewComplete)
<div class="alert alert-success border-0 shadow-sm d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 py-3" role="status">
    <div>
        <strong>{{ __('admin.review_complete_banner_title') }}</strong>
        <span class="text-body-secondary ms-md-2 d-block d-md-inline mt-1 mt-md-0">{{ __('admin.reviewed_at') }}: {{ $order->reviewed_at->format('Y-m-d H:i') }}</span>
    </div>
    <span class="small text-muted mb-0">{{ __('admin.review_complete_banner_hint') }}</span>
</div>
@endif

{{-- 2) Execution status & order details --}}
<div class="row g-4 mb-2">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-light border-0 py-3">
                <h5 class="mb-0">{{ __('admin.execution_section_title') }}</h5>
            </div>
            <div class="card-body">
                <p class="mb-3 fs-5">
                    <span class="text-muted me-2">{{ __('admin.execution_status_label') }}:</span>
                    <span class="badge bg-{{ $execBadge }} fs-6">{{ __('admin.execution_status_'.$exec) }}</span>
                </p>

                <div class="row g-2">
                    <div class="col-md-6">
                        <p class="mb-1"><strong>{{ __('admin.origin') }}:</strong> {{ $order->origin }}</p>
                        <p class="mb-1"><strong>{{ __('admin.total') }} (snapshot):</strong> {{ $order->order_total_snapshot !== null ? number_format($order->order_total_snapshot, 2) : number_format($order->total_amount, 2) }} {{ $order->currency }}</p>
                        <p class="mb-1"><strong>{{ __('admin.estimated') }}:</strong> {{ $order->estimated ? __('admin.yes') : __('admin.no') }}</p>
                    </div>
                    <div class="col-md-6">
                        <p class="mb-1"><strong>{{ __('admin.placed_at') }}:</strong> {{ $order->placed_at?->format('Y-m-d H:i') ?? '-' }}</p>
                        <p class="mb-1"><strong>{{ __('admin.estimated_delivery') }}:</strong> {{ $order->estimated_delivery ?? '-' }}</p>
                        <p class="mb-0"><strong>{{ __('admin.shipping_address') }}:</strong> {{ Str::limit($order->shipping_address_text ?? '-', 120) }}</p>
                    </div>
                </div>
                @if($order->admin_notes)
                    <p class="mb-0 mt-3 pt-3 border-top"><strong>{{ __('admin.admin_notes') }}:</strong> {{ $order->admin_notes }}</p>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- 3) Fulfillment summary & line items --}}
@include('admin.orders.partials.fulfillment-summary')
@include('admin.orders.partials.fulfillment-stage-strip')
@include('admin.orders.partials.procurement-line-items')
@include('admin.warehouse.partials.receive-modal')
@include('admin.warehouse.partials.receive-modal-script')

{{-- 4) Payments & notifications --}}
<div class="row g-4 mt-1">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header"><h5 class="mb-0">{{ __('admin.payments') }}</h5></div>
            <div class="card-body">
                @if(((float) ($order->wallet_applied_amount ?? 0)) > 0 && ($order->needs_review || $isAwaitingReview))
                    <div class="alert alert-light border mb-3 py-2 small">
                        <strong>{{ __('admin.wallet_checkout_pending') }}:</strong>
                        {{ number_format((float) $order->wallet_applied_amount, 2) }} {{ $order->currency }}
                        <div class="text-muted mt-1 mb-0">{{ __('admin.wallet_checkout_pending_help') }}</div>
                    </div>
                @endif
                @forelse($order->payments as $p)
                    @php
                        $statusValue = $p->status->value;
                        $badgeClass = $statusValue === 'paid' ? 'success' : ($statusValue === 'failed' ? 'danger' : 'secondary');
                        $methodLabel = match ((string) ($p->provider ?? '')) {
                            'wallet' => __('admin.payment_method_wallet'),
                            'square' => 'Square',
                            'stripe' => 'Stripe',
                            default => $p->provider ? (string) $p->provider : '—',
                        };
                    @endphp
                    <div class="mb-3 pb-2 border-bottom">
                        <p class="mb-1">
                            <strong>{{ __('admin.reference') }}:</strong> {{ $p->reference }}
                            <span class="badge bg-{{ $badgeClass }} ms-1">{{ $statusValue }}</span>
                        </p>
                        <p class="mb-1 small"><strong>{{ __('admin.payment_method') }}:</strong> {{ $methodLabel }}</p>
                        @if($p->provider && (string) $p->provider !== 'wallet')
                            <p class="mb-1 small text-muted">{{ __('admin.provider') }}: {{ $p->provider }}</p>
                        @endif
                        <p class="mb-0 small">{{ number_format((float)$p->amount, 2) }} {{ $p->currency }} @if($p->paid_at) — {{ __('admin.paid') }} {{ $p->paid_at->format('Y-m-d H:i') }} @endif</p>
                        @if($statusValue === 'failed' && ($p->failure_code || $p->failure_message))
                            <p class="mb-0 small text-danger">{{ __('admin.failure_code') }}: {{ $p->failure_code ?? '-' }} — {{ __('admin.failure_message') }}: {{ Str::limit($p->failure_message ?? '-', 100) }}</p>
                        @endif
                        @if($p->attempts->isNotEmpty() || $p->events->isNotEmpty())
                            <details class="mt-1 small">
                                <summary>{{ __('admin.payment_attempts') }} ({{ $p->attempts->count() }}) / {{ __('admin.payment_events') }} ({{ $p->events->count() }})</summary>
                                @if($p->attempts->isNotEmpty())
                                    <ul class="list-unstyled mb-0 mt-1">
                                        @foreach($p->attempts->sortByDesc('created_at')->take(5) as $a)
                                            <li>{{ $a->created_at->format('Y-m-d H:i') }} — {{ $a->status ?? 'attempt' }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                                @if($p->events->isNotEmpty())
                                    <ul class="list-unstyled mb-0 mt-1">
                                        @foreach($p->events->sortByDesc('created_at')->take(5) as $ev)
                                            <li>{{ $ev->created_at->format('Y-m-d H:i') }} — {{ $ev->event_type }} ({{ $ev->source?->value ?? '-' }})</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </details>
                        @endif
                    </div>
                @empty
                    <p class="text-muted mb-0">{{ __('admin.no_payments') }}</p>
                @endforelse
            </div>
        </div>
    </div>
    @if($order->user)
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header"><h5 class="mb-0">{{ __('admin.send_push_to_customer') }}</h5></div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.notifications.send-to-user', $order->user) }}" class="ajax-submit-form">
                    @csrf
                    <input type="text" name="title" class="form-control form-control-sm mb-2" placeholder="{{ __('admin.title') }}" required maxlength="200">
                    <textarea name="body" class="form-control form-control-sm mb-2" rows="2" placeholder="{{ __('admin.body') }}" maxlength="1000"></textarea>
                    <input type="hidden" name="target_type" value="order">
                    <input type="hidden" name="target_id" value="{{ $order->id }}">
                    <input type="hidden" name="route_key" value="order_details">
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('admin.send') }}</button>
                </form>
            </div>
        </div>
    </div>
    @endif
</div>

{{-- 5) Shipments & logs --}}
@include('admin.orders.partials.outbound-shipments')

@if ($priceLines->isNotEmpty())
<div class="card mt-4">
    <div class="card-header"><h5 class="mb-0">{{ __('admin.price_lines') }}</h5></div>
    <div class="card-body">
        <table class="table">
            @foreach($priceLines as $pl)
            <tr>
                <td>{{ $pl->label }}</td>
                <td class="{{ $pl->is_discount ? 'text-success' : '' }}">{{ $pl->amount }}</td>
            </tr>
            @endforeach
        </table>
    </div>
</div>
@endif

@if($order->operationLogs->isNotEmpty())
<div class="card mt-4">
    <div class="card-header"><h5 class="mb-0">{{ __('admin.operation_log') }}</h5></div>
    <div class="card-body">
        <table class="table table-sm">
            <thead><tr><th>{{ __('admin.date') }}</th><th>{{ __('admin.action') }}</th><th>{{ __('admin.details') }}</th><th>{{ __('admin.admin') }}</th></tr></thead>
            <tbody>
                @foreach($order->operationLogs->sortByDesc('created_at') as $log)
                <tr>
                    <td>{{ $log->created_at->format('Y-m-d H:i') }}</td>
                    <td>{{ $log->action }}</td>
                    <td>{{ $log->notes }} @if($log->payload) <br><small class="text-muted">{{ json_encode($log->payload) }}</small> @endif</td>
                    <td>{{ $log->admin?->name ?? '-' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

<div class="mt-4">
    <a href="{{ route('admin.orders.index') }}" class="btn btn-outline-secondary">{{ __('admin.back') }}</a>
</div>
@endsection

@include('admin.partials.ajax-form-script', ['redirect' => route('admin.orders.show', $order)])
