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
     * Checkout review. Uses cart item unit_price and shipping_cost as stored (snapshot for imported items).
     * No silent recalculation of shipping or pricing.
     */
    public function review(Request $request): JsonResponse
    {
        $items = CartItem::where('user_id', $request->user()->id)->get();
        $defaultAddress = Address::where('user_id', $request->user()->id)->where('is_default', true)->with('country')->first();
        $wallet = Wallet::firstOrCreate(
            ['user_id' => $request->user()->id],
            ['available_balance' => 0, 'pending_balance' => 0, 'promo_balance' => 0]
        );

        $byCountry = $items->groupBy(fn ($i) => $i->country ?? 'Other');
        $shipments = $byCountry->map(fn ($group, $country) => [
            'origin_label' => $country . ' Shipment',
            'items' => $group->map(fn ($i) => [
                'name' => $i->name,
                'price' => '$' . number_format($i->unit_price, 2),
                'quantity' => $i->quantity,
                'eta' => '4–12 Days',
                'image_url' => $i->image_url,
                'reviewed' => ($i->review_status ?? 'pending_review') === 'reviewed',
                'shipping_cost' => $i->shipping_cost ? '$' . number_format($i->shipping_cost * $i->quantity, 2) : null,
            ])->values()->toArray(),
            'reviewed' => $group->every(fn ($i) => ($i->review_status ?? 'pending_review') === 'reviewed'),
        ])->values()->toArray();

        $subtotal = $items->sum(fn ($i) => $i->unit_price * $i->quantity);
        $shipping = $items->sum(fn ($i) => ($i->shipping_cost ?? 12) * $i->quantity);
        $insurance = 5.0;
        $consolidation = 45.0;
        $walletAmount = min((float) $wallet->available_balance, $subtotal + $shipping + $insurance - $consolidation);
        $total = $subtotal + $shipping + $insurance - $consolidation - $walletAmount;

        return response()->json([
            'shipping_address_short' => $defaultAddress ? substr($defaultAddress->address_line ?? $defaultAddress->street_address ?? '', 0, 50) . '...' : '',
            'consolidation_savings' => '$' . number_format($consolidation, 2),
            'wallet_balance_enabled' => true,
            'wallet_balance' => '$' . number_format($walletAmount, 2),
            'subtotal' => '$' . number_format($subtotal, 2),
            'shipping' => '$' . number_format($shipping, 2),
            'insurance' => '$' . number_format($insurance, 2),
            'total' => '$' . number_format(max(0, $total), 2),
            'shipments' => $shipments,
        ]);
    }

    public function confirm(Request $request): JsonResponse
    {
        $items = CartItem::where('user_id', $request->user()->id)->get();
        if ($items->isEmpty()) {
            return response()->json(['message' => 'Cart is empty'], 400);
        }

        $useWallet = $request->boolean('use_wallet_balance', true);
        $defaultAddress = Address::where('user_id', $request->user()->id)->where('is_default', true)->first();
        if (!$defaultAddress) {
            return response()->json(['message' => 'Please set a default address'], 400);
        }

        $orderNumber = 'ZY-' . strtoupper(Str::random(6));
        $order = Order::create([
            'user_id' => $request->user()->id,
            'order_number' => $orderNumber,
            'origin' => $items->pluck('country')->unique()->count() > 1 ? 'multi_origin' : 'usa',
            'status' => 'in_transit',
            'placed_at' => now(),
            'total_amount' => 0,
            'currency' => 'USD',
            'shipping_address_id' => $defaultAddress->id,
            'shipping_address_text' => $defaultAddress->address_line ?? $defaultAddress->street_address,
        ]);

        $byCountry = $items->groupBy(fn ($i) => $i->country ?? 'Other');
        foreach ($byCountry as $country => $group) {
            $shipment = OrderShipment::create([
                'order_id' => $order->id,
                'country_code' => strlen($country) === 2 ? $country : 'US',
                'country_label' => $country . ' Shipment',
                'shipping_method' => 'Air Express',
                'eta' => '4–12 Days',
                'subtotal' => $group->sum(fn ($i) => $i->unit_price * $i->quantity),
                'shipping_fee' => $group->sum(fn ($i) => ($i->shipping_cost ?? 12) * $i->quantity),
            ]);
            foreach ($group as $i) {
                OrderLineItem::create([
                    'order_shipment_id' => $shipment->id,
                    'name' => $i->name,
                    'store_name' => $i->store_name,
                    'sku' => $i->product_id ?? '',
                    'price' => $i->unit_price * $i->quantity,
                    'quantity' => $i->quantity,
                    'image_url' => $i->image_url,
                ]);
            }
        }

        $totalAmount = $order->shipments->sum(fn ($s) => $s->subtotal + $s->shipping_fee);
        $order->update(['total_amount' => $totalAmount]);

        if ($useWallet) {
            $wallet = Wallet::firstOrCreate(['user_id' => $request->user()->id], ['available_balance' => 0, 'pending_balance' => 0, 'promo_balance' => 0]);
            $deduct = min($wallet->available_balance, $totalAmount);
            if ($deduct > 0) {
                $wallet->available_balance -= $deduct;
                $wallet->save();
                $wallet->transactions()->create([
                    'type' => 'payment',
                    'title' => 'Order #' . $orderNumber,
                    'amount' => -$deduct,
                    'subtitle' => 'SHIPPING FEE',
                    'reference_type' => 'order',
                    'reference_id' => $order->id,
                ]);
            }
        }

        CartItem::where('user_id', $request->user()->id)->delete();

        return response()->json([
            'message' => 'Order placed',
            'order_id' => $order->id,
            'order_number' => $orderNumber,
        ], 201);
    }
}
