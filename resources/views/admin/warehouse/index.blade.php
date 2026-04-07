@extends('layouts.admin')

@section('title', __('admin.warehouse_title'))

@section('content')
<h4 class="py-4 mb-2">{{ __('admin.warehouse_title') }}</h4>
<p class="text-muted mb-4">{{ __('admin.warehouse_intro') }}</p>

<div class="card border-0 shadow-sm">
    <div class="card-header">
        <form id="warehouse-filter" class="row g-2 align-items-end flex-wrap">
            <div class="col-auto">
                <label class="form-label small mb-0">{{ __('admin.warehouse_filter_queue') }}</label>
                <select name="queue" id="warehouse-queue" class="form-select form-select-sm">
                    <option value="awaiting">{{ __('admin.warehouse_queue_awaiting') }}</option>
                    <option value="received">{{ __('admin.warehouse_queue_received') }}</option>
                    <option value="special">{{ __('admin.warehouse_queue_special') }}</option>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label small mb-0">{{ __('admin.warehouse_filter_user') }}</label>
                <input type="number" name="user_id" id="warehouse-user" class="form-control form-control-sm" min="1" placeholder="—">
            </div>
            <div class="col-auto">
                <label class="form-label small mb-0">{{ __('admin.warehouse_filter_order') }}</label>
                <input type="text" name="order_number" id="warehouse-order" class="form-control form-control-sm" placeholder="—">
            </div>
            <div class="col-auto">
                <label class="form-label small mb-0">{{ __('admin.warehouse_filter_store') }}</label>
                <input type="text" name="store" id="warehouse-store" class="form-control form-control-sm" placeholder="—">
            </div>
            <div class="col-auto">
                <button type="button" id="warehouse-filter-btn" class="btn btn-sm btn-primary">{{ __('admin.filter') }}</button>
            </div>
        </form>
    </div>
    <div class="table-responsive text-nowrap">
        <table id="warehouse-table" class="table table-hover table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>{{ __('admin.order_number') }}</th>
                    <th>{{ __('admin.user_name') }}</th>
                    <th>{{ __('admin.product') }}</th>
                    <th>{{ __('admin.store') }}</th>
                    <th>{{ __('admin.procurement_status') }}</th>
                    <th>{{ __('admin.store_tracking') }}</th>
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
    const table = $('#warehouse-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('admin.warehouse.data') }}",
            data: function(d) {
                d.queue = $('#warehouse-queue').val();
                d.user_id = $('#warehouse-user').val();
                d.order_number = $('#warehouse-order').val();
                d.store = $('#warehouse-store').val();
            }
        },
        order: [[0, 'desc']],
        columns: [
            { data: 'id', name: 'order_line_items.id' },
            { data: 'order_number', name: 'order_number', orderable: false, searchable: false },
            { data: 'customer', name: 'customer', orderable: false, searchable: false },
            { data: 'product', name: 'order_line_items.name', searchable: true },
            { data: 'store_name', name: 'order_line_items.store_name', searchable: true },
            { data: 'fulfillment', name: 'order_line_items.fulfillment_status', orderable: true, searchable: false },
            { data: 'store_tracking', name: 'store_tracking', orderable: false, searchable: false },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        language: dtLang
    });
    $('#warehouse-filter-btn').on('click', () => table.ajax.reload());
});
</script>
@endpush
