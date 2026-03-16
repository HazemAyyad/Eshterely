@extends('layouts.admin')

@section('title', __('admin.shipping_zones'))

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-header">
        <h5 class="mb-0">{{ __('admin.add_shipping_zone') }}</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.config.shipping-zones.store') }}">
            @csrf
            @include('admin.config.shipping-zones.form', ['zone' => null])
            <div class="mt-4">
                <button type="submit" class="btn btn-primary">{{ __('admin.save') }}</button>
                <a href="{{ route('admin.config.shipping-zones.index') }}" class="btn btn-link">{{ __('admin.cancel') }}</a>
            </div>
        </form>
    </div>
</div>
@endsection

