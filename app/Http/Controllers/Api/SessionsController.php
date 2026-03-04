<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SessionsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $sessions = DB::table('user_sessions')->where('user_id', $request->user()->id)->get();

        if ($sessions->isEmpty()) {
            return response()->json([[
                'id' => '1',
                'device_name' => 'Current Device',
                'location' => 'Active now',
                'last_active' => 'Active now',
                'client_info' => 'Zayer App',
                'is_current' => true,
            ]]);
        }

        return response()->json($sessions->map(fn ($s) => [
            'id' => (string) $s->id,
            'device_name' => $s->device_name ?? 'Unknown',
            'location' => $s->location ?? '',
            'last_active' => $s->last_active_at ?? '',
            'client_info' => $s->client_info ?? '',
            'is_current' => (bool) $s->is_current,
        ]));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        DB::table('user_sessions')->where('user_id', $request->user()->id)->where('id', $id)->delete();

        return response()->json(['message' => 'Session ended']);
    }
}
