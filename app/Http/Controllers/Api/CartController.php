<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = CartItem::where('user_id', $request->user()->id)->get();

        return response()->json($items->map(fn ($i) => [
            'id' => (string) $i->id,
            'url' => $i->product_url,
            'name' => $i->name,
            'price' => (float) $i->unit_price,
            'quantity' => $i->quantity,
            'currency' => $i->currency,
            'image_url' => $i->image_url,
            'store_key' => $i->store_key,
            'store_name' => $i->store_name,
            'product_id' => $i->product_id,
            'country' => $i->country,
            'source' => $i->source ?? 'paste_link',
            'review_status' => $i->review_status ?? 'pending_review',
            'shipping_cost' => $i->shipping_cost ? (float) $i->shipping_cost : null,
        ]));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'url' => 'required|string',
            'name' => 'required|string',
            'price' => 'required|numeric|min:0',
            'quantity' => 'integer|min:1',
            'currency' => 'string|max:10',
            'image_url' => 'nullable|string',
            'store_key' => 'nullable|string',
            'store_name' => 'nullable|string',
            'product_id' => 'nullable|string',
            'country' => 'nullable|string',
            'source' => 'nullable|in:webview,paste_link',
        ]);

        $item = CartItem::create([
            'user_id' => $request->user()->id,
            'product_url' => $validated['url'],
            'name' => $validated['name'],
            'unit_price' => $validated['price'],
            'quantity' => $validated['quantity'] ?? 1,
            'currency' => $validated['currency'] ?? 'USD',
            'image_url' => $validated['image_url'] ?? null,
            'store_key' => $validated['store_key'] ?? null,
            'store_name' => $validated['store_name'] ?? null,
            'product_id' => $validated['product_id'] ?? null,
            'country' => $validated['country'] ?? null,
            'source' => $validated['source'] ?? 'paste_link',
        ]);

        return response()->json($item, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $item = CartItem::where('user_id', $request->user()->id)->findOrFail($id);

        $validated = $request->validate(['quantity' => 'required|integer|min:1']);

        if ($validated['quantity'] < 1) {
            $item->delete();
            return response()->json(['message' => 'Removed']);
        }

        $item->update(['quantity' => $validated['quantity']]);

        return response()->json($item);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        CartItem::where('user_id', $request->user()->id)->where('id', $id)->delete();

        return response()->json(['message' => 'Removed']);
    }

    public function clear(Request $request): JsonResponse
    {
        CartItem::where('user_id', $request->user()->id)->delete();

        return response()->json(['message' => 'Cart cleared']);
    }
}
