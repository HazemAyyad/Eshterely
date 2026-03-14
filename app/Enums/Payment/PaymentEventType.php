<?php

namespace App\Enums\Payment;

/**
 * Event types for payment lifecycle. Extend as needed for Square webhooks etc.
 */
final class PaymentEventType
{
    public const CREATED = 'payment.created';
    public const PENDING = 'payment.pending';
    public const REQUIRES_ACTION = 'payment.requires_action';
    public const PROCESSING = 'payment.processing';
    public const PAID = 'payment.paid';
    public const FAILED = 'payment.failed';
    public const CANCELLED = 'payment.cancelled';
    public const REFUNDED = 'payment.refunded';
    public const ATTEMPT_CREATED = 'payment.attempt.created';
    public const ATTEMPT_SUCCEEDED = 'payment.attempt.succeeded';
    public const ATTEMPT_FAILED = 'payment.attempt.failed';

    public static function all(): array
    {
        return [
            self::CREATED,
            self::PENDING,
            self::REQUIRES_ACTION,
            self::PROCESSING,
            self::PAID,
            self::FAILED,
            self::CANCELLED,
            self::REFUNDED,
            self::ATTEMPT_CREATED,
            self::ATTEMPT_SUCCEEDED,
            self::ATTEMPT_FAILED,
        ];
    }
}
