<?php

namespace App\Support;

/**
 * Values for user_activities.action_type (and API filters).
 */
final class UserActivityAction
{
    public const LOGIN = 'login';

    public const LOGIN_NEW_DEVICE = 'login_new_device';

    public const PA_REQUEST_CREATED = 'request_created';

    public const PA_REQUEST_DELETED = 'request_deleted';

    public const PA_PAYMENT_STARTED = 'payment_started';

    /** Purchase Assistant: order paid */
    public const PA_PAYMENT_COMPLETED = 'payment_completed';

    public const ORDER_CREATED = 'order_created';

    public const ORDER_PAID = 'order_paid';

    public const SHIPMENT_CREATED = 'shipment_created';

    public const SHIPMENT_SHIPPED = 'shipment_shipped';

    public const SHIPMENT_DELIVERED = 'shipment_delivered';

    public const DELIVERY_CONFIRMED_BY_USER = 'delivery_confirmed_by_user';

    public const RATING_SUBMITTED = 'rating_submitted';

    public const WALLET_TOPUP = 'wallet_topup';

    public const WALLET_PAYMENT = 'wallet_payment';

    public const REFUND_RECEIVED = 'refund_received';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::LOGIN,
            self::LOGIN_NEW_DEVICE,
            self::PA_REQUEST_CREATED,
            self::PA_REQUEST_DELETED,
            self::PA_PAYMENT_STARTED,
            self::PA_PAYMENT_COMPLETED,
            self::ORDER_CREATED,
            self::ORDER_PAID,
            self::SHIPMENT_CREATED,
            self::SHIPMENT_SHIPPED,
            self::SHIPMENT_DELIVERED,
            self::DELIVERY_CONFIRMED_BY_USER,
            self::RATING_SUBMITTED,
            self::WALLET_TOPUP,
            self::WALLET_PAYMENT,
            self::REFUND_RECEIVED,
        ];
    }
}
