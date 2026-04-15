<?php

namespace App\Services\Payments;

use App\Enums\Payment\PaymentEventSource;
use App\Enums\Payment\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\Shipment;
use App\Models\Wallet;
use App\Models\WalletTopUpPayment;
use App\Services\Cart\RemoveOrderedCartItemsService;
use App\Services\PurchaseAssistant\PurchaseAssistantRequestStatusSync;
use App\Services\Fcm\OrderShipmentNotificationTrigger;
use App\Services\Shipments\ShipmentDraftFinalizationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SquareWebhookService
{
    private const PAYMENT_EVENTS = [
        'payment.created',
        'payment.updated',
    ];

    private const REFUND_ORDER_EVENTS = [
        'refund.updated',
        'order.updated',
    ];

    public function __construct(
        protected PaymentService $paymentService,
        protected OrderShipmentNotificationTrigger $notificationTrigger,
        protected ShipmentDraftFinalizationService $shipmentDraftFinalization
    ) {}

    /**
     * Handle a Square webhook event. Logs and returns without throwing when payment cannot be resolved.
     */
    public function handleEvent(?string $eventType, array $payload): void
    {
        if ($eventType === null || $eventType === '') {
            return;
        }

        $supported = array_merge(self::PAYMENT_EVENTS, self::REFUND_ORDER_EVENTS);
        if (! in_array($eventType, $supported, true)) {
            Log::debug('Square webhook unsupported event type', ['event_type' => $eventType]);
            return;
        }

        if (in_array($eventType, self::PAYMENT_EVENTS, true)) {
            $this->handlePaymentEvent($eventType, $payload);
            return;
        }

        if ($eventType === 'refund.updated' || $eventType === 'order.updated') {
            $this->handleRefundOrOrderEvent($eventType, $payload);
        }
    }

    protected function handlePaymentEvent(string $eventType, array $payload): void
    {
        $data = $payload['data'] ?? [];
        if (! is_array($data)) {
            Log::warning('Square webhook payment event missing data', ['event_type' => $eventType]);
            return;
        }

        $paymentObject = $data['object']['payment'] ?? [];
        if (! is_array($paymentObject)) {
            $paymentObject = [];
        }

        $payment = $this->resolvePayment($data, $paymentObject, $payload);
        if ($payment === null) {
            // Wallet top-up payments use the same Square event stream but do not map to orders/payments table.
            $topUp = $this->resolveWalletTopUpPayment($data, $paymentObject);
            if ($topUp !== null) {
                $this->handleWalletTopUpStatus($topUp, $eventType, $payload, $paymentObject);
                return;
            }
            Log::info('Square webhook payment not found', [
                'event_type' => $eventType,
                'event_id' => $payload['event_id'] ?? null,
            ]);
            return;
        }

        Log::info('Square webhook payment resolved', [
            'payment_id' => $payment->id,
            'event_type' => $eventType,
        ]);

        $status = $this->normalizeSquareStatus($paymentObject);

        $this->addWebhookEvent($payment, $eventType, $payload);

        if ($status !== null) {
            $this->applyPaymentStatus($payment, $status, $paymentObject);
        }
    }

    protected function handleRefundOrOrderEvent(string $eventType, array $payload): void
    {
        $payment = $this->resolvePaymentFromRefundOrOrderPayload($payload);
        if ($payment === null) {
            Log::debug('Square webhook refund/order event: no matching payment', ['event_type' => $eventType]);
            return;
        }

        $this->addWebhookEvent($payment, $eventType, $payload);
    }

    /**
     * Resolve internal Payment from Square payment event data.
     * Uses data.object.payment: id (provider_payment_id), order_id (provider_order_id), reference_id / metadata.
     */
    protected function resolvePayment(array $data, array $paymentObject, array $fullPayload): ?Payment
    {
        $squarePaymentId = $paymentObject['id'] ?? $data['id'] ?? null;
        if (is_string($squarePaymentId) && $squarePaymentId !== '') {
            $payment = Payment::where('provider', 'square')
                ->where('provider_payment_id', $squarePaymentId)
                ->first();
            if ($payment !== null) {
                return $payment;
            }
        }

        $orderId = $paymentObject['order_id'] ?? null;
        if (is_string($orderId) && $orderId !== '') {
            $payment = Payment::where('provider', 'square')
                ->where('provider_order_id', $orderId)
                ->first();
            if ($payment !== null) {
                return $payment;
            }
        }

        $reference = $paymentObject['reference_id'] ?? null;
        if (is_string($reference) && $reference !== '') {
            $payment = Payment::where('reference', $reference)->first();
            if ($payment !== null) {
                return $payment;
            }
        }

        $metadata = $paymentObject['metadata'] ?? [];
        if (is_array($metadata) && isset($metadata['reference'])) {
            $ref = $metadata['reference'];
            if (is_string($ref) && $ref !== '') {
                $payment = Payment::where('reference', $ref)->first();
                if ($payment !== null) {
                    return $payment;
                }
            }
        }

        return null;
    }

    /**
     * Resolve wallet top-up payment from Square payment event payload.
     * Prefers provider_order_id, then reference_id.
     */
    protected function resolveWalletTopUpPayment(array $data, array $paymentObject): ?WalletTopUpPayment
    {
        $orderId = $paymentObject['order_id'] ?? null;
        if (is_string($orderId) && $orderId !== '') {
            $p = WalletTopUpPayment::where('provider', 'square')->where('provider_order_id', $orderId)->first();
            if ($p !== null) {
                return $p;
            }
        }

        $reference = $paymentObject['reference_id'] ?? null;
        if (is_string($reference) && $reference !== '') {
            $p = WalletTopUpPayment::where('reference', $reference)->first();
            if ($p !== null) {
                return $p;
            }
        }

        $squarePaymentId = $paymentObject['id'] ?? $data['id'] ?? null;
        if (is_string($squarePaymentId) && $squarePaymentId !== '') {
            $p = WalletTopUpPayment::where('provider', 'square')->where('provider_payment_id', $squarePaymentId)->first();
            if ($p !== null) {
                return $p;
            }
        }

        return null;
    }

    /**
     * Apply Square status changes to wallet top-up payment and credit wallet when paid.
     * Idempotent: credits wallet once using metadata marker.
     */
    protected function handleWalletTopUpStatus(WalletTopUpPayment $topUp, string $eventType, array $payload, array $paymentObject): void
    {
        $squareStatus = $paymentObject['status'] ?? null;
        $normalized = is_string($squareStatus) ? strtoupper($squareStatus) : null;

        $newStatus = match ($normalized) {
            'COMPLETED' => 'paid',
            'APPROVED', 'PENDING' => 'processing',
            'CANCELED', 'CANCELLED' => 'cancelled',
            'FAILED' => 'failed',
            default => null,
        };

        Log::info('Square webhook wallet top-up resolved', [
            'top_up_id' => $topUp->id,
            'event_type' => $eventType,
            'square_status' => $squareStatus,
        ]);

        DB::transaction(function () use ($topUp, $newStatus, $paymentObject): void {
            $topUp->refresh();

            $updates = [];
            $squarePaymentId = $paymentObject['id'] ?? null;
            if (is_string($squarePaymentId) && $squarePaymentId !== '' && ($topUp->provider_payment_id === null || $topUp->provider_payment_id === '')) {
                $updates['provider_payment_id'] = $squarePaymentId;
            }
            $squareOrderId = $paymentObject['order_id'] ?? null;
            if (is_string($squareOrderId) && $squareOrderId !== '' && ($topUp->provider_order_id === null || $topUp->provider_order_id === '')) {
                $updates['provider_order_id'] = $squareOrderId;
            }

            if ($newStatus !== null && $topUp->status !== $newStatus) {
                $updates['status'] = $newStatus;
                if ($newStatus === 'paid') {
                    $updates['paid_at'] = $topUp->paid_at ?? now();
                    $updates['failure_code'] = null;
                    $updates['failure_message'] = null;
                }
                if ($newStatus === 'failed') {
                    $updates['failure_code'] = is_string($paymentObject['failure_code'] ?? null) ? $paymentObject['failure_code'] : null;
                    $updates['failure_message'] = is_string($paymentObject['failure_message'] ?? null) ? $paymentObject['failure_message'] : null;
                }
            }

            if ($updates !== []) {
                $topUp->update($updates);
            }

            if ($newStatus === 'paid') {
                $meta = $topUp->metadata ?? [];
                if (! isset($meta['wallet_credited_at'])) {
                    $wallet = Wallet::lockForUpdate()->find($topUp->wallet_id);
                    if ($wallet) {
                        $wallet->available_balance = (float) $wallet->available_balance + (float) $topUp->amount;
                        $wallet->save();

                        $wallet->transactions()->create([
                            'type' => 'top_up',
                            'title' => 'Top-up',
                            'amount' => (float) $topUp->amount,
                            'subtitle' => 'COMPLETED',
                            'reference_type' => 'wallet_top_up_payment',
                            'reference_id' => $topUp->id,
                        ]);
                    }

                    $meta['wallet_credited_at'] = now()->toIso8601String();
                    $topUp->update(['metadata' => $meta]);
                }
            }
        });
    }

    protected function resolvePaymentFromRefundOrOrderPayload(array $payload): ?Payment
    {
        $data = $payload['data'] ?? [];
        if (! is_array($data)) {
            return null;
        }

        $object = $data['object'] ?? [];
        $paymentId = $data['id'] ?? $object['id'] ?? null;
        $orderId = $object['order_id'] ?? null;

        if (is_string($paymentId) && $paymentId !== '') {
            $p = Payment::where('provider', 'square')->where('provider_payment_id', $paymentId)->first();
            if ($p !== null) {
                return $p;
            }
        }

        if (is_string($orderId) && $orderId !== '') {
            return Payment::where('provider', 'square')->where('provider_order_id', $orderId)->first();
        }

        return null;
    }

    /**
     * Map Square payment status to our PaymentStatus.
     * Reads from data.object.payment.status.
     * COMPLETED => paid; APPROVED/PENDING => processing; CANCELED => cancelled; FAILED => failed.
     */
    protected function normalizeSquareStatus(array $paymentObject): ?PaymentStatus
    {
        $status = $paymentObject['status'] ?? null;
        if (! is_string($status)) {
            return null;
        }

        $status = strtoupper($status);

        return match ($status) {
            'COMPLETED' => PaymentStatus::Paid,
            'APPROVED', 'PENDING' => PaymentStatus::Processing,
            'CANCELED', 'CANCELLED' => PaymentStatus::Cancelled,
            'FAILED' => PaymentStatus::Failed,
            default => null,
        };
    }

    protected function addWebhookEvent(Payment $payment, string $eventType, array $payload): PaymentEvent
    {
        $safePayload = $this->sanitizePayloadForLog($payload);

        return $this->paymentService->addEvent(
            $payment,
            PaymentEventSource::Webhook,
            $eventType,
            $safePayload,
            'Square webhook: ' . $eventType
        );
    }

    /**
     * Apply status change to payment. Idempotent for paid; stores provider_payment_id and paid_at when becoming paid.
     * Uses fields from data.object.payment: id, order_id, status, failure_code, failure_message, card_details.status.
     */
    protected function applyPaymentStatus(Payment $payment, PaymentStatus $newStatus, array $paymentObject): void
    {
        if ($payment->status === $newStatus) {
            return;
        }

        if ($payment->isTerminal()) {
            Log::debug('Square webhook: payment already terminal', ['payment_id' => $payment->id]);
            return;
        }

        $notifyPaymentPaid = null;
        DB::transaction(function () use ($payment, $newStatus, $paymentObject, &$notifyPaymentPaid): void {
            $payment->refresh();

            if ($newStatus === PaymentStatus::Paid) {
                $updates = [
                    'status' => PaymentStatus::Paid,
                    'paid_at' => $payment->paid_at ?? now(),
                    'failure_code' => null,
                    'failure_message' => null,
                ];
                $squarePaymentId = $paymentObject['id'] ?? null;
                if (is_string($squarePaymentId) && $squarePaymentId !== '') {
                    $updates['provider_payment_id'] = $squarePaymentId;
                }
                $orderId = $paymentObject['order_id'] ?? null;
                if (is_string($orderId) && $orderId !== '' && ($payment->provider_order_id === null || $payment->provider_order_id === '')) {
                    $updates['provider_order_id'] = $orderId;
                }
                $payment->update($updates);
                $this->paymentService->addEvent($payment, PaymentEventSource::Webhook, 'payment.paid', [
                    'square_status' => $paymentObject['status'] ?? null,
                ], 'Square webhook: payment completed');
                $this->syncOrderToPaid($payment);
                $this->finalizeOutboundShipmentAfterGatewayPayment($payment);
                Log::info('Square webhook payment status changed', [
                    'payment_id' => $payment->id,
                    'status' => 'paid',
                ]);
                $notifyPaymentPaid = $payment->id;
                return;
            }

            if ($newStatus === PaymentStatus::Failed) {
                $cardDetails = $paymentObject['card_details'] ?? [];
                $code = (is_array($cardDetails) ? ($cardDetails['status'] ?? null) : null)
                    ?? $paymentObject['failure_code'] ?? null;
                $message = $paymentObject['failure_message'] ?? null;
                $this->paymentService->markFailed(
                    $payment,
                    is_string($code) ? $code : null,
                    is_string($message) ? $message : null,
                    ['source' => 'webhook']
                );
                Log::info('Square webhook payment status changed', [
                    'payment_id' => $payment->id,
                    'status' => 'failed',
                ]);
                $paymentForNotification = Payment::with('order.user')->find($payment->id);
                if ($paymentForNotification && $paymentForNotification->order) {
                    $this->notificationTrigger->onPaymentFailed($paymentForNotification);
                }
                return;
            }

            if ($newStatus === PaymentStatus::Cancelled) {
                $this->paymentService->markCancelled($payment, ['source' => 'webhook']);
                Log::info('Square webhook payment status changed', [
                    'payment_id' => $payment->id,
                    'status' => 'cancelled',
                ]);
                return;
            }

            if ($newStatus === PaymentStatus::Processing) {
                $payment->update(['status' => PaymentStatus::Processing]);
                $this->paymentService->addEvent($payment, PaymentEventSource::Webhook, 'payment.processing', [
                    'square_status' => $paymentObject['status'] ?? null,
                ], 'Square webhook: processing');
            }
        });

        if ($notifyPaymentPaid !== null) {
            $paymentForNotification = Payment::with('order.user')->find($notifyPaymentPaid);
            if ($paymentForNotification && $paymentForNotification->order) {
                $this->notificationTrigger->onPaymentSuccess($paymentForNotification);
            }
        }
    }

    /**
     * When payment becomes paid, update order status to paid and set placed_at.
     * Idempotent: only updates if order is still pending_payment (duplicate webhooks do not duplicate transitions).
     */
    protected function finalizeOutboundShipmentAfterGatewayPayment(Payment $payment): void
    {
        if ($payment->order_id !== null || $payment->shipment_id === null) {
            return;
        }

        $shipment = Shipment::find($payment->shipment_id);
        if (! $shipment || $shipment->status !== Shipment::STATUS_DRAFT) {
            return;
        }

        $this->shipmentDraftFinalization->finalizeDraftAndMarkPaid($shipment);
    }

    protected function syncOrderToPaid(Payment $payment): void
    {
        if ($payment->order_id === null) {
            return;
        }
        $updated = Order::where('id', $payment->order_id)
            ->where('status', Order::STATUS_PENDING_PAYMENT)
            ->update([
                'status' => Order::STATUS_PAID,
                'placed_at' => now(),
            ]);

        if ($updated > 0) {
            Log::info('Square webhook: order marked paid', [
                'order_id' => $payment->order_id,
                'payment_id' => $payment->id,
            ]);
            $order = Order::find($payment->order_id);
            if ($order) {
                app(PurchaseAssistantRequestStatusSync::class)->onOrderMarkedPaid($order);
            }
        }

        (app(RemoveOrderedCartItemsService::class))((int) $payment->order_id);
    }

    /**
     * Remove sensitive fields from payload before storing in payment_events.
     */
    protected function sanitizePayloadForLog(array $payload): array
    {
        $allowed = ['type', 'event_id', 'created_at', 'data'];
        $out = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $payload)) {
                $out[$key] = $payload[$key];
            }
        }
        return $out;
    }
}
