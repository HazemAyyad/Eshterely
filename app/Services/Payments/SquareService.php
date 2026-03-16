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
        $amount = $order->order_total_snapshot ?? $order->total_amount;
        $amountMinor = (int) round((float) $amount * 100);

        $money = new Money();
        $money->setAmount($amountMinor);
        $money->setCurrency($currency);

        $lineItem = new OrderLineItem('1');
        $lineItem->setName('Order ' . ($order->order_number ?? $order->id));
        $lineItem->setBasePriceMoney($money);
        $lineItem->setTotalMoney($money);

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
            $errors = $apiResponse->getResult()->getErrors();
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
