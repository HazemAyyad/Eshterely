<?php

namespace Database\Factories;

use App\Models\ImportedProduct;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImportedProduct>
 */
class ImportedProductFactory extends Factory
{
    protected $model = ImportedProduct::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'source_url' => fake()->url(),
            'store_key' => 'unknown',
            'store_name' => fake()->company(),
            'country' => 'US',
            'title' => fake()->sentence(4),
            'image_url' => fake()->imageUrl(),
            'product_price' => 10.00,
            'product_currency' => 'USD',
            'package_info' => ['quantity' => 1],
            'shipping_quote_snapshot' => ['amount' => 5.00, 'currency' => 'USD', 'estimated' => false],
            'final_pricing_snapshot' => ['final_total' => 15.00, 'estimated' => false],
            'estimated' => false,
            'missing_fields' => [],
            'status' => ImportedProduct::STATUS_DRAFT,
        ];
    }
}

