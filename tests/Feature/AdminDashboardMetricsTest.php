<?php

namespace Tests\Feature;

use App\Enums\Payment\PaymentStatus;
use App\Models\DraftOrder;
use App\Models\ImportedProduct;
use App\Models\NotificationDispatch;
use App\Models\Order;
use App\Models\OrderShipment;
use App\Models\Payment;
use App\Services\Admin\AdminDashboardMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_metrics_aggregate_core_counts(): void
    {
        ImportedProduct::factory()->count(3)->create();
        ImportedProduct::factory()->count(2)->create(['status' => ImportedProduct::STATUS_ADDED_TO_CART]);

        DraftOrder::factory()->count(4)->create(['status' => DraftOrder::STATUS_DRAFT]);

        Order::factory()->count(2)->pendingPayment()->create();
        Order::factory()->count(3)->create(['status' => Order::STATUS_PAID]);
        $needsReview = Order::factory()->create(['needs_review' => true]);
        OrderShipment::create([
            'order_id' => $needsReview->id,
            'country_code' => 'US',
            'country_label' => 'US',
            'shipment_status' => 'in_transit',
        ]);

        Payment::factory()->count(2)->create(['status' => PaymentStatus::Failed]);

        NotificationDispatch::factory()->count(5)->create(['send_status' => NotificationDispatch::STATUS_SENT]);
        NotificationDispatch::factory()->count(2)->create(['send_status' => NotificationDispatch::STATUS_FAILED]);

        $service = app(AdminDashboardMetricsService::class);
        $data = $service->getDashboardData();

        $this->assertSame(5, $data['summary']['imported_products_total']);
        $this->assertSame(2, $data['summary']['imported_products_confirmed']);
        $this->assertSame(4, $data['summary']['draft_orders']);
        $this->assertSame(2, $data['summary']['orders_pending_payment']);
        $this->assertSame(3, $data['summary']['orders_paid']);
        $this->assertSame(1, $data['summary']['orders_needing_review']);
        $this->assertSame(1, $data['summary']['shipments_in_transit']);
        $this->assertSame(2, $data['summary']['failed_payments']);
        $this->assertSame(5, $data['summary']['successful_notifications']);
        $this->assertSame(2, $data['summary']['failed_notifications']);
    }

    public function test_payment_metrics_breakdown_by_status(): void
    {
        Payment::factory()->create(['status' => PaymentStatus::Pending]);
        Payment::factory()->create(['status' => PaymentStatus::RequiresAction]);
        Payment::factory()->create(['status' => PaymentStatus::Processing]);
        Payment::factory()->count(2)->create(['status' => PaymentStatus::Paid]);
        Payment::factory()->create(['status' => PaymentStatus::Failed]);
        Payment::factory()->create(['status' => PaymentStatus::Cancelled]);
        Payment::factory()->create(['status' => PaymentStatus::Refunded]);

        $service = app(AdminDashboardMetricsService::class);
        $data = $service->getDashboardData();

        $counts = $data['payments']['counts'];
        $this->assertSame(1, $counts['pending']);
        $this->assertSame(1, $counts['requires_action']);
        $this->assertSame(1, $counts['processing']);
        $this->assertSame(2, $counts['paid']);
        $this->assertSame(1, $counts['failed']);
        $this->assertSame(1, $counts['cancelled']);
        $this->assertSame(1, $counts['refunded']);
    }

    public function test_review_metrics_respect_needs_review_and_estimated(): void
    {
        Order::factory()->count(2)->create(['needs_review' => true]);
        Order::factory()->count(3)->create(['estimated' => true]);
        DraftOrder::factory()->count(2)->create(['needs_review' => true]);

        $service = app(AdminDashboardMetricsService::class);
        $data = $service->getDashboardData();

        $this->assertSame(2, $data['review']['orders_needs_review']);
        $this->assertSame(3, $data['review']['estimated_orders']);
        $this->assertSame(2, $data['review']['orders_blocked_from_checkout']);
    }

    public function test_shipment_metrics_group_by_status_and_carrier(): void
    {
        $order = Order::factory()->create();
        OrderShipment::create([
            'order_id' => $order->id,
            'country_code' => 'US',
            'country_label' => 'US',
            'shipment_status' => 'in_transit',
            'carrier' => 'DHL',
        ]);
        OrderShipment::create([
            'order_id' => $order->id,
            'country_code' => 'US',
            'country_label' => 'US',
            'shipment_status' => 'delivered',
            'carrier' => 'DHL',
        ]);
        OrderShipment::create([
            'order_id' => $order->id,
            'country_code' => 'UK',
            'country_label' => 'UK',
            'shipment_status' => 'exception',
            'carrier' => 'FedEx',
            'status_tags' => ['exception'],
        ]);

        $service = app(AdminDashboardMetricsService::class);
        $data = $service->getDashboardData();

        $this->assertSame(1, $data['shipments']['by_status']['in_transit'] ?? 0);
        $this->assertSame(1, $data['shipments']['by_status']['delivered'] ?? 0);
        $this->assertSame(1, $data['shipments']['by_status']['exception'] ?? 0);
        $this->assertSame(3, $data['shipments']['delivered_shipments'] + $data['shipments']['shipments_with_exceptions'] - 1);
    }

    public function test_notification_metrics_group_by_type_and_status(): void
    {
        NotificationDispatch::factory()->create(['type' => NotificationDispatch::TYPE_BULK, 'send_status' => NotificationDispatch::STATUS_SENT]);
        NotificationDispatch::factory()->create(['type' => NotificationDispatch::TYPE_INDIVIDUAL, 'send_status' => NotificationDispatch::STATUS_FAILED]);
        NotificationDispatch::factory()->create(['type' => NotificationDispatch::TYPE_SYSTEM_EVENT, 'send_status' => NotificationDispatch::STATUS_PARTIAL]);

        $service = app(AdminDashboardMetricsService::class);
        $data = $service->getDashboardData();

        $this->assertSame(1, $data['notifications']['by_type'][NotificationDispatch::TYPE_BULK] ?? 0);
        $this->assertSame(1, $data['notifications']['by_type'][NotificationDispatch::TYPE_INDIVIDUAL] ?? 0);
        $this->assertSame(1, $data['notifications']['by_type'][NotificationDispatch::TYPE_SYSTEM_EVENT] ?? 0);

        $this->assertSame(1, $data['notifications']['by_status'][NotificationDispatch::STATUS_SENT] ?? 0);
        $this->assertSame(1, $data['notifications']['by_status'][NotificationDispatch::STATUS_FAILED] ?? 0);
        $this->assertSame(1, $data['notifications']['by_status'][NotificationDispatch::STATUS_PARTIAL] ?? 0);
    }
}

