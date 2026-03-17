<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentLaunchResource;
use App\Models\Order;
use App\Services\Payments\PaymentEligibilityService;
use App\Services\Payments\PaymentService;
use App\Services\Payments\SquareService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Order-centric payment start. Validates ownership and eligibility, creates payment and Square session.
 * Payment amount comes from order snapshots only (no recalculation).
 */
class OrderPaymentController extends Controller
{
    public function __construct(
        protected PaymentEligibilityService $eligibilityService,
        protected PaymentService $paymentService,
        protected SquareService $squareService
    ) {}

    /**
     * POST /api/orders/{order}/start-payment
     * Start payment for an order: validate ownership, check eligibility, create payment record and Square checkout URL.
     */
    public function startPayment(Request $request, Order $order): PaymentLaunchResource|JsonResponse
    {
        if ($order->user_id !== $request->user()->id) {
            abort(404, 'Order not found.');
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

        $payment = $this->paymentService->createPendingPaymentForOrder($order, ['provider' => 'square']);

        $attempt = $this->paymentService->createAttempt($payment, [
            'order_id' => $order->id,
            'payment_id' => $payment->id,
            'reference' => $payment->reference,
            'amount' => (float) $payment->amount,
            'currency' => $payment->currency ?? 'USD',
        ]);

        try {
            $sessionResult = $this->squareService->createCheckoutSession($payment, $order);
        } catch (\Throwable $e) {
            $this->paymentService->updateAttemptWithResponse($attempt, [
                'error' => $e->getMessage(),
            ], 'failed');
            throw $e;
        }

        $this->paymentService->updateAttemptWithResponse($attempt, [
            'checkout_url' => $sessionResult['checkout_url'],
            'square_order_id' => $sessionResult['square_order_id'],
            'square_payment_id' => $sessionResult['square_payment_id'],
        ], 'success');

        if (! empty($sessionResult['square_order_id'])) {
            $payment->update(['provider_order_id' => $sessionResult['square_order_id']]);
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
}
