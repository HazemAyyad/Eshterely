<?php

namespace App\Http\Controllers\Admin\Config;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Config\ShippingCarrierRateRequest;
use App\Models\ShippingCarrierRate;
use App\Models\ShippingCarrierZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class ShippingCarrierRatesController extends Controller
{
    public function index(Request $request): View
    {
        $query = ShippingCarrierRate::query()->orderBy('carrier')->orderBy('zone_code')->orderBy('weight_min_kg');

        if ($carrier = $request->get('carrier')) {
            $query->where('carrier', strtolower($carrier));
        }
        if ($pricingMode = $request->get('pricing_mode')) {
            $query->where('pricing_mode', $pricingMode);
        }

        $rates = $query->paginate(25)->withQueryString();
        $zones = ShippingCarrierZone::query()
            ->orderBy('carrier')
            ->orderBy('destination_country')
            ->orderBy('zone_code')
            ->get();

        return view('admin.config.shipping-rates.index', compact('rates', 'zones'));
    }

    public function create(): View
    {
        $zones = ShippingCarrierZone::query()
            ->where('active', true)
            ->orderBy('carrier')
            ->orderBy('destination_country')
            ->orderBy('zone_code')
            ->get();

        return view('admin.config.shipping-rates.create', compact('zones'));
    }

    public function store(ShippingCarrierRateRequest $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $rate = ShippingCarrierRate::query()->create($data);

        Log::info('Admin shipping rate created', [
            'rate_id' => $rate->id,
            'carrier' => $rate->carrier,
            'zone_code' => $rate->zone_code,
            'admin_id' => $request->user('admin')?->id,
        ]);

        return redirect()->route('admin.config.shipping-rates.index')
            ->with('success', __('admin.saved_successfully'));
    }

    public function edit(ShippingCarrierRate $shipping_rate): View
    {
        $zones = ShippingCarrierZone::query()
            ->where('active', true)
            ->orderBy('carrier')
            ->orderBy('destination_country')
            ->orderBy('zone_code')
            ->get();

        return view('admin.config.shipping-rates.edit', ['rate' => $shipping_rate, 'zones' => $zones]);
    }

    public function update(ShippingCarrierRateRequest $request, ShippingCarrierRate $shipping_rate): RedirectResponse
    {
        $data = $this->validatedData($request);
        $shipping_rate->update($data);

        Log::info('Admin shipping rate updated', [
            'rate_id' => $shipping_rate->id,
            'carrier' => $shipping_rate->carrier,
            'admin_id' => $request->user('admin')?->id,
        ]);

        return redirect()->route('admin.config.shipping-rates.index')
            ->with('success', __('admin.saved_successfully'));
    }

    public function destroy(Request $request, ShippingCarrierRate $shipping_rate): RedirectResponse
    {
        $id = $shipping_rate->id;
        $carrier = $shipping_rate->carrier;
        $shipping_rate->delete();

        Log::info('Admin shipping rate deleted', [
            'rate_id' => $id,
            'carrier' => $carrier,
            'admin_id' => $request->user('admin')?->id,
        ]);

        return redirect()->route('admin.config.shipping-rates.index')
            ->with('success', __('admin.deleted_successfully'));
    }

    private function validatedData(ShippingCarrierRateRequest $request): array
    {
        $data = $request->validated();
        $data['carrier'] = strtolower($data['carrier']);
        $data['active'] = $request->boolean('active', true);
        $data['created_by_admin_id'] = $data['created_by_admin_id'] ?? $request->user('admin')?->id;
        $data['updated_by_admin_id'] = $request->user('admin')?->id;

        $min = (float) $data['weight_min_kg'];
        $max = $data['weight_max_kg'] !== null ? (float) $data['weight_max_kg'] : null;
        if ($max !== null && $max <= $min) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'weight_max_kg' => [__('validation.gt.numeric', ['attribute' => 'weight_max_kg', 'value' => $min])],
            ]);
        }

        return $data;
    }
}

