<?php

namespace App\Services\Admin;

use App\Enums\Payment\PaymentStatus;
use App\Models\CartItem;
use App\Models\DraftOrder;
use App\Models\ImportedProduct;
use App\Models\NotificationDispatch;
use App\Models\Order;
use App\Models\OrderShipment;
use App\Models\Payment;
use Illuminate\Support\Carbon;

class AdminDashboardMetricsService
{
    public function getDashboardData(): array
    {
        return [
            'summary' => $this->summaryMetrics(),
            'review' => $this->reviewMetrics(),
            'payments' => $this->paymentMetrics(),
            'shipments' => $this->shipmentMetrics(),
            'notifications' => $this->notificationMetrics(),
            'top' => $this->topEntitiesMetrics(),
            'recent_activity' => $this->recentActivity(),
        ];
    }

    protected function summaryMetrics(): array
    {
        return [
            'imported_products_total' => ImportedProduct::count(),
            'imported_products_confirmed' => ImportedProduct::where('status', ImportedProduct::STATUS_ADDED_TO_CART)
                ->orWhere('status', ImportedProduct::STATUS_ORDERED)
                ->count(),
            'active_carts' => CartItem::whereNull('draft_order_id')->count(),
            'draft_orders' => DraftOrder::where('status', DraftOrder::STATUS_DRAFT)->count(),
            'orders_pending_payment' => Order::where('status', Order::STATUS_PENDING_PAYMENT)->count(),
            'orders_paid' => Order::where('status', Order::STATUS_PAID)->count(),
            'orders_needing_review' => Order::where('needs_review', true)->count(),
            'orders_in_fulfillment' => Order::whereIn('status', [
                Order::STATUS_UNDER_REVIEW,
                Order::STATUS_APPROVED,
                Order::STATUS_PROCESSING,
                Order::STATUS_PURCHASED,
                Order::STATUS_SHIPPED_TO_WAREHOUSE,
                Order::STATUS_INTERNATIONAL_SHIPPING,
                Order::STATUS_IN_TRANSIT,
            ])->count(),
            'orders_delivered' => Order::where('status', Order::STATUS_DELIVERED)->count(),
            'shipments_in_transit' => OrderShipment::where('shipment_status', 'in_transit')->count(),
            'failed_payments' => Payment::where('status', PaymentStatus::Failed)->count(),
            'successful_notifications' => NotificationDispatch::where('send_status', NotificationDispatch::STATUS_SENT)->count(),
            'failed_notifications' => NotificationDispatch::where('send_status', NotificationDispatch::STATUS_FAILED)->count(),
        ];
    }

    protected function reviewMetrics(): array
    {
        $ordersNeedsReview = Order::where('needs_review', true)->count();
        $reviewStateCounts = Order::select('review_state')->whereNotNull('review_state')->get()
            ->reduce(function (array $carry, Order $order) {
                $state = $order->review_state ?? [];
                foreach ($state as $key => $flag) {
                    if ($flag) {
                        $carry[$key] = ($carry[$key] ?? 0) + 1;
                    }
                }
                return $carry;
            }, []);

        return [
            'orders_needs_review' => $ordersNeedsReview,
            'review_state_distribution' => $reviewStateCounts,
            'estimated_orders' => Order::where('estimated', true)->count(),
            'orders_blocked_from_checkout' => DraftOrder::where('needs_review', true)->count(),
            'orders_awaiting_admin_action' => Order::where('needs_review', true)
                ->orWhere(fn ($q) => $q->where('status', Order::STATUS_PENDING_PAYMENT)->whereHas('payments', function ($p) {
                    $p->whereIn('status', [PaymentStatus::RequiresAction, PaymentStatus::Failed, PaymentStatus::Cancelled]);
                }))
                ->count(),
        ];
    }

