@extends('layouts.admin')

@section('title', __('admin.promo_codes'))

@section('content')
<h4 class="py-4 mb-4">{{ __('admin.promo_codes') }}</h4>

@if (session('success'))
    <div class="alert alert-success alert-dismissible">{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <span class="text-primary d-block mb-2">Total codes</span>
                <h4 class="mb-1 text-primary">{{ number_format($stats['total_codes'] ?? 0) }}</h4>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <span class="text-success d-block mb-2">Active codes</span>
                <h4 class="mb-1 text-success">{{ number_format($stats['active_codes'] ?? 0) }}</h4>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <span class="text-info d-block mb-2">Redemptions</span>
                <h4 class="mb-1 text-info">{{ number_format($stats['total_redemptions'] ?? 0) }}</h4>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <span class="text-warning d-block mb-2">Total discount</span>
                <h4 class="mb-1 text-warning">${{ number_format((float) ($stats['total_discount'] ?? 0), 2) }}</h4>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">{{ __('admin.list') }}</h5>
        <a href="{{ route('admin.config.promo-codes.create') }}" class="btn btn-primary">
            <span class="d-flex align-items-center gap-2">
                <i class="icon-base ti tabler-plus icon-xs"></i>
                <span>{{ __('admin.add') }}</span>
            </span>
        </a>
    </div>
    <div class="table-responsive text-nowrap">
        <table id="promo-codes-table" class="table table-hover table-striped">
            <thead>
            <tr>
                <th>#</th>
                <th>{{ __('admin.code') }}</th>
                <th>Type</th>
                <th>Value</th>
                <th>Limits</th>
                <th>Usages</th>
                <th>{{ __('admin.status') }}</th>
                <th>Starts</th>
                <th>Ends</th>
                <th>{{ __('admin.actions') }}</th>
            </tr>
            </thead>
        </table>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header">
        <h5 class="mb-0">Recent redemptions</h5>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
            <tr>
                <th>Code</th>
                <th>User</th>
                <th>Order</th>
                <th>Discount</th>
                <th>Before</th>
                <th>After</th>
                <th>Redeemed at</th>
            </tr>
            </thead>
            <tbody>
            @forelse($recentRedemptions as $redemption)
                <tr>
                    <td class="fw-semibold">{{ $redemption->code_snapshot }}</td>
                    <td>{{ $redemption->user?->name ?? $redemption->user?->email ?? '-' }}</td>
                    <td>#{{ $redemption->order?->order_number ?? $redemption->order_id }}</td>
                    <td>${{ number_format((float) $redemption->discount_amount, 2) }}</td>
                    <td>${{ number_format((float) $redemption->total_before_amount, 2) }}</td>
                    <td>${{ number_format((float) $redemption->total_after_amount, 2) }}</td>
                    <td>{{ $redemption->redeemed_at?->format('Y-m-d H:i') ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">No redemption history yet.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="https://cdn.datatables.net/2.0.8/css/dataTables.bootstrap5.min.css" />
@endpush

@push('scripts')
<script src="https://cdn.datatables.net/2.0.8/js/dataTables.min.js"></script>
<script src="https://cdn.datatables.net/2.0.8/js/dataTables.bootstrap5.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const dtLang = @json(__('datatables'));
    const table = $('#promo-codes-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: "{{ route('admin.config.promo-codes.data') }}",
        columns: [
            { data: 'id', name: 'id' },
            { data: 'code', name: 'code' },
            { data: 'discount_type', name: 'discount_type' },
            { data: 'discount_value', name: 'discount_value' },
            { data: 'limits', name: 'limits', orderable: false, searchable: false },
            { data: 'usages_count', name: 'usages_count' },
            { data: 'is_active', name: 'is_active' },
            { data: 'starts_at', name: 'starts_at' },
            { data: 'ends_at', name: 'ends_at' },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        language: dtLang
    });

    $(document).on('click', '#promo-codes-table .btn-delete', function() {
        const url = $(this).data('url');
        const label = $(this).data('label') || @json(__('admin.delete'));
        Swal.fire({
            title: label,
            text: @json(__('admin.confirm_delete')),
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: @json(__('admin.yes')),
            cancelButtonText: @json(__('admin.no'))
        }).then((r) => {
            if (!r.isConfirmed) return;
            Swal.fire({ title: @json(__('admin.loading')), allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            fetch(url, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(x => x.json())
            .then(d => {
                Swal.close();
                if (d.success) {
                    Swal.fire({ icon: 'success', title: d.message });
                    table.ajax.reload();
                } else {
                    Swal.fire({ icon: 'error', title: d.message || @json(__('admin.error')) });
                }
            })
            .catch(() => Swal.fire({ icon: 'error', title: @json(__('admin.error')) }));
        });
    });
});
</script>
@endpush
