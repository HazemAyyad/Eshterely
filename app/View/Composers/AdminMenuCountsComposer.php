<?php

namespace App\View\Composers;

use App\Enums\Payment\PaymentStatus;
use App\Models\OrderLineItem;
use App\Models\Shipment;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class AdminMenuCountsComposer
{
    public function compose(View $view): void
    {
        $counts = [
            'orders_procurement' => 0,
            'warehouse_queue' => 0,
            'shipments_ops' => 0,
        ];

        if (Schema::hasTable('order_line_items')) {
            $counts['orders_procurement'] = OrderLineItem::query()
                ->whereIn('fulfillment_status', [
                    OrderLineItem::FULFILLMENT_PAID,
                    OrderLineItem::FULFILLMENT_REVIEWED,
                ])
                ->whereHas('shipment.order', fn ($q) => $q->whereHas('payments', fn ($p) => $p->where('status', PaymentStatus::Paid)))
                ->count();

            $counts['warehouse_queue'] = OrderLineItem::query()
                ->whereIn('fulfillment_status', [
                    OrderLineItem::FULFILLMENT_PURCHASED,
                    OrderLineItem::FULFILLMENT_IN_TRANSIT_TO_WAREHOUSE,
                ])
                ->whereHas('shipment.order', fn ($q) => $q->whereHas('payments', fn ($p) => $p->where('status', PaymentStatus::Paid)))
                ->count();
        }

        if (Schema::hasTable('shipments')) {
            $counts['shipments_ops'] = Shipment::query()
                ->whereIn('status', [Shipment::STATUS_PAID, Shipment::STATUS_PACKED])
                ->count();
        }

        $view->with('adminMenuCounts', $counts);
    }
}
