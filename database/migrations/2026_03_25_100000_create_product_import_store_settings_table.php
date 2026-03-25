<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_import_store_settings', function (Blueprint $table) {
            $table->id();
            $table->string('store_key')->unique();
            $table->string('display_name')->nullable();
            $table->boolean('is_enabled')->default(true);

            // Free lightweight providers
            $table->boolean('free_attempts_enabled')->default(true);
            $table->boolean('allow_ai_extraction')->default(true);

            // Playwright (heavy free fallback — requires separate Node.js service)
            $table->boolean('allow_playwright_fallback')->default(false);
            $table->integer('playwright_priority')->default(6)->comment('Position in attempt_order (1-based)');
            $table->integer('playwright_timeout_seconds')->default(30);

            // Paid scraper
            $table->boolean('allow_paid_fallback')->default(true);
            $table->string('paid_provider')->default('scraperapi');
            $table->integer('paid_provider_priority')->default(7);

            // Attempt order: JSON array of provider keys, e.g. ["structured_data","json_ld","open_graph","direct_html","ai_extraction","playwright","paid_scraper"]
            $table->json('attempt_order')->nullable();

            $table->integer('max_retries')->default(1);
            $table->integer('timeout_seconds')->default(45);
            $table->float('minimum_confidence')->default(0.5);
            $table->boolean('requires_manual_review_for_missing_specs')->default(true);

            $table->timestamps();
        });

        // Seed sensible defaults for known stores
        $defaults = [
            ['store_key' => 'amazon',      'display_name' => 'Amazon',      'allow_paid_fallback' => true,  'allow_ai_extraction' => true],
            ['store_key' => 'ebay',        'display_name' => 'eBay',        'allow_paid_fallback' => true,  'allow_ai_extraction' => true],
            ['store_key' => 'walmart',     'display_name' => 'Walmart',     'allow_paid_fallback' => true,  'allow_ai_extraction' => true],
            ['store_key' => 'aliexpress',  'display_name' => 'AliExpress',  'allow_paid_fallback' => true,  'allow_ai_extraction' => true],
            ['store_key' => 'etsy',        'display_name' => 'Etsy',        'allow_paid_fallback' => false, 'allow_ai_extraction' => true],
            ['store_key' => 'trendyol',    'display_name' => 'Trendyol',    'allow_paid_fallback' => false, 'allow_ai_extraction' => true],
            ['store_key' => 'noon',        'display_name' => 'Noon',        'allow_paid_fallback' => false, 'allow_ai_extraction' => true],
            ['store_key' => 'temu',        'display_name' => 'Temu',        'allow_paid_fallback' => true,  'allow_ai_extraction' => true],
            ['store_key' => 'shein',       'display_name' => 'SHEIN',       'allow_paid_fallback' => true,  'allow_ai_extraction' => true],
            ['store_key' => 'shopify',     'display_name' => 'Shopify',     'allow_paid_fallback' => false, 'allow_ai_extraction' => true],
            ['store_key' => 'woocommerce', 'display_name' => 'WooCommerce', 'allow_paid_fallback' => false, 'allow_ai_extraction' => true],
            ['store_key' => 'generic',     'display_name' => 'Generic',     'allow_paid_fallback' => false, 'allow_ai_extraction' => true],
        ];

        $defaultOrder = json_encode(['structured_data', 'json_ld', 'open_graph', 'direct_html', 'ai_extraction', 'playwright', 'paid_scraper']);
        $now = now();

        foreach ($defaults as $row) {
            \DB::table('product_import_store_settings')->insert(array_merge($row, [
                'is_enabled'          => true,
                'free_attempts_enabled' => true,
                'allow_playwright_fallback' => false,
                'playwright_priority' => 6,
                'playwright_timeout_seconds' => 30,
                'paid_provider'       => 'scraperapi',
                'paid_provider_priority' => 7,
                'attempt_order'       => $defaultOrder,
                'max_retries'         => 1,
                'timeout_seconds'     => 45,
                'minimum_confidence'  => 0.5,
                'requires_manual_review_for_missing_specs' => true,
                'created_at'          => $now,
                'updated_at'          => $now,
            ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_import_store_settings');
    }
};
