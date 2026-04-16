@extends('layouts.admin')

@section('title', __('admin.orders_list'))

@section('content')
<h4 class="py-4 mb-2">{{ __('admin.orders_list') }}</h4>
<p class="text-muted mb-4">{{ __('admin.orders_list_procurement_hint') }}</p>

<div class="card border-0 shadow-sm">
    <div class="card-header">
        <form id="orders-filter" class="d-flex flex-wrap gap-2 align-items-center">
            <select name="execution_status" id="orders-execution-status" class="form-select" style="max-width: 320px;">
                <option value="">{{ __('admin.all_execution_statuses') }}</option>
                @foreach(\App\Support\OrderExecutionStatus::filterableExecutionStatuses() as $execKey)
                    <option value="{{ $execKey }}">{{ __('admin.execution_status_'.$execKey) }}</option>
                @endforeach
            </select>
            <select name="origin" id="orders-origin" class="form-select" style="max-width: 150px;">
                <option value="">{{ __('admin.all_origins') }}</option>
                <option value="usa">USA</option>
                <option value="turkey">Turkey</option>
                <option value="multi_origin">Multi</option>
            </select>
            <select name="source" id="orders-source" class="form-select" style="max-width: 220px;" title="{{ __('admin.orders_source_filter') }}">
                <option value="">{{ __('admin.orders_source_all') }}</option>
                <option value="purchase_assistant">{{ __('admin.orders_source_purchase_assistant') }}</option>
                <option value="standard">{{ __('admin.orders_source_standard') }}</option>
            </select>
            <button type="button" id="orders-filter-btn" class="btn btn-primary">{{ __('admin.filter') }}</button>
        </form>
    </div>
    <div class="table-responsive text-nowrap">
        <table id="orders-table" class="table table-hover table-striped">
            <thead>
                <tr>
                    <th>{{ __('admin.order_number') }}</th>
                    <th>{{ __('admin.customer') }}</th>
                    <th>{{ __('admin.origin') }}</th>
                    <th>{{ __('admin.execution_status_label') }}</th>
                    <th>{{ __('admin.payment') }}</th>
                    <th>{{ __('admin.total') }}</th>
                    <th>{{ __('admin.paid_at') }}</th>
                    <th>{{ __('admin.placed_at') }}</th>
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
                d.execution_status = $('#orders-execution-status').val();
                d.origin = $('#orders-origin').val();
                d.source = $('#orders-source').val();
            }
        },
        order: [[7, 'desc']],
        columns: [
            { data: 'order_number', name: 'order_number' },
            { data: 'customer', name: 'customer', orderable: false, searchable: true },
            { data: 'origin', name: 'origin' },
            { data: 'execution_status', name: 'execution_status', orderable: false, searchable: false },
            { data: 'payment_status', name: 'payment_status', orderable: false, searchable: false },
            { data: 'order_total_snapshot', name: 'order_total_snapshot', orderable: false, searchable: false },
            { data: 'paid_at', name: 'paid_at', orderable: false, searchable: false },
            { data: 'placed_at', name: 'placed_at' },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        language: dtLang
    });
    $('#orders-filter-btn').on('click', () => table.ajax.reload());
});
</script>
@endpush
