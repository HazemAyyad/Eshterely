<?php

namespace App\Http\Requests\Admin\Config;

use Illuminate\Foundation\Http\FormRequest;

class ShippingCarrierZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null;
    }

    public function rules(): array
    {
        return [
            'carrier' => 'required|string|in:dhl,ups,fedex',
            'origin_country' => 'nullable|string|size:2',
            'destination_country' => 'required|string|size:2',
            'zone_code' => 'required|string|max:50',
            'active' => 'sometimes|boolean',
            'notes' => 'nullable|string|max:500',
        ];
    }
}

