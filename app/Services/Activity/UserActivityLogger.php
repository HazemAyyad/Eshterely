<?php

namespace App\Services\Activity;

use App\Models\User;
use App\Models\UserActivity;
use App\Support\UserActivityAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\PersonalAccessToken;

class UserActivityLogger
{
    public function log(
        User $user,
        string $actionType,
        string $title,
        ?string $description = null,
        array $meta = [],
        ?Request $request = null,
    ): void {
        if (! Schema::hasTable('user_activities')) {
            return;
        }

        $ip = $request?->ip();
        $ua = $request?->userAgent();

        UserActivity::query()->create([
            'user_id' => $user->id,
            'action_type' => $actionType,
            'title' => $title,
            'description' => $description,
            'meta' => $meta === [] ? null : $meta,
            'ip_address' => is_string($ip) ? mb_substr($ip, 0, 45) : null,
            'user_agent' => is_string($ua) ? mb_substr($ua, 0, 2000) : null,
            'created_at' => now(),
        ]);
    }

    /**
     * Compare incoming request device fingerprint with existing Sanctum tokens (excluding the new one).
     */
    public function isNewDevice(User $user, Request $request, ?int $newTokenId = null): bool
    {
        if (! Schema::hasTable('personal_access_tokens')) {
            return true;
        }

        $incoming = $this->requestDeviceFingerprint($request);
        $q = PersonalAccessToken::query()
            ->where('tokenable_type', $user::class)
            ->where('tokenable_id', $user->id);
        if ($newTokenId !== null) {
            $q->whereKeyNot($newTokenId);
        }
        $existing = $q->get();
        if ($existing->isEmpty()) {
            return true;
        }

        foreach ($existing as $t) {
            if ($this->tokenFingerprint($t) === $incoming) {
                return false;
            }
        }

        return true;
    }

    public function requestDeviceFingerprint(Request $request): string
    {
        $dt = strtolower(trim((string) $request->input('device_type', '')));
        $dm = strtolower(trim((string) ($request->input('device_model') ?: $request->input('device_name', ''))));

        return sha1($dt.'|'.$dm);
    }

    private function tokenFingerprint(PersonalAccessToken $t): string
    {
        $dt = strtolower(trim((string) ($t->getAttribute('device_type') ?? '')));
        $dm = strtolower(trim((string) ($t->getAttribute('device_model') ?? '')));

        return sha1($dt.'|'.$dm);
    }

    public function logAuthLogin(User $user, Request $request, bool $newDevice, ?int $newTokenId = null): void
    {
        if ($newDevice) {
            $this->log(
                $user,
                UserActivityAction::LOGIN_NEW_DEVICE,
                'Logged in from a new device',
                null,
                [
                    'token_id' => $newTokenId,
                ],
                $request
            );
        } else {
            $this->log(
                $user,
                UserActivityAction::LOGIN,
                'Signed in',
                null,
                [
                    'token_id' => $newTokenId,
                ],
                $request
            );
        }
    }
}
