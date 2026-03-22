<?php

namespace App\Http\Controllers\Api;

use App\Enums\Payment\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\CheckoutSessionResource;
use App\Models\Order;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentCheckoutController extends Controller
{
    /**
     * POST /api/orders/{order}/pay
     * Create a pending payment and hosted checkout session; return checkout URL for the app.
     */
    public function __invoke(Request $request, Order $order): CheckoutSessionResource|JsonResponse
    {
        $user = $request->user();

        if ($order->user_id !== $user->id) {
            abort(404, 'Order not found.');
        }

        $hasPaidPayment = $order->payments()->where('status', PaymentStatus::Paid)->exists();
        if ($hasPaidPayment) {
            $due = (float) ($order->amount_due_now ?? 0);
            $hasCardPaid = $order->payments()
                ->where('status', PaymentStatus::Paid)
                ->whereIn('provider', ['square', 'stripe'])
                ->exists();
            if ($due <= 0.00001 || $hasCardPaid) {
                return response()->json(['message' => 'Order is already paid.'], 422);
            }
        }

        $paymentService = app(PaymentService::class);

        $requestedGateway = $request->input('gateway');
        $gatewayManager = app(PaymentGatewayManager::class);
        try {
            $gateway = is_string($requestedGateway) && trim($requestedGateway) !== ''
                ? $gatewayManager->resolve($requestedGateway)
                : $gatewayManager->resolveDefault();
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => 'Payment gateway unavailable.',
                'error_key' => 'gateway_unavailable',
                'errors' => [],
                'status' => 422,
            ], 422);
        }

        $payment = $paymentService->createPendingPaymentForOrder($order, ['provider' => $gateway->gatewayCode()]);

        $requestPayload = [
            'order_id' => $order->id,
            'payment_id' => $payment->id,
            'reference' => $payment->reference,
            'amount' => (float) (((float) ($order->amount_due_now ?? 0) > 0) ? $order->amount_due_now : ($order->order_total_snapshot ?? $order->total_amount)),
            'currency' => $order->currency ?? 'USD',
        ];

        $attempt = $paymentService->createAttempt($payment, $requestPayload);

        try {
            $result = $gateway->createOrderCheckoutSession($payment, $order);
        } catch (\Throwable $e) {
            $paymentService->updateAttemptWithResponse($attempt, [
                'error' => $e->getMessage(),
            ], 'failed');
            throw $e;
        }

        $paymentService->updateAttemptWithResponse($attempt, [
            'checkout_url' => $result['checkout_url'],
            'provider' => $result['provider'],
            'provider_order_id' => $result['provider_order_id'],
            'provider_payment_id' => $result['provider_payment_id'],
        ], 'success');

        if (! empty($result['provider_order_id'])) {
            $payment->update(['provider_order_id' => $result['provider_order_id']]);
        }
        if (! empty($result['provider_payment_id'])) {
            $payment->update(['provider_payment_id' => $result['provider_payment_id']]);
        }

        return new CheckoutSessionResource([
            'payment_id' => $payment->id,
            'reference' => $payment->reference,
            'provider' => $payment->provider ?? 'square',
            'checkout_url' => $result['checkout_url'],
            'status' => $payment->status->value,
            'order_id' => $order->id,
        ]);
    }
}
