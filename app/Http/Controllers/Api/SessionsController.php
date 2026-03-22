<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        return response()->json($tokens->map(fn (PersonalAccessToken $t) => [
            'id' => (string) $t->id,
            'device_name' => $t->name ?: 'Session',
            'location' => '',
            'last_active' => ($t->last_used_at ?? $t->created_at)?->toIso8601String(),
            'client_info' => $t->name ?: '',
            'is_current' => $current ? ($t->id === $current->id) : false,
            'created_at' => $t->created_at?->toIso8601String(),
        ])->values());
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

        return response()->json($tokens->map(fn (PersonalAccessToken $t) => [
            'id' => (string) $t->id,
            'location' => '',
            'device' => $t->name ?: 'Unknown device',
            'timestamp' => ($t->last_used_at ?? $t->created_at)?->toIso8601String() ?? '',
            'last_active' => ($t->last_used_at ?? $t->created_at)?->toIso8601String() ?? '',
        ])->values());
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
