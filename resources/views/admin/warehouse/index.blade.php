@extends('layouts.admin')

@section('title', __('admin.warehouse_title'))

@section('content')
@if (session('error'))
    <div class="alert alert-danger alert-dismissible py-2">{{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif
<h4 class="py-4 mb-2">{{ __('admin.warehouse_title') }}</h4>
<p class="text-muted mb-3">{{ __('admin.warehouse_intro_tabs') }}</p>

<ul class="nav nav-tabs mb-0 flex-wrap" id="warehouse-tabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-awaiting-arrival" data-bs-toggle="tab" data-bs-target="#pane-awaiting-arrival" type="button" role="tab" data-queue="awaiting_arrival" aria-controls="pane-awaiting-arrival" aria-selected="false">
            {{ __('admin.warehouse_tab_awaiting_arrival') }}
            <span class="badge rounded-pill bg-warning text-dark ms-1">{{ $counts['awaiting_arrival'] ?? 0 }}</span>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="tab-ready" data-bs-toggle="tab" data-bs-target="#pane-ready" type="button" role="tab" data-queue="ready_to_receive" aria-controls="pane-ready" aria-selected="true">
            {{ __('admin.warehouse_tab_ready') }}
            <span class="badge rounded-pill bg-primary ms-1">{{ $counts['ready_to_receive'] ?? 0 }}</span>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-received" data-bs-toggle="tab" data-bs-target="#pane-received" type="button" role="tab" data-queue="received" aria-controls="pane-received" aria-selected="false">
            {{ __('admin.warehouse_tab_received') }}
            <span class="badge rounded-pill bg-success ms-1">{{ $counts['received'] ?? 0 }}</span>
        </button>
    </li>
</ul>

<input type="hidden" id="warehouse-queue" value="ready_to_receive">

<div class="tab-content border border-top-0 rounded-bottom shadow-sm bg-body">
    <div class="tab-pane fade p-3" id="pane-awaiting-arrival" role="tabpanel" aria-labelledby="tab-awaiting-arrival" tabindex="0">
        <p class="small text-muted mb-0">{{ __('admin.warehouse_tab_awaiting_arrival_help') }}</p>
    </div>
    <div class="tab-pane fade show active p-3" id="pane-ready" role="tabpanel" aria-labelledby="tab-ready" tabindex="0">
        <p class="small text-muted mb-0">{{ __('admin.warehouse_tab_ready_help') }}</p>
    </div>
    <div class="tab-pane fade p-3" id="pane-received" role="tabpanel" aria-labelledby="tab-received" tabindex="0">
        <p class="small text-muted mb-0">{{ __('admin.warehouse_tab_received_help') }}</p>
    </div>
</div>

<div class="card border-0 shadow-sm mt-3">
    <div class="card-body">
        <form id="warehouse-filter" class="row g-2 align-items-end flex-wrap mb-3">
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
                <label class="form-label small mb-0">{{ __('admin.orders_source_filter') }}</label>
                <select name="source" id="warehouse-source" class="form-select form-select-sm">
                    <option value="">{{ __('admin.orders_source_all') }}</option>
                    <option value="purchase_assistant">{{ __('admin.orders_source_purchase_assistant') }}</option>
                    <option value="standard">{{ __('admin.orders_source_standard') }}</option>
                </select>
            </div>
            <div class="col-auto">
                <button type="button" id="warehouse-filter-btn" class="btn btn-sm btn-primary">{{ __('admin.filter') }}</button>
            </div>
        </form>
        <div class="table-responsive text-nowrap">
            <table id="warehouse-table" class="table table-hover table-striped align-middle">
                <thead>
                    <tr>
                        <th>{{ __('admin.product') }}</th>
                        <th>{{ __('admin.customer') }}</th>
                        <th>{{ __('admin.order_number') }}</th>
                        <th>{{ __('admin.procurement_status') }}</th>
                        <th>{{ __('admin.store') }}</th>
                        <th>{{ __('admin.store_tracking') }}</th>
                        <th style="min-width:12rem">{{ __('admin.wh_intake_details_col') }}</th>
                        <th>{{ __('admin.actions') }}</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>

@include('admin.warehouse.partials.receive-modal')
@endsection

@push('styles')
<link rel="stylesheet" href="https://cdn.datatables.net/2.0.8/css/dataTables.bootstrap5.min.css" />
<style>
    #warehouse-tabs .nav-link { cursor: pointer; }
</style>
@endpush

@push('scripts')
<script src="https://cdn.datatables.net/2.0.8/js/dataTables.min.js"></script>
<script src="https://cdn.datatables.net/2.0.8/js/dataTables.bootstrap5.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const dtLang = @json(__('datatatables'));
    const queueInput = document.getElementById('warehouse-queue');
    const table = $('#warehouse-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('admin.warehouse.data') }}",
            data: function(d) {
                d.queue = (queueInput && queueInput.value) ? queueInput.value : 'ready_to_receive';
                d.user_id = $('#warehouse-user').val();
                d.order_number = $('#warehouse-order').val();
                d.store = $('#warehouse-store').val();
                d.source = $('#warehouse-source').val();
            }
        },
        order: [[0, 'asc']],
        columns: [
            { data: 'product', name: 'order_line_items.name', searchable: true },
            { data: 'customer', name: 'customer', orderable: false, searchable: false },
            { data: 'order_number', name: 'order_number', orderable: false, searchable: false },
            { data: 'fulfillment', name: 'order_line_items.fulfillment_status', orderable: true, searchable: false },
            { data: 'store_name', name: 'order_line_items.store_name', searchable: true },
            { data: 'store_tracking', name: 'store_tracking', orderable: false, searchable: false },
            { data: 'intake', name: 'intake', orderable: false, searchable: false },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        language: dtLang
    });

    document.querySelectorAll('#warehouse-tabs [data-bs-toggle="tab"]').forEach(function(el) {
        el.addEventListener('shown.bs.tab', function(e) {
            const q = e.target.getAttribute('data-queue');
            if (q) {
                queueInput.value = q;
                table.ajax.reload();
            }
        });
    });

    $('#warehouse-filter-btn').on('click', function() { table.ajax.reload(); });

    window.warehouseReceiveOnSuccess = function() { table.ajax.reload(); };
});
</script>
@endpush

@include('admin.warehouse.partials.receive-modal-script')
