@extends('layouts.admin')

@section('title', __('admin.order_number') . ' ' . $order->order_number)

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 py-4 mb-2">
    <h4 class="mb-0">{{ __('admin.order_number') }} {{ $order->order_number }}</h4>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('admin.warehouse.index') }}" class="btn btn-sm btn-outline-primary">{{ __('admin.link_warehouse') }}</a>
        <a href="{{ route('admin.shipments.index') }}" class="btn btn-sm btn-outline-primary">{{ __('admin.link_shipments') }}</a>
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

<div class="row g-4 mb-2">
    <div class="col-12 col-xl-8">
        @php
            $exec = $executionStatus ?? \App\Support\OrderExecutionStatus::AWAITING_PAYMENT;
            $execBadge = \App\Support\OrderExecutionStatus::badgeClass($exec);
            $fs = $orderFulfillmentState ?? 'no_items';
        @endphp
        <div class="card border-0 shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0">{{ __('admin.order_info') }}</h5>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                @if(count($canTransitionTo) > 0)
                <form method="POST" action="{{ route('admin.orders.update-status', $order) }}" class="d-flex gap-2 ajax-submit-form">
                    @csrf
                    @method('PATCH')
                    <select name="status" class="form-select form-select-sm" style="width:auto" required>
                        <option value="">{{ __('admin.change_status') }}</option>
                        @foreach($canTransitionTo as $s)
                            <option value="{{ $s }}">{{ $s }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('admin.update') }}</button>
                </form>
                @endif
                @if(in_array(\App\Models\Order::STATUS_CANCELLED, $canTransitionTo ?? [], true))
                <form method="POST" action="{{ route('admin.orders.update-status', $order) }}" class="ajax-submit-form" onsubmit="return confirm({{ json_encode(__('admin.confirm_cancel_order')) }});">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="{{ \App\Models\Order::STATUS_CANCELLED }}">
                    <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('admin.cancel_order') }}</button>
                </form>
                @endif
                </div>
            </div>
            <div class="card-body">
                <p class="mb-2">
                    <strong>{{ __('admin.execution_status_label') }}:</strong>
                    <span class="badge bg-{{ $execBadge }}">{{ __('admin.execution_status_'.$exec) }}</span>
                </p>
                <details class="mb-3 small text-muted">
                    <summary class="fw-semibold text-body">{{ __('admin.internal_record_status_heading') }}</summary>
                    <p class="mb-1 mt-2"><strong>{{ __('admin.order_record_status') }}:</strong> <span class="badge bg-secondary">{{ $order->status }}</span></p>
                    <p class="mb-0"><strong>{{ __('admin.derived_fulfillment_snapshot') }}:</strong> {{ __('admin.order_fulfillment_state_'.$fs) }}</p>
                </details>
                <p><strong>{{ __('admin.origin') }}:</strong> {{ $order->origin }}</p>
                <p><strong>{{ __('admin.total') }} (snapshot):</strong> {{ $order->order_total_snapshot !== null ? number_format($order->order_total_snapshot, 2) : number_format($order->total_amount, 2) }} {{ $order->currency }}</p>
                <p><strong>{{ __('admin.estimated') }}:</strong> {{ $order->estimated ? __('admin.yes') : __('admin.no') }}</p>
                @if($order->admin_notes)
                    <p><strong>{{ __('admin.admin_notes') }}:</strong> {{ $order->admin_notes }}</p>
                @endif
                <p><strong>{{ __('admin.placed_at') }}:</strong> {{ $order->placed_at?->format('Y-m-d H:i') ?? '-' }}</p>
                <p><strong>{{ __('admin.estimated_delivery') }}:</strong> {{ $order->estimated_delivery ?? '-' }}</p>
                <p><strong>{{ __('admin.shipping_address') }}:</strong> {{ Str::limit($order->shipping_address_text ?? '-', 80) }}</p>

                <details class="mt-3 pt-3 border-top border-primary border-opacity-25 rounded px-2 pb-2" @if($exec === \App\Support\OrderExecutionStatus::AWAITING_REVIEW) open @endif>
                    <summary class="fw-semibold text-body">{{ __('admin.review_step_title') }}</summary>
                    <p class="small text-muted mb-2 mt-2">{{ __('admin.checkout_review_gate_help') }}</p>
                    <p class="small mb-1"><strong>{{ __('admin.needs_review') }}:</strong> {{ $order->needs_review ? __('admin.yes') : __('admin.no') }}</p>
                    <p class="small mb-3"><strong>{{ __('admin.reviewed_at') }}:</strong> {{ $order->reviewed_at?->format('Y-m-d H:i') ?? '—' }}</p>
                    @if($exec === \App\Support\OrderExecutionStatus::AWAITING_REVIEW)
                        <form method="POST" action="{{ route('admin.orders.review', $order) }}" class="ajax-submit-form">
                            @csrf
                            @method('PATCH')
                            <div class="mb-2">
                                <label class="form-label small">{{ __('admin.notes_review_comments') }}</label>
                                <textarea name="admin_notes" class="form-control form-control-sm" rows="2" maxlength="5000">{{ old('admin_notes', $order->admin_notes) }}</textarea>
                            </div>
                            <button type="submit" class="btn btn-sm btn-outline-secondary">{{ __('admin.mark_reviewed_btn') }}</button>
                        </form>
                    @endif
                </details>
            </div>
        </div>
    </div>
</div>

@include('admin.orders.partials.fulfillment-summary')
@include('admin.orders.partials.fulfillment-stage-strip')
@include('admin.orders.partials.procurement-line-items')
@include('admin.warehouse.partials.receive-modal')
@include('admin.warehouse.partials.receive-modal-script')

<div class="row g-4 mt-1">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header"><h5 class="mb-0">{{ __('admin.payments') }}</h5></div>
            <div class="card-body">
                @if(((float) ($order->wallet_applied_amount ?? 0)) > 0 && ($order->needs_review || $order->status === \App\Models\Order::STATUS_UNDER_REVIEW))
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