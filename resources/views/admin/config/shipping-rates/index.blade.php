@extends('layouts.admin')

@section('title', __('admin.shipping_rates'))

@section('content')
@if (session('success'))
    <div class="alert alert-success alert-dismissible">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">{{ __('admin.shipping_rates') }}</h5>
        <a href="{{ route('admin.config.shipping-rates.create') }}" class="btn btn-sm btn-primary">
            {{ __('admin.add_new') }}
        </a>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3 mb-3">
            <div class="col-md-3">
                <label class="form-label">{{ __('admin.carrier') }}</label>
                <select name="carrier" class="form-select">
                    <option value="">{{ __('admin.all') }}</option>
                    @foreach(['dhl', 'ups', 'fedex'] as $c)
                        <option value="{{ $c }}" {{ request('carrier') === $c ? 'selected' : '' }}>
                            {{ strtoupper($c) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">{{ __('admin.pricing_mode') }}</label>
                <select name="pricing_mode" class="form-select">
                    <option value="">{{ __('admin.all') }}</option>
                    @foreach(['direct', 'warehouse'] as $mode)
                        <option value="{{ $mode }}" {{ request('pricing_mode') === $mode ? 'selected' : '' }}>
                            {{ __('admin.pricing_mode_'.$mode) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 align-self-end">
                <button type="submit" class="btn btn-outline-secondary">{{ __('admin.filter') }}</button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                <tr>
                    <th>{{ __('admin.carrier') }}</th>
                    <th>{{ __('admin.zone') }}</th>
                    <th>{{ __('admin.pricing_mode') }}</th>
                    <th>{{ __('admin.weight_range_kg') }}</th>
                    <th>{{ __('admin.base_rate') }}</th>
                    <th>{{ __('admin.active') }}</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse($rates as $rate)
                    <tr>
                        <td class="text-uppercase">{{ $rate->carrier }}</td>
                        <td>{{ $rate->zone_code }}</td>
                        <td>{{ __('admin.pricing_mode_'.$rate->pricing_mode) }}</td>
                        <td>
                            {{ number_format($rate->weight_min_kg, 3) }}
                            –
                            {{ $rate->weight_max_kg !== null ? number_format($rate->weight_max_kg, 3) : '∞' }}
                        </td>
                        <td>{{ number_format($rate->base_rate, 2) }}</td>
                        <td>
                            <span class="badge bg-{{ $rate->active ? 'success' : 'secondary' }}">
                                {{ $rate->active ? __('admin.active') : __('admin.inactive') }}
                            </span>
                        </td>
                        <td class="text-end">
                            <a href="{{ route('admin.config.shipping-rates.edit', $rate) }}" class="btn btn-sm btn-outline-secondary">
                                {{ __('admin.edit') }}
                            </a>
                            <form action="{{ route('admin.config.shipping-rates.destroy', $rate) }}" method="POST" class="d-inline"
                                  onsubmit="return confirm('{{ __('admin.are_you_sure') }}')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    {{ __('admin.delete') }}
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted">{{ __('admin.no_data') }}</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">
            {{ $rates->links() }}
        </div>
    </div>
</div>
@endsection

