<?php

namespace Tests\Feature;

use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\Order;
use App\Models\OrderLineItem;
use App\Models\User;
use App\Services\CheckoutReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DraftOrderCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_readiness_evaluation_blocks_when_needs_review(): void
    {
        $user = User::factory()->create();
        $draft = DraftOrder::create([
            'user_id' => $user->id,
            'status' => 'draft',
            'currency' => 'USD',
            'subtotal_snapshot' => 50,
            'shipping_total_snapshot' => 10,
            'service_fee_total_snapshot' => 2,
            'final_total_snapshot' => 62,
            'needs_review' => true,
        ]);
        DraftOrderItem::create([
            'draft_order_id' => $draft->id,
            'quantity' => 1,
            'product_snapshot' => ['name' => 'Test', 'unit_price' => 50, 'country' => 'US'],
            'pricing_snapshot' => ['subtotal' => 50, 'final_total' => 62],
            'review_metadata' => ['carrier' => 'dhl'],
        ]);

        $service = app(CheckoutReadinessService::class);
        $result = $service->evaluate($draft);

        $this->assertFalse($result['ready_for_checkout']);
        $this->assertTrue($result['needs_review']);
        $this->assertNotEmpty($result['blocking_issues']);
    }

    public function test_readiness_evaluation_blocks_when_any_item_estimated(): void
    {
        $user = User::factory()->create();
        $draft = DraftOrder::create([
            'user_id' => $user->id,
            'status' => 'draft',
            'currency' => 'USD',
            'subtotal_snapshot' => 50,
            'shipping_total_snapshot' => 10,
            'service_fee_total_snapshot' => 0,
            'final_total_snapshot' => 60,
            'needs_review' => false,
            'estimated' => true,
        ]);
        DraftOrderItem::create([
            'draft_order_id' => $draft->id,
            'quantity' => 1,
            'estimated' => true,
            'product_snapshot' => ['name' => 'Test', 'unit_price' => 50, 'country' => 'US'],
            'pricing_snapshot' => ['subtotal' => 50, 'final_total' => 60],
            'review_metadata' => ['carrier' => 'dhl'],
        ]);

        $service = app(CheckoutReadinessService::class);
        $result = $service->evaluate($draft);

        $this->assertFalse($result['ready_for_checkout']);
        $this->assertNotEmpty($result['blocking_issues']);
    }

    public function test_readiness_evaluation_blocks_when_missing_fields(): void
    {
        $user = User::factory()->create();
        $draft = DraftOrder::create([
            'user_id' => $user->id,
            'status' => 'draft',
            'currency' => 'USD',
            'subtotal_snapshot' => 50,
            'shipping_total_snapshot' => 10,
            'service_fee_total_snapshot' => 0,
            'final_total_snapshot' => 60,
            'needs_review' => false,
        ]);
        DraftOrderItem::create([
            'draft_order_id' => $draft->id,
            'quantity' => 1,
            'missing_fields' => ['weight'],
            'product_snapshot' => ['name' => 'Test', 'unit_price' => 50, 'country' => 'US'],
            'pricing_snapshot' => ['subtotal' => 50, 'final_total' => 60],
            'review_metadata' => ['carrier' => 'dhl'],
        ]);

        $service = app(CheckoutReadinessService::class);
        $result = $service->evaluate($draft);

        $this->assertFalse($result['ready_for_checkout']);
        $this->assertNotEmpty($result['blocking_issues']);
    }

    public function test_readiness_evaluation_blocks_when_carrier_unresolved(): void
    {
        $user = User::factory()->create();
        $draft = DraftOrder::create([
            'user_id' => $user->id,
            'status' => 'draft',
            'currency' => 'USD',
            'subtotal_snapshot' => 50,
            'shipping_total_snapshot' => 10,
            'service_fee_total_snapshot' => 0,
            'final_total_snapshot' => 60,
            'needs_review' => false,
        ]);
        DraftOrderItem::create([
            'draft_order_id' => $draft->id,
            'quantity' => 1,
            'product_snapshot' => ['name' => 'Test', 'unit_price' => 50, 'country' => 'US'],
            'pricing_snapshot' => ['subtotal' => 50, 'final_total' => 60],
            'review_metadata' => ['carrier' => 'auto'],
        ]);

        $service = app(CheckoutReadinessService::class);
        $result = $service->evaluate($draft);

        $this->assertFalse($result['ready_for_checkout']);
        $this->assertNotEmpty($result['blocking_issues']);
    }

    public function test_readiness_evaluation_passes_when_ready(): void
    {
        $user = User::factory()->create();
        $draft = DraftOrder::create([
            'user_id' => $user->id,
            'status' => 'draft',
            'currency' => 'USD',
            'subtotal_snapshot' => 50,
            'shipping_total_snapshot' => 10,
            'service_fee_total_snapshot' => 0,
            'final_total_snapshot' => 60,
            'needs_review' => false,
        ]);
        DraftOrderItem::create([
            'draft_order_id' => $draft->id,
            'quantity' => 1,
            'product_snapshot' => ['name' => 'Test', 'unit_price' => 50, 'country' => 'US'],
            'pricing_snapshot' => ['subtotal' => 50, 'final_total' => 60],
            'review_metadata' => ['carrier' => 'dhl'],
        ]);

        $service = app(CheckoutReadinessService::class);
        $result = $service->evaluate($draft);

        $this->assertTrue($result['ready_for_checkout']);
        $this->assertEmpty($result['blocking_issues']);
    }

    public function test_checkout_endpoint_returns_422_when_not_ready(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $draft = DraftOrder::create([
            'user_id' => $user->id,
            'status' => 'draft',
            'currency' => 'USD',
            'subtotal_snapshot' => 50,
            'shipping_total_snapshot' => 10,
            'service_fee_total_snapshot' => 0,
            'final_total_snapshot' => 60,
            'needs_review' => true,
        ]);
        DraftOrderItem::create([
            'draft_order_id' => $draft->id,
            'quantity' => 1,
            'product_snapshot' => ['name' => 'Test', 'country' => 'US'],
            'pricing_snapshot' => ['final_total' => 60],
            'review_metadata' => ['carrier' => 'dhl'],
        ]);

        $response = $this->postJson("/api/draft-orders/{$draft->id}/checkout");

        $response->assertStatus(422);
        $response->assertJsonPath('ready_for_checkout', false);
        $response->assertJsonPath('needs_review', true);
        $this->assertArrayHasKey('blocking_issues', $response->json());
    }

    public function test_checkout_endpoint_creates_order_when_ready(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $draft = DraftOrder::create([
            'user_id' => $user->id,
            'status' => 'draft',
            'currency' => 'USD',
            'subtotal_snapshot' => 50,
            'shipping_total_snapshot' => 10,
            'service_fee_total_snapshot' => 2,
            'final_total_snapshot' => 62,
            'needs_review' => false,
        ]);
        DraftOrderItem::create([
            'draft_order_id' => $draft->id,
            'quantity' => 2,
            'product_snapshot' => ['name' => 'Widget', 'unit_price' => 25, 'country' => 'US', 'store_name' => 'Store'],
            'pricing_snapshot' => ['subtotal' => 50, 'shipping_amount' => 10, 'final_total' => 62],
            'shipping_snapshot' => ['carrier' => 'dhl', 'amount' => 10],
            'review_metadata' => ['carrier' => 'dhl'],
        ]);

        $response = $this->postJson("/api/draft-orders/{$draft->id}/checkout");

        $response->assertStatus(201);
        $response->assertJsonPath('data.status', 'pending_payment');
        $this->assertEqualsWithDelta(62, (float) $response->json('data.total'), 0.01);
        $response->assertJsonPath('data.currency', 'USD');
        $response->assertJsonPath('data.order_number', fn ($v) => str_starts_with($v, 'ZY-'));

        $orderId = $response->json('data.id');
        $order = Order::find($orderId);
        $this->assertNotNull($order);
        $this->assertEquals($draft->id, $order->draft_order_id);
        $this->assertEquals(62, (float) $order->order_total_snapshot);
        $this->assertEquals(10, (float) $order->shipping_total_snapshot);
        $this->assertEquals(2, (float) $order->service_fee_snapshot);
    }

    public function test_snapshot_integrity_preserved_on_order_creation(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $draft = DraftOrder::create([
            'user_id' => $user->id,
            'status' => 'draft',
            'currency' => 'USD',
            'subtotal_snapshot' => 100,
            'shipping_total_snapshot' => 15,
            'service_fee_total_snapshot' => 3,
            'final_total_snapshot' => 118,
            'needs_review' => false,
        ]);
        DraftOrderItem::create([
            'draft_order_id' => $draft->id,
            'quantity' => 1,
            'product_snapshot' => ['name' => 'Snap Product', 'unit_price' => 100, 'country' => 'US'],
            'pricing_snapshot' => ['subtotal' => 100, 'final_total' => 118],
            'shipping_snapshot' => ['carrier' => 'ups'],
            'review_metadata' => ['carrier' => 'ups'],
        ]);

        $this->postJson("/api/draft-orders/{$draft->id}/checkout")->assertStatus(201);

        $order = Order::where('draft_order_id', $draft->id)->first();
        $this->assertNotNull($order);
        $this->assertEquals(118, (float) $order->order_total_snapshot);
        $this->assertEquals(15, (float) $order->shipping_total_snapshot);
        $this->assertEquals(3, (float) $order->service_fee_snapshot);

        $lineItem = OrderLineItem::where('order_shipment_id', $order->shipments->first()->id)->first();
        $this->assertNotNull($lineItem);
        $this->assertEquals('Snap Product', $lineItem->name);
        $this->assertNotNull($lineItem->product_snapshot);
        $this->assertNotNull($lineItem->pricing_snapshot);
    }

    public function test_checkout_enforces_ownership(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $draft = DraftOrder::create([
            'user_id' => $owner->id,
            'status' => 'draft',
            'currency' => 'USD',
            'subtotal_snapshot' => 50,
            'shipping_total_snapshot' => 10,
            'service_fee_total_snapshot' => 0,
            'final_total_snapshot' => 60,
            'needs_review' => false,
        ]);
        DraftOrderItem::create([
            'draft_order_id' => $draft->id,
            'quantity' => 1,
            'product_snapshot' => ['name' => 'Test', 'country' => 'US'],
            'pricing_snapshot' => ['final_total' => 60],
            'review_metadata' => ['carrier' => 'dhl'],
        ]);

        Sanctum::actingAs($other);
        $response = $this->postJson("/api/draft-orders/{$draft->id}/checkout");

        $response->assertStatus(403);
        $this->assertEquals(0, Order::where('draft_order_id', $draft->id)->count());
    }
}
