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
}
