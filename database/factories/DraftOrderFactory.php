<?php

namespace Database\Factories;

use App\Models\DraftOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DraftOrder>
 */
class DraftOrderFactory extends Factory
{
    protected $model = DraftOrder::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => DraftOrder::STATUS_DRAFT,
            'currency' => 'USD',
            'subtotal_snapshot' => 0,
            'shipping_total_snapshot' => 0,
            'service_fee_total_snapshot' => 0,
            'final_total_snapshot' => 0,
            'estimated' => false,
            'needs_review' => false,
            'review_state' => [],
            'notes' => [],
            'warnings' => [],
        ];
    }
}

