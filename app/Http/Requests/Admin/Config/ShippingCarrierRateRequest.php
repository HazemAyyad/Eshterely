<?php

namespace App\Http\Requests\Admin\Config;

use Illuminate\Foundation\Http\FormRequest;

class ShippingCarrierRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null;
    }

    public function rules(): array
    {
        return [
            'carrier' => 'required|string|in:dhl,ups,fedex',
            'zone_code' => 'required|string|max:50',
            'pricing_mode' => 'required|string|in:direct,warehouse',
            'weight_min_kg' => 'required|numeric|min:0',
            'weight_max_kg' => 'nullable|numeric|min:0',
            'base_rate' => 'required|numeric|min:0',
            'active' => 'sometimes|boolean',
            'notes' => 'nullable|string|max:500',
        ];
    }
}

