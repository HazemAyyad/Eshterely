<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Order;
use App\Models\OrderOperationLog;
use App\Models\OrderShipment;
use App\Models\OrderShipmentEvent;
use App\Models\Payment;
use App\Enums\Payment\PaymentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipmentOperationsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Admin
    {
        return Admin::factory()->create();
    }

    private function paidOrderWithShipment(array $orderAttrs = []): array
    {
        $order = Order::factory()->create(array_merge(['status' => Order::STATUS_IN_TRANSIT], $orderAttrs));
        Payment::factory()->forOrder($order)->paid()->create();
        $shipment = OrderShipment::create([
            'order_id' => $order->id,
            'country_code' => 'US',
            'country_label' => 'US Shipment',
            'subtotal' => 50,
            'shipping_fee' => 10,
        ]);
        return ['order' => $order, 'shipment' => $shipment];
    }

    public function test_admin_can_assign_carrier(): void
    {
        $admin = $this->admin();
        ['order' => $order, 'shipment' => $shipment] = $this->paidOrderWithShipment();

        $response = $this->actingAs($admin, 'admin')
            ->patch(route('admin.orders.shipments.update', [$order, $shipment]), [
                'carrier' => 'DHL',
            ]);

        $response->assertRedirect();
        $shipment->refresh();
        $this->assertSame('DHL', $shipment->carrier);

        $log = OrderOperationLog::where('order_id', $order->id)
            ->where('action', OrderOperationLog::ACTION_SHIPMENT_CARRIER_CHANGED)->first();
        $this->assertNotNull($log);
        $this->assertSame($shipment->id, $log->payload['order_shipment_id'] ?? null);
        $this->assertSame('DHL', $log->payload['carrier'] ?? null);
    }

    public function test_admin_can_assign_tracking_number(): void
    {
        $admin = $this->admin();
        ['order' => $order, 'shipment' => $shipment] = $this->paidOrderWithShipment();

        $response = $this->actingAs($admin, 'admin')
            ->patch(route('admin.orders.shipments.update', [$order, $shipment]), [
                'tracking_number' => '1Z999AA10123456784',
            ]);

        $response->assertRedirect();
        $shipment->refresh();
        $this->assertSame('1Z999AA10123456784', $shipment->tracking_number);

        $log = OrderOperationLog::where('order_id', $order->id)
            ->where('action', OrderOperationLog::ACTION_SHIPMENT_TRACKING_ASSIGNED)->first();
        $this->assertNotNull($log);
        $this->assertSame('1Z999AA10123456784', $log->payload['tracking_number'] ?? null);
    }

    public function test_admin_can_append_shipment_event(): void
    {
        $admin = $this->admin();
        ['order' => $order, 'shipment' => $shipment] = $this->paidOrderWithShipment();

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.orders.shipments.events.store', [$order, $shipment]), [
                'event_type' => OrderShipmentEvent::TYPE_OUT_FOR_DELIVERY,
                'event_label' => 'Out for delivery',
                'notes' => 'Driver dispatched',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('order_shipment_events', [
            'order_shipment_id' => $shipment->id,
            'event_type' => OrderShipmentEvent::TYPE_OUT_FOR_DELIVERY,
        ]);

        $log = OrderOperationLog::where('order_id', $order->id)
            ->where('action', OrderOperationLog::ACTION_SHIPMENT_EVENT_ADDED)->first();
        $this->assertNotNull($log);
        $this->assertSame(OrderShipmentEvent::TYPE_OUT_FOR_DELIVERY, $log->payload['event_type'] ?? null);
    }

    public function test_delivered_shipment_updates_order_status_when_all_delivered(): void
    {
        $admin = $this->admin();
        ['order' => $order, 'shipment' => $shipment] = $this->paidOrderWithShipment();
        $this->assertSame(Order::STATUS_IN_TRANSIT, $order->status);

        $response = $this->actingAs($admin, 'admin')
            ->patch(route('admin.orders.shipments.delivered', [$order, $shipment]));

        $response->assertRedirect();
        $order->refresh();
        $shipment->refresh();
        $this->assertSame(Order::STATUS_DELIVERED, $order->status);
        $this->assertNotNull($order->delivered_at);
        $this->assertNotNull($shipment->delivered_at);
        $this->assertSame('delivered', $shipment->shipment_status);

        $log = OrderOperationLog::where('order_id', $order->id)
            ->where('action', OrderOperationLog::ACTION_SHIPMENT_DELIVERED)->first();
        $this->assertNotNull($log);
    }

    public function test_order_stays_not_delivered_until_all_shipments_delivered(): void
    {
        $admin = $this->admin();
        $order = Order::factory()->create(['status' => Order::STATUS_IN_TRANSIT]);
        Payment::factory()->forOrder($order)->paid()->create();
        $ship1 = OrderShipment::create([
            'order_id' => $order->id,
            'country_code' => 'US',
            'country_label' => 'US',
            'subtotal' => 30,
            'shipping_fee' => 5,
        ]);
        $ship2 = OrderShipment::create([
            'order_id' => $order->id,
            'country_code' => 'UK',
            'country_label' => 'UK',
            'subtotal' => 20,
            'shipping_fee' => 8,
        ]);

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.orders.shipments.delivered', [$order, $ship1]));

        $order->refresh();
        $this->assertSame(Order::STATUS_IN_TRANSIT, $order->status);

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.orders.shipments.delivered', [$order, $ship2]));

        $order->refresh();
        $this->assertSame(Order::STATUS_DELIVERED, $order->status);
    }

    public function test_invalid_order_transition_to_delivered_blocked_by_workflow(): void
    {
        $admin = $this->admin();
        // Order in STATUS_PAID cannot jump to DELIVERED; workflow only allows DELIVERED from IN_TRANSIT
        $order = Order::factory()->create(['status' => Order::STATUS_PAID]);
        Payment::factory()->forOrder($order)->paid()->create();
        $shipment = OrderShipment::create([
            'order_id' => $order->id,
            'country_code' => 'US',
            'country_label' => 'US',
            'subtotal' => 50,
            'shipping_fee' => 10,
        ]);

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.orders.shipments.delivered', [$order, $shipment]));

        $order->refresh();
        $shipment->refresh();
        $this->assertNotNull($shipment->delivered_at);
        $this->assertSame('delivered', $shipment->shipment_status);
        // Order stays PAID because transition PAID -> DELIVERED is not allowed
        $this->assertSame(Order::STATUS_PAID, $order->status);
        $this->assertNull($order->delivered_at);
    }

    public function test_shipment_operations_generate_audit_logs(): void
    {
        $admin = $this->admin();
        ['order' => $order, 'shipment' => $shipment] = $this->paidOrderWithShipment();

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.orders.shipments.update', [$order, $shipment]), [
                'carrier' => 'FedEx',
                'tracking_number' => '123456789',
                'shipment_status' => 'in_transit',
            ]);

        $actions = OrderOperationLog::where('order_id', $order->id)
            ->whereIn('action', [
                OrderOperationLog::ACTION_SHIPMENT_CARRIER_CHANGED,
                OrderOperationLog::ACTION_SHIPMENT_TRACKING_ASSIGNED,
                OrderOperationLog::ACTION_SHIPMENT_STATUS_UPDATED,
            ])->pluck('action')->toArray();

        $this->assertContains(OrderOperationLog::ACTION_SHIPMENT_CARRIER_CHANGED, $actions);
        $this->assertContains(OrderOperationLog::ACTION_SHIPMENT_TRACKING_ASSIGNED, $actions);
        $this->assertContains(OrderOperationLog::ACTION_SHIPMENT_STATUS_UPDATED, $actions);
    }

    public function test_shipping_override_remains_traceable(): void
    {
        $admin = $this->admin();
        $order = Order::factory()->create(['status' => Order::STATUS_PAID]);
        $shipment = OrderShipment::create([
            'order_id' => $order->id,
            'country_code' => 'US',
            'country_label' => 'US',
            'subtotal' => 50,
            'shipping_fee' => 10,
        ]);

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.orders.shipping-override', $order), [
                'order_shipment_id' => $shipment->id,
                'shipping_override_amount' => 15.00,
                'shipping_override_carrier' => 'override_carrier',
                'shipping_override_notes' => 'Override for shipment preparation',
            ]);

        $log = OrderOperationLog::where('order_id', $order->id)
            ->where('action', OrderOperationLog::ACTION_SHIPPING_OVERRIDE)->first();
        $this->assertNotNull($log);
        $this->assertSame($shipment->id, $log->payload['order_shipment_id']);
        $this->assertEquals(15.00, $log->payload['shipping_override_amount']);
        $this->assertSame('override_carrier', $log->payload['shipping_override_carrier']);
        $shipment->refresh();
        $this->assertEquals(15.00, (float) $shipment->shipping_override_amount);
        $this->assertNotNull($shipment->shipping_override_at);
    }

    public function test_shipment_show_rejects_wrong_order(): void
    {
        $admin = $this->admin();
        $order1 = Order::factory()->create();
        $order2 = Order::factory()->create();
        $shipment = OrderShipment::create([
            'order_id' => $order1->id,
            'country_code' => 'US',
            'country_label' => 'US',
            'subtotal' => 50,
            'shipping_fee' => 10,
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->patch(route('admin.orders.shipments.update', [$order2, $shipment]), [
                'carrier' => 'DHL',
            ]);

        $response->assertStatus(404);
    }
}
