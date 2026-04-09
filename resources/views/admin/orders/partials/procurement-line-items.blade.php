@php
    use App\Support\AdminFulfillmentLabels;
    use App\Support\AdminOrderLineItemDisplay;
    use App\Support\AdminWarehouseReceiptImages;
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
                        <th style="min-width:130px">{{ __('admin.wh_receive_status_col') }}</th>
                        <th style="min-width:160px">{{ __('admin.outbound_shipments_col') }}</th>
                        <th style="min-width:120px">{{ __('admin.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($order->lineItems as $li)
                        @php
                            $p = AdminFulfillmentLabels::lineItemFulfillment($li->fulfillment_status);
                            $tracking = $li->review_metadata['store_tracking'] ?? '';
                            $notes = $li->review_metadata['purchase_notes'] ?? '';
                            $details = $li->review_metadata['purchase_details'] ?? '';
                            $buyer = $li->review_metadata['assigned_buyer'] ?? '';
                            $actualPrice = $li->review_metadata['actual_purchase_price'] ?? '';
                            $canReceive = AdminOrderLineItemDisplay::canReceiveIntoWarehouse($li);
                            $receipt = $li->latestWarehouseReceipt;
                            $sourceUrl = AdminOrderLineItemDisplay::sourceProductUrl($li);
                            $unitPrice = AdminOrderLineItemDisplay::unitPrice($li);
                            $serviceFee = AdminOrderLineItemDisplay::serviceFeeAmount($li);
                            $firstPay = AdminOrderLineItemDisplay::firstPaymentTotal($li);
                            $cur = $order->currency ?? 'USD';
                            $procPayload = [
                                'action' => route('admin.order-line-items.procurement', $li),
                                'store_tracking' => $tracking,
                                'purchase_notes' => $notes,
                                'purchase_details' => $details,
                                'assigned_buyer' => $buyer,
                                'actual_purchase_price' => $actualPrice,
                                'fulfillment_status' => $li->fulfillment_status,
                            ];
                            $procPayloadB64 = base64_encode(json_encode($procPayload, JSON_UNESCAPED_UNICODE));
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
                            <td class="small">
                                @if($receipt)
                                    <span class="badge bg-success">{{ __('admin.wh_received_badge') }}</span>
                                    <div class="text-muted mt-1">{{ $receipt->received_at?->format('Y-m-d H:i') ?? '—' }}</div>
                                    @php
                                        $receiptImgs = [];
                                        if (is_array($receipt->images)) {
                                            foreach ($receipt->images as $entry) {
                                                if (is_string($entry) && $entry !== '') {
                                                    $receiptImgs[] = AdminWarehouseReceiptImages::displayUrl($entry);
                                                }
                                            }
                                        }
                                    @endphp
                                    @if(count($receiptImgs) > 0)
                                        <div class="d-flex flex-wrap gap-1 mt-1">
                                            @foreach(array_slice($receiptImgs, 0, 4) as $iu)
                                                <a href="{{ $iu }}" target="_blank" rel="noopener noreferrer" title="{{ __('admin.receipt_images_upload') }}">
                                                    <img src="{{ $iu }}" alt="" class="rounded border" width="36" height="36" style="object-fit:cover" loading="lazy">
                                                </a>
                                            @endforeach
                                        </div>
                                    @endif
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
                                <button type="button"
                                    class="btn btn-sm btn-primary mb-1"
                                    data-bs-toggle="modal"
                                    data-bs-target="#orderLineProcurementModal"
                                    data-procurement-b64="{{ $procPayloadB64 }}">
                                    {{ __('admin.procurement_details_btn') }}
                                </button>
                                @if($canReceive)
                                    @php
                                        $receivePostUrl = route('admin.warehouse.receive', $li);
                                        $onDisp = e($order->order_number ?? '');
                                        $pnDisp = e(Str::limit($li->name, 80));
                                    @endphp
                                    <button type="button"
                                        class="btn btn-sm btn-success d-block js-wh-receive-modal"
                                        data-bs-toggle="modal"
                                        data-bs-target="#warehouseReceiveModal"
                                        data-receive-url="{{ $receivePostUrl }}"
                                        data-order-number="{{ $onDisp }}"
                                        data-product-name="{{ $pnDisp }}">{{ __('admin.warehouse_receive') }}</button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

@include('admin.orders.partials.procurement-modal')
