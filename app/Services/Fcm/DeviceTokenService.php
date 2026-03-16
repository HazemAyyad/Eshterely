<?php

namespace App\Services\Fcm;

use App\Models\User;
use App\Models\UserDeviceToken;
use Illuminate\Support\Str;

/**
 * FCM device token management: upsert, refresh, deactivate. Multiple active tokens per user.
 */
class DeviceTokenService
{
    /**
     * Register or refresh a device token. Sets is_active, last_seen_at, platform (from device_type if needed).
     */
    public function upsertToken(
        User $user,
        string $fcmToken,
        ?string $deviceType = null,
        ?string $platform = null,
        ?string $deviceName = null,
        ?string $appVersion = null
    ): UserDeviceToken {
        $platform = $platform ?? $this->normalizePlatform($deviceType);
        $record = UserDeviceToken::updateOrCreate(
            [
                'user_id' => $user->id,
                'fcm_token' => $fcmToken,
            ],
            [
                'device_type' => $deviceType ?? 'unknown',
                'platform' => $platform,
                'device_name' => $deviceName,
                'app_version' => $appVersion,
                'last_seen_at' => now(),
                'is_active' => true,
            ]
        );

        return $record;
    }

    /**
     * Mark a token as inactive (e.g. after FCM reports invalid).
     */
    public function deactivateToken(UserDeviceToken $token): void
    {
        $token->update(['is_active' => false]);
    }

    /**
     * Deactivate by raw fcm_token string (e.g. from send failure).
     */
    public function deactivateByToken(string $fcmToken): void
    {
        UserDeviceToken::where('fcm_token', $fcmToken)->update(['is_active' => false]);
    }

    /**
     * Get all active FCM tokens for a user.
     *
     * @return array<string>
     */
    public function getActiveTokensForUser(User $user): array
    {
        return $user->deviceTokens()
            ->active()
            ->pluck('fcm_token')
            ->unique()
            ->values()
            ->all();
    }

    private function normalizePlatform(?string $deviceType): string
    {
        if (empty($deviceType)) {
            return 'unknown';
        }
        $t = Str::lower($deviceType);
        if (in_array($t, ['android', 'ios', 'web'], true)) {
            return $t;
        }
        if (Str::contains($t, 'android')) {
            return self::PLATFORM_ANDROID;
        }
        if (Str::contains($t, 'ios') || Str::contains($t, 'iphone')) {
            return self::PLATFORM_IOS;
        }
        return $t;
    }
}
