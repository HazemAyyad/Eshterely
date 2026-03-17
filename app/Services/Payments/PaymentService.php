<?php

namespace App\Services\Payments;

use App\Enums\Payment\PaymentEventSource;
use App\Enums\Payment\PaymentEventType;
use App\Enums\Payment\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function __construct(
        protected PaymentReferenceGenerator $referenceGenerator
    ) {}

    /**
     * Create a pending payment for an order. User is taken from order when available.
     */
    /**
     * Create a pending payment for an order. Uses order snapshot total when present (payment-safe).
     */
    public function createPendingPaymentForOrder(Order $order, array $context = []): Payment
    {
        $reference = $this->referenceGenerator->generate();
        $currency = $order->currency ?? 'USD';
        $amount = ((float) ($order->amount_due_now ?? 0) > 0)
            ? $order->amount_due_now
            : ($order->order_total_snapshot ?? $order->total_amount);

        $idempotencyKey = $context['idempotency_key'] ?? $reference;

        $payment = Payment::create([
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'provider' => $context['provider'] ?? 'square',
            'currency' => $currency,
            'amount' => $amount,
            'status' => PaymentStatus::Pending,
            'reference' => $reference,
            'idempotency_key' => $idempotencyKey,
            'metadata' => $context['metadata'] ?? null,
        ]);

        $this->addEvent($payment, PaymentEventSource::System, PaymentEventType::CREATED, [
            'amount' => (float) $amount,
            'currency' => $currency,
        ], 'Payment created for order');

        return $payment;
    }

    /**
     * Create a payment attempt and record it.
     */
    public function createAttempt(Payment $payment, array $requestPayload = []): PaymentAttempt
    {
        $attemptNo = (int) $payment->attempts()->max('attempt_no') + 1;

        $attempt = $payment->attempts()->create([
            'provider' => $payment->provider,
            'attempt_no' => $attemptNo,
            'request_payload' => $requestPayload ?: null,
            'status' => 'created',
        ]);

        $this->addEvent($payment, PaymentEventSource::System, PaymentEventType::ATTEMPT_CREATED, [
            'attempt_id' => $attempt->id,
            'attempt_no' => $attemptNo,
        ]);

        return $attempt;
    }

    /**
     * Update a payment attempt with response payload and status.
     */
    public function updateAttemptWithResponse(PaymentAttempt $attempt, array $responsePayload, string $status): PaymentAttempt
    {
        $attempt->update([
            'response_payload' => $responsePayload,
            'status' => $status,
        ]);

        return $attempt->fresh();
    }

    /**
     * Mark payment as processing. No-op if already in a terminal state.
     */
    public function markProcessing(Payment $payment, array $meta = []): Payment
    {
        if ($payment->isTerminal()) {
            return $payment;
        }

        return DB::transaction(function () use ($payment, $meta) {
            $payment->update([
                'status' => PaymentStatus::Processing,
                'metadata' => array_merge($payment->metadata ?? [], $meta),
            ]);
            $this->addEvent($payment, PaymentEventSource::System, PaymentEventType::PROCESSING, $meta);
            return $payment->fresh();
        });
    }

    /**
     * Mark payment as paid. Sets paid_at. Idempotent for already-paid payments.
     */
    public function markPaid(Payment $payment, array $meta = []): Payment
    {
        if ($payment->isPaid()) {
            return $payment;
        }

        if ($payment->isTerminal()) {
            throw new \InvalidArgumentException('Cannot mark as paid: payment is in terminal state.');
        }

        return DB::transaction(function () use ($payment, $meta) {
            $payment->update([
                'status' => PaymentStatus::Paid,
                'paid_at' => $payment->paid_at ?? now(),
                'failure_code' => null,
                'failure_message' => null,
                'metadata' => array_merge($payment->metadata ?? [], $meta),
            ]);
            $this->addEvent($payment, PaymentEventSource::System, PaymentEventType::PAID, $meta);
            return $payment->fresh();
        });
    }

    /**
     * Mark payment as failed.
     */
    public function markFailed(Payment $payment, ?string $code = null, ?string $message = null, array $meta = []): Payment
    {
        if ($payment->isTerminal()) {
            return $payment;
        }

        return DB::transaction(function () use ($payment, $code, $message, $meta) {
            $payment->update([
                'status' => PaymentStatus::Failed,
                'failure_code' => $code,
                'failure_message' => $message,
                'metadata' => array_merge($payment->metadata ?? [], $meta),
            ]);
            $this->addEvent($payment, PaymentEventSource::System, PaymentEventType::FAILED, array_merge($meta, [
                'failure_code' => $code,
                'failure_message' => $message,
            ]));
            return $payment->fresh();
        });
    }

    /**
     * Mark payment as cancelled.
     */
    public function markCancelled(Payment $payment, array $meta = []): Payment
    {
        if ($payment->isTerminal()) {
            return $payment;
        }

        return DB::transaction(function () use ($payment, $meta) {
            $payment->update([
                'status' => PaymentStatus::Cancelled,
                'metadata' => array_merge($payment->metadata ?? [], $meta),
            ]);
            $this->addEvent($payment, PaymentEventSource::System, PaymentEventType::CANCELLED, $meta);
            return $payment->fresh();
        });
    }

    /**
     * Add an event to the payment log.
     */
    public function addEvent(
        Payment $payment,
        PaymentEventSource|string $source,
        string $eventType,
        array $payload = [],
        ?string $notes = null
    ): \App\Models\PaymentEvent {
        $sourceValue = $source instanceof PaymentEventSource ? $source->value : $source;

        return $payment->events()->create([
            'source' => $sourceValue,
            'event_type' => $eventType,
            'payload' => $payload ?: null,
            'notes' => $notes,
        ]);
    }
}
