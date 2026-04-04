<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmImportedProductRequest;
use App\Http\Resources\ImportedProductResource;
use App\Models\CartItem;
use App\Models\ImportedProduct;
use App\Services\CartItemReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImportedProductController extends Controller
{
    /**
     * Confirm an imported product and persist a stable snapshot (product + shipping + final pricing).
     * Does not recalculate; stores exactly what the user confirmed.
     */
    public function confirm(ConfirmImportedProductRequest $request): JsonResponse
    {
        $attrs = $request->getSnapshotAttributes();
        $imported = ImportedProduct::create([
            'user_id' => $request->user()->id,
            'status' => ImportedProduct::STATUS_DRAFT,
            ...$attrs,
        ]);

        return (new ImportedProductResource($imported))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Add a confirmed imported product to the user's cart.
     * Preserves pricing and shipping snapshots; marks imported product as added_to_cart.
     */
    public function addToCart(Request $request, ImportedProduct $importedProduct, CartItemReviewService $reviewService): JsonResponse
    {
        if ($importedProduct->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($importedProduct->status !== ImportedProduct::STATUS_DRAFT) {
            return response()->json([
                'message' => 'Product has already been added to cart or is no longer in draft.',
            ], 422);
        }

        $shippingSnapshot = $importedProduct->shipping_quote_snapshot ?? [];
        $shippingAmount = (float) ($shippingSnapshot['amount'] ?? 0);
        $needsReview = $reviewService->importedProductNeedsReview($importedProduct);

        $shipSnap = is_array($importedProduct->shipping_quote_snapshot) ? $importedProduct->shipping_quote_snapshot : [];

        $weight = $this->packageValue($importedProduct, 'weight');
        if ($weight === null || (float) $weight <= 0) {
            $weight = isset($shipSnap['package_weight']) ? (float) $shipSnap['package_weight'] : null;
        }
        $length = $this->packageValue($importedProduct, 'length');
        if ($length === null || (float) $length <= 0) {
            $length = isset($shipSnap['package_length']) ? (float) $shipSnap['package_length'] : null;
        }
        $width = $this->packageValue($importedProduct, 'width');
        if ($width === null || (float) $width <= 0) {
            $width = isset($shipSnap['package_width']) ? (float) $shipSnap['package_width'] : null;
        }
        $height = $this->packageValue($importedProduct, 'height');
        if ($height === null || (float) $height <= 0) {
            $height = isset($shipSnap['package_height']) ? (float) $shipSnap['package_height'] : null;
        }

        $weightUnit = $importedProduct->package_info['weight_unit'] ?? null;
        if ($weightUnit === null || $weightUnit === '') {
            $weightUnit = isset($shipSnap['package_weight_unit']) ? (string) $shipSnap['package_weight_unit'] : null;
        }
        $dimUnit = $importedProduct->package_info['dimension_unit'] ?? null;
        if ($dimUnit === null || $dimUnit === '') {
            $dimUnit = isset($shipSnap['package_dimension_unit']) ? (string) $shipSnap['package_dimension_unit'] : null;
        }

        $cartItem = CartItem::create([
            'user_id' => $request->user()->id,
            'imported_product_id' => $importedProduct->id,
            'product_url' => $importedProduct->source_url,
            'name' => $importedProduct->title,
            'unit_price' => $importedProduct->product_price,
            'quantity' => (int) (($importedProduct->package_info['quantity'] ?? 1) ?: 1),
            'currency' => $importedProduct->product_currency,
            'image_url' => $importedProduct->image_url,
            'store_key' => $importedProduct->store_key,
            'store_name' => $importedProduct->store_name,
            'product_id' => null,
            'country' => $importedProduct->country,
            'source' => CartItem::SOURCE_IMPORTED,
            'review_status' => CartItem::REVIEW_STATUS_PENDING,
            'shipping_cost' => $shippingAmount > 0 ? $shippingAmount : null,
            'pricing_snapshot' => $importedProduct->final_pricing_snapshot,
            'shipping_snapshot' => $importedProduct->shipping_quote_snapshot,
            'weight' => $weight,
            'weight_unit' => $weightUnit,
            'length' => $length,
            'width' => $width,
            'height' => $height,
            'dimension_unit' => $dimUnit,
            'estimated' => $importedProduct->estimated,
            'missing_fields' => $importedProduct->missing_fields,
            'carrier' => $importedProduct->carrier,
            'pricing_mode' => $importedProduct->pricing_mode,
            'needs_review' => $needsReview,
        ]);

        $importedProduct->update(['status' => ImportedProduct::STATUS_ADDED_TO_CART]);

        return response()->json([
            'message' => 'Added to cart',
            'imported_product' => new ImportedProductResource($importedProduct->fresh()),
            'cart_item' => (new \App\Http\Resources\CartItemResource($cartItem))->toArray($request),
        ], 201);
    }

    /**
     * Show a single imported product (owned by the user).
     */
    public function show(Request $request, ImportedProduct $importedProduct): JsonResponse
    {
        if ($importedProduct->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json(new ImportedProductResource($importedProduct));
    }

    private function packageValue(ImportedProduct $imported, string $key): ?float
    {
        $info = $imported->package_info ?? [];
        $v = $info[$key] ?? null;
        if ($v === null || $v === '') {
            return null;
        }
        return (float) $v;
    }

}
