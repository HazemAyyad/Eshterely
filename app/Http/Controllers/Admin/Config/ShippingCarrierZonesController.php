<?php

namespace App\Http\Controllers\Admin\Config;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Config\ShippingCarrierZoneRequest;
use App\Models\ShippingCarrierZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        ShippingCarrierZone::query()->create($data);

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

        return redirect()->route('admin.config.shipping-zones.index')
            ->with('success', __('admin.saved_successfully'));
    }

    public function destroy(Request $request, ShippingCarrierZone $shipping_zone): RedirectResponse
    {
        $shipping_zone->delete();

        return redirect()->route('admin.config.shipping-zones.index')
            ->with('success', __('admin.deleted_successfully'));
    }
}

