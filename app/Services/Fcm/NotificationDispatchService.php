<?php

namespace App\Services\Fcm;

use App\Models\Admin;
use App\Models\NotificationDispatch;
use App\Models\Order;
use App\Models\OrderShipment;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Dispatch FCM notifications and persist send history for auditability.
 */
class NotificationDispatchService
{
    public function __construct(
        protected FcmNotificationService $fcm,
        protected DeviceTokenService $tokenService
    ) {}

    /**
     * Send to one user and log. Optional deep-link meta.
     *
     * @param array{target_type?: string, target_id?: string, route_key?: string, payload?: string}|null $meta
     */
    public function sendToUser(
        User $user,
        string $title,
        string $body,
        ?string $imageUrl = null,
        ?array $data = null,
        ?array $meta = null,
        ?Admin $createdBy = null
    ): NotificationDispatch {
        $dispatch = $this->createDispatchRecord(
            NotificationDispatch::TYPE_INDIVIDUAL,
            $title,
            $body,
            'user_ids',
            $user->id,
            null,
            null,
            $meta,
            $createdBy
        );

        $result = $this->fcm->sendToUser($user, $title, $body, $imageUrl, $this->stringifyData($data));
        $this->updateDispatchFromResult($dispatch, $result);
        return $dispatch->fresh();
    }

    /**
     * Send to specific tokens (e.g. from user's devices) and log.
     */
    public function sendToTokens(
        array $tokens,
        string $title,
        string $body,
        ?string $imageUrl = null,
        ?array $data = null,
        ?array $meta = null,
        ?Admin $createdBy = null,
        ?int $userId = null
    ): NotificationDispatch {
        $dispatch = $this->createDispatchRecord(
            NotificationDispatch::TYPE_INDIVIDUAL,
            $title,
            $body,
            'tokens',
            $userId,
            null,
            null,
            $meta,
            $createdBy
        );

        $result = $this->fcm->sendToTokens($tokens, $title, $body, $imageUrl, $this->stringifyData($data));
        $this->updateDispatchFromResult($dispatch, $result);
        return $dispatch->fresh();
    }

    /**
     * Bulk send to all users (or selected user IDs) and log.
     *
     * @param list<int>|null $userIds null = all users
     */
    public function sendBulk(
        string $title,
        string $body,
        ?string $imageUrl = null,
        ?array $data = null,
        ?array $meta = null,
        ?Admin $createdBy = null,
        ?array $userIds = null
    ): NotificationDispatch {
        $scope = $userIds === null ? 'all_users' : 'user_ids';
        $dispatch = $this->createDispatchRecord(
            NotificationDispatch::TYPE_BULK,
            $title,
            $body,
            $scope,
            null,
            null,
            null,
            $meta,
            $createdBy
        );

        if ($userIds !== null && $userIds !== []) {
            $users = User::whereIn('id', $userIds)->get();
        } else {
            $users = User::all();
        }
        $result = $this->fcm->sendToUsers($users, $title, $body, $imageUrl, $this->stringifyData($data));
        $this->updateDispatchFromResult($dispatch, $result);
        return $dispatch->fresh();
    }

    /**
     * System event: send to order's user and log with order/shipment context.
     */
    public function sendSystemEvent(
        string $title,
        string $body,
        User $user,
        ?array $data = null,
        ?int $orderId = null,
        ?int $shipmentId = null,
        ?array $meta = null
    ): NotificationDispatch {
        $dispatch = $this->createDispatchRecord(
            NotificationDispatch::TYPE_SYSTEM_EVENT,
            $title,
            $body,
            'user_ids',
            $user->id,
            $orderId,
            $shipmentId,
            $meta,
            null
        );

        $result = $this->fcm->sendToUser($user, $title, $body, null, $this->stringifyData($data));
        $this->updateDispatchFromResult($dispatch, $result);
        return $dispatch->fresh();
    }

    private function createDispatchRecord(
        string $type,
        string $title,
        string $body,
        string $targetScope,
        ?int $userId,
        ?int $orderId,
        ?int $shipmentId,
        ?array $meta,
        ?Admin $createdBy
    ): NotificationDispatch {
        return NotificationDispatch::create([
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'target_scope' => $targetScope,
            'user_id' => $userId,
            'order_id' => $orderId,
            'shipment_id' => $shipmentId,
            'send_status' => NotificationDispatch::STATUS_PENDING,
            'provider_response_summary' => null,
            'created_by' => $createdBy?->id,
            'meta' => $meta,
        ]);
    }

    /**
     * @param array{sent: int, failed: int, invalid_tokens: list<string>, summary_message: string} $result
     */
    private function updateDispatchFromResult(NotificationDispatch $dispatch, array $result): void
    {
        $status = NotificationDispatch::STATUS_SENT;
        if ($result['sent'] === 0 && $result['failed'] > 0) {
            $status = NotificationDispatch::STATUS_FAILED;
        } elseif ($result['failed'] > 0 && $result['sent'] > 0) {
            $status = NotificationDispatch::STATUS_PARTIAL;
        }
        $dispatch->update([
            'send_status' => $status,
            'provider_response_summary' => $result['summary_message'],
        ]);
    }

    /** FCM data values must be strings. */
    private function stringifyData(?array $data): ?array
    {
        if ($data === null || $data === []) {
            return null;
        }
        $out = [];
        foreach ($data as $k => $v) {
            $out[(string) $k] = is_scalar($v) ? (string) $v : json_encode($v);
        }
        return $out;
    }
}
