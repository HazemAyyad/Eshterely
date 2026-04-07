@php
    use App\Models\OrderLineItem;
    use App\Support\AdminFulfillmentLabels;
@endphp

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h5 class="mb-0">{{ __('admin.procurement_line_items') }}</h5>
        <div class="small text-muted">{{ __('admin.procurement_line_items_help') }}</div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('admin.product') }}</th>
                        <th>{{ __('admin.store') }}</th>
                        <th>{{ __('admin.qty') }}</th>
                        <th>{{ __('admin.procurement_status') }}</th>
                        <th>{{ __('admin.store_tracking') }}</th>
                        <th>{{ __('admin.purchase_notes') }}</th>
                        <th style="min-width:220px">{{ __('admin.actions') }}</th>
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
                        @endphp
                        <tr>
                            <td>{{ Str::limit($li->name, 48) }}</td>
                            <td>{{ $li->store_name ?? '—' }}</td>
                            <td>{{ $li->quantity }}</td>
                            <td><span class="badge bg-{{ $p['badge'] }}">{{ $p['label'] }}</span></td>
                            <td class="small">{{ $tracking !== '' ? Str::limit($tracking, 32) : '—' }}</td>
                            <td class="small">{{ $notes !== '' ? Str::limit($notes, 40) : '—' }}</td>
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
                                        <input type="hidden" name="action" value="mark_purchased">
                                        <button type="submit" class="btn btn-sm btn-primary" @if(!in_array($li->fulfillment_status, [OrderLineItem::FULFILLMENT_PAID, OrderLineItem::FULFILLMENT_REVIEWED], true)) disabled @endif>{{ __('admin.mark_purchased') }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.order-line-items.procurement', $li) }}" class="ajax-submit-form d-inline">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="action" value="mark_in_transit">
                                        <button type="submit" class="btn btn-sm btn-warning text-dark" @if(!in_array($li->fulfillment_status, [OrderLineItem::FULFILLMENT_PAID, OrderLineItem::FULFILLMENT_REVIEWED, OrderLineItem::FULFILLMENT_PURCHASED], true)) disabled @endif>{{ __('admin.mark_in_transit_wh') }}</button>
                                    </form>
                                </div>
                                <div class="small">
                                    @if($canReceive)
                                        <a href="{{ route('admin.warehouse.receive-form', $li) }}" class="btn btn-sm btn-success">{{ __('admin.warehouse_receive') }}</a>
                                    @endif
                                    @if($li->relationLoaded('shipmentItems') && $li->shipmentItems->isNotEmpty())
                                        @foreach($li->shipmentItems as $si)
                                            <a href="{{ route('admin.shipments.show', $si->shipment_id) }}" class="btn btn-sm btn-outline-secondary">{{ __('admin.outbound_shipment') }} #{{ $si->shipment_id }}</a>
                                        @endforeach
                                    @endif
                                    @if($li->latestWarehouseReceipt)
                                        <span class="text-muted ms-1">{{ __('admin.received_short') }} {{ $li->latestWarehouseReceipt->received_at?->format('Y-m-d') ?? '—' }}</span>
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
