@php
    use App\Models\OrderLineItem;
    use App\Support\AdminFulfillmentLabels;
    use App\Support\AdminOrderLineItemDisplay;
@endphp

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h5 class="mb-0">{{ __('admin.order_items_card_title') }}</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="min-width:140px">{{ __('admin.product') }}</th>
                        <th>{{ __('admin.store') }}</th>
                        <th style="min-width:88px">{{ __('admin.source_link') }}</th>
                        <th class="text-end" style="min-width:72px">{{ __('admin.unit_price') }}</th>
                        <th class="text-center">{{ __('admin.qty') }}</th>
                        <th class="text-end" style="min-width:72px">{{ __('admin.service_fee') }}</th>
                        <th class="text-end" style="min-width:88px">{{ __('admin.first_payment_total') }}</th>
                        <th style="min-width:120px">{{ __('admin.item_fulfillment_stage') }}</th>
                        <th style="min-width:100px">{{ __('admin.store_tracking') }}</th>
                        <th style="min-width:100px">{{ __('admin.purchase_notes') }}</th>
                        <th style="min-width:130px">{{ __('admin.wh_receive_status_col') }}</th>
                        <th style="min-width:160px">{{ __('admin.outbound_shipments_col') }}</th>
                        <th style="min-width:240px">{{ __('admin.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($order->lineItems as $li)
                        @php
                            $p = AdminFulfillmentLabels::lineItemFulfillment($li->fulfillment_status);
                            $tracking = $li->review_metadata['store_tracking'] ?? '';
                            $notes = $li->review_metadata['purchase_notes'] ?? '';
                            $canReceive = in_array($li->fulfillment_status, [
                                OrderLineItem::FULFILLMENT_PURCHASED,
                                OrderLineItem::FULFILLMENT_IN_TRANSIT_TO_WAREHOUSE,
                            ], true);
                            $receipt = $li->latestWarehouseReceipt;
                            $sourceUrl = AdminOrderLineItemDisplay::sourceProductUrl($li);
                            $unitPrice = AdminOrderLineItemDisplay::unitPrice($li);
                            $serviceFee = AdminOrderLineItemDisplay::serviceFeeAmount($li);
                            $firstPay = AdminOrderLineItemDisplay::firstPaymentTotal($li);
                            $cur = $order->currency ?? 'USD';
                        @endphp
                        <tr>
                            <td>{{ Str::limit($li->name, 56) }}</td>
                            <td>{{ $li->store_name ?? '—' }}</td>
                            <td class="small">
                                @if($sourceUrl)
                                    <a href="{{ $sourceUrl }}" target="_blank" rel="noopener noreferrer">{{ __('admin.source_link_open') }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="text-end small text-nowrap">{{ number_format($unitPrice, 2) }} {{ $cur }}</td>
                            <td class="text-center">{{ $li->quantity }}</td>
                            <td class="text-end small text-nowrap">{{ $serviceFee !== null ? number_format($serviceFee, 2).' '.$cur : '—' }}</td>
                            <td class="text-end small text-nowrap">{{ $firstPay !== null ? number_format($firstPay, 2).' '.$cur : '—' }}</td>
                            <td><span class="badge bg-{{ $p['badge'] }}">{{ $p['label'] }}</span></td>
                            <td class="small">{{ $tracking !== '' ? Str::limit($tracking, 36) : '—' }}</td>
                            <td class="small">{{ $notes !== '' ? Str::limit($notes, 40) : '—' }}</td>
                            <td class="small">
                                @if($receipt)
                                    <span class="badge bg-success">{{ __('admin.wh_received_badge') }}</span>
                                    <div class="text-muted mt-1">{{ $receipt->received_at?->format('Y-m-d H:i') ?? '—' }}</div>
                                @elseif($canReceive)
                                    <span class="badge bg-warning text-dark">{{ __('admin.wh_awaiting_intake_badge') }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="small">
                                @if($li->relationLoaded('shipmentItems') && $li->shipmentItems->isNotEmpty())
                                    @foreach($li->shipmentItems as $si)
                                        @php
                                            $os = $si->shipment;
                                            $op = $os ? AdminFulfillmentLabels::outboundShipment($os->status) : ['label' => '—', 'badge' => 'secondary'];
                                        @endphp
                                        @if($os)
                                            <div class="mb-1 pb-1 @if(!$loop->last) border-bottom @endif">
                                                <a href="{{ route('admin.shipments.show', $os) }}" class="fw-semibold">#{{ $os->id }}</a>
                                                <span class="badge bg-{{ $op['badge'] }} ms-1">{{ $op['label'] }}</span>
                                                @if($os->carrier || $os->tracking_number)
                                                    <div class="text-muted text-truncate" style="max-width:14rem" title="{{ $os->carrier }} {{ $os->tracking_number }}">
                                                        {{ $os->carrier ?? '—' }}
                                                        @if($os->tracking_number)
                                                            · {{ Str::limit($os->tracking_number, 24) }}
                                                        @endif
                                                    </div>
                                                @endif
                                            </div>
                                        @endif
                                    @endforeach
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                <form method="POST" action="{{ route('admin.order-line-items.procurement', $li) }}" class="ajax-submit-form mb-2">
                                    @csrf
                                    @method('PATCH')
                                    <div class="input-group input-group-sm mb-1">
                                        <span class="input-group-text">{{ __('admin.store_tracking') }}</span>
                                        <input type="text" name="store_tracking" class="form-control" value="{{ old('store_tracking.'.$li->id, $tracking) }}" maxlength="255" autocomplete="off">
                                    </div>
                                    <div class="input-group input-group-sm mb-1">
                                        <span class="input-group-text">{{ __('admin.purchase_notes') }}</span>
                                        <input type="text" name="purchase_notes" class="form-control" value="{{ old('purchase_notes.'.$li->id, $notes) }}" maxlength="2000" autocomplete="off">
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-outline-primary">{{ __('admin.save_procurement_notes') }}</button>
                                </form>
                                <div class="d-flex flex-wrap gap-1 mb-1">
                                    <form method="POST" action="{{ route('admin.order-line-items.procurement', $li) }}" class="ajax-submit-form d-inline">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="procurement_action" value="mark_purchased">
                                        <button type="submit" class="btn btn-sm btn-primary" @if(!in_array($li->fulfillment_status, [OrderLineItem::FULFILLMENT_PAID, OrderLineItem::FULFILLMENT_REVIEWED], true)) disabled @endif>{{ __('admin.mark_purchased') }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.order-line-items.procurement', $li) }}" class="ajax-submit-form d-inline">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="procurement_action" value="mark_in_transit">
                                        <button type="submit" class="btn btn-sm btn-warning text-dark" @if(!in_array($li->fulfillment_status, [OrderLineItem::FULFILLMENT_PAID, OrderLineItem::FULFILLMENT_REVIEWED, OrderLineItem::FULFILLMENT_PURCHASED], true)) disabled @endif>{{ __('admin.mark_in_transit_wh') }}</button>
                                    </form>
                                </div>
                                <div class="small">
                                    @if($canReceive)
                                        <a href="{{ route('admin.warehouse.receive-form', $li) }}" class="btn btn-sm btn-success">{{ __('admin.warehouse_receive') }}</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
