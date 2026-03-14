<?php

namespace App\Http\Controllers\Api;

use App\Enums\Payment\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\CheckoutSessionResource;
use App\Models\Order;
use App\Services\Payments\PaymentService;
use App\Services\Payments\SquareService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentCheckoutController extends Controller
{
    /**
     * POST /api/orders/{order}/pay
     * Create a pending payment and Square checkout session; return checkout URL for the app.
     */
    public function __invoke(Request $request, Order $order): CheckoutSessionResource|JsonResponse
    {
        $user = $request->user();

        if ($order->user_id !== $user->id) {
            abort(404, 'Order not found.');
        }

        $existingPaid = $order->payments()->where('status', PaymentStatus::Paid)->exists();
        if ($existingPaid) {
            return response()->json(['message' => 'Order is already paid.'], 422);
        }

        $paymentService = app(PaymentService::class);
        $payment = $paymentService->createPendingPaymentForOrder($order, ['provider' => 'square']);

        $requestPayload = [
            'order_id' => $order->id,
            'payment_id' => $payment->id,
            'reference' => $payment->reference,
            'amount' => (float) $order->total_amount,
            'currency' => $order->currency ?? 'USD',
        ];

        $attempt = $paymentService->createAttempt($payment, $requestPayload);

        try {
            $squareService = app(SquareService::class);
            $result = $squareService->createCheckoutSession($payment, $order);
        } catch (\Throwable $e) {
            $paymentService->updateAttemptWithResponse($attempt, [
                'error' => $e->getMessage(),
            ], 'failed');
            throw $e;
        }

        $paymentService->updateAttemptWithResponse($attempt, [
            'checkout_url' => $result['checkout_url'],
            'square_order_id' => $result['square_order_id'],
            'square_payment_id' => $result['square_payment_id'],
        ], 'success');

        if ($result['square_order_id']) {
            $payment->update(['provider_order_id' => $result['square_order_id']]);
        }

        return new CheckoutSessionResource([
            'payment_id' => $payment->id,
            'reference' => $payment->reference,
            'checkout_url' => $result['checkout_url'],
            'status' => $payment->status->value,
        ]);
    }
}
