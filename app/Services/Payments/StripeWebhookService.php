<?php

namespace App\Services\Payments;

use App\Enums\Payment\PaymentEventSource;
use App\Enums\Payment\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\SavedPaymentMethod;
use App\Models\Shipment;
use App\Models\WalletTopUpPayment;
use App\Services\Wallet\WalletCardVerificationNotifier;
use App\Services\Wallet\WalletTopUpCreditNotifier;
use App\Services\Wallet\WalletTopUpPaymentCompletionService;
use App\Services\Cart\RemoveOrderedCartItemsService;
use App\Services\PurchaseAssistant\PurchaseAssistantRequestStatusSync;
use App\Services\Fcm\OrderShipmentNotificationTrigger;
use App\Services\Shipments\ShipmentDraftFinalizationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StripeWebhookService
{
    public function __construct(
        protected PaymentService $paymentService,
        protected OrderShipmentNotificationTrigger $notificationTrigger,
        protected ShipmentDraftFinalizationService $shipmentDraftFinalization,
        protected WalletTopUpPaymentCompletionService $walletTopUpCompletion
    ) {}

    public function handleEvent(?string $eventType, array $payload): void
    {
        if ($eventType === null || $eventType === '') {
            return;
        }

        $dataObject = $payload['data']['object'] ?? [];
        if (! is_array($dataObject)) {
            $dataObject = [];
        }

        // Hosted Checkout events for both order payments and wallet top-ups.
        if (str_starts_with($eventType, 'checkout.session.') || str_starts_with($eventType, 'payment_intent.')) {
            $this->handleCheckoutEvent($eventType, $payload, $dataObject);
            return;
        }

        Log::debug('Stripe webhook unsupported event type', ['event_type' => $eventType]);
    }

    protected function handleCheckoutEvent(string $eventType, array $fullPayload, array $object): void
    {
        $metadata = is_array($object['metadata'] ?? null) ? $object['metadata'] : [];

        // Card verification micro-charges (PaymentIntent only — no wallet_top_up_reference).
        if (str_starts_with($eventType, 'payment_intent.')) {
            $spmId = $metadata['saved_payment_method_id'] ?? null;
            $hasTopUpRef = is_string($metadata['wallet_top_up_reference'] ?? null)
                && trim((string) $metadata['wallet_top_up_reference']) !== '';
            if (is_string($spmId) && $spmId !== '' && ! $hasTopUpRef) {
                $this->handleSavedCardVerificationIntent($eventType, $object);

                return;
            }
        }

        $paymentReference = $metadata['payment_reference'] ?? null;
        $topUpReference = $metadata['wallet_top_up_reference'] ?? null;

        $providerSessionId = $object['id'] ?? null;
        $paymentStatus = $object['payment_status'] ?? null;
        $providerStatus = is_string($paymentStatus) ? strtolower($paymentStatus) : null;

        // Determine terminal mapping for our PaymentStatus.
        $newPaymentStatus = match ($eventType) {
            'checkout.session.completed' => match ($providerStatus) {
                'paid' => PaymentStatus::Paid,
                default => PaymentStatus::Processing,
            },
            'checkout.session.async_payment_succeeded' => PaymentStatus::Paid,
            'checkout.session.async_payment_failed' => PaymentStatus::Failed,
            'checkout.session.expired' => PaymentStatus::Cancelled,
            'checkout.session.canceled' => PaymentStatus::Cancelled,
            'payment_intent.succeeded' => PaymentStatus::Paid,
            'payment_intent.processing' => PaymentStatus::Processing,
            'payment_intent.requires_action' => PaymentStatus::RequiresAction,
            'payment_intent.payment_failed' => PaymentStatus::Failed,
            'payment_intent.canceled' => PaymentStatus::Cancelled,
            default => null,
        };

        // Wallet top-up resolution
        if (is_string($topUpReference) && $topUpReference !== '') {
            $topUp = WalletTopUpPayment::where('provider', 'stripe')->where('reference', $topUpReference)->first();
            if ($topUp) {
                $this->applyTopUpStatusFromStripe($topUp, $newPaymentStatus, $eventType, $object, $fullPayload);
                return;
            }
        }

        // Order payment resolution
        if (is_string($paymentReference) && $paymentReference !== '') {
            $payment = Payment::where('provider', 'stripe')->where('reference', $paymentReference)->first();
            if ($payment) {
                $this->applyPaymentStatusFromStripe($payment, $newPaymentStatus, $eventType, $object, $fullPayload);
                return;
            }
        }

        // Fallback: resolve by provider_session_id if metadata was missing.
        if (is_string($providerSessionId) && $providerSessionId !== '') {
            $payment = Payment::where('provider', 'stripe')->where('provider_payment_id', $providerSessionId)->first();
            if ($payment) {
                $this->applyPaymentStatusFromStripe($payment, $newPaymentStatus, $eventType, $object, $fullPayload);
                return;
            }

            $topUp = WalletTopUpPayment::where('provider', 'stripe')->where('provider_payment_id', $providerSessionId)->first();
            if ($topUp) {
                $this->applyTopUpStatusFromStripe($topUp, $newPaymentStatus, $eventType, $object, $fullPayload);
                return;
            }
        }

        Log::info('Stripe webhook resolved target not found', [
            'event_type' => $eventType,
            'payment_reference' => is_string($paymentReference) ? $paymentReference : null,
            'wallet_top_up_reference' => is_string($topUpReference) ? $topUpReference : null,
            'provider_session_id' => is_string($providerSessionId) ? $providerSessionId : null,
        ]);
    }

    protected function handleSavedCardVerificationIntent(string $eventType, array $object): void
    {
        if ($eventType !== 'payment_intent.payment_failed') {
            return;
        }

        $metadata = is_array($object['metadata'] ?? null) ? $object['metadata'] : [];
        $spmId = $metadata['saved_payment_method_id'] ?? null;
        if (! is_string($spmId) || $spmId === '') {
            return;
        }

        $card = SavedPaymentMethod::query()->find($spmId);
        if ($card === null) {
            return;
        }

        DB::transaction(function () use ($card): void {
            $card->refresh();
            if ($card->verification_status !== SavedPaymentMethod::STATUS_PENDING) {
                return;
            }
            $card->update(['verification_status' => SavedPaymentMethod::STATUS_FAILED]);
        });

        app(WalletCardVerificationNotifier::class)->notifyFailed($card->fresh());
    }

    protected function applyPaymentStatusFromStripe(
        Payment $payment,
        ?PaymentStatus $newStatus,
        string $eventType,
        array $stripeObject,
        array $fullPayload
    ): void {
        if ($newStatus === null) {
            return;
        }

        // Avoid duplicate transitions for already terminal payments.
        if ($payment->status->isTerminal()) {
            return;
        }

        $this->addWebhookEvent($payment, $eventType, $fullPayload);

        DB::transaction(function () use ($payment, $newStatus, $stripeObject): void {
            $payment->refresh();

            if ($payment->status->isTerminal()) {
                return;
            }

            $updates = [];
            if (is_string($stripeObject['id'] ?? null) && ($payment->provider_payment_id === null || $payment->provider_payment_id === '')) {
                $updates['provider_payment_id'] = $stripeObject['id'];
            }

            // Store provider reference to help reconciliation.
            if (is_string($stripeObject['metadata']['order_id'] ?? null) && ($payment->provider_order_id === null || $payment->provider_order_id === '')) {
                $updates['provider_order_id'] = (string) $stripeObject['metadata']['order_id'];
            }

            $updates['status'] = $newStatus;
            if ($newStatus === PaymentStatus::Paid) {
                $updates['paid_at'] = $payment->paid_at ?? now();
                $updates['failure_code'] = null;
                $updates['failure_message'] = null;
            }

            $payment->update($updates);

            if ($newStatus === PaymentStatus::Paid) {
                $this->syncOrderToPaid($payment);
                $this->finalizeOutboundShipmentAfterGatewayPayment($payment);
                $this->notificationTrigger->onPaymentSuccess($payment);
            } elseif ($newStatus === PaymentStatus::Failed) {
                $this->notificationTrigger->onPaymentFailed($payment);
            }
        });
    }

    protected function applyTopUpStatusFromStripe(
        WalletTopUpPayment $topUp,
        ?PaymentStatus $newStatus,
        string $eventType,
        array $stripeObject,
        array $fullPayload
    ): void {
        if ($newStatus === null) {
            return;
        }

        // wallet topup statuses are strings, but mapping follows our PaymentStatus.
        $newStatusString = match ($newStatus) {
            PaymentStatus::Paid => 'paid',
            PaymentStatus::Processing => 'processing',
            PaymentStatus::Failed => 'failed',
            PaymentStatus::Cancelled => 'cancelled',
            PaymentStatus::RequiresAction => 'processing',
            default => null,
        };

        if ($newStatusString === null) {
            return;
        }

        $creditedThisRun = false;

        DB::transaction(function () use ($topUp, $newStatusString, $stripeObject, &$creditedThisRun): void {
            $topUp->refresh();

            // Idempotency: if already terminal, do nothing.
            if (in_array($topUp->status, ['paid', 'failed', 'cancelled'], true)) {
                return;
            }

            $updates = [];
            if (is_string($stripeObject['id'] ?? null) && ($topUp->provider_payment_id === null || $topUp->provider_payment_id === '')) {
                $updates['provider_payment_id'] = $stripeObject['id'];
            }
            if (is_string($stripeObject['metadata']['wallet_top_up_id'] ?? null) && ($topUp->provider_order_id === null || $topUp->provider_order_id === '')) {
                $updates['provider_order_id'] = (string) $stripeObject['metadata']['wallet_top_up_id'];
            }

            if ($topUp->status !== $newStatusString) {
                $updates['status'] = $newStatusString;
                if ($newStatusString === 'paid') {
                    $updates['paid_at'] = $topUp->paid_at ?? now();
                    $updates['failure_code'] = null;
                    $updates['failure_message'] = null;
                }
            }

            if ($updates !== []) {
                $topUp->update($updates);
            }

            // Credit wallet exactly once for paid top-ups.
            if ($newStatusString === 'paid') {
                $topUp->refresh();
                if ($this->walletTopUpCompletion->creditWalletIfPaidAndNotYetCredited($topUp)) {
                    $creditedThisRun = true;
                }
            }
        });

        if ($creditedThisRun) {
            DB::afterCommit(function () use ($topUp): void {
                app(WalletTopUpCreditNotifier::class)->notifyCredited($topUp->fresh());
            });
        }
    }

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
            $order = Order::find($payment->order_id);
            if ($order) {
                app(PurchaseAssistantRequestStatusSync::class)->onOrderMarkedPaid($order);
            }
        }

        (app(RemoveOrderedCartItemsService::class))((int) $payment->order_id);
    }

    protected function addWebhookEvent(Payment $payment, string $eventType, array $payload): void
    {
        $safePayload = $this->sanitizePayloadForLog($payload);

        // PaymentEventType is optional here; we store $eventType as event_type for traceability.
        $this->paymentService->addEvent(
            $payment,
            PaymentEventSource::Webhook,
            $eventType,
            $safePayload,
            'Stripe webhook: ' . $eventType
        );
    }

    protected function sanitizePayloadForLog(array $payload): array
    {
        $allowed = ['id', 'type', 'created', 'event_id', 'data'];
        $out = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $payload)) {
                $out[$key] = $payload[$key];
            }
        }

        return $out;
    }
}