    protected function paymentMetrics(): array
    {
        $recentPayments = Payment::with('order')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $recentAttempts = \App\Models\PaymentAttempt::with('payment')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return [
            'counts' => [
                'pending' => Payment::where('status', PaymentStatus::Pending)->count(),
                'requires_action' => Payment::where('status', PaymentStatus::RequiresAction)->count(),
                'processing' => Payment::where('status', PaymentStatus::Processing)->count(),
                'paid' => Payment::where('status', PaymentStatus::Paid)->count(),
                'failed' => Payment::where('status', PaymentStatus::Failed)->count(),
                'cancelled' => Payment::where('status', PaymentStatus::Cancelled)->count(),
                'refunded' => Payment::where('status', PaymentStatus::Refunded)->count(),
            ],
            'recent_payments' => $recentPayments,
            'recent_attempts' => $recentAttempts,
            'recent_webhook_success' => \App\Models\PaymentEvent::where('event_type', 'webhook_success')
                ->orderByDesc('created_at')
                ->limit(10)
                ->get(),
        ];
    }

    protected function shipmentMetrics(): array
    {
        $shipmentsByStatus = OrderShipment::selectRaw('shipment_status, COUNT(*) as total')
            ->groupBy('shipment_status')
            ->pluck('total', 'shipment_status');

        $ordersWithTracking = OrderShipment::whereNotNull('tracking_number')->distinct('order_id')->count('order_id');

        $deliveredShipments = OrderShipment::where('shipment_status', 'delivered')->count();

        $shipmentsWithExceptions = OrderShipment::whereJsonContains('status_tags', 'exception')
            ->orWhere('shipment_status', 'exception')
            ->count();

        $topCarriers = OrderShipment::selectRaw('carrier, COUNT(*) as total')
            ->whereNotNull('carrier')
            ->groupBy('carrier')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $topCountries = OrderShipment::selectRaw('country_code, country_label, COUNT(*) as total')
            ->groupBy('country_code', 'country_label')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        return [
            'by_status' => $shipmentsByStatus,
            'orders_with_tracking' => $ordersWithTracking,
            'delivered_shipments' => $deliveredShipments,
            'shipments_with_exceptions' => $shipmentsWithExceptions,
            'top_carriers' => $topCarriers,
            'top_countries' => $topCountries,
        ];
    }

    protected function notificationMetrics(): array
    {
        $recentDispatches = NotificationDispatch::orderByDesc('created_at')
            ->limit(15)
            ->get();

        $typeCounts = NotificationDispatch::selectRaw('type, COUNT(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $statusCounts = NotificationDispatch::selectRaw('send_status, COUNT(*) as total')
            ->groupBy('send_status')
            ->pluck('total', 'send_status');

        return [
            'by_type' => $typeCounts,
            'by_status' => $statusCounts,
            'recent' => $recentDispatches,
        ];
    }

    protected function topEntitiesMetrics(): array
    {
        $topDestinationCountries = OrderShipment::selectRaw('country_code, country_label, COUNT(*) as total')
            ->groupBy('country_code', 'country_label')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $topCarriers = OrderShipment::selectRaw('carrier, COUNT(*) as total')
            ->whereNotNull('carrier')
            ->groupBy('carrier')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $topOrderStatuses = Order::selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        return [
            'destination_countries' => $topDestinationCountries,
            'carriers' => $topCarriers,
            'order_statuses' => $topOrderStatuses,
        ];
    }

    protected function recentActivity(): array
    {
        $since = Carbon::now()->subDays(7);

        $recentOrders = Order::where('created_at', '>=', $since)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['id', 'status', 'total_amount', 'created_at']);

        $recentShipments = OrderShipment::where('created_at', '>=', $since)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['id', 'order_id', 'shipment_status', 'carrier', 'country_label', 'created_at']);

        $recentPayments = Payment::where('created_at', '>=', $since)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['id', 'order_id', 'amount', 'status', 'created_at']);

        $recentNotifications = NotificationDispatch::where('created_at', '>=', $since)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['id', 'type', 'send_status', 'title', 'created_at']);

        return [
            'orders' => $recentOrders,
            'shipments' => $recentShipments,
            'payments' => $recentPayments,
            'notifications' => $recentNotifications,
        ];
    }
}

