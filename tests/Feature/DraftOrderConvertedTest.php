<?php

namespace Tests\Feature;

use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DraftOrderConvertedTest extends TestCase
{
    use RefreshDatabase;

    public function test_converted_draft_order_is_marked_converted_with_order_reference(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $draft = DraftOrder::create([
            'user_id' => $user->id,
            'status' => DraftOrder::STATUS_DRAFT,
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

        $response = $this->postJson("/api/draft-orders/{$draft->id}/checkout");
        $response->assertStatus(201);

        $draft->refresh();
        $this->assertSame(DraftOrder::STATUS_CONVERTED, $draft->status);
        $this->assertNotNull($draft->converted_order_id);
        $this->assertNotNull($draft->converted_at);

        $orderId = $response->json('data.id');
        $this->assertEquals((string) $draft->converted_order_id, $orderId);

        $order = Order::find($orderId);
        $this->assertNotNull($order);
        $this->assertEquals($draft->id, $order->draft_order_id);
    }

    public function test_converted_draft_is_no_longer_active(): void
    {
        $user = User::factory()->create();
        $draft = DraftOrder::create([
            'user_id' => $user->id,
            'status' => DraftOrder::STATUS_CONVERTED,
            'converted_order_id' => null,
            'converted_at' => now(),
            'currency' => 'USD',
            'final_total_snapshot' => 10,
        ]);

        $this->assertSame(DraftOrder::STATUS_CONVERTED, $draft->status);
        $this->assertNotSame(DraftOrder::STATUS_DRAFT, $draft->status);
    }
}
