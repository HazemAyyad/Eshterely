<?php

namespace Database\Factories;

use App\Enums\Payment\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function configure(): static
    {
        return $this->afterCreating(function (Payment $payment) {
            if ($payment->user_id === null && $payment->order_id) {
                $payment->update(['user_id' => $payment->order->user_id]);
            }
        });
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'order_id' => Order::factory(),
            'provider' => 'square',
            'currency' => 'USD',
            'amount' => fake()->randomFloat(2, 10, 500),
            'status' => PaymentStatus::Pending,
            'reference' => 'PAY-' . now()->format('Ymd') . '-' . strtoupper(fake()->unique()->regexify('[A-Z0-9]{6}')),
            'metadata' => null,
        ];
    }

    public function forOrder(Order $order): static
    {
        return $this->state(fn (array $attributes) => [
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'currency' => $order->currency ?? 'USD',
            'amount' => $order->total_amount,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => ['status' => PaymentStatus::Pending]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::Failed,
            'failure_code' => 'DECLINED',
            'failure_message' => 'Card declined',
        ]);
    }
}
