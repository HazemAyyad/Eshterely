<?php

namespace App\Http\Resources;

use App\Models\PurchaseAssistantRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PurchaseAssistantRequest
 */
class PurchaseAssistantRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PurchaseAssistantRequest $r */
        $r = $this->resource;

        $qty = max(1, (int) $r->quantity);
        $totalPayable = null;
        if ($r->admin_product_price !== null && $r->admin_service_fee !== null) {
            $totalPayable = round(((float) $r->admin_product_price * $qty) + (float) $r->admin_service_fee, 2);
        }

        return [
            'id' => (string) $r->id,
            'source_url' => $r->source_url,
            'source_domain' => $r->source_domain,
            'store_display_name' => $r->store_display_name,
            'title' => $r->title,
            'details' => $r->details,
            'quantity' => $r->quantity,
            'variant_details' => $r->variant_details,
            'customer_estimated_price' => $r->customer_estimated_price !== null ? (float) $r->customer_estimated_price : null,
            'currency' => $r->currency,
            'image_urls' => $r->image_paths ?? [],
            'admin_product_price' => $r->admin_product_price !== null ? (float) $r->admin_product_price : null,
            'admin_service_fee' => $r->admin_service_fee !== null ? (float) $r->admin_service_fee : null,
            'total_payable' => $totalPayable,
            'admin_notes' => $r->admin_notes,
            'status' => $r->status,
            'origin' => $r->origin,
            'converted_order_id' => $r->converted_order_id !== null ? (string) $r->converted_order_id : null,
            'status_explanation' => self::statusExplanation($r->status),
            'created_at' => $r->created_at?->toIso8601String(),
            'updated_at' => $r->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Short user-facing explanation for the dedicated app UI (English; app may localize later).
     */
    public static function statusExplanation(string $status): string
    {
        return match ($status) {
            PurchaseAssistantRequest::STATUS_SUBMITTED => 'Your request was received and is waiting to be reviewed.',
            PurchaseAssistantRequest::STATUS_UNDER_REVIEW => 'Our team is reviewing your product link and pricing.',
            PurchaseAssistantRequest::STATUS_AWAITING_CUSTOMER_PAYMENT => 'Pricing is ready. Complete payment to proceed.',
            PurchaseAssistantRequest::STATUS_PAYMENT_UNDER_REVIEW => 'Payment received — we are confirming it.',
            PurchaseAssistantRequest::STATUS_PAID => 'Payment confirmed. We will proceed with purchasing.',
            PurchaseAssistantRequest::STATUS_PURCHASING => 'We are purchasing your item from the store.',
            PurchaseAssistantRequest::STATUS_PURCHASED => 'The item has been purchased and is being prepared for shipment to our warehouse.',
            PurchaseAssistantRequest::STATUS_IN_TRANSIT_TO_WAREHOUSE => 'Your item is on its way to our warehouse.',
            PurchaseAssistantRequest::STATUS_RECEIVED_AT_WAREHOUSE => 'Your item arrived at our warehouse.',
            PurchaseAssistantRequest::STATUS_COMPLETED => 'This Purchase Assistant request is complete.',
            PurchaseAssistantRequest::STATUS_REJECTED => 'This request could not be fulfilled.',
            PurchaseAssistantRequest::STATUS_CANCELLED => 'This request was cancelled.',
            default => 'Status updated.',
        };
    }
}
