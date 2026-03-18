<?php

namespace App\Services\Payments\Gateways;

use App\Contracts\Payments\PaymentGatewayInterface;
use App\Models\Order;
use App\Models\Payment;
use App\Models\WalletTopUpPayment;
use App\Services\Payments\StripeWebhookService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session as StripeCheckoutSession;
use Stripe\Stripe;
use Stripe\Webhook;

class StripePaymentGateway implements PaymentGatewayInterface
{
    public function __construct(
        protected string $secretKey,
        protected string $environment = 'test',
        protected string $webhookSecret = '',
        protected ?string $currencyDefault = null,
        protected ?StripeWebhookService $webhookService = null
    ) {
        $this->webhookService ??= app(StripeWebhookService::class);
    }

    public function gatewayCode(): string
    {
        return 'stripe';
    }

    public function createOrderCheckoutSession(Payment $payment, Order $order): array
    {
        Stripe::setApiKey($this->secretKey);

        $currency = $payment->currency ?: ($order->currency ?? $this->currencyDefault ?: 'USD');
        $amountMinor = (int) round(((float) $payment->amount) * 100);

        $orderLabel = 'Order ' . ($order->order_number ?? $order->id);
        $successUrl = config('app.url') . '/payment/stripe/success?session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl = config('app.url') . '/payment/stripe/cancel?session_id={CHECKOUT_SESSION_ID}';

        $user = $order->user;
        $customerEmail = $user?->email ?? null;

        $session = StripeCheckoutSession::create([
            'mode' => 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'customer_email' => $customerEmail,
            'line_items' => [
                [
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => strtoupper((string) $currency),
                        'unit_amount' => $amountMinor,
                        'product_data' => [
                            'name' => $orderLabel,
                        ],
                    ],
                ],
            ],
            'metadata' => [
                'payment_reference' => $payment->reference,
                'payment_id' => (string) $payment->id,
                'order_id' => (string) $order->id,
            ],
        ]);

        if (empty($session->url)) {
            Log::warning('Stripe checkout session has no URL', ['payment_id' => $payment->id]);
            throw new \RuntimeException('Stripe did not return a checkout URL.');
        }

        return [
            'checkout_url' => $session->url,
            'provider_payment_id' => (string) $session->id,
            'provider_order_id' => (string) $order->id,
            'provider' => 'stripe',
        ];
    }

    public function createWalletTopUpCheckoutSession(WalletTopUpPayment $topUp): array
    {
        Stripe::setApiKey($this->secretKey);

        $currency = $topUp->currency ?: ($this->currencyDefault ?: 'USD');
        $amountMinor = (int) round(((float) $topUp->amount) * 100);

        $successUrl = config('app.url') . '/payment/stripe/success?session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl = config('app.url') . '/payment/stripe/cancel?session_id={CHECKOUT_SESSION_ID}';

        $session = StripeCheckoutSession::create([
            'mode' => 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'line_items' => [
                [
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => strtoupper((string) $currency),
                        'unit_amount' => $amountMinor,
                        'product_data' => [
                            'name' => 'Wallet top-up',
                        ],
                    ],
                ],
            ],
            'metadata' => [
                'wallet_top_up_reference' => $topUp->reference,
                'wallet_top_up_id' => (string) $topUp->id,
            ],
        ]);

        if (empty($session->url)) {
            Log::warning('Stripe checkout session has no URL', ['top_up_id' => $topUp->id]);
            throw new \RuntimeException('Stripe did not return a checkout URL.');
        }

        return [
            'checkout_url' => $session->url,
            'provider_payment_id' => (string) $session->id,
            'provider_order_id' => (string) $topUp->id,
            'provider' => 'stripe',
        ];
    }

    public function handleWebhook(Request $request): Response
    {
        $rawBody = $request->getContent();
        $signatureHeader = $request->header('Stripe-Signature') ?? '';

        if ($rawBody === '') {
            return response('', 400);
        }

        $skipVerification = config('app.env') === 'testing'
            && config('stripe.webhook_skip_verification', false);

        $webhookSecret = $this->webhookSecret ?: config('stripe.webhook_secret', '');

        if (! $skipVerification) {
            if (trim($signatureHeader) === '') {
                return response('', 403);
            }

            if (trim($webhookSecret) === '') {
                return response('', 500);
            }

            try {
                $event = Webhook::constructEvent($rawBody, $signatureHeader, $webhookSecret);
            } catch (\Throwable $e) {
                Log::warning('Stripe webhook signature verification failed', [
                    'error' => $e->getMessage(),
                ]);
                return response('', 403);
            }

            $eventType = $event->type ?? '';
            $payload = $event->toArray(true);
        } else {
            $json = json_decode($rawBody, true);
            if (! is_array($json)) {
                return response('', 400);
            }
            $eventType = $json['type'] ?? '';
            $payload = $json;
        }

        $this->webhookService->handleEvent($eventType, $payload);

        return response('', 200);
    }
}

