<?php

namespace App\Support;

use App\Models\OrderLineItem;

/**
 * Read-only display helpers for admin order line rows (snapshots at checkout / draft).
 */
class AdminOrderLineItemDisplay
{
    public static function sourceProductUrl(OrderLineItem $li): ?string
    {
        $ps = $li->product_snapshot ?? [];
        if (! empty($ps['product_url']) && is_string($ps['product_url'])) {
            return $ps['product_url'];
        }
        if ($li->relationLoaded('cartItem') && $li->cartItem?->product_url) {
            return (string) $li->cartItem->product_url;
        }
        if ($li->relationLoaded('importedProduct') && $li->importedProduct?->source_url) {
            return (string) $li->importedProduct->source_url;
        }

        return null;
    }

    /**
     * Unit merchandise price: cart checkout stores unit price on {@see OrderLineItem::$price};
     * draft finalization stores line subtotal on {@see OrderLineItem::$price}.
     */
    public static function unitPrice(OrderLineItem $li): float
    {
        $qty = max(1, (int) $li->quantity);
        $ps = $li->product_snapshot ?? [];
        if (is_array($ps) && isset($ps['unit_price']) && is_numeric($ps['unit_price'])) {
            return round((float) $ps['unit_price'], 2);
        }

        $pricing = $li->pricing_snapshot ?? [];
        if (is_array($pricing)) {
            $lineMerch = null;
            if (isset($pricing['line_subtotal']) && is_numeric($pricing['line_subtotal'])) {
                $lineMerch = (float) $pricing['line_subtotal'];
            } elseif (isset($pricing['subtotal']) && is_numeric($pricing['subtotal'])) {
                $lineMerch = (float) $pricing['subtotal'];
            }
            if ($lineMerch !== null) {
                return round($lineMerch / $qty, 2);
            }
        }

        if ($li->draft_order_item_id !== null) {
            return round((float) $li->price / $qty, 2);
        }

        return round((float) $li->price, 2);
    }

    public static function lineSubtotal(OrderLineItem $li): float
    {
        $pricing = $li->pricing_snapshot ?? [];
        if (is_array($pricing)) {
            if (isset($pricing['line_subtotal']) && is_numeric($pricing['line_subtotal'])) {
                return round((float) $pricing['line_subtotal'], 2);
            }
            if (isset($pricing['subtotal']) && is_numeric($pricing['subtotal'])) {
                return round((float) $pricing['subtotal'], 2);
            }
        }

        if ($li->draft_order_item_id !== null) {
            return round((float) $li->price, 2);
        }

        return round(self::unitPrice($li) * (int) $li->quantity, 2);
    }

    /**
     * Service / app fee for the line (from snapshot at confirmation).
     */
    public static function serviceFeeAmount(OrderLineItem $li): ?float
    {
        $ps = $li->pricing_snapshot ?? [];
        if (! is_array($ps)) {
            return null;
        }
        if (isset($ps['app_fee_amount']) && is_numeric($ps['app_fee_amount'])) {
            return round((float) $ps['app_fee_amount'], 2);
        }
        if (isset($ps['service_fee']) && is_numeric($ps['service_fee'])) {
            return round((float) $ps['service_fee'], 2);
        }

        return null;
    }

    /**
     * First checkout payment total for this line (product + app/service fee, as stored).
     */
    public static function firstPaymentTotal(OrderLineItem $li): ?float
    {
        $ps = $li->pricing_snapshot ?? [];
        if (! is_array($ps)) {
            return null;
        }
        if (isset($ps['payable_now_total']) && is_numeric($ps['payable_now_total'])) {
            return round((float) $ps['payable_now_total'], 2);
        }
        if (isset($ps['final_total']) && is_numeric($ps['final_total'])) {
            return round((float) $ps['final_total'], 2);
        }

        $fee = self::serviceFeeAmount($li);
        if ($fee !== null) {
            return round(self::lineSubtotal($li) + $fee, 2);
        }

        return null;
    }
}
