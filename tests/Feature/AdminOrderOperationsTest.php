<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Order;
use App\Models\OrderOperationLog;
use App\Models\OrderShipment;
use App\Models\Payment;
use App\Enums\Payment\PaymentStatus;
use App\Services\Admin\OrderStatusWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOrderOperationsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Admin
    {
        return Admin::factory()->create();
    }

    public function test_admin_can_mark_order_as_reviewed(): void
    {
        $admin = $this->admin();
        $order = Order::factory()->create([
            'status' => Order::STATUS_PAID,
            'needs_review' => true,
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->patch(route('admin.orders.review', $order), [
                'admin_notes' => 'Reviewed and approved.',
            ]);

        $response->assertRedirect();
        $order->refresh();
        $this->assertFalse($order->needs_review);
        $this->assertNotNull($order->reviewed_at);
        $this->assertSame('Reviewed and approved.', $order->admin_notes);

        $log = OrderOperationLog::where('order_id', $order->id)->where('action', 'review')->first();
        $this->assertNotNull($log);
        $this->assertSame($admin->id, $log->admin_id);
    }

    public function test_admin_can_update_status_when_allowed(): void
    {
        $admin = $this->admin();
        $order = Order::factory()->create(['status' => Order::STATUS_PAID]);
        Payment::factory()->forOrder($order)->paid()->create();

        $response = $this->actingAs($admin, 'admin')
            ->patch(route('admin.orders.update-status', $order), [
                'status' => Order::STATUS_UNDER_REVIEW,
            ]);

        $response->assertRedirect();
        $order->refresh();
        $this->assertSame(Order::STATUS_UNDER_REVIEW, $order->status);

        $log = OrderOperationLog::where('order_id', $order->id)->where('action', 'status_change')->first();
        $this->assertNotNull($log);
        $this->assertSame('paid', $log->payload['from'] ?? null);
        $this->assertSame(Order::STATUS_UNDER_REVIEW, $log->payload['to'] ?? null);
    }

    public function test_invalid_status_transition_is_blocked(): void
    {
        $admin = $this->admin();
        $order = Order::factory()->create(['status' => Order::STATUS_PAID]);

        $response = $this->actingAs($admin, 'admin')
            ->patch(route('admin.orders.update-status', $order), [
                'status' => Order::STATUS_DELIVERED,
            ]);

        $response->assertSessionHas('error');
        $order->refresh();
        $this->assertSame(Order::STATUS_PAID, $order->status);
    }

    public function test_unpaid_order_cannot_move_to_fulfillment(): void
    {
        $workflow = new OrderStatusWorkflowService();
        $order = Order::factory()->create([
            'status' => Order::STATUS_PENDING_PAYMENT,
        ]);
        $this->assertFalse($order->payments()->where('status', PaymentStatus::Paid)->exists());

        $check = $workflow->canTransitionTo($order, Order::STATUS_PROCESSING);
        $this->assertFalse($check['allowed']);
        // Reason is either "not allowed" transition or "must be paid" for fulfillment
        $this->assertNotEmpty($check['reason']);
    }

    public function test_paid_order_can_move_to_processing(): void
    {
        $workflow = new OrderStatusWorkflowService();
        $order = Order::factory()->create(['status' => Order::STATUS_PAID]);
        Payment::factory()->forOrder($order)->paid()->create();

        $check = $workflow->canTransitionTo($order, Order::STATUS_PROCESSING);
        $this->assertTrue($check['allowed']);
    }

    public function test_cancelled_order_cannot_change_status(): void
    {
        $workflow = new OrderStatusWorkflowService();
        $order = Order::factory()->create(['status' => Order::STATUS_CANCELLED]);

        $check = $workflow->canTransitionTo($order, Order::STATUS_PAID);
        $this->assertFalse($check['allowed']);
    }

    public function test_shipping_override_is_logged(): void
    {
        $admin = $this->admin();
        $order = Order::factory()->create(['status' => Order::STATUS_PAID]);
        $shipment = OrderShipment::create([
            'order_id' => $order->id,
            'country_code' => 'US',
            'country_label' => 'US Shipment',
            'subtotal' => 50,
            'shipping_fee' => 10,
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->patch(route('admin.orders.shipping-override', $order), [
                'order_shipment_id' => $shipment->id,
                'shipping_override_amount' => 12.50,
                'shipping_override_carrier' => 'dhl',
                'shipping_override_notes' => 'Adjusted for weight.',
            ]);

        $response->assertRedirect();
        $shipment->refresh();
        $this->assertEquals(12.50, (float) $shipment->shipping_override_amount);
        $this->assertSame('dhl', $shipment->shipping_override_carrier);
        $this->assertNotNull($shipment->shipping_override_at);

        $log = OrderOperationLog::where('order_id', $order->id)->where('action', 'shipping_override')->first();
        $this->assertNotNull($log);
        $this->assertSame($shipment->id, $log->payload['order_shipment_id'] ?? null);
    }
}
