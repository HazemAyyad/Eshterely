@extends('layouts.admin')

@section('title', __('admin.orders_list'))

@section('content')
<h4 class="py-4 mb-2">{{ __('admin.orders_list') }}</h4>
<p class="text-muted mb-4">{{ __('admin.orders_list_procurement_hint') }}</p>

<div class="card border-0 shadow-sm">
    <div class="card-header">
        <form id="orders-filter" class="d-flex flex-wrap gap-2 align-items-center">
            <select name="status" id="orders-status" class="form-select" style="max-width: 150px;">
                <option value="">{{ __('admin.all_statuses') }}</option>
                <option value="pending_payment">Pending Payment</option>
                <option value="paid">Paid</option>
                <option value="under_review">Under Review</option>
                <option value="approved">Approved</option>
                <option value="processing">Processing</option>
                <option value="in_transit">{{ __('admin.in_transit') }}</option>
                <option value="delivered">{{ __('admin.delivered') }}</option>
                <option value="cancelled">{{ __('admin.cancelled') }}</option>
            </select>
            <select name="origin" id="orders-origin" class="form-select" style="max-width: 150px;">
                <option value="">{{ __('admin.all_origins') }}</option>
                <option value="usa">USA</option>
                <option value="turkey">Turkey</option>
                <option value="multi_origin">Multi</option>
            </select>
            <button type="button" id="orders-filter-btn" class="btn btn-primary">{{ __('admin.filter') }}</button>
        </form>
    </div>
    <div class="table-responsive text-nowrap">
        <table id="orders-table" class="table table-hover table-striped">
            <thead>
                <tr>
                    <th>#</th>
                    <th>{{ __('admin.order_number') }}</th>
                    <th>User</th>
                    <th>Origin</th>
                    <th>Payment</th>
                    <th>{{ __('admin.status') }}</th>
                    <th>Est.</th>
                    <th>Review</th>
                    <th>Total (snapshot)</th>
                    <th>Payment Ref.</th>
                    <th>Carrier</th>
                    <th>{{ __('admin.date') }}</th>
                    <th>{{ __('admin.actions') }}</th>
                </tr>
            </thead>
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
    const dtLang = @json(__('datatatables'));
    const table = $('#orders-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('admin.orders.data') }}",
            data: function(d) {
                d.status = $('#orders-status').val();
                d.origin = $('#orders-origin').val();
            }
        },
        columns: [
            { data: 'id', name: 'id' },
            { data: 'order_number', name: 'order_number' },
            { data: 'user_contact', name: 'user_contact' },
            { data: 'origin', name: 'origin' },
            { data: 'payment_status', name: 'payment_status', orderable: false, searchable: false },
            { data: 'status', name: 'status' },
            { data: 'estimated', name: 'estimated', orderable: false, searchable: false },
            { data: 'needs_review', name: 'needs_review', orderable: false, searchable: false },
            { data: 'order_total_snapshot', name: 'order_total_snapshot', orderable: false, searchable: false },
            { data: 'payment_reference', name: 'payment_reference', orderable: false, searchable: false },
            { data: 'source_carrier', name: 'source_carrier', orderable: false, searchable: false },
            { data: 'placed_at', name: 'placed_at' },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        language: dtLang
    });
    $('#orders-filter-btn').on('click', () => table.ajax.reload());
});
</script>
@endpush
