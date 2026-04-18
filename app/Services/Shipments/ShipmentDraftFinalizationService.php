<?php

namespace App\Services\Shipments;

use App\Models\OrderLineItem;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Services\PurchaseAssistant\PurchaseAssistantRequestStatusSync;
use Illuminate\Support\Facades\DB;

/**
 * Attaches line items to an outbound shipment once the user commits to pay.
 * Draft shipments never create ShipmentItem rows or change line fulfillment until this runs.
 */
class ShipmentDraftFinalizationService
{
    /**
     * Validates lines and attaches them; sets shipment to awaiting_payment. Idempotent if items already exist.
     *
     * @param  list<int>  $selectedIds
     */
    public function attachLinesAndSetAwaitingPayment(Shipment $shipment, array $selectedIds, int $userId): void
    {
        if ($shipment->status !== Shipment::STATUS_DRAFT) {
            return;
        }

        if ($shipment->items()->exists()) {
            $shipment->update([
                'status' => Shipment::STATUS_AWAITING_PAYMENT,
                'draft_payload' => null,
            ]);

            return;
        }

        $lineItems = OrderLineItem::query()
            ->whereIn('id', $selectedIds)
            ->with(['shipmentItems'])
            ->get();

        if ($lineItems->count() !== count(array_unique($selectedIds))) {
            throw new \InvalidArgumentException('Invalid line items.');
        }

        foreach ($lineItems as $line) {
            if (! $this->userOwnsLineItem($line, $userId)) {
                throw new \InvalidArgumentException('Forbidden.');
            }
            if ($line->fulfillment_status !== OrderLineItem::FULFILLMENT_ARRIVED_AT_WAREHOUSE) {
                throw new \InvalidArgumentException('Item is not at warehouse.');
            }
            if ($line->shipmentItems()->exists()) {
                throw new \InvalidArgumentException('Item already assigned to a shipment.');
            }
        }

        foreach ($lineItems as $line) {
            ShipmentItem::create([
                'shipment_id' => $shipment->id,
                'order_line_item_id' => $line->id,
            ]);
            $line->update(['fulfillment_status' => OrderLineItem::FULFILLMENT_READY_FOR_SHIPMENT]);
            app(PurchaseAssistantRequestStatusSync::class)->syncFromOrderLineItem($line->fresh());
        }

        $shipment->update([
            'status' => Shipment::STATUS_AWAITING_PAYMENT,
            'draft_payload' => null,
        ]);
    }

    /**
     * Used when a gateway payment webhook marks the payment paid: attach lines then mark shipment paid.
     */
    public function finalizeDraftAndMarkPaid(Shipment $shipment): void
    {
        if ($shipment->status !== Shipment::STATUS_DRAFT) {
            return;
        }

        $payload = $shipment->draft_payload;
        if (! is_array($payload)) {
            return;
        }

        $ids = $payload['selected_order_item_ids'] ?? [];
        if (! is_array($ids) || $ids === []) {
            return;
        }

        $intIds = array_map('intval', $ids);

        DB::transaction(function () use ($shipment, $intIds) {
            $shipment->refresh();
            if ($shipment->status !== Shipment::STATUS_DRAFT) {
                return;
            }

            $this->attachLinesAndSetAwaitingPayment($shipment, $intIds, (int) $shipment->user_id);

            $shipment->refresh();
            if ($shipment->status === Shipment::STATUS_AWAITING_PAYMENT) {
                $shipment->update(['status' => Shipment::STATUS_PAID]);
            }
        });
    }

    private function userOwnsLineItem(OrderLineItem $line, int $userId): bool
    {
        return OrderLineItem::query()
            ->where('order_line_items.id', $line->id)
            ->join('order_shipments', 'order_line_items.order_shipment_id', '=', 'order_shipments.id')
            ->join('orders', 'order_shipments.order_id', '=', 'orders.id')
            ->where('orders.user_id', $userId)
            ->exists();
    }
}
