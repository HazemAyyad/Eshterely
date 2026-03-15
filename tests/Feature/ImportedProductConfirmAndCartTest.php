<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\ImportedProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ImportedProductConfirmAndCartTest extends TestCase
{
    use RefreshDatabase;

    private function validConfirmPayload(array $overrides = []): array
    {
        return array_merge([
            'source_url' => 'https://example.com/product/123',
            'name' => 'Test Product Title',
            'price' => 29.99,
            'currency' => 'USD',
            'image_url' => 'https://example.com/image.jpg',
            'store_key' => 'amazon',
            'store_name' => 'Amazon',
            'country' => 'US',
            'quantity' => 2,
            'shipping_quote' => [
                'amount' => 12.50,
                'currency' => 'USD',
                'carrier' => 'dhl',
                'pricing_mode' => 'default',
                'estimated' => false,
                'missing_fields' => [],
                'notes' => [],
            ],
            'final_pricing' => [
                'product_price' => 29.99,
                'product_currency' => 'USD',
                'shipping_amount' => 12.50,
                'service_fee' => 2.00,
                'markup_amount' => 4.20,
                'subtotal' => 72.48,
                'final_total' => 78.68,
                'carrier' => 'dhl',
                'pricing_mode' => 'default',
                'estimated' => false,
                'notes' => [],
            ],
            'extraction_source' => 'json_ld',
        ], $overrides);
    }

    public function test_confirm_requires_authentication(): void
    {
        $response = $this->postJson('/api/imported-products/confirm', $this->validConfirmPayload());
        $response->assertStatus(401);
    }

    public function test_user_can_confirm_imported_product_snapshot(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/imported-products/confirm', $this->validConfirmPayload());

        $response->assertStatus(201);
        $response->assertJsonPath('data.title', 'Test Product Title');
        $response->assertJsonPath('data.source_url', 'https://example.com/product/123');
        $response->assertJsonPath('data.product_price', 29.99);
        $response->assertJsonPath('data.status', 'draft');

        $this->assertDatabaseHas('imported_products', [
            'title' => 'Test Product Title',
            'source_url' => 'https://example.com/product/123',
            'product_price' => 29.99,
            'status' => ImportedProduct::STATUS_DRAFT,
        ]);
    }

    public function test_confirmed_imported_product_stores_snapshots(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $payload = $this->validConfirmPayload();
        $response = $this->postJson('/api/imported-products/confirm', $payload);

        $response->assertStatus(201);
        $id = $response->json('data.id');
        $imported = ImportedProduct::find($id);

        $this->assertNotNull($imported);
        $this->assertEquals($payload['shipping_quote'], $imported->shipping_quote_snapshot);
        $this->assertEquals($payload['final_pricing'], $imported->final_pricing_snapshot);
        $this->assertEquals(29.99, (float) $imported->product_price);
        $this->assertEquals('dhl', $imported->carrier);
    }

    public function test_user_can_add_confirmed_imported_product_to_cart(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $confirmResponse = $this->postJson('/api/imported-products/confirm', $this->validConfirmPayload());
        $confirmResponse->assertStatus(201);
        $importedId = $confirmResponse->json('data.id');

        $addResponse = $this->postJson("/api/imported-products/{$importedId}/add-to-cart");

        $addResponse->assertStatus(201);
        $addResponse->assertJsonPath('message', 'Added to cart');
        $addResponse->assertJsonPath('cart_item.name', 'Test Product Title');
        $addResponse->assertJsonPath('cart_item.source', 'imported');
        $addResponse->assertJsonPath('cart_item.quantity', 2);
        $addResponse->assertJsonPath('imported_product.id', $importedId);

        $imported = ImportedProduct::find($importedId);
        $this->assertEquals(ImportedProduct::STATUS_ADDED_TO_CART, $imported->status);

        $cartItem = CartItem::where('imported_product_id', $importedId)->first();
        $this->assertNotNull($cartItem);
        $this->assertEquals($user->id, $cartItem->user_id);
        $this->assertEquals(29.99, (float) $cartItem->unit_price);
        $this->assertEquals(12.50, (float) $cartItem->shipping_cost);
        $this->assertNotNull($cartItem->pricing_snapshot);
        $this->assertNotNull($cartItem->shipping_snapshot);
    }

    public function test_ownership_enforced_on_add_to_cart(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $imported = ImportedProduct::create([
            'user_id' => $owner->id,
            'source_url' => 'https://example.com/p',
            'title' => 'Product',
            'product_price' => 10,
            'product_currency' => 'USD',
            'status' => ImportedProduct::STATUS_DRAFT,
            'shipping_quote_snapshot' => ['amount' => 5, 'currency' => 'USD'],
            'final_pricing_snapshot' => ['final_total' => 15],
        ]);

        Sanctum::actingAs($other);
        $response = $this->postJson("/api/imported-products/{$imported->id}/add-to-cart");

        $response->assertStatus(403);
        $this->assertDatabaseMissing('cart_items', ['imported_product_id' => $imported->id]);
    }

    public function test_ownership_enforced_on_show(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $imported = ImportedProduct::create([
            'user_id' => $owner->id,
            'source_url' => 'https://example.com/p',
            'title' => 'Product',
            'product_price' => 10,
            'product_currency' => 'USD',
            'status' => ImportedProduct::STATUS_DRAFT,
        ]);

        Sanctum::actingAs($other);
        $response = $this->getJson("/api/imported-products/{$imported->id}");

        $response->assertStatus(403);
    }

    public function test_estimated_pricing_flags_preserved(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $payload = $this->validConfirmPayload();
        $payload['shipping_quote']['estimated'] = true;
        $payload['shipping_quote']['missing_fields'] = ['weight', 'length'];
        $payload['final_pricing']['estimated'] = true;

        $confirmResponse = $this->postJson('/api/imported-products/confirm', $payload);
        $confirmResponse->assertStatus(201);
        $confirmResponse->assertJsonPath('data.estimated', true);
        $confirmResponse->assertJsonPath('data.missing_fields', ['weight', 'length']);

        $importedId = $confirmResponse->json('data.id');
        $addResponse = $this->postJson("/api/imported-products/{$importedId}/add-to-cart");
        $addResponse->assertStatus(201);

        $cartItem = CartItem::where('imported_product_id', $importedId)->first();
        $this->assertTrue($cartItem->shipping_snapshot['estimated'] ?? false);
        $this->assertEquals(['weight', 'length'], $cartItem->shipping_snapshot['missing_fields'] ?? []);
    }

    public function test_cannot_add_to_cart_twice(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $confirmResponse = $this->postJson('/api/imported-products/confirm', $this->validConfirmPayload());
        $importedId = $confirmResponse->json('data.id');

        $this->postJson("/api/imported-products/{$importedId}/add-to-cart")->assertStatus(201);
        $second = $this->postJson("/api/imported-products/{$importedId}/add-to-cart");

        $second->assertStatus(422);
    }

    public function test_existing_cart_behavior_unchanged(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $existingItem = CartItem::create([
            'user_id' => $user->id,
            'product_url' => 'https://store.com/item',
            'name' => 'Paste link item',
            'unit_price' => 15.00,
            'quantity' => 1,
            'currency' => 'USD',
            'source' => CartItem::SOURCE_PASTE_LINK,
        ]);

        $indexResponse = $this->getJson('/api/cart/');
        $indexResponse->assertStatus(200);
        $items = $indexResponse->json();
        $this->assertCount(1, $items);
        $this->assertEquals('Paste link item', $items[0]['name']);
        $this->assertEquals('paste_link', $items[0]['source']);
        $this->assertNull($items[0]['imported_product_id']);
        $this->assertEquals('paste_link', $items[0]['source_type']);
        $this->assertFalse($items[0]['needs_review']);
    }

    public function test_confirm_validates_required_fields(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/imported-products/confirm', [
            'source_url' => 'not-a-url',
            'name' => '',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['source_url', 'name', 'price', 'shipping_quote', 'final_pricing']);
    }
}
