<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DraftOrderResource;
use App\Http\Resources\OrderResource;
use App\Models\DraftOrder;
use App\Services\CheckoutReadinessService;
use App\Services\OrderFinalizationService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DraftOrderController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected CheckoutReadinessService $readinessService,
        protected OrderFinalizationService $finalizationService
    ) {}

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

    /**
     * POST /api/draft-orders/{draftOrder}/checkout
     * Validate ownership, evaluate readiness, create order from draft if ready.
     */
    public function checkout(Request $request, DraftOrder $draft_order): JsonResponse
    {
        $this->authorize('view', $draft_order);

        $readiness = $this->readinessService->evaluate($draft_order);

        if (! $readiness['ready_for_checkout']) {
            return response()->json([
                'message' => 'Draft order is not ready for checkout.',
                'ready_for_checkout' => false,
                'needs_review' => $readiness['needs_review'],
                'warnings' => $readiness['warnings'],
                'blocking_issues' => $readiness['blocking_issues'],
            ], 422);
        }

        $order = $this->finalizationService->createOrderFromDraft($draft_order);

        return (new OrderResource($order->load('shipments.lineItems')))
            ->response()
            ->setStatusCode(201);
    }
}
