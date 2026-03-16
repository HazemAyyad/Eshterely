<?php

namespace Tests\Feature;

use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderStatusKeyTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_resource_exposes_frontend_friendly_status_key(): void
    {
        $user = User::factory()->create();
        $order = Order::create([
            'user_id' => $user->id,
            'order_number' => 'ZY-TEST',
            'origin' => 'app',
            'status' => Order::STATUS_PENDING_PAYMENT,
            'total_amount' => 10,
            'currency' => 'USD',
            'needs_review' => false,
            'estimated' => false,
        ]);

        $payload = (new OrderResource($order))->toArray(request());
        $this->assertSame('pending_payment', $payload['status_key']);

        $order->update(['needs_review' => true]);
        $payload2 = (new OrderResource($order->fresh()))->toArray(request());
        $this->assertSame('pending_review', $payload2['status_key']);
    }
}

