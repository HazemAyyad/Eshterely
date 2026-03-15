<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DraftOrderResource;
use App\Models\DraftOrder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DraftOrderController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): JsonResponse
    {
        $drafts = DraftOrder::where('user_id', $request->user()->id)
            ->with('items')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(DraftOrderResource::collection($drafts));
    }

    public function show(Request $request, DraftOrder $draft_order): JsonResponse
    {
        $this->authorize('view', $draft_order);
        $draft_order->load('items');

        return response()->json(new DraftOrderResource($draft_order));
    }
}
