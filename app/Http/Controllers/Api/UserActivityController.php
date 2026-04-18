<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserActivityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $limit = min(100, max(1, (int) $request->query('limit', 30)));

        $q = UserActivity::query()->where('user_id', $user->id)->orderByDesc('created_at');

        $rows = $q->limit($limit)->get();

        return response()->json([
            'activities' => $rows->map(fn (UserActivity $a) => [
                'id' => (string) $a->id,
                'action_type' => $a->action_type,
                'title' => $a->title,
                'description' => $a->description,
                'meta' => $a->meta ?? [],
                'created_at' => $a->created_at?->toIso8601String(),
            ]),
        ]);
    }
}
