@extends('layouts.admin')

@section('title', __('admin.pack_shipment') . ' #' . $shipment->id)

@section('content')
<h4 class="py-4 mb-2">{{ __('admin.pack_shipment') }} #{{ $shipment->id }}</h4>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.outbound-shipments.pack', $shipment) }}">
            @csrf
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">{{ __('admin.weight_kg') }} *</label>
                    <input type="number" step="0.0001" min="0" name="final_weight" class="form-control" value="{{ old('final_weight', $shipment->final_weight) }}" required>
                </div>
                <div class="col-12"><span class="text-muted small">{{ __('admin.dims_lwh') }} *</span></div>
                <div class="col-md-4">
                    <label class="form-label">L *</label>
                    <input type="number" step="0.0001" min="0" name="final_length" class="form-control" value="{{ old('final_length', $shipment->final_length) }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">W *</label>
                    <input type="number" step="0.0001" min="0" name="final_width" class="form-control" value="{{ old('final_width', $shipment->final_width) }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">H *</label>
                    <input type="number" step="0.0001" min="0" name="final_height" class="form-control" value="{{ old('final_height', $shipment->final_height) }}" required>
                </div>
                <div class="col-12">
                    <label class="form-label">{{ __('admin.final_box_image') }}</label>
                    <input type="text" name="final_box_image" class="form-control" maxlength="2000" value="{{ old('final_box_image', $shipment->final_box_image) }}">
                </div>
            </div>
            @if ($errors->any())
                <div class="alert alert-danger mt-3 mb-0">{{ $errors->first() }}</div>
            @endif
            <div class="mt-3">
                <button type="submit" class="btn btn-primary">{{ __('admin.save') }}</button>
                <a href="{{ route('admin.shipments.show', $shipment) }}" class="btn btn-outline-secondary">{{ __('admin.cancel') }}</a>
            </div>
        </form>
    </div>
</div>
@endsection
