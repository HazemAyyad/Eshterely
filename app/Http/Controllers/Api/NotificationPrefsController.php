<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationPrefsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $prefs = DB::table('notification_prefs')->where('user_id', $request->user()->id)->first();

        return response()->json($prefs ? [
            'push_enabled' => (bool) ($prefs->push_enabled ?? true),
            'email_enabled' => (bool) ($prefs->email_enabled ?? true),
            'sms_enabled' => (bool) ($prefs->sms_enabled ?? false),
            'live_status_updates' => (bool) ($prefs->live_status_updates ?? true),
            'quiet_hours_enabled' => (bool) ($prefs->quiet_hours_enabled ?? true),
            'quiet_hours_from' => $prefs->quiet_hours_from ?? '22:00',
            'quiet_hours_to' => $prefs->quiet_hours_to ?? '07:00',
        ] : [
            'push_enabled' => true,
            'email_enabled' => true,
            'sms_enabled' => false,
            'live_status_updates' => true,
            'quiet_hours_enabled' => true,
            'quiet_hours_from' => '22:00',
            'quiet_hours_to' => '07:00',
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'push_enabled' => 'boolean',
            'email_enabled' => 'boolean',
            'sms_enabled' => 'boolean',
            'live_status_updates' => 'boolean',
            'quiet_hours_enabled' => 'boolean',
            'quiet_hours_from' => 'string|max:5',
            'quiet_hours_to' => 'string|max:5',
        ]);

        $userId = $request->user()->id;
        DB::table('notification_prefs')->updateOrInsert(
            ['user_id' => $userId],
            array_merge($validated, ['updated_at' => now(), 'created_at' => now()])
        );

        return response()->json(['message' => 'Updated']);
    }
}
