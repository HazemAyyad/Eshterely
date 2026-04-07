@extends('layouts.admin')

@section('title', __('admin.order_number') . ' ' . $order->order_number)

@section('content')
<h4 class="py-4 mb-4">{{ __('admin.order_number') }} {{ $order->order_number }}</h4>

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

<div class="alert alert-light border shadow-sm mb-4">
    <div class="d-flex flex-wrap gap-3 align-items-center justify-content-between">
        <div>
            <strong>{{ __('admin.ops_flow_title') }}</strong>
            <span class="text-muted ms-1">{{ __('admin.ops_flow_body') }}</span>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('admin.warehouse.index') }}" class="btn btn-sm btn-outline-primary">{{ __('admin.menu_warehouse_ops') }}</a>
            <a href="{{ route('admin.shipments.index') }}" class="btn btn-sm btn-outline-primary">{{ __('admin.menu_shipments_ops') }}</a>
        </div>
    </div>
</div>

@include('admin.orders.partials.procurement-line-items')

<div class="row g-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0">{{ __('admin.order_info') }}</h5>
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
            </div>
            <div class="card-body">
                <p><strong>{{ __('admin.status') }}:</strong> <span class="badge bg-{{ $order->status === 'delivered' ? 'success' : ($order->status === 'cancelled' ? 'danger' : 'warning') }}">{{ $order->status }}</span></p>
                <p><strong>{{ __('admin.payment') }}:</strong>
                    @if($order->payments->contains(fn ($p) => $p->status->value === 'paid'))
                        <span class="badge bg-success">{{ __('admin.paid') }}</span>
                        ({{ $order->payments->first(fn ($p) => $p->paid_at)?->reference ?? '-' }})
                    @elseif($order->payments->contains(fn ($p) => $p->status->value === 'failed'))
                        <span class="badge bg-danger">{{ __('admin.failed') }}</span>
                    @else
                        <span class="badge bg-secondary">{{ __('admin.pending') }}</span>
                    @endif
                </p>
                <p><strong>{{ __('admin.origin') }}:</strong> {{ $order->origin }}</p>
                <p><strong>{{ __('admin.total') }} (snapshot):</strong> {{ $order->order_total_snapshot !== null ? number_format($order->order_total_snapshot, 2) : number_format($order->total_amount, 2) }} {{ $order->currency }}</p>
                <p><strong>{{ __('admin.estimated') }}:</strong> {{ $order->estimated ? __('admin.yes') : __('admin.no') }}</p>
                <p><strong>{{ __('admin.needs_review') }}:</strong> {{ $order->needs_review ? __('admin.yes') : __('admin.no') }}</p>
                <p><strong>{{ __('admin.reviewed_at') }}:</strong> {{ $order->reviewed_at?->format('Y-m-d H:i') ?? '-' }}</p>
                @if($order->admin_notes)
                    <p><strong>{{ __('admin.admin_notes') }}:</strong> {{ $order->admin_notes }}</p>
                @endif
                <p><strong>{{ __('admin.placed_at') }}:</strong> {{ $order->placed_at?->format('Y-m-d H:i') ?? '-' }}</p>
                <p><strong>{{ __('admin.estimated_delivery') }}:</strong> {{ $order->estimated_delivery ?? '-' }}</p>
                <p><strong>{{ __('admin.shipping_address') }}:</strong> {{ Str::limit($order->shipping_address_text ?? '-', 80) }}</p>
            </div>
        </div>

        @if($order->needs_review || !$order->reviewed_at)
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header"><h5 class="mb-0">{{ __('admin.mark_reviewed') }}</h5></div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.orders.review', $order) }}" class="ajax-submit-form">
                    @csrf
                    @method('PATCH')
                    <div class="mb-2">
                        <label class="form-label">{{ __('admin.notes_review_comments') }}</label>
                        <textarea name="admin_notes" class="form-control" rows="2" maxlength="5000">{{ old('admin_notes', $order->admin_notes) }}</textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">{{ __('admin.mark_reviewed_btn') }}</button>
                </form>
            </div>
        </div>
        @endif
    </div>

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
        @if($order->user)
        <div class="card border-0 shadow-sm mt-4">
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
        @endif
    </div>
</div>

