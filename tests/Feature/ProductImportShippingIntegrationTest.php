<?php

namespace Tests\Feature;

use App\Models\ShippingSetting;
use App\Models\User;
use App\Services\ProductExtractionService;
use App\Services\ProductPageFetcherService;
use App\Services\Shipping\ProductImportShippingQuoteService;
use App\Services\Shipping\ShippingPricingConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductImportShippingIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedShippingSettings();
    }

    private function seedShippingSettings(): void
    {
        $defaults = [
            ShippingPricingConfigService::KEY_VOLUMETRIC_DIVISOR => '5000',
            ShippingPricingConfigService::KEY_DEFAULT_CURRENCY => 'USD',
            ShippingPricingConfigService::KEY_MIN_SHIPPING_CHARGE => '0',
            ShippingPricingConfigService::KEY_WAREHOUSE_HANDLING_FEE => '0',
        ];
        foreach ($defaults as $key => $value) {
            if (! ShippingSetting::query()->where('key', $key)->exists()) {
                ShippingSetting::setValue($key, $value);
            }
        }
    }

    /** @return array<string, mixed> */
    private function normalizedProductWithShippingData(string $source = 'amazon_structured_api'): array
    {
        return [
            'name' => 'Test Product',
            'price' => 29.99,
            'currency' => 'USD',
            'image_url' => 'https://example.com/img.jpg',
            'store_key' => 'amazon',
            'store_name' => 'Amazon',
            'country' => 'USA',
            'canonical_url' => 'https://amazon.com/dp/B001',
            'extraction_source' => $source,
            'fetch_source' => 'scraperapi',
            'weight' => 1.5,
            'weight_unit' => 'kg',
            'length' => 20,
            'width' => 15,
            'height' => 10,
            'dimension_unit' => 'cm',
            'quantity' => 1,
        ];
    }

    /** @return array<string, mixed> */
    private function normalizedProductWithoutShippingData(string $source = 'json_ld'): array
    {
        return [
            'name' => 'Test Product',
            'price' => 19.99,
            'currency' => 'USD',
            'image_url' => null,
            'store_key' => 'unknown',
            'store_name' => 'Unknown',
            'country' => 'Unknown',
            'canonical_url' => 'https://example.com/product',
            'extraction_source' => $source,
        ];
    }

    public function test_import_response_includes_shipping_quote_when_sufficient_data_exists(): void
    {
        $product = $this->normalizedProductWithShippingData();
        $this->mock(ProductPageFetcherService::class, function ($mock) {
            $mock->shouldReceive('fetchHtml')->once()->andReturn(['html' => '<html></html>']);
        });
        $this->mock(ProductExtractionService::class, function ($mock) use ($product) {
            $mock->shouldReceive('extract')->once()->andReturn($product);
        });

        Sanctum::actingAs(User::factory()->create());
        $response = $this->postJson('/api/products/import-from-url', ['url' => 'https://amazon.com/dp/B001']);

        $response->assertStatus(200);
        $response->assertJsonPath('name', 'Test Product');
        $response->assertJsonPath('price', 29.99);
        $response->assertJsonStructure(['shipping_quote' => [
            'carrier',
            'warehouse_mode',
            'actual_weight',
            'volumetric_weight',
            'chargeable_weight',
            'currency',
            'amount',
            'estimated',
            'missing_fields',
            'notes',
        ]]);
        $sq = $response->json('shipping_quote');
        $this->assertFalse($sq['estimated']);
        $this->assertSame([], $sq['missing_fields']);
        $this->assertSame('USD', $sq['currency']);
        $this->assertIsNumeric($sq['amount']);
    }

    public function test_shipping_quote_works_regardless_of_extraction_source(): void
    {
        $product = $this->normalizedProductWithShippingData('json_ld');
        $product['extraction_source'] = 'json_ld';
        $this->mock(ProductPageFetcherService::class, function ($mock) {
            $mock->shouldReceive('fetchHtml')->once()->andReturn(['html' => '<html></html>']);
        });
        $this->mock(ProductExtractionService::class, function ($mock) use ($product) {
            $mock->shouldReceive('extract')->once()->andReturn($product);
        });

        Sanctum::actingAs(User::factory()->create());
        $response = $this->postJson('/api/products/import-from-url', ['url' => 'https://example.com/p']);

        $response->assertStatus(200);
        $response->assertJsonPath('extraction_source', 'json_ld');
        $response->assertJsonStructure(['shipping_quote']);
        $this->assertNotNull($response->json('shipping_quote'));
    }

    public function test_product_preview_succeeds_when_shipping_quote_is_null(): void
    {
        $product = $this->normalizedProductWithoutShippingData();
        $this->mock(ProductPageFetcherService::class, function ($mock) {
            $mock->shouldReceive('fetchHtml')->once()->andReturn(['html' => '<html></html>']);
        });
        $this->mock(ProductExtractionService::class, function ($mock) use ($product) {
            $mock->shouldReceive('extract')->once()->andReturn($product);
        });

        Sanctum::actingAs(User::factory()->create());
        $response = $this->postJson('/api/products/import-from-url', ['url' => 'https://example.com/product']);

        $response->assertStatus(200);
        $response->assertJsonPath('name', 'Test Product');
        $response->assertJsonPath('price', 19.99);
        $response->assertJsonPath('shipping_quote', null);
    }

    public function test_missing_dimensions_produce_estimated_quote(): void
    {
        $product = $this->normalizedProductWithoutShippingData();
        $product['weight'] = 2.0;
        $product['weight_unit'] = 'kg';
        // no length, width, height
        $this->mock(ProductPageFetcherService::class, function ($mock) {
            $mock->shouldReceive('fetchHtml')->once()->andReturn(['html' => '<html></html>']);
        });
        $this->mock(ProductExtractionService::class, function ($mock) use ($product) {
            $mock->shouldReceive('extract')->once()->andReturn($product);
        });

        Sanctum::actingAs(User::factory()->create());
        $response = $this->postJson('/api/products/import-from-url', ['url' => 'https://example.com/p']);

        $response->assertStatus(200);
        $sq = $response->json('shipping_quote');
        $this->assertNotNull($sq);
        $this->assertTrue($sq['estimated']);
        $this->assertNotEmpty($sq['missing_fields']);
        $missing = $sq['missing_fields'];
        $this->assertContains('length', $missing);
        $this->assertContains('width', $missing);
        $this->assertContains('height', $missing);
    }

    public function test_shipping_quote_uses_admin_config(): void
    {
        ShippingSetting::setValue(ShippingPricingConfigService::KEY_DEFAULT_CURRENCY, 'SAR');
        ShippingSetting::setValue(ShippingPricingConfigService::KEY_MIN_SHIPPING_CHARGE, '10');
        ShippingSetting::clearCache();

        $product = $this->normalizedProductWithShippingData();
        $this->mock(ProductPageFetcherService::class, function ($mock) {
            $mock->shouldReceive('fetchHtml')->once()->andReturn(['html' => '<html></html>']);
        });
        $this->mock(ProductExtractionService::class, function ($mock) use ($product) {
            $mock->shouldReceive('extract')->once()->andReturn($product);
        });

        Sanctum::actingAs(User::factory()->create());
        $response = $this->postJson('/api/products/import-from-url', ['url' => 'https://amazon.com/dp/B001']);

        $response->assertStatus(200);
        $sq = $response->json('shipping_quote');
        $this->assertNotNull($sq);
        $this->assertSame('SAR', $sq['currency']);
        $this->assertGreaterThanOrEqual(10, $sq['amount']);
    }

    public function test_shipping_quote_failure_returns_null_keeps_product_preview(): void
    {
        $product = $this->normalizedProductWithShippingData();
        $this->mock(ProductPageFetcherService::class, function ($mock) {
            $mock->shouldReceive('fetchHtml')->once()->andReturn(['html' => '<html></html>']);
        });
        $this->mock(ProductExtractionService::class, function ($mock) use ($product) {
            $mock->shouldReceive('extract')->once()->andReturn($product);
        });
        $this->mock(ProductImportShippingQuoteService::class, function ($mock) {
            $mock->shouldReceive('quoteFromProduct')->once()->andReturn(null);
        });

        Sanctum::actingAs(User::factory()->create());
        $response = $this->postJson('/api/products/import-from-url', ['url' => 'https://amazon.com/dp/B001']);

        $response->assertStatus(200);
        $response->assertJsonPath('name', 'Test Product');
        $response->assertJsonPath('shipping_quote', null);
    }
}
