<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Country;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AddressContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_and_list_addresses_return_consistent_types(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $country = Country::create(['code' => 'AE', 'name' => 'UAE']);
        $city = City::create(['country_id' => $country->id, 'name' => 'Dubai', 'code' => 'DXB']);

        $create = $this->postJson('/api/me/addresses', [
            'country_id' => 'AE',
            'country_name' => 'UAE',
            'city_id' => 'DXB',
            'city_name' => 'Dubai',
            'address_line' => 'Line 1',
            'phone' => '500000000',
            'is_default' => true,
        ]);

        $create->assertStatus(201);
        $create->assertJsonStructure([
            'data' => [
                'id',
                'country_id',
                'country_db_id',
                'country_code',
                'city_id',
                'city_db_id',
                'city_code',
                'phone',
            ],
        ]);
        $this->assertIsString($create->json('data.id'));
        $this->assertIsString($create->json('data.country_id'));
        $this->assertIsInt($create->json('data.country_db_id'));
        $this->assertIsString($create->json('data.phone'));

        $list = $this->getJson('/api/me/addresses');
        $list->assertOk();
        $this->assertIsArray($list->json());
        $this->assertIsString($list->json('0.id'));
        $this->assertIsString($list->json('0.country_id'));
        $this->assertIsInt($list->json('0.country_db_id'));
    }
}

