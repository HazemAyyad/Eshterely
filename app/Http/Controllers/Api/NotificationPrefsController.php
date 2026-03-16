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

        return response()->json([
            'push_enabled' => (bool) ($prefs->push_enabled ?? true),
            'email_enabled' => (bool) ($prefs->email_enabled ?? true),
            'sms_enabled' => (bool) ($prefs->sms_enabled ?? false),
            'live_status_updates' => (bool) ($prefs->live_status_updates ?? true),
            'smart_filter' => (bool) ($prefs->smart_filter ?? true),
            'duty_tax_payments' => (bool) ($prefs->duty_tax_payments ?? true),
            'document_requests' => (bool) ($prefs->document_requests ?? true),
            'payment_failed' => (bool) ($prefs->payment_failed ?? true),
            'mute_all_marketing' => (bool) ($prefs->mute_all_marketing ?? false),
            'quiet_hours_enabled' => (bool) ($prefs->quiet_hours_enabled ?? true),
            'quiet_hours_from' => $prefs->quiet_hours_from ?? '22:00',
            'quiet_hours_to' => $prefs->quiet_hours_to ?? '07:00',
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'push_enabled' => 'sometimes|boolean',
            'email_enabled' => 'sometimes|boolean',
            'sms_enabled' => 'sometimes|boolean',
            'live_status_updates' => 'sometimes|boolean',
            'smart_filter' => 'sometimes|boolean',
            'duty_tax_payments' => 'sometimes|boolean',
            'document_requests' => 'sometimes|boolean',
            'payment_failed' => 'sometimes|boolean',
            'mute_all_marketing' => 'sometimes|boolean',
            'quiet_hours_enabled' => 'sometimes|boolean',
            'quiet_hours_from' => ['sometimes', 'string', 'max:5', 'regex:/^\d{2}:\d{2}$/'],
            'quiet_hours_to' => ['sometimes', 'string', 'max:5', 'regex:/^\d{2}:\d{2}$/'],
        ]);

        $userId = $request->user()->id;
        $now = now();
        $existing = DB::table('notification_prefs')->where('user_id', $userId)->first();

        $update = [];
        foreach ([
            'push_enabled',
            'email_enabled',
            'sms_enabled',
            'live_status_updates',
            'smart_filter',
            'duty_tax_payments',
            'document_requests',
            'payment_failed',
            'mute_all_marketing',
            'quiet_hours_enabled',
        ] as $key) {
            if (array_key_exists($key, $validated)) {
                $update[$key] = (bool) $validated[$key];
            }
        }
        foreach (['quiet_hours_from', 'quiet_hours_to'] as $key) {
            if (array_key_exists($key, $validated)) {
                $update[$key] = $validated[$key];
            }
        }
        $update['updated_at'] = $now;
        if (!$existing) {
            $update['created_at'] = $now;
        }

        DB::table('notification_prefs')->updateOrInsert(
            ['user_id' => $userId],
            $update
        );

        return $this->show($request);
    }
}
