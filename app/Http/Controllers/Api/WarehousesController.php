<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class WarehousesController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $warehouses = DB::table('warehouses')->where('is_active', true)->orderBy('label')->get();

        return response()->json($warehouses->map(fn ($w) => [
            'id' => $w->slug,
            'label' => $w->label,
        ]));
    }
}
