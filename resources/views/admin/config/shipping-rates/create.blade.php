@extends('layouts.admin')

@section('title', __('admin.shipping_rates'))

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-header">
        <h5 class="mb-0">{{ __('admin.add_shipping_rate') }}</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.config.shipping-rates.store') }}">
            @csrf
            @include('admin.config.shipping-rates.form', ['rate' => null, 'zones' => $zones])
            <div class="mt-4">
                <button type="submit" class="btn btn-primary">{{ __('admin.save') }}</button>
                <a href="{{ route('admin.config.shipping-rates.index') }}" class="btn btn-link">{{ __('admin.cancel') }}</a>
            </div>
        </form>
    </div>
</div>
@endsection

