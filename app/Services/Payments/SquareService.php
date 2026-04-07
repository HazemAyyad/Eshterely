<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;
use Square\Legacy\Authentication\BearerAuthCredentialsBuilder;
use Square\Legacy\Models\CreatePaymentLinkRequest;
use Square\Legacy\Models\Money;
use Square\Legacy\Models\Order as SquareOrder;
use Square\Legacy\Models\OrderLineItem;
use Square\Legacy\SquareClientBuilder;
use Square\Legacy\Environment;

class SquareService
{
    public function __construct(
        protected string $accessToken,
        protected string $locationId,
        protected string $environment
    ) {
    }

    /**
     * Create a Square Checkout (Payment Link) session for the given payment and order.
     * Uses idempotency_key from the payment.
     *
     * @return array{checkout_url: string, square_payment_id: string|null, square_order_id: string|null}
     * @throws \Throwable
     */
    public function createCheckoutSession(Payment $payment, Order $order): array
    {
        $idempotencyKey = $payment->idempotency_key ?? $payment->reference;
        if (strlen($idempotencyKey) > 192) {
            $idempotencyKey = substr($idempotencyKey, 0, 192);
        }

        $currency = $order->currency ?? 'USD';
        $amount = ((float) ($order->amount_due_now ?? 0) > 0)
            ? $order->amount_due_now
            : ($order->order_total_snapshot ?? $order->total_amount);
        $amountMinor = (int) round((float) $amount * 100);

        $money = new Money();
        $money->setAmount($amountMinor);
        $money->setCurrency($currency);

        $lineItem = new OrderLineItem('1');
        $lineItem->setName('Order ' . ($order->order_number ?? $order->id));
        $lineItem->setBasePriceMoney($money);

        $squareOrder = new SquareOrder($this->locationId);
        $squareOrder->setLineItems([$lineItem]);
        $squareOrder->setReferenceId((string) $order->id);

        $request = new CreatePaymentLinkRequest();
        $request->setIdempotencyKey($idempotencyKey);
        $request->setOrder($squareOrder);
        $request->setDescription('Payment for order ' . ($order->order_number ?? $order->id));

        $client = $this->buildClient();
        $apiResponse = $client->getCheckoutApi()->createPaymentLink($request);

        if (! $apiResponse->isSuccess()) {
            $result = $apiResponse->getResult();
            $errors = null;
            if (is_object($result) && method_exists($result, 'getErrors')) {
                $errors = $result->getErrors();
            } elseif (is_array($result)) {
                $errors = $result['errors'] ?? $result['error'] ?? null;
            }
            $message = $errors ? json_encode($errors) : 'Square API error';
            Log::warning('Square createPaymentLink failed', ['payment_id' => $payment->id, 'errors' => $errors]);
            throw new \RuntimeException('Square checkout failed: ' . $message);
        }

        $result = $apiResponse->getResult();
        $paymentLink = $result->getPaymentLink();
        $checkoutUrl = $paymentLink ? $paymentLink->getUrl() : null;
        $squareOrderId = $paymentLink ? $paymentLink->getOrderId() : null;

        if (! $checkoutUrl) {
            throw new \RuntimeException('Square did not return a checkout URL.');
        }

        // Payment is created when customer completes checkout; square_payment_id may come from webhook later
        return [
            'checkout_url' => $checkoutUrl,
            'square_payment_id' => null,
            'square_order_id' => $squareOrderId,
        ];
    }

