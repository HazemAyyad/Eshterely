<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DraftOrderFromCartTest extends TestCase
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
                'subtotal' => 39.99,
                'shipping_amount' => 10,
                'service_fee' => 1.5,
                'final_total' => 41.49,
                'carrier' => 'dhl',
                'estimated' => false,
                'notes' => [],
            ],
        ], $overrides);
    }

    public function test_draft_order_creation_from_cart(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $confirm = $this->postJson('/api/imported-products/confirm', $this->validConfirmPayload());
        $confirm->assertStatus(201);
        $importedId = $confirm->json('data.id') ?? $confirm->json('id');
        $this->postJson("/api/imported-products/{$importedId}/add-to-cart")->assertStatus(201);

        $response = $this->postJson('/api/cart/create-draft-order');
        $response->assertStatus(201);

        $response->assertJsonPath('data.status', 'draft');
        $response->assertJsonPath('data.currency', 'USD');
        $response->assertJsonPath('data.estimated', false);
        $response->assertJsonPath('data.needs_review', false);
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('items', $data);
        $items = $data['items'] ?? [];
        $this->assertNotEmpty($items);
        $this->assertCount(1, $items);

        $draftId = $data['id'];
        $this->assertDatabaseHas('draft_orders', ['id' => $draftId, 'user_id' => $user->id]);
        $this->assertDatabaseHas('draft_order_items', ['draft_order_id' => $draftId]);
    }

    public function test_snapshot_data_preserved_in_draft_order_items(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $payload = $this->validConfirmPayload();
        $payload['final_pricing']['final_total'] = 99.99;
        $payload['final_pricing']['subtotal'] = 89.99;
        $payload['shipping_quote']['amount'] = 15.50;

        $confirm = $this->postJson('/api/imported-products/confirm', $payload);
        $importedId = $confirm->json('data.id') ?? $confirm->json('id');
        $this->postJson("/api/imported-products/{$importedId}/add-to-cart")->assertStatus(201);

        $createResponse = $this->postJson('/api/cart/create-draft-order');
        $createResponse->assertStatus(201);

        $draftData = $createResponse->json('data') ?? $createResponse->json();
        $draftItem = $draftData['items'][0] ?? null;
        $this->assertNotNull($draftItem);
        $this->assertEquals(99.99, $draftItem['pricing_snapshot']['final_total'] ?? null);
        $this->assertEquals(89.99, $draftItem['pricing_snapshot']['subtotal'] ?? null);
        $this->assertEquals(15.50, $draftItem['shipping_snapshot']['amount'] ?? null);
        $this->assertArrayHasKey('product_snapshot', $draftItem);
        $this->assertEquals('Test Product', $draftItem['product_snapshot']['name'] ?? null);
    }

    public function test_review_flags_propagate_to_draft_order(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $payload = $this->validConfirmPayload();
        $payload['shipping_quote']['estimated'] = true;
        $payload['shipping_quote']['missing_fields'] = ['weight'];
        $payload['final_pricing']['estimated'] = true;

        $confirm = $this->postJson('/api/imported-products/confirm', $payload);
        $importedId = $confirm->json('data.id') ?? $confirm->json('id');
        $this->postJson("/api/imported-products/{$importedId}/add-to-cart")->assertStatus(201);

        $response = $this->postJson('/api/cart/create-draft-order');
        $response->assertStatus(201);
        $response->assertJsonPath('data.needs_review', true);
        $response->assertJsonPath('data.estimated', true);

        $draftId = $response->json('data.id') ?? $response->json('id');
        $draft = DraftOrder::find($draftId);
        $this->assertTrue($draft->needs_review);
        $this->assertTrue($draft->estimated);
    }

    public function test_estimated_flags_propagate_on_draft_order_items(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $payload = $this->validConfirmPayload();
        $payload['final_pricing']['estimated'] = true;
        $payload['shipping_quote']['estimated'] = true;

        $confirm = $this->postJson('/api/imported-products/confirm', $payload);
        $importedId = $confirm->json('data.id') ?? $confirm->json('id');
        $this->postJson("/api/imported-products/{$importedId}/add-to-cart")->assertStatus(201);

        $response = $this->postJson('/api/cart/create-draft-order');
        $response->assertStatus(201);
        $data = $response->json('data') ?? $response->json();
        $items = $data['items'] ?? [];
        $this->assertNotEmpty($items);
        $item = $items[0];
        $this->assertTrue($item['estimated'] ?? false, 'Draft order item should have estimated=true when cart item was estimated');
        $this->assertArrayHasKey('review_metadata', $item);
        $this->assertTrue($item['review_metadata']['estimated'] ?? false);
    }

    public function test_cart_post_conversion_items_attached_to_draft(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $confirm = $this->postJson('/api/imported-products/confirm', $this->validConfirmPayload());
        $importedId = $confirm->json('data.id') ?? $confirm->json('id');
        $this->postJson("/api/imported-products/{$importedId}/add-to-cart")->assertStatus(201);

        $cartBefore = $this->getJson('/api/cart/');
        $cartBefore->assertStatus(200);
        $this->assertCount(1, $cartBefore->json());

        $createResponse = $this->postJson('/api/cart/create-draft-order');
        $createResponse->assertStatus(201);

        $cartAfter = $this->getJson('/api/cart/');
        $cartAfter->assertStatus(200);
        $this->assertCount(0, $cartAfter->json(), 'Active cart should be empty after draft order creation');

        $cartItem = CartItem::where('user_id', $user->id)->first();
        $this->assertNotNull($cartItem);
        $this->assertNotNull($cartItem->draft_order_id);
        $draftId = $createResponse->json('data.id') ?? $createResponse->json('id');
        $this->assertEquals($draftId, (string) $cartItem->draft_order_id);
    }

    public function test_empty_cart_returns_422(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/cart/create-draft-order');
        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Cart is empty.');
    }

    public function test_create_draft_order_only_uses_authenticated_user_cart(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        CartItem::create([
            'user_id' => $owner->id,
            'product_url' => 'https://example.com/p',
            'name' => 'Owner Item',
            'unit_price' => 10,
            'quantity' => 1,
            'currency' => 'USD',
        ]);

        Sanctum::actingAs($other);
        $response = $this->postJson('/api/cart/create-draft-order');
        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Cart is empty.');

        $ownerItem = CartItem::where('user_id', $owner->id)->first();
        $this->assertNotNull($ownerItem);
        $this->assertNull($ownerItem->draft_order_id, 'Owner cart item must not be attached to any draft');
    }

    public function test_user_can_only_access_own_draft_orders(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $draft = DraftOrder::create([
            'user_id' => $owner->id,
            'status' => DraftOrder::STATUS_DRAFT,
            'currency' => 'USD',
            'subtotal_snapshot' => 10,
            'shipping_total_snapshot' => 5,
            'service_fee_total_snapshot' => 0,
            'final_total_snapshot' => 15,
        ]);

        Sanctum::actingAs($other);
        $response = $this->getJson("/api/draft-orders/{$draft->id}");
        $response->assertStatus(403);
    }

    public function test_user_can_list_and_show_own_draft_orders(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $draft = DraftOrder::create([
            'user_id' => $user->id,
            'status' => DraftOrder::STATUS_DRAFT,
            'currency' => 'USD',
            'subtotal_snapshot' => 20,
            'shipping_total_snapshot' => 8,
            'service_fee_total_snapshot' => 2,
            'final_total_snapshot' => 30,
        ]);
        DraftOrderItem::create([
            'draft_order_id' => $draft->id,
            'quantity' => 1,
            'product_snapshot' => ['name' => 'Test'],
            'pricing_snapshot' => ['final_total' => 30],
        ]);

        $indexResponse = $this->getJson('/api/draft-orders/');
        $indexResponse->assertStatus(200);
        $list = $indexResponse->json('data') ?? $indexResponse->json();
        $this->assertNotEmpty($list);
        $first = is_array($list) && isset($list[0]) ? $list[0] : $list;
        $this->assertEquals((string) $draft->id, $first['id'] ?? null);

        $showResponse = $this->getJson("/api/draft-orders/{$draft->id}");
        $showResponse->assertStatus(200);
        $showData = $showResponse->json('data') ?? $showResponse->json();
        $this->assertEquals((string) $draft->id, $showData['id'] ?? null);
        $this->assertEqualsWithDelta(30, (float) ($showData['final_total'] ?? 0), 0.01);
        $this->assertCount(1, $showData['items'] ?? []);
    }
}
