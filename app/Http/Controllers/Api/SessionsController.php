<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\PersonalAccessToken;

class SessionsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $current = $user->currentAccessToken();

        $tokens = PersonalAccessToken::query()
            ->where('tokenable_type', $user::class)
            ->where('tokenable_id', $user->id)
            ->orderByDesc('last_used_at')
            ->orderByDesc('created_at')
            ->get();

        $hasMeta = Schema::hasColumn('personal_access_tokens', 'device_type');

        $hasDeviceModel = Schema::hasColumn('personal_access_tokens', 'device_model');

        return response()->json($tokens->map(function (PersonalAccessToken $t) use ($current, $hasMeta, $hasDeviceModel) {
            $deviceType = $hasMeta ? (string) ($t->getAttribute('device_type') ?? '') : '';
            $deviceModel = $hasDeviceModel ? (string) ($t->getAttribute('device_model') ?? '') : '';
            $location = '';
            if ($hasMeta) {
                $location = (string) ($t->getAttribute('location_label') ?? '');
                if ($location === '') {
                    $ip = (string) ($t->getAttribute('ip_address') ?? '');
                    $location = $ip !== '' ? 'IP: '.$ip : '';
                }
            }

            // Primary title: rich model (e.g. Xiaomi Redmi … · Android 13), else token name + platform.
            $deviceLabel = $deviceModel !== ''
                ? $deviceModel
                : (($t->name ?: 'Session').($deviceType !== '' ? ' · '.$deviceType : ''));

            if ($deviceModel !== '' && $deviceType !== '') {
                $clientInfo = strtoupper($deviceType).' · '.$deviceModel;
            } elseif ($deviceModel !== '') {
                $clientInfo = $deviceModel;
            } elseif ($deviceType !== '') {
                $clientInfo = strtoupper($deviceType);
            } else {
                $clientInfo = $t->name ?: 'API';
            }

            $ip = $hasMeta ? (string) ($t->getAttribute('ip_address') ?? '') : '';

            return [
                'id' => (string) $t->id,
                'device_name' => $deviceLabel,
                'device_type' => $deviceType,
                'device_model' => $deviceModel,
                'location' => $location,
                'last_active' => ($t->last_used_at ?? $t->created_at)?->toIso8601String(),
                'client_info' => $clientInfo,
                'is_current' => $current ? ($t->id === $current->id) : false,
                'created_at' => $t->created_at?->toIso8601String(),
                'ip_address' => $ip !== '' ? $ip : null,
            ];
        })->values());
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $current = $user->currentAccessToken();
        if ($current && $current->id === $id) {
            return response()->json(['message' => 'Cannot end current session'], 422);
        }

        PersonalAccessToken::query()
            ->where('tokenable_type', $user::class)
            ->where('tokenable_id', $user->id)
            ->whereKey($id)
            ->delete();

        return response()->json(['message' => 'Session ended']);
    }

    /**
     * Recent sign-in activity derived from Sanctum personal access tokens.
     */
    public function loginHistory(Request $request): JsonResponse
    {
        $user = $request->user();
        $tokens = PersonalAccessToken::query()
            ->where('tokenable_type', $user::class)
            ->where('tokenable_id', $user->id)
            ->orderByDesc('last_used_at')
            ->orderByDesc('created_at')
            ->limit(30)
            ->get();

        $hasMeta = Schema::hasColumn('personal_access_tokens', 'device_type');
        $hasDeviceModel = Schema::hasColumn('personal_access_tokens', 'device_model');

        return response()->json($tokens->map(function (PersonalAccessToken $t) use ($hasMeta, $hasDeviceModel) {
            $deviceType = $hasMeta ? (string) ($t->getAttribute('device_type') ?? '') : '';
            $deviceModel = $hasDeviceModel ? (string) ($t->getAttribute('device_model') ?? '') : '';
            $location = '';
            if ($hasMeta) {
                $location = (string) ($t->getAttribute('location_label') ?? '');
                if ($location === '') {
                    $ip = (string) ($t->getAttribute('ip_address') ?? '');
                    $location = $ip !== '' ? 'IP: '.$ip : '';
                }
            }
            $device = $deviceModel !== ''
                ? $deviceModel
                : (($t->name ?: 'Unknown device').($deviceType !== '' ? ' · '.$deviceType : ''));

            $ip = $hasMeta ? (string) ($t->getAttribute('ip_address') ?? '') : '';

            return [
                'id' => (string) $t->id,
                'location' => $location,
                'device' => $device,
                'device_type' => $deviceType,
                'device_model' => $deviceModel,
                'timestamp' => ($t->last_used_at ?? $t->created_at)?->toIso8601String() ?? '',
                'last_active' => ($t->last_used_at ?? $t->created_at)?->toIso8601String() ?? '',
                'ip_address' => $ip !== '' ? $ip : null,
            ];
        })->values());
    }

    public function revokeOthers(Request $request): JsonResponse
    {
        $user = $request->user();
        $current = $user->currentAccessToken();
        if (! $current) {
            return response()->json(['message' => 'No current session'], 422);
        }

        $user->tokens()->whereKeyNot($current->id)->delete();

        return response()->json(['message' => 'Other sessions ended']);
    }

    /**
     * End every session including the current token (logout from all devices).
     */
    public function revokeAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'All sessions ended']);
    }
}
