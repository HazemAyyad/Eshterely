<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Country;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CitiesController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $countryId = $request->query('country_id');
        $countryCode = $request->query('country_code');

        $query = City::query()->with('country');

        if ($countryId !== null && $countryId !== '') {
            if (is_numeric($countryId)) {
                $query->where('country_id', (int) $countryId);
            } else {
                $country = Country::where('code', $countryId)->first();
                if ($country) {
                    $query->where('country_id', $country->id);
                }
            }
        } elseif ($countryCode !== null && $countryCode !== '') {
            $country = Country::where('code', $countryCode)->first();
            if ($country) {
                $query->where('country_id', $country->id);
            }
        }

        $cities = $query->orderBy('name')->get(['id', 'country_id', 'name', 'code']);

        return response()->json($cities->map(fn ($c) => [
            'id' => $c->id,
            'name' => $c->name,
        ]));
    }
}
