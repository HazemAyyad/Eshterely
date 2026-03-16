<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLocaleAndMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_set_locale_requires_admin_auth(): void
    {
        $response = $this->get(route('admin.set-locale', ['lang' => 'en']));
        $response->assertRedirect(route('admin.login'));
    }

    public function test_authenticated_admin_can_set_locale(): void
    {
        $admin = Admin::factory()->create();
        $response = $this->actingAs($admin, 'admin')->get(route('admin.set-locale', ['lang' => 'en']));
        $response->assertRedirect();
        $this->assertSame('en', session('admin_locale'));
    }

    public function test_set_locale_ignores_invalid_lang(): void
    {
        $admin = Admin::factory()->create();
        session()->put('admin_locale', 'ar');
        $response = $this->actingAs($admin, 'admin')->get(route('admin.set-locale', ['lang' => 'fr']));
        $response->assertRedirect();
        $this->assertSame('ar', session('admin_locale'));
    }

    public function test_dashboard_and_shipping_routes_are_in_menu(): void
    {
        $admin = Admin::factory()->create();
        $response = $this->actingAs($admin, 'admin')->get(route('admin.dashboard'));
        $response->assertOk();
        $response->assertSee(route('admin.config.shipping-zones.index'), false);
        $response->assertSee(route('admin.config.shipping-rates.index'), false);
    }
}
