<?php

namespace App\Services\Fcm;

use App\Models\User;
use App\Models\UserDeviceToken;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\MulticastSendReport;
use Kreait\Firebase\Messaging\Notification;

/**
 * Send FCM messages to tokens or users. Handles invalid tokens gracefully and logs results.
 */
class FcmNotificationService
{
    private const LOG_PREFIX = 'FCM_SEND';

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
            $this->logFcm('warning', self::LOG_PREFIX . ' no tokens for user', [
                'delivery_mode' => 'single',
                'user_id' => $user->id,
                'notification_title' => $title,
                'notification_body' => $body,
                'has_notification' => true,
                'has_data' => $data !== null && $data !== [],
                'token_count' => 0,
                'reason' => 'No active FCM tokens for user',
                'timestamp' => now()->toIso8601String(),
            ]);
            return [
                'sent' => 0,
                'failed' => 0,
                'invalid_tokens' => [],
                'summary_message' => 'No active tokens for user',
            ];
        }
        $logContext = ['delivery_mode' => 'single', 'user_id' => $user->id];
        return $this->sendToTokens($tokens, $title, $body, $imageUrl, $data, $logContext);
    }

    /**
     * Send to specific tokens. Invalid/unknown tokens are deactivated. Returns summary.
     *
     * @param list<string> $tokens
     * @param array{delivery_mode?: string, user_id?: int, user_count?: int} $logContext
     * @return array{sent: int, failed: int, invalid_tokens: list<string>, summary_message: string}
     */
    public function sendToTokens(
        array $tokens,
        string $title,
        string $body,
        ?string $imageUrl = null,
        ?array $data = null,
        array $logContext = []
    ): array {
        $tokens = array_values(array_unique(array_filter($tokens)));
        if ($tokens === []) {
            $this->logFcm('warning', self::LOG_PREFIX . ' no tokens provided', [
                'delivery_mode' => $logContext['delivery_mode'] ?? 'tokens',
                'user_id' => $logContext['user_id'] ?? null,
                'notification_title' => $title,
                'notification_body' => $body,
                'has_notification' => true,
                'has_data' => false,
                'token_count' => 0,
                'reason' => 'No tokens provided',
                'timestamp' => now()->toIso8601String(),
            ]);
            return [
                'sent' => 0,
                'failed' => 0,
                'invalid_tokens' => [],
                'summary_message' => 'No tokens provided',
            ];
        }

        if (! app()->bound(Messaging::class)) {
            $this->logFcm('error', self::LOG_PREFIX . ' FCM not configured', [
                'delivery_mode' => $logContext['delivery_mode'] ?? 'tokens',
                'user_id' => $logContext['user_id'] ?? null,
                'notification_title' => $title,
                'notification_body' => $body,
                'has_notification' => true,
                'has_data' => $data !== null && $data !== [],
                'data_keys' => $data !== null ? array_keys($data) : [],
                'token_count' => count($tokens),
                'fcm_tokens_masked' => $this->maskTokens($tokens),
                'reason' => 'FCM not configured (missing credentials)',
                'timestamp' => now()->toIso8601String(),
            ]);
            return [
                'sent' => 0,
                'failed' => count($tokens),
                'invalid_tokens' => [],
                'summary_message' => 'FCM not configured (missing credentials)',
            ];
        }

        $payloadInfo = [
            'delivery_mode' => $logContext['delivery_mode'] ?? 'tokens',
            'user_id' => $logContext['user_id'] ?? null,
            'notification_title' => $title,
            'notification_body' => $body,
            'notification_image' => $imageUrl !== null,
            'has_notification' => true,
            'has_data' => $data !== null && $data !== [],
            'data_keys' => $data !== null ? array_keys($data) : [],
            'token_count' => count($tokens),
            'fcm_tokens_masked' => $this->maskTokens($tokens),
            'timestamp' => now()->toIso8601String(),
        ];
        $this->logFcm('info', self::LOG_PREFIX . ' attempt', $payloadInfo);

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
            $this->logFcm('error', self::LOG_PREFIX . ' exception', [
                'delivery_mode' => $logContext['delivery_mode'] ?? 'tokens',
                'user_id' => $logContext['user_id'] ?? null,
                'notification_title' => $title,
                'notification_body' => $body,
                'has_notification' => true,
                'has_data' => $data !== null && $data !== [],
                'data_keys' => $data !== null ? array_keys($data) : [],
                'token_count' => count($tokens),
                'fcm_tokens_masked' => $this->maskTokens($tokens),
                'exception_class' => $e::class,
                'exception_message' => $e->getMessage(),
                'timestamp' => now()->toIso8601String(),
            ]);
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

        $perTokenResults = $this->buildPerTokenResults($report, $tokens);
        $logLevel = $failed > 0 ? 'warning' : 'info';
        $logMessage = $failed > 0 ? self::LOG_PREFIX . ' partial_or_failed' : self::LOG_PREFIX . ' success';
        $this->logFcm($logLevel, $logMessage, [
            'delivery_mode' => $logContext['delivery_mode'] ?? 'tokens',
            'user_id' => $logContext['user_id'] ?? null,
            'notification_title' => $title,
            'notification_body' => $body,
            'sent' => $sent,
            'failed' => $failed,
            'invalid_count' => count($invalid),
            'invalid_tokens_masked' => $this->maskTokens($invalid),
            'firebase_response_summary' => $summary,
            'per_token_results' => $perTokenResults,
            'timestamp' => now()->toIso8601String(),
        ]);

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
        $userList = $users instanceof \Traversable ? iterator_to_array($users) : $users;
        foreach ($userList as $user) {
            if ($user instanceof User) {
                $allTokens = array_merge($allTokens, $this->tokenService->getActiveTokensForUser($user));
            }
        }
        $allTokens = array_values(array_unique($allTokens));
        $logContext = ['delivery_mode' => 'bulk', 'user_count' => count($userList)];
        return $this->sendToTokens($allTokens, $title, $body, $imageUrl, $data, $logContext);
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

    /**
     * Build full system notification data for FCM: title, body, type, reference_id + deep-link fields.
     * All values are stringified for FCM data payload.
     *
     * @param array<string, mixed> $meta
     */
    public static function systemEventData(
        string $type,
        string $referenceId,
        string $title,
        string $body,
        ?string $targetType = null,
        ?string $targetId = null,
        ?string $routeKey = null,
        array $meta = []
    ): array {
        $data = [
            'type' => $type,
            'reference_id' => (string) $referenceId,
            'title' => $title,
            'body' => $body,
        ];
        $base = self::dataPayload($targetType, $targetId, $routeKey, $meta);
        return array_merge($data, $base);
    }

    /**
     * Write to FCM log channel (or stack with prefix). Production-safe.
     */
    private function logFcm(string $level, string $message, array $context = []): void
    {
        $channel = $this->getFcmLogChannel();
        Log::channel($channel)->log($level, $message, $context);
    }

    private function getFcmLogChannel(): string
    {
        $channels = config('logging.channels', []);

        return array_key_exists('fcm', $channels) ? 'fcm' : 'stack';
    }

    /**
     * Mask FCM token for logging (production-safe: no full tokens in logs).
     *
     * @param list<string> $tokens
     * @return list<string>
     */
    private function maskTokens(array $tokens): array
    {
        $out = [];
        foreach ($tokens as $t) {
            $out[] = self::maskToken($t);
        }
        return $out;
    }

    private static function maskToken(string $token): string
    {
        $len = strlen($token);
        if ($len <= 12) {
            return '***';
        }
        return substr($token, 0, 6) . '...' . substr($token, -4);
    }

    /**
     * Build per-token result list for logging (masked tokens, message IDs, errors).
     *
     * @param list<string> $originalTokens
     * @return list<array{token_masked: string, result: string, message_id?: string, error?: string}>
     */
    private function buildPerTokenResults(MulticastSendReport $report, array $originalTokens): array
    {
        $items = $report->getItems();
        $results = [];
        foreach ($items as $item) {
            $target = $item->target();
            $token = $target->value();
            $masked = self::maskToken($token);
            if ($item->isSuccess()) {
                $result = $item->result();
                $messageId = isset($result['name']) ? (string) $result['name'] : null;
                $results[] = array_filter([
                    'token_masked' => $masked,
                    'result' => 'success',
                    'message_id' => $messageId,
                ]);
            } else {
                $err = $item->error();
                $results[] = [
                    'token_masked' => $masked,
                    'result' => 'failure',
                    'error' => $err ? $err->getMessage() : 'unknown',
                ];
            }
        }
        return $results;
    }
}
