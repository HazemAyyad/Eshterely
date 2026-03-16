<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label">{{ __('admin.carrier') }}</label>
        <select name="carrier" class="form-select">
            @php($current = old('carrier', $zone->carrier ?? 'dhl'))
            @foreach(['dhl', 'ups', 'fedex'] as $c)
                <option value="{{ $c }}" {{ $current === $c ? 'selected' : '' }}>{{ strtoupper($c) }}</option>
            @endforeach
        </select>
        @error('carrier')<div class="text-danger small">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">{{ __('admin.origin_country') }}</label>
        <input type="text" name="origin_country" class="form-control"
               value="{{ old('origin_country', $zone->origin_country ?? '') }}" placeholder="AE" maxlength="2">
        @error('origin_country')<div class="text-danger small">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">{{ __('admin.destination_country') }}</label>
        <input type="text" name="destination_country" class="form-control"
               value="{{ old('destination_country', $zone->destination_country ?? '') }}" placeholder="US" maxlength="2">
        @error('destination_country')<div class="text-danger small">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">{{ __('admin.zone') }}</label>
        <input type="text" name="zone_code" class="form-control"
               value="{{ old('zone_code', $zone->zone_code ?? '') }}" placeholder="Z1" maxlength="50">
        @error('zone_code')<div class="text-danger small">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label d-block">{{ __('admin.active') }}</label>
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="active" value="1"
                   {{ old('active', $zone->active ?? true) ? 'checked' : '' }}>
        </div>
        @error('active')<div class="text-danger small">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-12">
        <label class="form-label">{{ __('admin.notes') }}</label>
        <textarea name="notes" class="form-control" rows="2"
                  placeholder="{{ __('admin.optional') }}">{{ old('notes', $zone->notes ?? '') }}</textarea>
        @error('notes')<div class="text-danger small">{{ $message }}</div>@enderror
    </div>
</div>

