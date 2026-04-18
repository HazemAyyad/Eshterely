<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentLaunchResource;
use App\Http\Resources\PurchaseAssistantRequestResource;
use App\Models\Order;
use App\Models\PurchaseAssistantRequest;
use App\Services\Activity\UserActivityLogger;
use App\Support\PurchaseAssistantStoreDisplayName;
use App\Support\UserActivityAction;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PurchaseAssistantRequestController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected OrderPaymentController $orderPaymentController,
        protected UserActivityLogger $activityLogger
    ) {}

    public function index(Request $request): JsonResponse
    {
        $items = PurchaseAssistantRequest::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return response()->json(PurchaseAssistantRequestResource::collection($items));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source_url' => 'required|url|max:2000',
            'title' => 'nullable|string|max:500',
            'details' => 'nullable|string|max:10000',
            'quantity' => 'nullable|integer|min:1|max:999',
            'variant_details' => 'nullable|string|max:2000',
            'customer_estimated_price' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:10',
            'images' => 'nullable|array|max:8',
            'images.*' => 'file|image|max:12288',
        ]);

        $url = $validated['source_url'];
        $host = parse_url($url, PHP_URL_HOST);
        $domain = is_string($host) ? $host : null;

        $paths = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $paths[] = Storage::url($file->store('purchase-assistant', 'public'));
            }
        }

        $req = PurchaseAssistantRequest::create([
            'user_id' => $request->user()->id,
            'source_url' => $url,
            'source_domain' => $domain,
            'store_display_name' => PurchaseAssistantStoreDisplayName::fromSourceUrl($url),
            'title' => $validated['title'] ?? null,
            'details' => $validated['details'] ?? null,
            'quantity' => $validated['quantity'] ?? 1,
            'variant_details' => $validated['variant_details'] ?? null,
            'customer_estimated_price' => $validated['customer_estimated_price'] ?? null,
            'currency' => $validated['currency'] ?? 'USD',
            'image_paths' => $paths,
            'status' => PurchaseAssistantRequest::STATUS_SUBMITTED,
            'origin' => PurchaseAssistantRequest::ORIGIN_PURCHASE_ASSISTANT,
        ]);

        $this->activityLogger->log(
            $request->user(),
            UserActivityAction::PA_REQUEST_CREATED,
            'Purchase Assistant request #'.$req->id.' submitted',
            null,
            [
                'purchase_assistant_request_id' => $req->id,
                'source_url' => $url,
            ],
            $request
        );

        return (new PurchaseAssistantRequestResource($req))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, PurchaseAssistantRequest $purchaseAssistantRequest): PurchaseAssistantRequestResource
    {
        $this->authorize('view', $purchaseAssistantRequest);

        return new PurchaseAssistantRequestResource($purchaseAssistantRequest);
    }

    /**
     * POST /api/purchase-assistant-requests/{purchaseAssistantRequest}/start-payment
     * Delegates to the same rules as POST /api/orders/{order}/start-payment (checkout payment mode + wallet/gateway).
     */
    public function startPayment(Request $request, PurchaseAssistantRequest $purchaseAssistantRequest): PaymentLaunchResource|JsonResponse
    {
        $this->authorize('view', $purchaseAssistantRequest);

        if ($purchaseAssistantRequest->status !== PurchaseAssistantRequest::STATUS_AWAITING_CUSTOMER_PAYMENT) {
            return response()->json([
                'message' => 'This request is not awaiting payment.',
                'error_key' => 'not_awaiting_payment',
                'errors' => [],
                'status' => 422,
            ], 422);
        }

        $orderId = $purchaseAssistantRequest->converted_order_id;
        if ($orderId === null) {
            return response()->json([
                'message' => 'No order linked to this request.',
                'error_key' => 'missing_order',
                'errors' => [],
                'status' => 422,
            ], 422);
        }

        $order = Order::findOrFail($orderId);
        if ($order->user_id !== $request->user()->id) {
            abort(404);
        }

        $response = $this->orderPaymentController->startPayment($request, $order);

        if ($response instanceof PaymentLaunchResource) {
            $this->activityLogger->log(
                $request->user(),
                UserActivityAction::PA_PAYMENT_STARTED,
                'Purchase Assistant payment started (request #'.$purchaseAssistantRequest->id.')',
                null,
                [
                    'purchase_assistant_request_id' => $purchaseAssistantRequest->id,
                    'order_id' => $orderId,
                ],
                $request
            );

            $data = $response->toArray($request);
            $checkoutUrl = isset($data['checkout_url']) && is_string($data['checkout_url'])
                ? trim($data['checkout_url'])
                : '';
            if ($checkoutUrl !== '' && $purchaseAssistantRequest->status === PurchaseAssistantRequest::STATUS_AWAITING_CUSTOMER_PAYMENT) {
                $purchaseAssistantRequest->update([
                    'status' => PurchaseAssistantRequest::STATUS_PAYMENT_UNDER_REVIEW,
                ]);
            }
        }

        return $response;
    }

    public function destroy(Request $request, PurchaseAssistantRequest $purchaseAssistantRequest): JsonResponse
    {
        $this->authorize('delete', $purchaseAssistantRequest);

        if ($purchaseAssistantRequest->status !== PurchaseAssistantRequest::STATUS_SUBMITTED) {
            return response()->json([
                'message' => 'Only submitted requests can be removed.',
                'error_key' => 'cannot_delete_status',
                'errors' => [],
                'status' => 422,
            ], 422);
        }

        $rid = $purchaseAssistantRequest->id;
        $purchaseAssistantRequest->delete();

        $this->activityLogger->log(
            $request->user(),
            UserActivityAction::PA_REQUEST_DELETED,
            'Purchase Assistant request #'.$rid.' deleted',
            null,
            ['purchase_assistant_request_id' => $rid],
            $request
        );

        return response()->json(null, 204);
    }
}
