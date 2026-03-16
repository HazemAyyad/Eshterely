<?php

namespace App\Http\Controllers\Admin\Config;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Config\ShippingCarrierZoneRequest;
use App\Models\ShippingCarrierZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class ShippingCarrierZonesController extends Controller
{
    public function index(): View
    {
        $zones = ShippingCarrierZone::query()
            ->orderBy('carrier')
            ->orderBy('destination_country')
            ->orderBy('zone_code')
            ->paginate(25);

        return view('admin.config.shipping-zones.index', compact('zones'));
    }

    public function create(): View
    {
        return view('admin.config.shipping-zones.create');
    }

    public function store(ShippingCarrierZoneRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['origin_country'] = $data['origin_country'] ? strtoupper($data['origin_country']) : null;
        $data['destination_country'] = strtoupper($data['destination_country']);
        $data['carrier'] = strtolower($data['carrier']);
        $data['active'] = $request->boolean('active', true);
        $data['created_by_admin_id'] = $request->user('admin')?->id;
        $data['updated_by_admin_id'] = $request->user('admin')?->id;

        $zone = ShippingCarrierZone::query()->create($data);

        Log::info('Admin shipping zone created', [
            'zone_id' => $zone->id,
            'carrier' => $zone->carrier,
            'destination_country' => $zone->destination_country,
            'admin_id' => $request->user('admin')?->id,
        ]);

        return redirect()->route('admin.config.shipping-zones.index')
            ->with('success', __('admin.saved_successfully'));
    }

    public function edit(ShippingCarrierZone $shipping_zone): View
    {
        return view('admin.config.shipping-zones.edit', ['zone' => $shipping_zone]);
    }

    public function update(ShippingCarrierZoneRequest $request, ShippingCarrierZone $shipping_zone): RedirectResponse
    {
        $data = $request->validated();
        $data['origin_country'] = $data['origin_country'] ? strtoupper($data['origin_country']) : null;
        $data['destination_country'] = strtoupper($data['destination_country']);
        $data['carrier'] = strtolower($data['carrier']);
        $data['active'] = $request->boolean('active', true);
        $data['updated_by_admin_id'] = $request->user('admin')?->id;

        $shipping_zone->update($data);

        Log::info('Admin shipping zone updated', [
            'zone_id' => $shipping_zone->id,
            'carrier' => $shipping_zone->carrier,
            'admin_id' => $request->user('admin')?->id,
        ]);

        return redirect()->route('admin.config.shipping-zones.index')
            ->with('success', __('admin.saved_successfully'));
    }

    public function destroy(Request $request, ShippingCarrierZone $shipping_zone): RedirectResponse
    {
        $id = $shipping_zone->id;
        $carrier = $shipping_zone->carrier;
        $shipping_zone->delete();

        Log::info('Admin shipping zone deleted', [
            'zone_id' => $id,
            'carrier' => $carrier,
            'admin_id' => $request->user('admin')?->id,
        ]);

        return redirect()->route('admin.config.shipping-zones.index')
            ->with('success', __('admin.deleted_successfully'));
    }
}

