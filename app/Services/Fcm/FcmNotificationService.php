<?php

namespace App\Services\Fcm;

use App\Models\User;
use App\Models\UserDeviceToken;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

/**
 * Send FCM messages to tokens or users. Handles invalid tokens gracefully and logs results.
 */
class FcmNotificationService
{
    public function __construct(
        protected DeviceTokenService $tokenService
    ) {}

    /**
     * Send to one user (all active tokens). Returns summary: sent, failed, invalid_tokens, summary_message.
     *
     * @return array{sent: int, failed: int, invalid_tokens: list<string>, summary_message: string}
     */
    public function sendToUser(
        User $user,
        string $title,
        string $body,
        ?string $imageUrl = null,
        ?array $data = null
    ): array {
        $tokens = $this->tokenService->getActiveTokensForUser($user);
        if ($tokens === []) {
            return [
                'sent' => 0,
                'failed' => 0,
                'invalid_tokens' => [],
                'summary_message' => 'No active tokens for user',
            ];
        }
        return $this->sendToTokens($tokens, $title, $body, $imageUrl, $data);
    }

    /**
     * Send to specific tokens. Invalid/unknown tokens are deactivated. Returns summary.
     *
     * @param list<string> $tokens
     * @return array{sent: int, failed: int, invalid_tokens: list<string>, summary_message: string}
     */
    public function sendToTokens(
        array $tokens,
        string $title,
        string $body,
        ?string $imageUrl = null,
        ?array $data = null
    ): array {
        $tokens = array_values(array_unique(array_filter($tokens)));
        if ($tokens === []) {
            return [
                'sent' => 0,
                'failed' => 0,
                'invalid_tokens' => [],
                'summary_message' => 'No tokens provided',
            ];
        }

        if (! app()->bound(Messaging::class)) {
            return [
                'sent' => 0,
                'failed' => count($tokens),
                'invalid_tokens' => [],
                'summary_message' => 'FCM not configured (missing credentials)',
            ];
        }

        $messaging = app(Messaging::class);
        $notification = Notification::create($title, $body, $imageUrl);
        $message = CloudMessage::new()
            ->withNotification($notification);

        if ($data !== null && $data !== []) {
            $message = $message->withData($data);
        }

        try {
            $report = $messaging->sendMulticast($message, $tokens);
        } catch (\Throwable $e) {
            return [
                'sent' => 0,
                'failed' => count($tokens),
                'invalid_tokens' => [],
                'summary_message' => 'FCM error: ' . $e->getMessage(),
            ];
        }

        $invalid = array_merge($report->invalidTokens(), $report->unknownTokens());
        foreach ($invalid as $token) {
            $this->tokenService->deactivateByToken($token);
        }

        $sent = $report->successes()->count();
        $failed = $report->failures()->count();
        $summary = "sent={$sent}, failed={$failed}";
        if ($invalid !== []) {
            $summary .= ', invalid_tokens=' . count($invalid);
        }

        return [
            'sent' => $sent,
            'failed' => $failed,
            'invalid_tokens' => $invalid,
            'summary_message' => $summary,
        ];
    }

    /**
     * Send to many users (each user's active tokens). Does not dedupe tokens across users.
     *
     * @param iterable<User> $users
     * @return array{sent: int, failed: int, invalid_tokens: list<string>, summary_message: string}
     */
    public function sendToUsers(
        iterable $users,
        string $title,
        string $body,
        ?string $imageUrl = null,
        ?array $data = null
    ): array {
        $allTokens = [];
        foreach ($users as $user) {
            if ($user instanceof User) {
                $allTokens = array_merge($allTokens, $this->tokenService->getActiveTokensForUser($user));
            }
        }
        $allTokens = array_values(array_unique($allTokens));
        return $this->sendToTokens($allTokens, $title, $body, $imageUrl, $data);
    }

    /**
     * Build data payload for deep-link readiness: target_type, target_id, route_key, payload/meta.
     *
     * @param array<string, mixed> $meta
     */
    public static function dataPayload(?string $targetType = null, ?string $targetId = null, ?string $routeKey = null, array $meta = []): array
    {
        $data = [];
        if ($targetType !== null) {
            $data['target_type'] = $targetType;
        }
        if ($targetId !== null) {
            $data['target_id'] = (string) $targetId;
        }
        if ($routeKey !== null) {
            $data['route_key'] = $routeKey;
        }
        if ($meta !== []) {
            $data['payload'] = json_encode($meta);
        }
        return $data;
    }
}
