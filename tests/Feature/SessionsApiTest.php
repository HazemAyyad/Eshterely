<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_sessions_index_lists_real_sanctum_tokens_and_marks_current(): void
    {
        $user = User::factory()->create();
        $token1 = $user->createToken('mobile-app');
        $token2 = $user->createToken('mobile-app');

        $res = $this->withHeader('Authorization', 'Bearer ' . $token1->plainTextToken)
            ->getJson('/api/me/sessions');

        $res->assertOk();
        $this->assertIsArray($res->json());
        $this->assertCount(2, $res->json());

        $current = collect($res->json())->firstWhere('is_current', true);
        $this->assertNotNull($current);
        $this->assertSame((string) $token1->accessToken->id, $current['id']);
    }

    public function test_sessions_destroy_cannot_end_current_session(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('mobile-app');

        $res = $this->withHeader('Authorization', 'Bearer ' . $token->plainTextToken)
            ->deleteJson('/api/me/sessions/' . $token->accessToken->id);

        $res->assertStatus(422);
    }

    public function test_sessions_destroy_can_revoke_other_session(): void
    {
        $user = User::factory()->create();
        $token1 = $user->createToken('mobile-app');
        $token2 = $user->createToken('mobile-app');

        $res = $this->withHeader('Authorization', 'Bearer ' . $token1->plainTextToken)
            ->deleteJson('/api/me/sessions/' . $token2->accessToken->id);

        $res->assertOk();

        $res2 = $this->withHeader('Authorization', 'Bearer ' . $token1->plainTextToken)
            ->getJson('/api/me/sessions');
        $res2->assertOk();
        $this->assertCount(1, $res2->json());
    }
}

