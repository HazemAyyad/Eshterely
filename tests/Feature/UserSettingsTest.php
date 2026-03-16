<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_show_returns_stable_defaults(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('mobile-app')->plainTextToken;

        $res = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/me/settings');

        $res->assertOk();
        $res->assertJsonPath('language_code', 'en');
        $res->assertJsonPath('currency_code', 'USD');
        $res->assertJsonPath('smart_consolidation_enabled', true);
        $res->assertJsonPath('auto_insurance_enabled', false);
        $this->assertArrayHasKey('default_warehouse_id', $res->json());
        $this->assertArrayHasKey('default_warehouse_label', $res->json());
        $this->assertArrayHasKey('language_label', $res->json());
        $this->assertArrayHasKey('currency_symbol', $res->json());
    }

    public function test_settings_update_normalizes_language_and_currency(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('mobile-app')->plainTextToken;

        $res = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/me/settings', [
                'language_code' => 'AR',
                'currency_code' => 'aed',
                'smart_consolidation_enabled' => false,
            ]);

        $res->assertOk();
        $res->assertJsonPath('language_code', 'ar');
        $res->assertJsonPath('currency_code', 'AED');
        $res->assertJsonPath('smart_consolidation_enabled', false);

        // Subsequent reads should match persisted values.
        $res2 = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/me/settings');
        $res2->assertOk();
        $res2->assertJsonPath('language_code', 'ar');
        $res2->assertJsonPath('currency_code', 'AED');
        $res2->assertJsonPath('smart_consolidation_enabled', false);
    }
}

