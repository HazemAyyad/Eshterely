<?php

namespace App\Services;

use App\Models\CartItem;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\ImportedProduct;
use App\Models\Order;
use Illuminate\Support\Collection;

/**
 * Creates draft orders from cart items. Snapshot-based only; no recalculation of pricing or shipping.
 * Designed to integrate with PreviewVerificationService when verification is enabled (future).
 */
class DraftOrderService
{
    public function __construct(
        protected ?PreviewVerificationService $previewVerification = null
    ) {
        $this->previewVerification ??= app(PreviewVerificationService::class);
    }

    /**
     * Create a draft order from the user's active cart items.
     * Copies product_snapshot, shipping_snapshot, pricing_snapshot and review metadata only.
     * Propagates needs_review and estimated from any item to the draft order.
     * Marks cart items as attached to the draft (excluded from active cart).
     *
     * @param  Collection<int, CartItem>  $cartItems Must be owned by the same user and active (draft_order_id null).
     * @return DraftOrder|null Created draft order, or null if cart is empty.
     */
    public function createFromCart(Collection $cartItems): ?DraftOrder
    {
        $cartItems = $cartItems->filter(fn (CartItem $i) => $i->draft_order_id === null);

        if ($cartItems->isEmpty()) {
            return null;
        }

        $currency = $cartItems->first()->currency ?? 'USD';

        $subtotal = 0.0;
        $shippingTotal = 0.0;
        $serviceFeeTotal = 0.0;
        $finalTotal = 0.0;
        $anyEstimated = false;
        $anyNeedsReview = false;

        foreach ($cartItems as $item) {
            $ps = $item->pricing_snapshot ?? [];
            $lineSubtotal = isset($ps['subtotal']) ? (float) $ps['subtotal'] : ((float) $item->unit_price * $item->quantity);
            $lineShipping = isset($ps['shipping_amount']) ? (float) $ps['shipping_amount'] : ((float) ($item->shipping_cost ?? 0) * $item->quantity);
            $subtotal += $lineSubtotal;
            $shippingTotal += $lineShipping;
            $serviceFeeTotal += (float) ($ps['service_fee'] ?? 0);
            $finalTotal += isset($ps['final_total']) ? (float) $ps['final_total'] : $lineSubtotal;
            if ($item->estimated) {
                $anyEstimated = true;
            }
            if ($item->needs_review) {
                $anyNeedsReview = true;
            }
        }

        $draft = DraftOrder::create([
            'user_id' => $cartItems->first()->user_id,
            'status' => DraftOrder::STATUS_DRAFT,
            'currency' => $currency,
            'subtotal_snapshot' => round($subtotal, 2),
            'shipping_total_snapshot' => round($shippingTotal, 2),
            'service_fee_total_snapshot' => round($serviceFeeTotal, 2),
            'final_total_snapshot' => round($finalTotal, 2),
            'estimated' => $anyEstimated,
            'needs_review' => $anyNeedsReview,
            'review_state' => $this->buildReviewState($anyNeedsReview, $anyEstimated),
            'notes' => null,
            'warnings' => null,
        ]);

        foreach ($cartItems as $cartItem) {
            $this->createDraftOrderItemFromCartItem($draft, $cartItem);
        }

        $cartItems->each(function (CartItem $i) use ($draft): void {
            $i->update(['draft_order_id' => $draft->id]);
        });

        return $draft->load('items');
    }

    /**
     * Build review_state for future granular flags (needs_admin_review, needs_reprice, needs_shipping_completion).
     */
    protected function buildReviewState(bool $needsReview, bool $estimated): array
    {
        $state = [];
        if ($needsReview) {
            $state['needs_review'] = true;
        }
        if ($estimated) {
            $state['estimated'] = true;
        }
        return $state;
    }

    /**
     * Create one draft order item from a cart item. Copies snapshots only; no recalculation.
     */
    protected function createDraftOrderItemFromCartItem(DraftOrder $draft, CartItem $cartItem): DraftOrderItem
    {
        $productSnapshot = [
            'name' => $cartItem->name,
            'unit_price' => (float) $cartItem->unit_price,
            'currency' => $cartItem->currency,
            'image_url' => $cartItem->image_url,
            'store_name' => $cartItem->store_name,
            'store_key' => $cartItem->store_key,
            'product_id' => $cartItem->product_id,
            'country' => $cartItem->country,
            'product_url' => $cartItem->product_url,
            'variation_text' => $cartItem->variation_text,
        ];

        $reviewMetadata = [
            'review_status' => $cartItem->review_status,
            'needs_review' => (bool) $cartItem->needs_review,
            'estimated' => (bool) $cartItem->estimated,
            'carrier' => $cartItem->carrier,
            'pricing_mode' => $cartItem->pricing_mode,
        ];

        return DraftOrderItem::create([
            'draft_order_id' => $draft->id,
            'cart_item_id' => $cartItem->id,
            'imported_product_id' => $cartItem->imported_product_id,
            'source_type' => $cartItem->source_type ?? ($cartItem->isImported() ? CartItem::SOURCE_IMPORTED : CartItem::SOURCE_PASTE_LINK),
            'product_snapshot' => $productSnapshot,
            'shipping_snapshot' => $cartItem->shipping_snapshot,
            'pricing_snapshot' => $cartItem->pricing_snapshot,
            'quantity' => $cartItem->quantity,
            'review_metadata' => $reviewMetadata,
            'estimated' => (bool) $cartItem->estimated,
            'missing_fields' => $cartItem->missing_fields ?? [],
        ]);
    }

    /**
     * Create a draft order from a confirmed imported product snapshot (legacy/placeholder).
     * Uses snapshot data only; does not recalculate pricing or shipping.
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
     *
     * @param  Collection<int, CartItem>  $cartItems
     * @return array{shipments: array<int, array{country_code: string, country_label: string, subtotal: float, shipping_fee: float, items: array<int, array{name: string, store_name: string|null, sku: string, price: float, quantity: int, image_url: string|null, pricing_snapshot: array|null, shipping_snapshot: array|null}>}>}
     */
    public function cartItemsToDraftStructure(Collection $cartItems): array
    {
        $cartItems = $cartItems->filter(fn (CartItem $i) => $i->draft_order_id === null);
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
