<?php

namespace App\Contracts\Payments;

use App\Models\Order;
use App\Models\Payment;
use App\Models\WalletTopUpPayment;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

interface PaymentGatewayInterface
{
    /**
     * Provider code used in database (e.g. square|stripe).
     */
    public function gatewayCode(): string;

    /**
     * Create hosted checkout URL for order payment.
     *
     * @return array{
     *   checkout_url: string,
     *   provider_payment_id: string|null,
     *   provider_order_id: string|null,
     *   provider: string
     * }
     */
    public function createOrderCheckoutSession(Payment $payment, Order $order): array;

    /**
     * Create hosted checkout URL for wallet top-up.
     *
     * @return array{
     *   checkout_url: string,
     *   provider_payment_id: string|null,
     *   provider_order_id: string|null,
     *   provider: string
     * }
     */
    public function createWalletTopUpCheckoutSession(WalletTopUpPayment $topUp): array;

    /**
     * Verify signature (if needed) and update payment/order/top-up state.
     */
    public function handleWebhook(Request $request): Response;
}