@foreach($order->shipments as $shipment)
<div class="card mt-4">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h5 class="mb-0">{{ __('admin.shipment') }} {{ $shipment->country_label ?? $shipment->country_code }}</h5>
        <div class="d-flex gap-1 flex-wrap">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#tracking-{{ $shipment->id }}">{{ __('admin.tracking') }}</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#override-{{ $shipment->id }}">{{ __('admin.shipping_override') }}</button>
        </div>
    </div>
    <div class="card-body">
        <p><strong>{{ __('admin.carrier') }}:</strong> {{ $shipment->carrier ?? '-' }}</p>
        <p><strong>{{ __('admin.tracking_number') }}:</strong> {{ $shipment->tracking_number ?? '-' }}</p>
        <p><strong>{{ __('admin.shipment_status') }}:</strong> {{ $shipment->shipment_status ?? '-' }}</p>
        <p><strong>{{ __('admin.estimated_delivery') }}:</strong> {{ $shipment->estimated_delivery_at?->format('Y-m-d') ?? '-' }}</p>
        <p><strong>{{ __('admin.delivered_at') }}:</strong> {{ $shipment->delivered_at?->format('Y-m-d H:i') ?? '-' }}</p>
        <p><strong>{{ __('admin.shipping_method') }}:</strong> {{ $shipment->shipping_method ?? '-' }}</p>
        <p><strong>{{ __('admin.subtotal') }}:</strong> {{ number_format($shipment->subtotal ?? 0, 2) }}</p>
        <p><strong>{{ __('admin.shipping_fee') }}:</strong> {{ number_format($shipment->shipping_fee ?? 0, 2) }}</p>
        @if($shipment->notes)
            <p><strong>{{ __('admin.notes') }}:</strong> {{ $shipment->notes }}</p>
        @endif
        <div class="collapse mt-2" id="tracking-{{ $shipment->id }}">
            <h6 class="mt-2">{{ __('admin.update_shipment') }}</h6>
            <form method="POST" action="{{ route('admin.orders.shipments.update', [$order, $shipment]) }}" class="ajax-submit-form mb-3">
                @csrf
                @method('PATCH')
                <div class="row g-2">
                    <div class="col-md-3"><input type="text" name="carrier" class="form-control form-control-sm" placeholder="{{ __('admin.carrier') }}" value="{{ $shipment->carrier }}" maxlength="50"></div>
                    <div class="col-md-3"><input type="text" name="tracking_number" class="form-control form-control-sm" placeholder="{{ __('admin.tracking_number') }}" value="{{ $shipment->tracking_number }}"></div>
                    <div class="col-md-2"><input type="text" name="shipment_status" class="form-control form-control-sm" placeholder="{{ __('admin.status') }}" value="{{ $shipment->shipment_status }}" maxlength="50"></div>
                    <div class="col-md-2"><input type="date" name="estimated_delivery_at" class="form-control form-control-sm" value="{{ $shipment->estimated_delivery_at?->format('Y-m-d') }}"></div>
                    <div class="col-md-2"><button type="submit" class="btn btn-sm btn-primary">{{ __('admin.update') }}</button></div>
                </div>
                <div class="mt-1"><input type="text" name="notes" class="form-control form-control-sm" placeholder="{{ __('admin.notes') }}" value="{{ $shipment->notes }}" maxlength="2000"></div>
            </form>
            <h6 class="mt-2">{{ __('admin.add_timeline_event') }}</h6>
            <form method="POST" action="{{ route('admin.orders.shipments.events.store', [$order, $shipment]) }}" class="ajax-submit-form mb-3">
                @csrf
                <div class="row g-2">
                    <div class="col-md-3">
                        <select name="event_type" class="form-select form-select-sm" required>
                            @foreach($shipmentEventTypes as $et)
                                <option value="{{ $et }}">{{ $et }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2"><input type="text" name="event_label" class="form-control form-control-sm" placeholder="{{ __('admin.label') }}"></div>
                    <div class="col-md-2"><input type="datetime-local" name="event_time" class="form-control form-control-sm"></div>
                    <div class="col-md-2"><input type="text" name="location" class="form-control form-control-sm" placeholder="{{ __('admin.location') }}"></div>
                    <div class="col-md-2"><input type="text" name="notes" class="form-control form-control-sm" placeholder="{{ __('admin.notes') }}"></div>
                    <div class="col-md-1"><button type="submit" class="btn btn-sm btn-primary">{{ __('admin.add') }}</button></div>
                </div>
            </form>
            @if(!$shipment->delivered_at)
            <form method="POST" action="{{ route('admin.orders.shipments.delivered', [$order, $shipment]) }}" class="ajax-submit-form d-inline">
                @csrf
                @method('PATCH')
                <button type="submit" class="btn btn-sm btn-success">{{ __('admin.mark_delivered') }}</button>
            </form>
            @endif
        </div>
        @if($shipment->events->isNotEmpty())
        <h6 class="mt-3">{{ __('admin.timeline_events') }}</h6>
        <table class="table table-sm">
            <thead><tr><th>{{ __('admin.time') }}</th><th>{{ __('admin.type') }}</th><th>{{ __('admin.label') }}</th><th>{{ __('admin.location') }}</th><th>{{ __('admin.notes') }}</th></tr></thead>
            <tbody>
                @foreach($shipment->events->sortByDesc('event_time') as $ev)
                <tr>
                    <td>{{ $ev->event_time?->format('Y-m-d H:i') ?? $ev->created_at->format('Y-m-d H:i') }}</td>
                    <td>{{ $ev->event_type }}</td>
                    <td>{{ $ev->event_label ?? '-' }}</td>
                    <td>{{ $ev->location ?? '-' }}</td>
                    <td>{{ $ev->notes ?? '-' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
        @if($shipment->shipping_override_amount !== null || $shipment->shipping_override_carrier)
            <p><strong>{{ __('admin.override_amount') }}:</strong> {{ $shipment->shipping_override_amount !== null ? number_format($shipment->shipping_override_amount, 2) : '-' }}</p>
            <p><strong>{{ __('admin.override_carrier') }}:</strong> {{ $shipment->shipping_override_carrier ?? '-' }}</p>
            <p><strong>{{ __('admin.override_at') }}:</strong> {{ $shipment->shipping_override_at?->format('Y-m-d H:i') ?? '-' }}</p>
        @endif
        <div class="collapse mt-2" id="override-{{ $shipment->id }}">
            <form method="POST" action="{{ route('admin.orders.shipping-override', $order) }}" class="ajax-submit-form">
                @csrf
                @method('PATCH')
                <input type="hidden" name="order_shipment_id" value="{{ $shipment->id }}">
                <div class="row g-2">
                    <div class="col-md-4"><input type="number" step="0.01" min="0" name="shipping_override_amount" class="form-control form-control-sm" placeholder="{{ __('admin.override_amount') }}" value="{{ $shipment->shipping_override_amount }}"></div>
                    <div class="col-md-4"><input type="text" name="shipping_override_carrier" class="form-control form-control-sm" placeholder="{{ __('admin.carrier') }}" value="{{ $shipment->shipping_override_carrier }}" maxlength="50"></div>
                    <div class="col-md-4"><input type="text" name="shipping_override_notes" class="form-control form-control-sm" placeholder="{{ __('admin.notes') }}" value="{{ $shipment->shipping_override_notes }}" maxlength="1000"></div>
                </div>
                <button type="submit" class="btn btn-sm btn-primary mt-2">{{ __('admin.apply_override') }}</button>
            </form>
        </div>
        <table class="table table-sm mt-3">
            <thead><tr><th>{{ __('admin.product') }}</th><th>{{ __('admin.store') }}</th><th>{{ __('admin.price') }}</th><th>{{ __('admin.qty') }}</th><th>{{ __('admin.source_carrier') }}</th></tr></thead>
            <tbody>
                @foreach($shipment->lineItems as $li)
                <tr>
                    <td>{{ $li->name }}</td>
                    <td>{{ $li->store_name ?? '-' }}</td>
                    <td>{{ number_format($li->price, 2) }}</td>
                    <td>{{ $li->quantity }}</td>
                    <td>{{ $li->source_type ?? '-' }} / {{ $li->review_metadata['carrier'] ?? '-' }}</td>
                </tr>
                @if($li->product_snapshot || $li->pricing_snapshot || $li->missing_fields)
                <tr class="table-light">
                    <td colspan="5" class="small">
                        @if($li->product_snapshot) <strong>Product snapshot:</strong> {{ json_encode($li->product_snapshot) }} @endif
                        @if($li->pricing_snapshot) <strong>Pricing snapshot:</strong> {{ json_encode($li->pricing_snapshot) }} @endif
                        @if($li->missing_fields) <strong>Missing fields:</strong> {{ json_encode($li->missing_fields) }} @endif
                        @if($li->estimated) <span class="badge bg-info">estimated</span> @endif
                        @if($li->imported_product_id) Imported ID: {{ $li->imported_product_id }} @endif
                        @if($li->cart_item_id) Cart item ID: {{ $li->cart_item_id }} @endif
                    </td>
                </tr>
                @endif
                @endforeach
            </tbody>
        </table>
        @if ($shipment->trackingEvents->isNotEmpty())
        <h6 class="mt-3">{{ __('admin.tracking_events') }}</h6>
        <ul class="list-unstyled">
            @foreach($shipment->trackingEvents->sortBy('sort_order') as $ev)
            <li>{{ $ev->title }} — {{ $ev->subtitle ?? '' }}</li>
            @endforeach
        </ul>
        @endif
    </div>
</div>
@endforeach

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