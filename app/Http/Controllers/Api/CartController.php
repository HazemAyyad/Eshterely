<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CartItemResource;
use App\Http\Resources\DraftOrderResource;
use App\Models\CartItem;
use App\Services\CartItemReviewService;
use App\Services\DraftOrderService;
use App\Services\Shipping\CartShippingEstimateService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
class CartController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private CartShippingEstimateService $cartShippingEstimate,
        private CartItemReviewService $reviewService,
    ) {}

    /**
     * List active cart items (user's own, not attached to a draft order).
     * Imported items use stored pricing_snapshot and shipping_snapshot only; no recalculation on read.
     */
    public function index(Request $request): JsonResponse
    {
        $items = CartItem::where('user_id', $request->user()->id)
            ->whereNull('draft_order_id')
            ->get();

        return response()->json($items->map(fn (CartItem $i) => (new CartItemResource($i))->toArray($request)));
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
            'variation_text' => 'nullable|string|max:500',
            'weight' => 'nullable|numeric|min:0',
            'weight_unit' => 'nullable|string|max:10',
            'length' => 'nullable|numeric|min:0',
            'width' => 'nullable|numeric|min:0',
            'height' => 'nullable|numeric|min:0',
            'dimension_unit' => 'nullable|string|max:10',
            'destination_address_id' => [
                'nullable',
                'integer',
                Rule::exists('addresses', 'id')->where(fn ($q) => $q->where('user_id', $request->user()->id)),
            ],
        ]);

        $qty = (int) ($validated['quantity'] ?? 1);
        $qty = $qty < 1 ? 1 : $qty;

        $destAddrId = isset($validated['destination_address_id']) ? (int) $validated['destination_address_id'] : null;
        $destAddrId = $destAddrId > 0 ? $destAddrId : null;

        // Best-effort shipping estimate on add-to-cart (paste link / webview).
        // This uses the same config-driven shipping engine and may use fallback defaults
        // when product data is incomplete (estimated=true + missing_fields populated).
        $quote = $this->cartShippingEstimate->quoteForUser($request->user(), $validated, $qty, $destAddrId);

        $item = CartItem::create([
            'user_id' => $request->user()->id,
            'product_url' => $validated['url'],
            'name' => $validated['name'],
            'unit_price' => $validated['price'],
            'quantity' => $qty,
            'currency' => $validated['currency'] ?? 'USD',
            'image_url' => $validated['image_url'] ?? null,
            'store_key' => $validated['store_key'] ?? null,
            'store_name' => $validated['store_name'] ?? null,
            'product_id' => $validated['product_id'] ?? null,
            'country' => $validated['country'] ?? null,
            'source' => $validated['source'] ?? 'paste_link',
            'variation_text' => $validated['variation_text'] ?? null,
            'weight' => $validated['weight'] ?? null,
            'weight_unit' => $validated['weight_unit'] ?? null,
            'length' => $validated['length'] ?? null,
            'width' => $validated['width'] ?? null,
            'height' => $validated['height'] ?? null,
            'dimension_unit' => $validated['dimension_unit'] ?? null,
            'shipping_cost' => $quote['amount'] ?? null,
            'shipping_snapshot' => $quote !== [] ? $quote : null,
            'estimated' => (bool) ($quote['estimated'] ?? false),
            'missing_fields' => $quote['missing_fields'] ?? [],
            'carrier' => $quote['carrier'] ?? null,
            'pricing_mode' => $quote['pricing_mode'] ?? null,
            'needs_review' => $this->reviewService->snapshotNeedsReview(
                (bool) ($quote['estimated'] ?? false),
                $quote['missing_fields'] ?? [],
                $quote['carrier'] ?? null,
                $quote,
                null
            ),
        ]);

        return response()->json((new CartItemResource($item))->toArray($request), 201);
    }

    /**
     * Preview shipping for paste-link / webview before or without persisting a cart line.
     */
    public function estimateShipping(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => 'integer|min:1',
            'weight' => 'nullable|numeric|min:0',
            'weight_unit' => 'nullable|string|max:10',
            'length' => 'nullable|numeric|min:0',
            'width' => 'nullable|numeric|min:0',
            'height' => 'nullable|numeric|min:0',
            'dimension_unit' => 'nullable|string|max:10',
            'destination_address_id' => [
                'nullable',
                'integer',
                Rule::exists('addresses', 'id')->where(fn ($q) => $q->where('user_id', $request->user()->id)),
            ],
        ]);

        $qty = (int) ($validated['quantity'] ?? 1);
        $qty = $qty < 1 ? 1 : $qty;

        $destAddrId = isset($validated['destination_address_id']) ? (int) $validated['destination_address_id'] : null;
        $destAddrId = $destAddrId > 0 ? $destAddrId : null;

        $quote = $this->cartShippingEstimate->quoteForUser($request->user(), $validated, $qty, $destAddrId);

        if ($quote === []) {
            return response()->json([
                'available' => false,
                'message' => 'Add a default delivery address in the app to calculate shipping.',
            ]);
        }

        return response()->json([
            'available' => true,
            'shipping_cost' => (float) ($quote['amount'] ?? 0),
            'currency' => (string) ($quote['currency'] ?? 'USD'),
            'estimated' => (bool) ($quote['estimated'] ?? false),
            'missing_fields' => $quote['missing_fields'] ?? [],
            'destination_country' => $quote['destination_country'] ?? null,
            'destination_label' => $quote['destination_label'] ?? null,
            'destination_address_id' => $quote['destination_address_id'] ?? null,
            'snapshot' => $quote,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $item = CartItem::whereNull('draft_order_id')->findOrFail($id);
        $this->authorize('update', $item);

        $validated = $request->validate(['quantity' => 'required|integer|min:1']);

        if ($validated['quantity'] < 1) {
            $item->delete();
            return response()->json(['message' => 'Removed']);
        }

        $item->update(['quantity' => $validated['quantity']]);

        return response()->json((new CartItemResource($item->fresh()))->toArray($request));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $item = CartItem::whereNull('draft_order_id')->findOrFail($id);
        $this->authorize('delete', $item);
        $item->delete();

        return response()->json(['message' => 'Removed']);
    }

    public function clear(Request $request): JsonResponse
    {
        CartItem::where('user_id', $request->user()->id)->whereNull('draft_order_id')->delete();

        return response()->json(['message' => 'Cart cleared']);
    }

    /**
     * Create a draft order from the user's active cart.
     * Snapshots are copied only; no recalculation. Cart items are marked as attached to the draft.
     */
    public function createDraftOrder(Request $request, DraftOrderService $draftOrderService): JsonResponse
    {
        $items = CartItem::where('user_id', $request->user()->id)->whereNull('draft_order_id')->get();

        if ($items->isEmpty()) {
            return response()->json(['message' => 'Cart is empty.'], 422);
        }

        $draft = $draftOrderService->createFromCart($items);

        if ($draft === null) {
            return response()->json(['message' => 'Could not create draft order.'], 422);
        }

        return (new DraftOrderResource($draft))->response()->setStatusCode(201);
    }
}
