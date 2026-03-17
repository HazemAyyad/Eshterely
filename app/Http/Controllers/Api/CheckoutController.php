<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderLineItem;
use App\Models\OrderShipment;
use App\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CheckoutController extends Controller
{
    /**
     * Checkout review.
     *
     * Contract goals for Flutter:
     * - Do not invent fake shipping/fees.
     * - Provide an explicit numeric breakdown + flags for estimated / needs_review.
     * - Keep legacy string fields for backward compatibility where they already exist.
     */
    public function review(Request $request): JsonResponse
    {
        $items = CartItem::where('user_id', $request->user()->id)->whereNull('draft_order_id')->get();
        $defaultAddress = Address::where('user_id', $request->user()->id)->where('is_default', true)->with('country')->first();
        $wallet = Wallet::firstOrCreate(
            ['user_id' => $request->user()->id],
            ['available_balance' => 0, 'pending_balance' => 0, 'promo_balance' => 0]
        );

        $byCountry = $items->groupBy(fn ($i) => $i->country ?? 'Other');
        $shipments = $byCountry->map(function ($group, $country) {
            $groupNeedsReview = $group->contains(fn ($i) => (bool) $i->needs_review || ($i->review_status ?? CartItem::REVIEW_STATUS_PENDING) !== CartItem::REVIEW_STATUS_REVIEWED);
            $groupEstimated = $group->contains(fn ($i) => (bool) $i->estimated || $i->shipping_cost === null);

            return [
                'origin_label' => $country . ' Shipment',
                'needs_review' => $groupNeedsReview,
                'estimated' => $groupEstimated,
                'items' => $group->map(function ($i) {
                    $itemSubtotal = (float) $i->unit_price * (int) $i->quantity;
                    $unitShipping = $i->shipping_cost !== null ? (float) $i->shipping_cost : null;
                    $shippingAmount = $unitShipping !== null ? $unitShipping * (int) $i->quantity : null;

                    return [
                        'id' => (string) $i->id,
                        'name' => $i->name,
                        // legacy formatted string fields (existing app contract)
                        'price' => '$' . number_format((float) $i->unit_price, 2),
                        'shipping_cost' => $shippingAmount !== null ? '$' . number_format($shippingAmount, 2) : null,
                        // numeric fields (preferred)
                        'unit_price' => (float) $i->unit_price,
                        'quantity' => (int) $i->quantity,
                        'subtotal' => round($itemSubtotal, 2),
                        'unit_shipping_amount' => $unitShipping,
                        'shipping_amount' => $shippingAmount !== null ? round($shippingAmount, 2) : null,
                        'currency' => $i->currency ?? 'USD',
                        'image_url' => $i->image_url,
                        'review_status' => $i->review_status ?? CartItem::REVIEW_STATUS_PENDING,
                        'needs_review' => (bool) $i->needs_review,
                        'estimated' => (bool) $i->estimated || $i->shipping_cost === null,
                        'missing_fields' => $i->missing_fields ?? [],
                        'carrier' => $i->carrier,
                        'pricing_mode' => $i->pricing_mode,
                    ];
                })->values()->toArray(),
            ];
        })->values()->toArray();

        $currency = strtoupper(trim((string) ($items->first()?->currency ?? 'USD')));
        if ($currency === '') {
            $currency = 'USD';
        }

        $subtotal = (float) $items->sum(fn ($i) => (float) $i->unit_price * (int) $i->quantity);
        $shippingKnown = (float) $items->sum(fn ($i) => $i->shipping_cost !== null ? (float) $i->shipping_cost * (int) $i->quantity : 0.0);
        $shippingHasUnknown = $items->contains(fn ($i) => $i->shipping_cost === null);
        $needsReview = $items->contains(fn ($i) => (bool) $i->needs_review || ($i->review_status ?? CartItem::REVIEW_STATUS_PENDING) !== CartItem::REVIEW_STATUS_REVIEWED);
        $estimated = $shippingHasUnknown || $items->contains(fn ($i) => (bool) $i->estimated);

        $total = round($subtotal + $shippingKnown, 2);
        $walletBalance = (float) $wallet->available_balance;
        $walletApplied = round(min($walletBalance, $total), 2);
        $amountDueNow = round(max(0, $total - $walletApplied), 2);

        return response()->json([
            // legacy keys kept (string formatted)
            'shipping_address_short' => $defaultAddress ? substr($defaultAddress->address_line ?? $defaultAddress->street_address ?? '', 0, 50) . '...' : '',
            'consolidation_savings' => '$0.00',
            'wallet_balance_enabled' => true,
            'wallet_balance' => '$' . number_format($walletBalance, 2),
            'subtotal' => '$' . number_format($subtotal, 2),
            'shipping' => '$' . number_format($shippingKnown, 2),
            'insurance' => '$0.00',
            'total' => '$' . number_format($total, 2),
            'shipments' => $shipments,

            // preferred explicit pricing contract (numeric)
            'currency' => $currency,
            'pricing' => [
                'subtotal' => round($subtotal, 2),
                'shipping' => round($shippingKnown, 2),
                'discounts' => 0.0,
                'wallet_balance' => round($walletBalance, 2),
                'wallet_applied_amount' => $walletApplied,
                'amount_due_now' => $amountDueNow,
                'total' => $total,
                'estimated' => $estimated,
                'needs_review' => $needsReview,
                'shipping_estimated' => $shippingHasUnknown,
                'breakdown' => [
                    ['key' => 'subtotal', 'label' => 'Subtotal', 'amount' => round($subtotal, 2)],
                    ['key' => 'shipping', 'label' => 'Shipping', 'amount' => round($shippingKnown, 2), 'estimated' => $shippingHasUnknown],
                    ['key' => 'wallet', 'label' => 'Wallet applied', 'amount' => -$walletApplied],
                ],
            ],
        ]);
    }

    public function confirm(Request $request): JsonResponse
    {
        $items = CartItem::where('user_id', $request->user()->id)->whereNull('draft_order_id')->get();
        if ($items->isEmpty()) {
            return response()->json(['message' => 'Cart is empty', 'errors' => [], 'status' => 400], 400);
        }

        $useWallet = $request->boolean('use_wallet_balance', true);
        $defaultAddress = Address::where('user_id', $request->user()->id)->where('is_default', true)->first();
        if (!$defaultAddress) {
            return response()->json(['message' => 'Please set a default address', 'errors' => [], 'status' => 400], 400);
        }

        $currency = strtoupper(trim((string) ($items->first()?->currency ?? 'USD')));
        if ($currency === '') {
            $currency = 'USD';
        }

        $subtotal = (float) $items->sum(fn ($i) => (float) $i->unit_price * (int) $i->quantity);
        $shippingKnown = (float) $items->sum(fn ($i) => $i->shipping_cost !== null ? (float) $i->shipping_cost * (int) $i->quantity : 0.0);
        $shippingHasUnknown = $items->contains(fn ($i) => $i->shipping_cost === null);
        $needsReview = $items->contains(fn ($i) => (bool) $i->needs_review || ($i->review_status ?? CartItem::REVIEW_STATUS_PENDING) !== CartItem::REVIEW_STATUS_REVIEWED);
        $estimated = $shippingHasUnknown || $items->contains(fn ($i) => (bool) $i->estimated);

        $total = round($subtotal + $shippingKnown, 2);

        $orderNumber = 'ZY-' . strtoupper(Str::random(6));
        $order = Order::create([
            'user_id' => $request->user()->id,
            'order_number' => $orderNumber,
            'origin' => $items->pluck('country')->unique()->count() > 1 ? 'multi_origin' : 'usa',
            // Payment start requires pending_payment; under_review blocks external payment until reviewed.
            'status' => ($needsReview || $estimated) ? Order::STATUS_UNDER_REVIEW : Order::STATUS_PENDING_PAYMENT,
            'placed_at' => null,
            'total_amount' => $total,
            'order_total_snapshot' => $total,
            'shipping_total_snapshot' => round($shippingKnown, 2),
            'currency' => $currency,
            'shipping_address_id' => $defaultAddress->id,
            'shipping_address_text' => $defaultAddress->address_line ?? $defaultAddress->street_address,
            'estimated' => $estimated,
            'needs_review' => $needsReview || $estimated,
            'review_state' => array_values(array_filter([
                Order::REVIEW_STATE_NEEDS_ADMIN_REVIEW => $needsReview ? true : null,
                Order::REVIEW_STATE_NEEDS_SHIPPING_COMPLETION => $shippingHasUnknown ? true : null,
            ], fn ($v) => $v !== null)),
        ]);

        $byCountry = $items->groupBy(fn ($i) => $i->country ?? 'Other');
        foreach ($byCountry as $country => $group) {
            $shipment = OrderShipment::create([
                'order_id' => $order->id,
                'country_code' => strlen($country) === 2 ? $country : 'US',
                'country_label' => $country . ' Shipment',
                'shipping_method' => $group->first()?->shipping_snapshot['method'] ?? null,
                'eta' => $group->first()?->shipping_snapshot['eta'] ?? null,
                'carrier' => $group->first()?->carrier,
                'subtotal' => $group->sum(fn ($i) => (float) $i->unit_price * (int) $i->quantity),
                'shipping_fee' => $group->sum(fn ($i) => $i->shipping_cost !== null ? (float) $i->shipping_cost * (int) $i->quantity : 0.0),
                'shipping_snapshot' => $group->first()?->shipping_snapshot,
            ]);
            foreach ($group as $i) {
                OrderLineItem::create([
                    'order_shipment_id' => $shipment->id,
                    'source_type' => $i->source,
                    'cart_item_id' => $i->id,
                    'imported_product_id' => $i->imported_product_id,
                    'name' => $i->name,
                    'store_name' => $i->store_name,
                    'sku' => $i->product_id ? (string) $i->product_id : null,
                    'price' => (float) $i->unit_price,
                    'quantity' => (int) $i->quantity,
                    'image_url' => $i->image_url,
                    'product_snapshot' => [
                        'title' => $i->name,
                        'image_url' => $i->image_url,
                        'store_key' => $i->store_key,
                        'store_name' => $i->store_name,
                        'product_url' => $i->product_url,
                        'country' => $i->country,
                    ],
                    'pricing_snapshot' => $i->pricing_snapshot,
                    'review_metadata' => [
                        'review_status' => $i->review_status,
                        'needs_review' => (bool) $i->needs_review,
                    ],
                    'estimated' => (bool) $i->estimated,
                    'missing_fields' => $i->missing_fields,
                ]);
            }
        }

        $wallet = Wallet::firstOrCreate(['user_id' => $request->user()->id], ['available_balance' => 0, 'pending_balance' => 0, 'promo_balance' => 0]);
        $walletBalance = (float) $wallet->available_balance;
        $walletApplied = 0.0;
        $amountDueNow = $total;

        if ($useWallet && $total > 0) {
            $walletApplied = round(min($walletBalance, $total), 2);
            $amountDueNow = round(max(0, $total - $walletApplied), 2);

            // Only deduct wallet immediately if it fully covers the order (no external payment risk).
            if ($walletApplied > 0 && $amountDueNow <= 0.0 && $order->status !== Order::STATUS_UNDER_REVIEW) {
                $wallet->available_balance = max(0, (float) $wallet->available_balance - $walletApplied);
                $wallet->save();
                $wallet->transactions()->create([
                    'type' => 'payment',
                    'title' => 'Order #' . $orderNumber,
                    'amount' => -$walletApplied,
                    'subtitle' => 'WALLET',
                    'reference_type' => 'order',
                    'reference_id' => $order->id,
                ]);

                $order->update([
                    'status' => Order::STATUS_PAID,
                    'placed_at' => now(),
                ]);
            }
        }

        CartItem::where('user_id', $request->user()->id)->whereNull('draft_order_id')->delete();

        return response()->json([
            'message' => 'Order placed',
            'order_id' => (string) $order->id,
            'order_number' => $orderNumber,
            'currency' => $currency,
            'pricing' => [
                'subtotal' => round($subtotal, 2),
                'shipping' => round($shippingKnown, 2),
                'discounts' => 0.0,
                'wallet_balance' => round($walletBalance, 2),
                'wallet_applied_amount' => $walletApplied,
                'amount_due_now' => $amountDueNow,
                'total' => $total,
                'estimated' => $estimated,
                'needs_review' => $order->needs_review,
                'shipping_estimated' => $shippingHasUnknown,
            ],
            'payment_required' => $order->status === Order::STATUS_PENDING_PAYMENT && $amountDueNow > 0,
        ], 201);
    }
}
