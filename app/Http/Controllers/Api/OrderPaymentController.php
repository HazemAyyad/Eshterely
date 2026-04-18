<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentLaunchResource;
use App\Models\Order;
use App\Models\PurchaseAssistantRequest;
use App\Models\Wallet;
use App\Services\Payments\CheckoutPaymentModeService;
use App\Services\Payments\OrderWalletPaymentService;
use App\Services\Payments\PaymentEligibilityService;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\PaymentService;
use App\Services\PurchaseAssistant\PurchaseAssistantOrderPricingSyncService;
use App\Services\PurchaseAssistant\PurchaseAssistantRequestStatusSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Order-centric payment start. Validates ownership and eligibility, creates payment and Square session.
 * Payment amount comes from order snapshots only (no recalculation).
 * Uses CheckoutPaymentModeService for wallet vs gateway rules (same as cart checkout / shipment pay).
 */
class OrderPaymentController extends Controller
{
    public function __construct(
        protected PaymentEligibilityService $eligibilityService,
        protected PaymentService $paymentService,
        protected PaymentGatewayManager $gatewayManager,
        protected CheckoutPaymentModeService $checkoutPaymentModeService,
        protected OrderWalletPaymentService $orderWalletPaymentService,
        protected PurchaseAssistantOrderPricingSyncService $purchaseAssistantPricingSync
    ) {}

    /**
     * GET /api/orders/{order}/payment-options
     * Same payment availability rules as checkout review (wallet / gateway / both).
     */
    public function paymentOptions(Request $request, Order $order): JsonResponse
    {
        if ($order->user_id !== $request->user()->id) {
            abort(404, 'Order not found.');
        }

        $this->syncPurchaseAssistantOrderPricingIfNeeded($order);

        $result = $this->eligibilityService->checkOrderEligibility($order);
        if (! $result['eligible']) {
            return response()->json([
                'message' => $result['message'],
                'error_key' => $result['error_key'],
                'errors' => [],
                'status' => 422,
            ], 422);
        }

        $wallet = Wallet::firstOrCreate(
            ['user_id' => $request->user()->id],
            ['available_balance' => 0, 'pending_balance' => 0, 'promo_balance' => 0]
        );

        $payable = $this->resolveOrderPayableAmount($order);
        $balance = round((float) $wallet->available_balance, 2);
        $modePayload = $this->checkoutPaymentModeService->buildModePayload($payable, $balance);

        return response()->json(array_merge([
            'order_id' => (string) $order->id,
            'currency' => $order->currency ?? 'USD',
            'amount_due_now' => $payable,
            'wallet_balance' => $balance,
        ], $modePayload));
    }

    /**
     * POST /api/orders/{order}/start-payment
     * Optional body: payment_method = wallet|gateway (defaults: gateway, except wallet_only → wallet).
     */
    public function startPayment(Request $request, Order $order): PaymentLaunchResource|JsonResponse
    {
        if ($order->user_id !== $request->user()->id) {
            abort(404, 'Order not found.');
        }

        $this->syncPurchaseAssistantOrderPricingIfNeeded($order);

        $request->validate([
            'payment_method' => 'sometimes|nullable|in:wallet,gateway',
            'gateway' => 'nullable|string',
        ]);

        $mode = $this->checkoutPaymentModeService->getMode();
        $explicit = $request->input('payment_method');
        $method = is_string($explicit) && trim($explicit) !== ''
            ? strtolower(trim($explicit))
            : ($mode === 'wallet_only' ? 'wallet' : 'gateway');

        $modeErr = $this->checkoutPaymentModeService->validatePaymentMethodForMode($method);
        if ($modeErr !== null) {
            return response()->json([
                'message' => $modeErr,
                'error_key' => 'payment_method_not_allowed',
                'errors' => [],
                'status' => 422,
            ], 422);
        }

        if ($method === 'wallet') {
            $out = $this->orderWalletPaymentService->settleOrderWithWallet($request->user(), $order);
            if (isset($out['error_response'])) {
                return $out['error_response'];
            }

            /** @var \App\Models\Payment $payment */
            $payment = $out['payment'];

            return new PaymentLaunchResource([
                'payment_id' => $payment->id,
                'reference' => $payment->reference,
                'provider' => $payment->provider,
                'checkout_url' => null,
                'status' => $payment->fresh()->status->value,
                'order_id' => $order->id,
            ]);
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

        app(PurchaseAssistantRequestStatusSync::class)->onCustomerInitiatedGatewayPayment($order->fresh());

        return new PaymentLaunchResource([
            'payment_id' => $payment->id,
            'reference' => $payment->reference,
            'provider' => $payment->provider,
            'checkout_url' => $sessionResult['checkout_url'],
            'status' => $payment->fresh()->status->value,
            'order_id' => $order->id,
        ]);
    }

    private function resolveOrderPayableAmount(Order $order): float
    {
        $due = (float) ($order->amount_due_now ?? 0);

        return round($due > 0 ? $due : (float) ($order->order_total_snapshot ?? $order->total_amount ?? 0), 2);
    }

    /**
     * Ensures PA-linked orders use the latest admin pricing before exposing amounts or creating payments.
     */
    private function syncPurchaseAssistantOrderPricingIfNeeded(Order $order): void
    {
        if ($order->purchase_assistant_request_id === null) {
            return;
        }

        $pa = PurchaseAssistantRequest::query()->find($order->purchase_assistant_request_id);
        if ($pa === null) {
            return;
        }

        $this->purchaseAssistantPricingSync->syncFromRequestIfEligible($pa);
        $order->refresh();
    }
}
