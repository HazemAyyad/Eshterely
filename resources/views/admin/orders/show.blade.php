@extends('layouts.admin')

@section('title', 'Order ' . $order->order_number)

@section('content')
<h4 class="py-4 mb-4">Order {{ $order->order_number }}</h4>

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

<div class="row g-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0">Order info</h5>
                @if(count($canTransitionTo) > 0)
                <form method="POST" action="{{ route('admin.orders.update-status', $order) }}" class="d-flex gap-2 ajax-submit-form">
                    @csrf
                    @method('PATCH')
                    <select name="status" class="form-select form-select-sm" style="width:auto" required>
                        <option value="">Change status</option>
                        @foreach($canTransitionTo as $s)
                            <option value="{{ $s }}">{{ $s }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-sm btn-primary">Update</button>
                </form>
                @endif
            </div>
            <div class="card-body">
                <p><strong>Status:</strong> <span class="badge bg-{{ $order->status === 'delivered' ? 'success' : ($order->status === 'cancelled' ? 'danger' : 'warning') }}">{{ $order->status }}</span></p>
                <p><strong>Payment:</strong>
                    @if($order->payments->contains(fn ($p) => $p->status->value === 'paid'))
                        <span class="badge bg-success">Paid</span>
                        ({{ $order->payments->first(fn ($p) => $p->paid_at)?->reference ?? '-' }})
                    @else
                        <span class="badge bg-secondary">Pending</span>
                    @endif
                </p>
                <p><strong>Origin:</strong> {{ $order->origin }}</p>
                <p><strong>Total (snapshot):</strong> {{ $order->order_total_snapshot !== null ? number_format($order->order_total_snapshot, 2) : number_format($order->total_amount, 2) }} {{ $order->currency }}</p>
                <p><strong>Estimated:</strong> {{ $order->estimated ? 'Yes' : 'No' }}</p>
                <p><strong>Needs review:</strong> {{ $order->needs_review ? 'Yes' : 'No' }}</p>
                <p><strong>Reviewed at:</strong> {{ $order->reviewed_at?->format('Y-m-d H:i') ?? '-' }}</p>
                @if($order->admin_notes)
                    <p><strong>Admin notes:</strong> {{ $order->admin_notes }}</p>
                @endif
                <p><strong>Placed at:</strong> {{ $order->placed_at?->format('Y-m-d H:i') ?? '-' }}</p>
                <p><strong>Estimated delivery:</strong> {{ $order->estimated_delivery ?? '-' }}</p>
                <p><strong>Shipping address:</strong> {{ Str::limit($order->shipping_address_text ?? '-', 80) }}</p>
            </div>
        </div>

        @if($order->needs_review || !$order->reviewed_at)
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header"><h5 class="mb-0">Mark as reviewed</h5></div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.orders.review', $order) }}" class="ajax-submit-form">
                    @csrf
                    @method('PATCH')
                    <div class="mb-2">
                        <label class="form-label">Notes / review comments</label>
                        <textarea name="admin_notes" class="form-control" rows="2" maxlength="5000">{{ old('admin_notes', $order->admin_notes) }}</textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Mark reviewed</button>
                </form>
            </div>
        </div>
        @endif
    </div>

    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header"><h5 class="mb-0">Payments</h5></div>
            <div class="card-body">
                @forelse($order->payments as $p)
                    <p class="mb-1"><strong>{{ $p->reference }}</strong> — {{ $p->status->value }} — {{ number_format($p->amount, 2) }} {{ $p->currency }}
                        @if($p->paid_at) (paid {{ $p->paid_at->format('Y-m-d') }}) @endif
                    </p>
                @empty
                    <p class="text-muted">No payments</p>
                @endforelse
            </div>
        </div>
        @if($order->user)
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header"><h5 class="mb-0">Send push to customer</h5></div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.notifications.send-to-user', $order->user) }}" class="ajax-submit-form">
                    @csrf
                    <input type="text" name="title" class="form-control form-control-sm mb-2" placeholder="Title" required maxlength="200">
                    <textarea name="body" class="form-control form-control-sm mb-2" rows="2" placeholder="Body" maxlength="1000"></textarea>
                    <input type="hidden" name="target_type" value="order">
                    <input type="hidden" name="target_id" value="{{ $order->id }}">
                    <input type="hidden" name="route_key" value="order_detail">
                    <button type="submit" class="btn btn-sm btn-primary">Send</button>
                </form>
            </div>
        </div>
        @endif
    </div>
</div>

@foreach($order->shipments as $shipment)
<div class="card mt-4">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h5 class="mb-0">Shipment {{ $shipment->country_label ?? $shipment->country_code }}</h5>
        <div class="d-flex gap-1 flex-wrap">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#tracking-{{ $shipment->id }}">Tracking</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#override-{{ $shipment->id }}">Shipping override</button>
        </div>
    </div>
    <div class="card-body">
        <p><strong>Carrier:</strong> {{ $shipment->carrier ?? '-' }}</p>
        <p><strong>Tracking number:</strong> {{ $shipment->tracking_number ?? '-' }}</p>
        <p><strong>Shipment status:</strong> {{ $shipment->shipment_status ?? '-' }}</p>
        <p><strong>Estimated delivery:</strong> {{ $shipment->estimated_delivery_at?->format('Y-m-d') ?? '-' }}</p>
        <p><strong>Delivered at:</strong> {{ $shipment->delivered_at?->format('Y-m-d H:i') ?? '-' }}</p>
        <p><strong>Shipping method:</strong> {{ $shipment->shipping_method ?? '-' }}</p>
        <p><strong>Subtotal:</strong> {{ number_format($shipment->subtotal ?? 0, 2) }}</p>
        <p><strong>Shipping fee:</strong> {{ number_format($shipment->shipping_fee ?? 0, 2) }}</p>
        @if($shipment->notes)
            <p><strong>Notes:</strong> {{ $shipment->notes }}</p>
        @endif
        <div class="collapse mt-2" id="tracking-{{ $shipment->id }}">
            <h6 class="mt-2">Update shipment</h6>
            <form method="POST" action="{{ route('admin.orders.shipments.update', [$order, $shipment]) }}" class="ajax-submit-form mb-3">
                @csrf
                @method('PATCH')
                <div class="row g-2">
                    <div class="col-md-3"><input type="text" name="carrier" class="form-control form-control-sm" placeholder="Carrier" value="{{ $shipment->carrier }}" maxlength="50"></div>
                    <div class="col-md-3"><input type="text" name="tracking_number" class="form-control form-control-sm" placeholder="Tracking number" value="{{ $shipment->tracking_number }}"></div>
                    <div class="col-md-2"><input type="text" name="shipment_status" class="form-control form-control-sm" placeholder="Status" value="{{ $shipment->shipment_status }}" maxlength="50"></div>
                    <div class="col-md-2"><input type="date" name="estimated_delivery_at" class="form-control form-control-sm" value="{{ $shipment->estimated_delivery_at?->format('Y-m-d') }}"></div>
                    <div class="col-md-2"><button type="submit" class="btn btn-sm btn-primary">Update</button></div>
                </div>
                <div class="mt-1"><input type="text" name="notes" class="form-control form-control-sm" placeholder="Notes" value="{{ $shipment->notes }}" maxlength="2000"></div>
            </form>
            <h6 class="mt-2">Add timeline event</h6>
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
                    <div class="col-md-2"><input type="text" name="event_label" class="form-control form-control-sm" placeholder="Label"></div>
                    <div class="col-md-2"><input type="datetime-local" name="event_time" class="form-control form-control-sm"></div>
                    <div class="col-md-2"><input type="text" name="location" class="form-control form-control-sm" placeholder="Location"></div>
                    <div class="col-md-2"><input type="text" name="notes" class="form-control form-control-sm" placeholder="Notes"></div>
                    <div class="col-md-1"><button type="submit" class="btn btn-sm btn-primary">Add</button></div>
                </div>
            </form>
            @if(!$shipment->delivered_at)
            <form method="POST" action="{{ route('admin.orders.shipments.delivered', [$order, $shipment]) }}" class="ajax-submit-form d-inline">
                @csrf
                @method('PATCH')
                <button type="submit" class="btn btn-sm btn-success">Mark delivered</button>
            </form>
            @endif
        </div>
        @if($shipment->events->isNotEmpty())
        <h6 class="mt-3">Timeline events</h6>
        <table class="table table-sm">
            <thead><tr><th>Time</th><th>Type</th><th>Label</th><th>Location</th><th>Notes</th></tr></thead>
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
            <p><strong>Override amount:</strong> {{ $shipment->shipping_override_amount !== null ? number_format($shipment->shipping_override_amount, 2) : '-' }}</p>
            <p><strong>Override carrier:</strong> {{ $shipment->shipping_override_carrier ?? '-' }}</p>
            <p><strong>Override at:</strong> {{ $shipment->shipping_override_at?->format('Y-m-d H:i') ?? '-' }}</p>
        @endif
        <div class="collapse mt-2" id="override-{{ $shipment->id }}">
            <form method="POST" action="{{ route('admin.orders.shipping-override', $order) }}" class="ajax-submit-form">
                @csrf
                @method('PATCH')
                <input type="hidden" name="order_shipment_id" value="{{ $shipment->id }}">
                <div class="row g-2">
                    <div class="col-md-4"><input type="number" step="0.01" min="0" name="shipping_override_amount" class="form-control form-control-sm" placeholder="Override amount" value="{{ $shipment->shipping_override_amount }}"></div>
                    <div class="col-md-4"><input type="text" name="shipping_override_carrier" class="form-control form-control-sm" placeholder="Carrier" value="{{ $shipment->shipping_override_carrier }}" maxlength="50"></div>
                    <div class="col-md-4"><input type="text" name="shipping_override_notes" class="form-control form-control-sm" placeholder="Notes" value="{{ $shipment->shipping_override_notes }}" maxlength="1000"></div>
                </div>
                <button type="submit" class="btn btn-sm btn-primary mt-2">Apply override</button>
            </form>
        </div>
        <table class="table table-sm mt-3">
            <thead><tr><th>Product</th><th>Store</th><th>Price</th><th>Qty</th><th>Source / Carrier</th></tr></thead>
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
        <h6 class="mt-3">Tracking events</h6>
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
    <div class="card-header"><h5 class="mb-0">Price lines</h5></div>
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
    <div class="card-header"><h5 class="mb-0">Operation log</h5></div>
    <div class="card-body">
        <table class="table table-sm">
            <thead><tr><th>Date</th><th>Action</th><th>Details</th><th>Admin</th></tr></thead>
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