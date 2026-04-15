<?php

namespace App\Services\PurchaseAssistant;

use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\Order;
use App\Models\PurchaseAssistantRequest;
use App\Services\OrderFinalizationService;
use App\Support\PurchaseAssistantStoreDisplayName;
use Illuminate\Support\Facades\DB;

/**
 * Builds a draft order from an admin-priced Purchase Assistant request and finalizes it to a real order
 * (pending payment). Skips cart and cart review.
 */
class PurchaseAssistantOrderFromRequestService
{
    public function __construct(
        protected OrderFinalizationService $finalizationService
    ) {}

    public function createPendingPaymentOrder(PurchaseAssistantRequest $request): Order
    {
        if ($request->converted_order_id !== null) {
            throw new \RuntimeException('An order is already linked to this request.');
        }

        if ($request->admin_product_price === null || $request->admin_service_fee === null) {
            throw new \InvalidArgumentException('Admin pricing is required before creating an order.');
        }

        return DB::transaction(function () use ($request) {
            $qty = max(1, (int) $request->quantity);
            $currency = $request->currency ?: 'USD';
            $productSubtotal = round((float) $request->admin_product_price * $qty, 2);
            $serviceFee = round((float) $request->admin_service_fee, 2);
            $finalTotal = round($productSubtotal + $serviceFee, 2);

            $domain = $request->source_domain;
            if ($domain === null || $domain === '') {
                $host = parse_url($request->source_url, PHP_URL_HOST);
                $domain = is_string($host) ? $host : null;
            }

            $storeLabel = $request->store_display_name;
            if ($storeLabel === null || $storeLabel === '') {
                $storeLabel = PurchaseAssistantStoreDisplayName::fromHost($domain);
            }

            $firstImage = null;
            if (is_array($request->image_paths) && $request->image_paths !== []) {
                $first = $request->image_paths[0];
                $firstImage = is_string($first) ? $first : null;
            }

            $draft = DraftOrder::create([
                'user_id' => $request->user_id,
                'status' => DraftOrder::STATUS_DRAFT,
                'currency' => $currency,
                'subtotal_snapshot' => $productSubtotal,
                'shipping_total_snapshot' => 0,
                'service_fee_total_snapshot' => $serviceFee,
                'final_total_snapshot' => $finalTotal,
                'estimated' => false,
                'needs_review' => false,
            ]);

            DraftOrderItem::create([
                'draft_order_id' => $draft->id,
                'source_type' => 'purchase_assistant',
                'quantity' => $qty,
                'estimated' => false,
                'missing_fields' => [],
                'product_snapshot' => [
                    'name' => $request->title ?: 'Purchase Assistant item',
                    'unit_price' => (float) $request->admin_product_price,
                    'store_name' => $storeLabel,
                    'country' => 'US',
                    'image_url' => $firstImage,
                    'product_id' => '',
                    'purchase_assistant' => true,
                    'source_url' => $request->source_url,
                    'variant_details' => $request->variant_details,
                    'purchase_assistant_request_id' => $request->id,
                ],
                'shipping_snapshot' => [
                    'carrier' => 'dhl',
                    'eta' => null,
                ],
                'pricing_snapshot' => [
                    'subtotal' => $productSubtotal,
                    'service_fee' => $serviceFee,
                    'final_total' => $finalTotal,
                    'shipping_amount' => 0,
                ],
                'review_metadata' => [
                    'carrier' => 'dhl',
                ],
            ]);

            $order = $this->finalizationService->createOrderFromDraft($draft->fresh('items'));

            $order->update([
                'purchase_assistant_request_id' => $request->id,
            ]);

            $request->update([
                'converted_order_id' => $order->id,
                'status' => PurchaseAssistantRequest::STATUS_AWAITING_CUSTOMER_PAYMENT,
            ]);

            foreach ($order->lineItems as $line) {
                $snap = $line->product_snapshot ?? [];
                $snap['purchase_assistant'] = true;
                $snap['purchase_assistant_request_id'] = $request->id;
                $line->update([
                    'badges' => array_values(array_unique(array_merge($line->badges ?? [], ['purchase_assistant']))),
                    'product_snapshot' => $snap,
                ]);
            }

            return $order->fresh(['shipments.lineItems']);
        });
    }
}
