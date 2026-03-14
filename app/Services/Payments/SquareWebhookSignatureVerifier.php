<?php

namespace App\Services\Payments;

use Square\Utils\WebhooksHelper;

/**
 * Verifies Square webhook signatures using the official SDK.
 * Can be mocked in tests by binding a custom implementation.
 */
class SquareWebhookSignatureVerifier
{
    public function verify(string $rawBody, string $signatureHeader): bool
    {
        $signatureKey = config('square.webhook_signature_key', '');
        $notificationUrl = config('square.webhook_notification_url', '');

        if ($signatureKey === '' || $notificationUrl === '') {
            return false;
        }

        return WebhooksHelper::verifySignature(
            $rawBody,
            $signatureHeader,
            $signatureKey,
            $notificationUrl
        );
    }
}
