<div class="row g-3">
    <div class="col-md-3">
        <label class="form-label">{{ __('admin.carrier') }}</label>
        <select name="carrier" class="form-select">
            @php($currentCarrier = old('carrier', $rate->carrier ?? 'dhl'))
            @foreach(['dhl', 'ups', 'fedex'] as $c)
                <option value="{{ $c }}" {{ $currentCarrier === $c ? 'selected' : '' }}>{{ strtoupper($c) }}</option>
            @endforeach
        </select>
        @error('carrier')<div class="text-danger small">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label class="form-label">{{ __('admin.zone') }}</label>
        <select name="zone_code" class="form-select">
            @php($currentZone = old('zone_code', $rate->zone_code ?? ''))
            @foreach($zones as $zone)
                @php($value = $zone->zone_code)
                <option value="{{ $value }}" {{ $currentZone === $value ? 'selected' : '' }}>
                    {{ strtoupper($zone->carrier) }} – {{ $zone->destination_country }} – {{ $zone->zone_code }}
                </option>
            @endforeach
        </select>
        @error('zone_code')<div class="text-danger small">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label class="form-label">{{ __('admin.pricing_mode') }}</label>
        @php($currentMode = old('pricing_mode', $rate->pricing_mode ?? 'direct'))
        <select name="pricing_mode" class="form-select">
            @foreach(['direct', 'warehouse'] as $mode)
                <option value="{{ $mode }}" {{ $currentMode === $mode ? 'selected' : '' }}>
                    {{ __('admin.pricing_mode_'.$mode) }}
                </option>
            @endforeach
        </select>
        @error('pricing_mode')<div class="text-danger small">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label class="form-label">{{ __('admin.base_rate') }}</label>
        <input type="text" name="base_rate" class="form-control"
               value="{{ old('base_rate', $rate->base_rate ?? '') }}" placeholder="0.00">
        @error('base_rate')<div class="text-danger small">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label class="form-label">{{ __('admin.weight_min_kg') }}</label>
        <input type="text" name="weight_min_kg" class="form-control"
               value="{{ old('weight_min_kg', $rate->weight_min_kg ?? '') }}" placeholder="0.000">
        @error('weight_min_kg')<div class="text-danger small">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label class="form-label">{{ __('admin.weight_max_kg') }}</label>
        <input type="text" name="weight_max_kg" class="form-control"
               value="{{ old('weight_max_kg', $rate->weight_max_kg ?? '') }}" placeholder="optional">
        @error('weight_max_kg')<div class="text-danger small">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label class="form-label d-block">{{ __('admin.active') }}</label>
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="active" value="1"
                   {{ old('active', $rate->active ?? true) ? 'checked' : '' }}>
        </div>
        @error('active')<div class="text-danger small">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-12">
        <label class="form-label">{{ __('admin.notes') }}</label>
        <textarea name="notes" class="form-control" rows="2"
                  placeholder="{{ __('admin.optional') }}">{{ old('notes', $rate->notes ?? '') }}</textarea>
        @error('notes')<div class="text-danger small">{{ $message }}</div>@enderror
    </div>
</div>

