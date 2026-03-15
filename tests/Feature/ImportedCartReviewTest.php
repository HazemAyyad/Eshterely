<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ImportedCartReviewTest extends TestCase
{
    use RefreshDatabase;

    private function validConfirmPayload(array $overrides = []): array
    {
        return array_merge([
            'source_url' => 'https://example.com/product/123',
            'name' => 'Test Product',
            'price' => 29.99,
            'currency' => 'USD',
            'store_key' => 'amazon',
            'store_name' => 'Amazon',
            'country' => 'US',
            'quantity' => 1,
            'shipping_quote' => [
                'amount' => 10,
                'currency' => 'USD',
                'carrier' => 'dhl',
                'pricing_mode' => 'default',
                'estimated' => false,
                'missing_fields' => [],
                'notes' => [],
            ],
            'final_pricing' => [
                'product_price' => 29.99,
                'final_total' => 39.99,
                'carrier' => 'dhl',
                'estimated' => false,
                'notes' => [],
            ],
        ], $overrides);
    }

    public function test_imported_cart_items_include_review_metadata(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $payload = $this->validConfirmPayload();
        $confirm = $this->postJson('/api/imported-products/confirm', $payload);
        $confirm->assertStatus(201);
        $importedId = $confirm->json('data.id');
        $this->postJson("/api/imported-products/{$importedId}/add-to-cart")->assertStatus(201);

        $cartResponse = $this->getJson('/api/cart/');
        $cartResponse->assertStatus(200);
        $items = $cartResponse->json();
        $this->assertNotEmpty($items);
        $importedItem = collect($items)->firstWhere('imported_product_id', $importedId);
        $this->assertNotNull($importedItem);
        $this->assertEquals('imported', $importedItem['source_type']);
        $this->assertArrayHasKey('review_status', $importedItem);
        $this->assertArrayHasKey('needs_review', $importedItem);
        $this->assertArrayHasKey('estimated', $importedItem);
        $this->assertArrayHasKey('missing_fields', $importedItem);
        $this->assertArrayHasKey('pricing_snapshot', $importedItem);
        $this->assertArrayHasKey('shipping_snapshot', $importedItem);
        $this->assertArrayHasKey('carrier', $importedItem);
        $this->assertArrayHasKey('pricing_mode', $importedItem);
        $this->assertArrayHasKey('source_url', $importedItem);
        $this->assertArrayHasKey('source_store', $importedItem);
    }

    public function test_estimated_items_flagged_for_review(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $payload = $this->validConfirmPayload();
        $payload['shipping_quote']['estimated'] = true;
        $payload['shipping_quote']['missing_fields'] = ['weight'];
        $payload['final_pricing']['estimated'] = true;

        $confirm = $this->postJson('/api/imported-products/confirm', $payload);
        $confirm->assertStatus(201);
        $importedId = $confirm->json('data.id');
        $add = $this->postJson("/api/imported-products/{$importedId}/add-to-cart");
        $add->assertStatus(201);

        $add->assertJsonPath('cart_item.needs_review', true);
        $add->assertJsonPath('cart_item.estimated', true);
        $add->assertJsonPath('cart_item.missing_fields', ['weight']);

        $cartItem = CartItem::where('imported_product_id', $importedId)->first();
        $this->assertTrue($cartItem->needs_review);
        $this->assertTrue($cartItem->estimated);
    }

    public function test_clean_imported_item_remains_non_review(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $payload = $this->validConfirmPayload();
        $payload['shipping_quote']['estimated'] = false;
        $payload['shipping_quote']['missing_fields'] = [];
        $payload['final_pricing']['estimated'] = false;
        $payload['final_pricing']['final_total'] = 45.00;

        $confirm = $this->postJson('/api/imported-products/confirm', $payload);
        $importedId = $confirm->json('data.id');
        $add = $this->postJson("/api/imported-products/{$importedId}/add-to-cart");
        $add->assertStatus(201);

        $add->assertJsonPath('cart_item.needs_review', false);
        $add->assertJsonPath('cart_item.estimated', false);

        $cartItem = CartItem::where('imported_product_id', $importedId)->first();
        $this->assertFalse($cartItem->needs_review);
    }

    public function test_snapshot_data_remains_unchanged_on_cart_read(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $payload = $this->validConfirmPayload();
        $payload['final_pricing']['final_total'] = 99.99;
        $payload['shipping_quote']['amount'] = 15.50;

        $confirm = $this->postJson('/api/imported-products/confirm', $payload);
        $importedId = $confirm->json('data.id');
        $this->postJson("/api/imported-products/{$importedId}/add-to-cart")->assertStatus(201);

        $cartResponse = $this->getJson('/api/cart/');
        $items = $cartResponse->json();
        $item = collect($items)->firstWhere('imported_product_id', $importedId);
        $this->assertNotNull($item);
        $this->assertEquals(99.99, $item['pricing_snapshot']['final_total'] ?? null);
        $this->assertEquals(15.50, $item['shipping_snapshot']['amount'] ?? null);
        $this->assertEquals(29.99, $item['price']);
        $this->assertEquals(15.50, $item['shipping_cost']);
    }

    public function test_cart_api_remains_backward_compatible(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $pasteItem = CartItem::create([
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
        $first = $items[0];
        $this->assertEquals((string) $pasteItem->id, $first['id']);
        $this->assertEquals('https://store.com/item', $first['url']);
        $this->assertEquals('Paste link item', $first['name']);
        $this->assertEquals(15.0, $first['price']);
        $this->assertEquals(1, $first['quantity']);
        $this->assertEquals('paste_link', $first['source']);
        $this->assertNull($first['imported_product_id']);
        $this->assertEquals('paste_link', $first['source_type']);
        $this->assertFalse($first['needs_review']);
        $this->assertFalse($first['estimated']);
        $this->assertEquals([], $first['missing_fields']);
    }

    public function test_user_can_only_update_own_cart_item(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $item = CartItem::create([
            'user_id' => $owner->id,
            'product_url' => 'https://example.com/p',
            'name' => 'Item',
            'unit_price' => 10,
            'quantity' => 1,
            'currency' => 'USD',
        ]);

        Sanctum::actingAs($other);
        $response = $this->patchJson("/api/cart/items/{$item->id}", ['quantity' => 2]);
        $response->assertStatus(403);
    }

    public function test_user_can_only_delete_own_cart_item(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $item = CartItem::create([
            'user_id' => $owner->id,
            'product_url' => 'https://example.com/p',
            'name' => 'Item',
            'unit_price' => 10,
            'quantity' => 1,
            'currency' => 'USD',
        ]);

        Sanctum::actingAs($other);
        $response = $this->deleteJson("/api/cart/items/{$item->id}");
        $response->assertStatus(403);
        $this->assertDatabaseHas('cart_items', ['id' => $item->id]);
    }
}
