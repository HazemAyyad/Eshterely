<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Country;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CountriesController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $countries = Country::orderBy('name')->get(['id', 'code', 'name']);

        return response()->json($countries->map(fn ($c) => [
            'id' => $c->code ?? (string) $c->id,
            'name' => $c->name,
        ]));
    }
}
