<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentLaunchResource;
use App\Http\Resources\PurchaseAssistantRequestResource;
use App\Models\Order;
use App\Models\PurchaseAssistantRequest;
use App\Support\PurchaseAssistantStoreDisplayName;
use App\Services\Payments\PaymentEligibilityService;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\PaymentService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PurchaseAssistantRequestController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected PaymentEligibilityService $eligibilityService,
        protected PaymentService $paymentService,
        protected PaymentGatewayManager $gatewayManager
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
     * Same contract as POST /api/orders/{order}/start-payment for the converted order.
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

        $result = $this->eligibilityService->checkOrderEligibility($order);

        if (! $result['eligible']) {
            return response()->json([
                'message' => $result['message'],
                'error_key' => $result['error_key'],
                'errors' => [],
                'status' => 422,
            ], 422);
        }

        $requestedGateway = $request->input('gateway');
        try {
            $gateway = is_string($requestedGateway) && trim($requestedGateway) !== ''
                ? $this->gatewayManager->resolve($requestedGateway)
                : $this->gatewayManager->resolveDefault();
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => 'Payment gateway unavailable.',
                'error_key' => 'gateway_unavailable',
                'errors' => [],
                'status' => 422,
            ], 422);
        }

        $payment = $this->paymentService->createPendingPaymentForOrder($order, ['provider' => $gateway->gatewayCode()]);

        $attempt = $this->paymentService->createAttempt($payment, [
            'order_id' => $order->id,
            'payment_id' => $payment->id,
            'reference' => $payment->reference,
            'amount' => (float) $payment->amount,
            'currency' => $payment->currency ?? 'USD',
        ]);

        try {
            $sessionResult = $gateway->createOrderCheckoutSession($payment, $order);
        } catch (\Throwable $e) {
            $this->paymentService->updateAttemptWithResponse($attempt, [
                'error' => $e->getMessage(),
            ], 'failed');
            throw $e;
        }

        $this->paymentService->updateAttemptWithResponse($attempt, [
            'checkout_url' => $sessionResult['checkout_url'],
            'provider' => $sessionResult['provider'],
            'provider_order_id' => $sessionResult['provider_order_id'],
            'provider_payment_id' => $sessionResult['provider_payment_id'],
        ], 'success');

        if (! empty($sessionResult['provider_order_id'])) {
            $payment->update(['provider_order_id' => $sessionResult['provider_order_id']]);
        }
        if (! empty($sessionResult['provider_payment_id'])) {
            $payment->update(['provider_payment_id' => $sessionResult['provider_payment_id']]);
        }

        if ($purchaseAssistantRequest->status === PurchaseAssistantRequest::STATUS_AWAITING_CUSTOMER_PAYMENT) {
            $purchaseAssistantRequest->update([
                'status' => PurchaseAssistantRequest::STATUS_PAYMENT_UNDER_REVIEW,
            ]);
        }

        return new PaymentLaunchResource([
            'payment_id' => $payment->id,
            'reference' => $payment->reference,
            'provider' => $payment->provider,
            'checkout_url' => $sessionResult['checkout_url'],
            'status' => $payment->fresh()->status->value,
            'order_id' => $order->id,
        ]);
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

        $purchaseAssistantRequest->delete();

        return response()->json(null, 204);
    }
}
