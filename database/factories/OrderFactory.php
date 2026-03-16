<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'order_number' => 'ZY-' . strtoupper(fake()->unique()->regexify('[A-Z0-9]{6}')),
            'origin' => 'multi_origin',
            'status' => 'in_transit',
            'placed_at' => now(),
            'total_amount' => fake()->randomFloat(2, 20, 300),
            'currency' => 'USD',
        ];
    }

    public function pendingPayment(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Order::STATUS_PENDING_PAYMENT,
            'placed_at' => null,
            'order_total_snapshot' => $attributes['total_amount'] ?? 0,
        ]);
    }
}
