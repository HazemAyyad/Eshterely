<?php

namespace App\Services;

use App\Models\DraftOrder;
use App\Models\Order;
use App\Models\OrderLineItem;
use App\Models\OrderShipment;
use App\Services\Shipping\ShippingPricingConfigService;
use Illuminate\Support\Str;

/**
 * Converts a draft order into a real order using stored snapshots only.
 * Does NOT recompute shipping or pricing; preserves snapshot integrity for payment.
 */
class OrderFinalizationService
{
    public const ORDER_STATUS_PENDING_PAYMENT = 'pending_payment';

    public function __construct(
        private ShippingPricingConfigService $shippingConfig
    ) {}

    /**
     * Create a real order from a draft order. Copies all snapshots and metadata; no recalculation.
     */
    public function createOrderFromDraft(DraftOrder $draftOrder): Order
    {
        $draftOrder->loadMissing('items');

        $orderNumber = $this->buildOrderNumber();
        $currency = $draftOrder->currency ?? 'USD';

        $order = Order::create([
            'user_id' => $draftOrder->user_id,
            'draft_order_id' => $draftOrder->id,
            'order_number' => $orderNumber,
            'origin' => $this->resolveOrigin($draftOrder),
            'status' => self::ORDER_STATUS_PENDING_PAYMENT,
            'placed_at' => null,
            'total_amount' => $draftOrder->final_total_snapshot,
            'currency' => $currency,
            'order_total_snapshot' => $draftOrder->final_total_snapshot,
            'shipping_total_snapshot' => $draftOrder->shipping_total_snapshot,
            'service_fee_snapshot' => $draftOrder->service_fee_total_snapshot,
            'estimated' => (bool) $draftOrder->estimated,
            'needs_review' => (bool) $draftOrder->needs_review,
        ]);

        $itemsByCountry = $draftOrder->items->groupBy(function ($item) {
            $country = $item->product_snapshot['country'] ?? 'Other';
            return is_string($country) ? $country : 'Other';
        });

        foreach ($itemsByCountry as $country => $items) {
            $shipment = $this->createShipmentFromDraftItems($order, $country, $items);
            foreach ($items as $draftItem) {
                $this->createLineItemFromDraftItem($shipment, $draftItem);
            }
        }

        $draftOrder->update([
            'status' => DraftOrder::STATUS_CONVERTED,
            'converted_order_id' => $order->id,
            'converted_at' => now(),
        ]);

        return $order->load(['shipments.lineItems']);
    }

    private function resolveOrigin(DraftOrder $draftOrder): string
    {
        $countries = $draftOrder->items->pluck('product_snapshot.country')->filter()->unique();
        return $countries->count() > 1 ? 'multi_origin' : 'usa';
    }

    private function createShipmentFromDraftItems(Order $order, string $country, $items): OrderShipment
    {
        $subtotal = 0.0;
        $shippingFee = 0.0;
        $firstShippingSnapshot = null;

        foreach ($items as $item) {
            $ps = $item->pricing_snapshot ?? [];
            $lineSubtotal = isset($ps['subtotal']) ? (float) $ps['subtotal'] : ((float) ($item->product_snapshot['unit_price'] ?? 0) * $item->quantity);
            $subtotal += $lineSubtotal;
            $shippingFee += (float) ($ps['shipping_amount'] ?? 0);
            if ($firstShippingSnapshot === null && $item->shipping_snapshot !== null) {
                $firstShippingSnapshot = $item->shipping_snapshot;
            }
        }

        return OrderShipment::create([
            'order_id' => $order->id,
            'country_code' => strlen($country) === 2 ? $country : 'US',
            'country_label' => $country . ' Shipment',
            'shipping_method' => $firstShippingSnapshot['carrier'] ?? 'Air Express',
            'eta' => $firstShippingSnapshot['eta'] ?? null,
            'subtotal' => round($subtotal, 2),
            'shipping_fee' => round($shippingFee, 2),
            'shipping_snapshot' => $firstShippingSnapshot,
        ]);
    }

    private function createLineItemFromDraftItem(OrderShipment $shipment, $draftItem): OrderLineItem
    {
        $snapshot = $draftItem->product_snapshot ?? [];
        $name = $snapshot['name'] ?? 'Product';
        $unitPrice = (float) ($snapshot['unit_price'] ?? 0);
        $quantity = (int) $draftItem->quantity;
        $lineTotal = $unitPrice * $quantity;
        $ps = $draftItem->pricing_snapshot ?? [];
        if (isset($ps['final_total'])) {
            $lineTotal = (float) $ps['final_total'];
        } elseif (isset($ps['subtotal'])) {
            $lineTotal = (float) $ps['subtotal'];
        }

        return OrderLineItem::create([
            'order_shipment_id' => $shipment->id,
            'draft_order_item_id' => $draftItem->id,
            'source_type' => $draftItem->source_type ?? 'imported',
            'cart_item_id' => $draftItem->cart_item_id,
            'imported_product_id' => $draftItem->imported_product_id,
            'name' => $name,
            'store_name' => $snapshot['store_name'] ?? null,
            'sku' => $snapshot['product_id'] ?? '',
            'price' => round($lineTotal, 2),
            'quantity' => $quantity,
            'image_url' => $snapshot['image_url'] ?? null,
            'product_snapshot' => $draftItem->product_snapshot,
            'pricing_snapshot' => $draftItem->pricing_snapshot,
            'review_metadata' => $draftItem->review_metadata,
            'estimated' => (bool) $draftItem->estimated,
            'missing_fields' => $draftItem->missing_fields ?? [],
        ]);
    }

    private function buildOrderNumber(): string
    {
        $prefix = $this->shippingConfig->orderNumberPrefix();

        return $prefix . '-' . strtoupper(Str::random(6));
    }
}
