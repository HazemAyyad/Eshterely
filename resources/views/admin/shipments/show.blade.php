@extends('layouts.admin')

@section('title', __('admin.shipment_detail') . ' #' . $shipment->id)

@section('content')
@php
    use App\Support\AdminFulfillmentLabels;
    use App\Support\AdminOrderLineItemDisplay;
    use App\Support\AdminWarehouseReceiptImages;
    use App\Models\Shipment;
    use Illuminate\Support\Str;
    $st = AdminFulfillmentLabels::outboundShipment($shipment->status);
@endphp

<h4 class="py-4 mb-2">{{ __('admin.shipment_detail') }} #{{ $shipment->id }}</h4>

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
            <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
                <h5 class="mb-0">{{ __('admin.details') }}</h5>
                <div class="d-flex flex-wrap gap-1">
                    @if(in_array($shipment->status, [Shipment::STATUS_PAID, Shipment::STATUS_PACKED], true))
                        <a href="{{ route('admin.shipments.pack-form', $shipment) }}" class="btn btn-sm btn-primary">{{ __('admin.pack_shipment') }}</a>
                    @endif
                    @if($shipment->status === Shipment::STATUS_PACKED)
                        <a href="{{ route('admin.shipments.ship-form', $shipment) }}" class="btn btn-sm btn-success">{{ __('admin.mark_shipped') }}</a>
                    @endif
                </div>
            </div>
            <div class="card-body">
                <p class="mb-1"><strong>{{ __('admin.status') }}:</strong> <span class="badge bg-{{ $st['badge'] }}">{{ $st['label'] }}</span></p>
                <p class="mb-1"><strong>{{ __('admin.user_name') }}:</strong>
                    @if($shipment->user)
                        <a href="{{ route('admin.users.show', $shipment->user) }}">{{ $shipment->user->phone ?? $shipment->user->email ?? ('#'.$shipment->user_id) }}</a>
                    @else
                        —
                    @endif
                </p>
                <p class="mb-1"><strong>{{ __('admin.currency') }}:</strong> {{ $shipment->currency }}</p>
                <p class="mb-1"><strong>{{ __('admin.carrier') }}:</strong> {{ $shipment->carrier ?? '—' }}</p>
                <p class="mb-1"><strong>{{ __('admin.tracking_number') }}:</strong> {{ $shipment->tracking_number ?? '—' }}</p>
                <p class="mb-1"><strong>{{ __('admin.date') }} (dispatched):</strong> {{ $shipment->dispatched_at?->format('Y-m-d H:i') ?? '—' }}</p>
                @if($shipment->final_box_image)
                    <p class="mb-1"><strong>{{ __('admin.final_box_image') }}:</strong>
                        <a href="{{ AdminWarehouseReceiptImages::displayUrl($shipment->final_box_image) }}" target="_blank" rel="noopener noreferrer">{{ __('admin.source_link_open') }}</a>
                    </p>
                @endif
                @if($shipment->pricing_breakdown && !empty($shipment->pricing_breakdown['admin_dispatch_note']))
                    <p class="mb-0"><strong>{{ __('admin.dispatch_note') }}:</strong> {{ $shipment->pricing_breakdown['admin_dispatch_note'] }}</p>
                @endif
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header"><h5 class="mb-0">{{ __('admin.shipping_address') }}</h5></div>
            <div class="card-body">
                @php $a = $shipment->destinationAddress; @endphp
                @if($a)
                    <p class="mb-1">{{ $a->address_line ?? $a->street_address }}</p>
                    <p class="mb-0 small text-muted">{{ $a->city?->name ?? '' }} {{ $a->country?->code ?? '' }}</p>
                @else
                    <p class="text-muted mb-0">—</p>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mt-4">
    <div class="card-header"><h5 class="mb-0">{{ __('admin.shipment_items') }}</h5></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="min-width:200px">{{ __('admin.product') }}</th>
                        <th style="min-width:120px">{{ __('admin.order_and_line_ref') }}</th>
                        <th style="min-width:140px">{{ __('admin.received_images_col') }}</th>
                        <th style="min-width:220px">{{ __('admin.wh_intake_details_col') }}</th>
                        <th>{{ __('admin.store') }}</th>
                        <th style="min-width:120px">{{ __('admin.item_fulfillment_stage') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($shipment->items as $si)
                        @php
                            $li = $si->orderLineItem;
                            $fp = $li ? AdminFulfillmentLabels::lineItemFulfillment($li->fulfillment_status) : ['label'=>'—','badge'=>'secondary'];
                            $ord = $li?->shipment?->order;
                            $cur = $ord?->currency ?? 'USD';
                        @endphp
                        <tr>
                            <td class="align-top">
                                @if($li)
                                    {!! AdminOrderLineItemDisplay::adminProductThumbnailWithNameHtml($li, 48, 72) !!}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="align-top small">
                                @if($ord)
                                    <a href="{{ route('admin.orders.show', $ord) }}" class="fw-semibold">{{ $ord->order_number }}</a>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                                @if($li)
                                    <div class="text-muted mt-1">{{ __('admin.line_item_id_label') }} {{ $li->id }}</div>
                                @endif
                            </td>
                            <td class="align-top small">
                                @if($li)
                                    {!! AdminOrderLineItemDisplay::adminWarehouseReceiptThumbnailsHtml($li, 40, 8) !!}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="align-top small">
                                @if($li && $li->warehouseReceipts->isNotEmpty())
                                    @foreach($li->warehouseReceipts->sortByDesc('received_at') as $wr)
                                        <div class="@if(!$loop->last) border-bottom pb-2 mb-2 @endif">
                                            <div class="text-muted mb-1">{{ $wr->received_at?->format('Y-m-d H:i') ?? '—' }}</div>
                                            @if($wr->received_weight !== null)
                                                <div><span class="text-muted">{{ __('admin.weight_lb') }}:</span> {{ $wr->received_weight }}</div>
                                            @endif
                                            @if($wr->received_length !== null || $wr->received_width !== null || $wr->received_height !== null)
                                                <div><span class="text-muted">{{ __('admin.dims_lwh') }}:</span>
                                                    {{ $wr->received_length ?? '—' }} × {{ $wr->received_width ?? '—' }} × {{ $wr->received_height ?? '—' }} <span class="text-muted">({{ __('admin.dim_unit_in') }})</span>
                                                </div>
                                            @endif
                                            @if(filled($wr->condition_notes))
                                                <div class="mt-1"><span class="text-muted">{{ __('admin.notes') }}:</span> {{ Str::limit($wr->condition_notes, 200) }}</div>
                                            @endif
                                            @if(filled($wr->special_handling_type))
                                                <div><span class="text-muted">{{ __('admin.special_handling') }}:</span> {{ $wr->special_handling_type }}</div>
                                            @endif
                                            @if($wr->additional_fee_amount !== null && (float) $wr->additional_fee_amount > 0)
                                                <div><span class="text-muted">{{ __('admin.additional_fee') }}:</span> {{ number_format((float) $wr->additional_fee_amount, 2) }} {{ $cur }}</div>
                                            @endif
                                        </div>
                                    @endforeach
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>{{ $li?->store_name ?? '—' }}</td>
                            <td><span class="badge bg-{{ $fp['badge'] }}">{{ $fp['label'] }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-4">
    <a href="{{ route('admin.shipments.index') }}" class="btn btn-outline-secondary">{{ __('admin.back') }}</a>
</div>
@endsection
