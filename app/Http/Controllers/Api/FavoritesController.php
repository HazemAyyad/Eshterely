<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Favorite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FavoritesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = Favorite::where('user_id', $request->user()->id)->get();

        return response()->json($items->map(fn ($f) => [
            'id' => (string) $f->id,
            'source_key' => $f->source_key,
            'source_label' => $f->source_label ?? 'FOUND ON ' . strtoupper($f->source_key),
            'title' => $f->title,
            'price' => (float) $f->price,
            'currency' => $f->currency ?? 'USD',
            'price_drop' => $f->price_drop ? (float) $f->price_drop : null,
            'tracking_on' => $f->tracking_on ?? true,
            'stock_status' => $f->stock_status ?? 'in_stock',
            'stock_label' => $f->stock_label ?? 'In Stock',
            'image_url' => $f->image_url,
        ]));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source_key' => 'required|string',
            'source_label' => 'nullable|string',
            'title' => 'required|string',
            'price' => 'required|numeric|min:0',
            'currency' => 'string|max:10',
            'image_url' => 'nullable|string',
            'product_url' => 'nullable|string',
        ]);

        $favorite = Favorite::create([
            'user_id' => $request->user()->id,
            'source_key' => $validated['source_key'],
            'source_label' => $validated['source_label'] ?? 'FOUND ON ' . strtoupper($validated['source_key']),
            'title' => $validated['title'],
            'price' => $validated['price'],
            'currency' => $validated['currency'] ?? 'USD',
            'image_url' => $validated['image_url'] ?? null,
            'product_url' => $validated['product_url'] ?? null,
            'stock_status' => 'in_stock',
            'stock_label' => 'In Stock',
            'tracking_on' => true,
        ]);

        return response()->json($favorite, 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        Favorite::where('user_id', $request->user()->id)->where('id', $id)->delete();

        return response()->json(['message' => 'Removed']);
    }
}
