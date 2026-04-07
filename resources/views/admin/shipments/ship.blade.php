@extends('layouts.admin')

@section('title', __('admin.mark_shipped') . ' #' . $shipment->id)

@section('content')
<h4 class="py-4 mb-2">{{ __('admin.mark_shipped') }} #{{ $shipment->id }}</h4>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.outbound-shipments.ship', $shipment) }}">
            @csrf
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">{{ __('admin.carrier') }} *</label>
                    <input type="text" name="carrier" class="form-control" maxlength="80" value="{{ old('carrier', $shipment->carrier) }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('admin.tracking_number') }} *</label>
                    <input type="text" name="tracking_number" class="form-control" value="{{ old('tracking_number', $shipment->tracking_number) }}" required>
                </div>
                <div class="col-12">
                    <label class="form-label">{{ __('admin.dispatch_note') }}</label>
                    <textarea name="dispatch_note" class="form-control" rows="2" maxlength="1000">{{ old('dispatch_note', $shipment->pricing_breakdown['admin_dispatch_note'] ?? '') }}</textarea>
                </div>
            </div>
            @if ($errors->any())
                <div class="alert alert-danger mt-3 mb-0">{{ $errors->first() }}</div>
            @endif
            <div class="mt-3">
                <button type="submit" class="btn btn-success">{{ __('admin.mark_shipped') }}</button>
                <a href="{{ route('admin.shipments.show', $shipment) }}" class="btn btn-outline-secondary">{{ __('admin.cancel') }}</a>
            </div>
        </form>
    </div>
</div>
@endsection
