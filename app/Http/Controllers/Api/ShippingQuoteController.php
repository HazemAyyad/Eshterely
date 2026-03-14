<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Shipping\ShippingQuoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShippingQuoteController extends Controller
{
    /**
     * Temporary authenticated preview: validate input, return normalized quote structure.
     * POST /api/shipping/quote-preview
     */
    public function quotePreview(Request $request, ShippingQuoteService $quoteService): JsonResponse
    {
        $validated = $request->validate([
            'destination_country' => 'required|string|max:10',
            'carrier' => 'nullable|string|max:50',
            'warehouse_mode' => 'boolean',
            'weight' => 'required|numeric|min:0',
            'weight_unit' => 'nullable|string|in:kg,lb,lbs',
            'length' => 'required|numeric|min:0',
            'width' => 'required|numeric|min:0',
            'height' => 'required|numeric|min:0',
            'dimension_unit' => 'nullable|string|in:cm,in',
            'quantity' => 'nullable|integer|min:1',
        ]);

        $result = $quoteService->quote($validated);

        return response()->json([
            'success' => true,
            'quote' => $result->toArray(),
        ]);
    }
}
