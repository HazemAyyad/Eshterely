<?php

namespace App\Services\Payments\Gateways;

use App\Contracts\Payments\PaymentGatewayInterface;
use App\Models\Order;
use App\Models\Payment;
use App\Models\WalletTopUpPayment;
use App\Services\Payments\SquareService;
use App\Services\Payments\SquareWebhookSignatureVerifier;
use App\Services\Payments\SquareWebhookService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SquarePaymentGateway implements PaymentGatewayInterface
{
    public function __construct(
        protected SquareService $squareService,
        protected SquareWebhookSignatureVerifier $verifier,
        protected SquareWebhookService $webhookService
    ) {}

    public function gatewayCode(): string
    {
        return 'square';
    }

    public function createOrderCheckoutSession(Payment $payment, Order $order): array
    {
        $result = $this->squareService->createCheckoutSession($payment, $order);

        return [
            'checkout_url' => $result['checkout_url'],
            'provider_payment_id' => $result['square_payment_id'] ?? null,
            'provider_order_id' => $result['square_order_id'] ?? null,
            'provider' => 'square',
        ];
    }

    public function createWalletTopUpCheckoutSession(WalletTopUpPayment $topUp): array
    {
        $result = $this->squareService->createWalletTopUpCheckoutSession($topUp);

        return [
            'checkout_url' => $result['checkout_url'],
            'provider_payment_id' => $result['square_payment_id'] ?? null,
            'provider_order_id' => $result['square_order_id'] ?? null,
            'provider' => 'square',
        ];
    }

    public function createShipmentShippingCheckoutSession(Payment $payment): array
    {
        $result = $this->squareService->createShipmentShippingCheckoutSession($payment);

        return [
            'checkout_url' => $result['checkout_url'],
            'provider_payment_id' => $result['square_payment_id'] ?? null,
            'provider_order_id' => $result['square_order_id'] ?? null,
            'provider' => 'square',
        ];
    }

    public function handleWebhook(Request $request): Response
    {
        $rawBody = $request->getContent();
        $signatureHeader = $request->header('x-square-hmacsha256-signature') ?? '';

        if ($rawBody === '') {
            return response('', 400);
        }

        $skipVerification = config('app.env') === 'testing'
            && config('square.webhook_skip_verification', false);

        if (! $skipVerification) {
            if ($signatureHeader === '') {
                return response('', 403);
            }

            if (! $this->verifier->verify($rawBody, $signatureHeader)) {
                return response('', 403);
            }
        }

        $payload = json_decode($rawBody, true);
        if (! is_array($payload)) {
            return response('', 400);
        }

        $eventType = $payload['type'] ?? null;

        $this->webhookService->handleEvent($eventType, $payload);

        return response('', 200);
    }
}