    /**
     * Create a Square Checkout (Payment Link) session for a wallet top-up.
     *
     * @return array{checkout_url: string, square_payment_id: string|null, square_order_id: string|null}
     * @throws \Throwable
     */
    public function createWalletTopUpCheckoutSession(\App\Models\WalletTopUpPayment $topUp): array
    {
        $idempotencyKey = $topUp->idempotency_key ?? $topUp->reference;
        if (strlen($idempotencyKey) > 192) {
            $idempotencyKey = substr($idempotencyKey, 0, 192);
        }

        $currency = $topUp->currency ?: 'USD';
        $amountMinor = (int) round((float) $topUp->amount * 100);

        $money = new Money();
        $money->setAmount($amountMinor);
        $money->setCurrency($currency);

        $lineItem = new OrderLineItem('1');
        $lineItem->setName('Wallet Top-up');
        $lineItem->setBasePriceMoney($money);

        $squareOrder = new SquareOrder($this->locationId);
        $squareOrder->setLineItems([$lineItem]);
        // Use our reference so webhook can resolve without an Order.
        $squareOrder->setReferenceId((string) $topUp->reference);

        $request = new CreatePaymentLinkRequest();
        $request->setIdempotencyKey($idempotencyKey);
        $request->setOrder($squareOrder);
        $request->setDescription('Wallet top-up ' . $topUp->reference);

        $client = $this->buildClient();
        $apiResponse = $client->getCheckoutApi()->createPaymentLink($request);

        if (! $apiResponse->isSuccess()) {
            $result = $apiResponse->getResult();
            $errors = null;
            if (is_object($result) && method_exists($result, 'getErrors')) {
                $errors = $result->getErrors();
            } elseif (is_array($result)) {
                $errors = $result['errors'] ?? $result['error'] ?? null;
            }
            $message = $errors ? json_encode($errors) : 'Square API error';
            Log::warning('Square createPaymentLink failed (wallet top-up)', ['top_up_id' => $topUp->id, 'errors' => $errors]);
            throw new \RuntimeException('Square checkout failed: ' . $message);
        }

        $result = $apiResponse->getResult();
        $paymentLink = $result->getPaymentLink();
        $checkoutUrl = $paymentLink ? $paymentLink->getUrl() : null;
        $squareOrderId = $paymentLink ? $paymentLink->getOrderId() : null;

        if (! $checkoutUrl) {
            throw new \RuntimeException('Square did not return a checkout URL.');
        }

        return [
            'checkout_url' => $checkoutUrl,
            'square_payment_id' => null,
            'square_order_id' => $squareOrderId,
        ];
    }

    /**
     * Square Payment Link for outbound shipment (Payment has shipment_id, order_id null).
     *
     * @return array{checkout_url: string, square_payment_id: string|null, square_order_id: string|null}
     */
    public function createShipmentShippingCheckoutSession(Payment $payment): array
    {
        $idempotencyKey = $payment->idempotency_key ?? $payment->reference;
        if (strlen($idempotencyKey) > 192) {
            $idempotencyKey = substr($idempotencyKey, 0, 192);
        }

        $currency = $payment->currency ?: 'USD';
        $amountMinor = (int) round((float) $payment->amount * 100);

        $money = new Money();
        $money->setAmount($amountMinor);
        $money->setCurrency($currency);

        $lineItem = new OrderLineItem('1');
        $lineItem->setName('Shipping & fees');
        $lineItem->setBasePriceMoney($money);

        $squareOrder = new SquareOrder($this->locationId);
        $squareOrder->setLineItems([$lineItem]);
        $squareOrder->setReferenceId((string) $payment->reference);

        $request = new CreatePaymentLinkRequest();
        $request->setIdempotencyKey($idempotencyKey);
        $request->setOrder($squareOrder);
        $request->setDescription('Shipment shipping '.$payment->reference);

        $client = $this->buildClient();
        $apiResponse = $client->getCheckoutApi()->createPaymentLink($request);

        if (! $apiResponse->isSuccess()) {
            $result = $apiResponse->getResult();
            $errors = null;
            if (is_object($result) && method_exists($result, 'getErrors')) {
                $errors = $result->getErrors();
            } elseif (is_array($result)) {
                $errors = $result['errors'] ?? $result['error'] ?? null;
            }
            $message = $errors ? json_encode($errors) : 'Square API error';
            Log::warning('Square createPaymentLink failed (shipment)', ['payment_id' => $payment->id, 'errors' => $errors]);
            throw new \RuntimeException('Square checkout failed: '.$message);
        }

        $result = $apiResponse->getResult();
        $paymentLink = $result->getPaymentLink();
        $checkoutUrl = $paymentLink ? $paymentLink->getUrl() : null;
        $squareOrderId = $paymentLink ? $paymentLink->getOrderId() : null;

        if (! $checkoutUrl) {
            throw new \RuntimeException('Square did not return a checkout URL.');
        }

        return [
            'checkout_url' => $checkoutUrl,
            'square_payment_id' => null,
            'square_order_id' => $squareOrderId,
        ];
    }

    protected function buildClient(): \Square\Legacy\SquareClient
    {
        $env = strtolower($this->environment) === 'sandbox' ? Environment::SANDBOX : Environment::PRODUCTION;

        return SquareClientBuilder::init()
            ->bearerAuthCredentials(
                BearerAuthCredentialsBuilder::init($this->accessToken)
            )
            ->environment($env)
            ->build();
    }
}
