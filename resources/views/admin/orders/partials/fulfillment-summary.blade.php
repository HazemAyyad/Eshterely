@php
    $s = $fulfillmentSummary;
    $state = $orderFulfillmentState ?? 'no_items';
@endphp

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header py-3">
        <h5 class="mb-0">{{ __('admin.fulfillment_summary_title') }}</h5>
        <p class="small text-muted mb-0 mt-1">{{ __('admin.fulfillment_summary_subtitle') }}</p>
        @if($state !== 'no_items')
            <div class="mt-2 d-flex flex-wrap align-items-center gap-2">
                <span class="small text-muted">{{ __('admin.order_fulfillment_state_label') }}</span>
                <span class="badge rounded-pill bg-primary">{{ __('admin.order_fulfillment_state_'.$state) }}</span>
            </div>
        @endif
    </div>
    <div class="card-body pt-0">
        <div class="row g-2 g-md-3">
            <div class="col-6 col-md-3 col-xl-2">
                <div class="border rounded p-2 h-100 text-center bg-light">
                    <div class="small text-muted">{{ __('admin.fulfillment_sum_total') }}</div>
                    <div class="fs-4 fw-semibold">{{ $s['total_items'] }}</div>
                </div>
            </div>
            <div class="col-6 col-md-3 col-xl-2">
                <div class="border rounded p-2 h-100 text-center">
                    <div class="small text-muted">{{ __('admin.fulfillment_sum_awaiting_purchase') }}</div>
                    <div class="fs-4 fw-semibold text-secondary">{{ $s['awaiting_purchase'] }}</div>
                </div>
            </div>
            <div class="col-6 col-md-3 col-xl-2">
                <div class="border rounded p-2 h-100 text-center">
                    <div class="small text-muted">{{ __('admin.fulfillment_sum_purchased') }}</div>
                    <div class="fs-4 fw-semibold text-primary">{{ $s['purchased'] }}</div>
                </div>
            </div>
            <div class="col-6 col-md-3 col-xl-2">
                <div class="border rounded p-2 h-100 text-center">
                    <div class="small text-muted">{{ __('admin.fulfillment_sum_in_transit') }}</div>
                    <div class="fs-4 fw-semibold text-warning">{{ $s['in_transit'] }}</div>
                </div>
            </div>
            <div class="col-6 col-md-3 col-xl-2">
                <div class="border rounded p-2 h-100 text-center">
                    <div class="small text-muted">{{ __('admin.fulfillment_sum_at_warehouse') }}</div>
                    <div class="fs-4 fw-semibold text-success">{{ $s['at_warehouse'] }}</div>
                    <div class="small text-muted">{{ __('admin.fulfillment_sum_arrived_ready', ['a' => $s['arrived'], 'r' => $s['ready_for_shipment']]) }}</div>
                </div>
            </div>
            <div class="col-6 col-md-3 col-xl-2">
                <div class="border rounded p-2 h-100 text-center">
                    <div class="small text-muted">{{ __('admin.fulfillment_sum_on_outbound') }}</div>
                    <div class="fs-4 fw-semibold text-info">{{ $s['assigned_outbound'] }}</div>
                </div>
            </div>
            <div class="col-6 col-md-3 col-xl-2">
                <div class="border rounded p-2 h-100 text-center">
                    <div class="small text-muted">{{ __('admin.fulfillment_sum_outbound_shipped') }}</div>
                    <div class="fs-4 fw-semibold text-success">{{ $s['outbound_shipped'] }}</div>
                </div>
            </div>
        </div>
    </div>
</div>
