<?php

namespace Database\Factories;

use App\Models\NotificationDispatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationDispatch>
 */
class NotificationDispatchFactory extends Factory
{
    protected $model = NotificationDispatch::class;

    public function definition(): array
    {
        return [
            'type' => NotificationDispatch::TYPE_INDIVIDUAL,
            'title' => fake()->sentence(3),
            'body' => fake()->sentence(8),
            'target_scope' => 'user',
            'user_id' => User::factory(),
            'order_id' => null,
            'shipment_id' => null,
            'send_status' => NotificationDispatch::STATUS_PENDING,
            'provider_response_summary' => null,
            'created_by' => null,
            'meta' => [],
        ];
    }
}

