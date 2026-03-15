<?php

namespace App\Services;

use App\Models\CartItem;
use App\Models\ImportedProduct;
use App\Models\Order;
use Illuminate\Support\Collection;

/**
 * Foundation for creating draft orders from imported product snapshots and cart items.
 * Cart items (including imported with pricing_snapshot/shipping_snapshot) convert to draft
 * line structure without mixing cart logic into order creation.
 */
class DraftOrderService
{
    /**
     * Create a draft order from a confirmed imported product snapshot.
     * Uses snapshot data only; does not recalculate pricing or shipping.
     *
     * Next task: implement full flow (address, wallet, payment intent, status draft → placed).
     *
     * @return Order|null Draft order, or null if not yet implemented / validation fails
     */
    public function createFromImportedProduct(ImportedProduct $imported): ?Order
    {
        if ($imported->status !== ImportedProduct::STATUS_DRAFT && $imported->status !== ImportedProduct::STATUS_ADDED_TO_CART) {
            return null;
        }

        // Placeholder: next task will create Order with status 'draft', OrderShipment, OrderLineItem
        // from $imported->final_pricing_snapshot, shipping_quote_snapshot, and product fields.
        return null;
    }

    /**
     * Convert cart items to a structure ready for draft order creation.
     * Uses stored unit_price and shipping_cost (snapshot for imported items); no recalculation.
     * Grouped by country for shipment-level structure. Order creation logic will use this
     * without reading cart directly.
     *
     * @param  Collection<int, CartItem>  $cartItems
     * @return array{shipments: array<int, array{country_code: string, country_label: string, subtotal: float, shipping_fee: float, items: array<int, array{name: string, store_name: string|null, sku: string, price: float, quantity: int, image_url: string|null, pricing_snapshot: array|null, shipping_snapshot: array|null}>}>}
     */
    public function cartItemsToDraftStructure(Collection $cartItems): array
    {
        $byCountry = $cartItems->groupBy(fn (CartItem $i) => $i->country ?? 'Other');

        $shipments = [];
        foreach ($byCountry as $country => $items) {
            $subtotal = $items->sum(fn (CartItem $i) => (float) $i->unit_price * $i->quantity);
            $shippingFee = $items->sum(fn (CartItem $i) => (float) ($i->shipping_cost ?? 0) * $i->quantity);
            $shipments[] = [
                'country_code' => strlen($country) === 2 ? $country : 'US',
                'country_label' => $country . ' Shipment',
                'subtotal' => round($subtotal, 2),
                'shipping_fee' => round($shippingFee, 2),
                'items' => $items->map(fn (CartItem $i) => [
                    'name' => $i->name,
                    'store_name' => $i->store_name,
                    'sku' => $i->product_id ?? '',
                    'price' => (float) $i->unit_price * $i->quantity,
                    'quantity' => $i->quantity,
                    'image_url' => $i->image_url,
                    'pricing_snapshot' => $i->pricing_snapshot,
                    'shipping_snapshot' => $i->shipping_snapshot,
                ])->values()->all(),
            ];
        }

        return ['shipments' => $shipments];
    }
}
