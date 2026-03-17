<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CartItemResource;
use App\Http\Resources\DraftOrderResource;
use App\Models\CartItem;
use App\Services\CartItemReviewService;
use App\Services\DraftOrderService;
use App\Services\Shipping\ProductToShippingInputMapper;
use App\Services\Shipping\ShippingQuoteService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CartController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private ProductToShippingInputMapper $shippingInputMapper,
        private ShippingQuoteService $shippingQuoteService,
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
        ]);

        $qty = (int) ($validated['quantity'] ?? 1);
        $qty = $qty < 1 ? 1 : $qty;

        // Best-effort shipping estimate on add-to-cart (paste link / webview).
        // This uses the same config-driven shipping engine and may use fallback defaults
        // when product data is incomplete (estimated=true + missing_fields populated).
        $quote = $this->quoteShippingEstimateForCartItem($validated, $qty);

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
     * Build and compute an estimated shipping quote for a cart item input.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>  compatible with shipping_snapshot (amount, currency, notes, estimated, missing_fields...)
     */
    private function quoteShippingEstimateForCartItem(array $validated, int $quantity): array
    {
        // Shipping depends on user's default shipping address country.
        // If user has no default address, we cannot estimate accurately.
        $user = request()->user();
        $default = null;
        if ($user && method_exists($user, 'addresses')) {
            $default = $user->addresses()->where('is_default', true)->with('country')->first();
        }
        $destinationCountry = $default?->country?->code ?? null;
        if (is_string($destinationCountry)) {
            $destinationCountry = Str::upper(trim($destinationCountry));
        }
        if (! is_string($destinationCountry) || $destinationCountry === '') {
            return [];
        }

        $normalized = [
            'weight' => $validated['weight'] ?? null,
            'weight_unit' => $validated['weight_unit'] ?? null,
            'length' => $validated['length'] ?? null,
            'width' => $validated['width'] ?? null,
            'height' => $validated['height'] ?? null,
            'dimension_unit' => $validated['dimension_unit'] ?? null,
            'quantity' => $quantity,
        ];

        $overrides = [
            'quantity' => $quantity,
            'destination_country' => $destinationCountry,
        ];

        try {
            $mapped = $this->shippingInputMapper->fromNormalizedProduct($normalized, $overrides);
            $input = $mapped['input'] ?? null;
            if (! is_array($input)) {
                return [];
            }

            $result = $this->shippingQuoteService->quote($input);

            return [
                'carrier' => $result->carrier ?? 'auto',
                'pricing_mode' => $result->pricingMode ?? 'default',
                'warehouse_mode' => (bool) ($result->warehouseMode ?? false),
                'actual_weight' => (float) ($result->actualWeightKg ?? 0),
                'volumetric_weight' => (float) ($result->volumetricWeightKg ?? 0),
                'chargeable_weight' => (float) ($result->chargeableWeightKg ?? 0),
                'currency' => (string) ($result->currency ?? 'USD'),
                'amount' => (float) ($result->finalAmount ?? 0),
                'estimated' => (bool) ($mapped['estimated'] ?? false),
                'missing_fields' => $mapped['missing_fields'] ?? [],
                'notes' => $result->calculationNotes ?? [],
                'calculation_breakdown' => $result->calculationBreakdown ?? [],
            ];
        } catch (\Throwable $e) {
            return [];
        }
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
