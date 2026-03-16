@extends('layouts.admin')

@section('title', __('admin.shipping_zones'))

@section('content')
@if (session('success'))
    <div class="alert alert-success alert-dismissible">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="card border-0 shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">{{ __('admin.shipping_zones') }}</h5>
        <a href="{{ route('admin.config.shipping-zones.create') }}" class="btn btn-sm btn-primary">
            {{ __('admin.add_new') }}
        </a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                <tr>
                    <th>{{ __('admin.carrier') }}</th>
                    <th>{{ __('admin.origin_country') }}</th>
                    <th>{{ __('admin.destination_country') }}</th>
                    <th>{{ __('admin.zone') }}</th>
                    <th>{{ __('admin.active') }}</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse($zones as $zone)
                    <tr>
                        <td class="text-uppercase">{{ $zone->carrier }}</td>
                        <td>{{ $zone->origin_country ?? '—' }}</td>
                        <td>{{ $zone->destination_country }}</td>
                        <td>{{ $zone->zone_code }}</td>
                        <td>
                            <span class="badge bg-{{ $zone->active ? 'success' : 'secondary' }}">
                                {{ $zone->active ? __('admin.active') : __('admin.inactive') }}
                            </span>
                        </td>
                        <td class="text-end">
                            <a href="{{ route('admin.config.shipping-zones.edit', $zone) }}" class="btn btn-sm btn-outline-secondary">
                                {{ __('admin.edit') }}
                            </a>
                            <form action="{{ route('admin.config.shipping-zones.destroy', $zone) }}" method="POST" class="d-inline"
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
                        <td colspan="6" class="text-center text-muted">{{ __('admin.no_data') }}</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">
            {{ $zones->links() }}
        </div>
    </div>
</div>
@endsection

