<?php

namespace App\Support;

use App\Models\OrderLineItem;
use Illuminate\Support\Str;

/**
 * Read-only display helpers for admin order line rows (snapshots at checkout / draft).
 */
class AdminOrderLineItemDisplay
{
    /**
     * Whether the line may be physically received into the warehouse (queue, order detail, POST validation).
     */
    public static function canReceiveIntoWarehouse(OrderLineItem $li): bool
    {
        return in_array($li->fulfillment_status, [
            OrderLineItem::FULFILLMENT_PURCHASED,
            OrderLineItem::FULFILLMENT_IN_TRANSIT_TO_WAREHOUSE,
        ], true);
    }

    /**
     * Canonical product image URL for admin UI (warehouse queue, shipment items, etc.).
     *
     * Priority: {@see OrderLineItem::$product_snapshot} image_url, line {@see OrderLineItem::$image_url},
     * {@see OrderLineItem::$importedProduct}, {@see OrderLineItem::$cartItem}.
     */
    public static function productImageUrl(OrderLineItem $li): ?string
    {
        $rawCandidates = [];

        $ps = $li->product_snapshot ?? [];
        if (is_array($ps) && isset($ps['image_url']) && is_string($ps['image_url'])) {
            $rawCandidates[] = trim($ps['image_url']);
        }

        if (! empty($li->image_url) && is_string($li->image_url)) {
            $rawCandidates[] = trim($li->image_url);
        }

        if ($li->relationLoaded('importedProduct') && $li->importedProduct?->image_url) {
            $rawCandidates[] = trim((string) $li->importedProduct->image_url);
        }

        if ($li->relationLoaded('cartItem') && $li->cartItem?->image_url) {
            $rawCandidates[] = trim((string) $li->cartItem->image_url);
        }

        foreach ($rawCandidates as $raw) {
            if ($raw === '') {
                continue;
            }

            return AdminWarehouseReceiptImages::displayUrl($raw);
        }

        return null;
    }

    /**
     * Display URLs for all images stored on warehouse receipts for this line (deduped).
     *
     * @return list<string>
     */
    public static function warehouseReceiptImageUrls(OrderLineItem $li): array
    {
        $receipts = null;
        if ($li->relationLoaded('warehouseReceipts') && $li->warehouseReceipts->isNotEmpty()) {
            $receipts = $li->warehouseReceipts;
        } elseif ($li->relationLoaded('latestWarehouseReceipt') && $li->latestWarehouseReceipt) {
            $receipts = collect([$li->latestWarehouseReceipt]);
        }

        if ($receipts === null || $receipts->isEmpty()) {
            return [];
        }

        $out = [];
        foreach ($receipts as $wr) {
            if (! is_array($wr->images)) {
                continue;
            }
            foreach ($wr->images as $entry) {
                if (is_string($entry) && $entry !== '') {
                    $out[] = AdminWarehouseReceiptImages::displayUrl($entry);
                }
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * Compact admin HTML: thumbnail (or placeholder) + product name, thumbnail links to full image.
     */
    public static function adminProductThumbnailWithNameHtml(OrderLineItem $li, int $thumbSize = 40, ?int $nameLimit = 60): string
    {
        $url = self::productImageUrl($li);
        $name = e(Str::limit($li->name, $nameLimit ?? 200));
        $size = max(24, min(96, $thumbSize));

        if ($url) {
            return '<a href="'.e($url).'" target="_blank" rel="noopener noreferrer" class="d-inline-flex align-items-center gap-2 text-decoration-none text-body" title="'.e(__('admin.product_image_col')).'">'
                .'<img src="'.e($url).'" alt="" class="rounded border flex-shrink-0 bg-body-secondary" width="'.$size.'" height="'.$size.'" style="object-fit:cover" loading="lazy">'
                .'<span class="small">'.$name.'</span></a>';
        }

        return '<div class="d-flex align-items-center gap-2">'
            .'<span class="rounded border bg-body-secondary d-inline-flex align-items-center justify-content-center flex-shrink-0 text-muted small user-select-none" style="width:'.$size.'px;height:'.$size.'px" title="'.e(__('admin.product_image_col')).'">—</span>'
            .'<span class="small">'.$name.'</span>'
            .'</div>';
    }

    /**
     * Small gallery HTML for warehouse receipt photos (links open full image in new tab).
     */
    public static function adminWarehouseReceiptThumbnailsHtml(OrderLineItem $li, int $thumbSize = 36, int $maxShow = 8): string
    {
        $urls = self::warehouseReceiptImageUrls($li);
        if ($urls === []) {
            return '<span class="text-muted small">—</span>';
        }

        $size = max(24, min(72, $thumbSize));
        $html = '<div class="d-flex flex-wrap gap-1 align-items-center">';
        $slice = array_slice($urls, 0, $maxShow);
        foreach ($slice as $u) {
            $html .= '<a href="'.e($u).'" target="_blank" rel="noopener noreferrer" title="'.e(__('admin.received_images_col')).'">'
                .'<img src="'.e($u).'" alt="" class="rounded border" width="'.$size.'" height="'.$size.'" style="object-fit:cover" loading="lazy"></a>';
        }
        $extra = count($urls) - count($slice);
        if ($extra > 0) {
            $html .= '<span class="small text-muted">+'.(int) $extra.'</span>';
        }
        $html .= '</div>';

        return $html;
    }

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
