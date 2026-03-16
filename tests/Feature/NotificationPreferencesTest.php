<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationPreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_preferences_show_returns_full_shape_with_defaults(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('mobile-app')->plainTextToken;

        $res = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/me/notification-preferences');

        $res->assertOk();
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
            'quiet_hours_from',
            'quiet_hours_to',
        ] as $key) {
            $this->assertArrayHasKey($key, $res->json());
        }
    }

    public function test_notification_preferences_update_persists_booleans_and_times(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('mobile-app')->plainTextToken;

        $res = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/me/notification-preferences', [
                'push_enabled' => false,
                'mute_all_marketing' => true,
                'quiet_hours_from' => '21:30',
                'quiet_hours_to' => '06:15',
            ]);

        $res->assertOk();
        $res->assertJsonPath('push_enabled', false);
        $res->assertJsonPath('mute_all_marketing', true);
        $res->assertJsonPath('quiet_hours_from', '21:30');
        $res->assertJsonPath('quiet_hours_to', '06:15');
    }
}

