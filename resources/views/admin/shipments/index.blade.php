@extends('layouts.admin')

@section('title', __('admin.shipments_ops_title'))

@section('content')
<h4 class="py-4 mb-2">{{ __('admin.shipments_ops_title') }}</h4>
<p class="text-muted mb-4">{{ __('admin.shipments_ops_intro') }}</p>

<div class="card border-0 shadow-sm">
    <div class="card-header">
        <form id="shipments-filter" class="d-flex flex-wrap gap-2 align-items-center">
            <select name="status" id="shipments-status" class="form-select" style="max-width: 200px;">
                <option value="">{{ __('admin.all_statuses') }}</option>
                <option value="draft">draft</option>
                <option value="awaiting_payment">awaiting_payment</option>
                <option value="paid">paid</option>
                <option value="packed">packed</option>
                <option value="shipped">shipped</option>
                <option value="delivered">delivered</option>
            </select>
            <button type="button" id="shipments-filter-btn" class="btn btn-primary">{{ __('admin.filter') }}</button>
        </form>
    </div>
    <div class="table-responsive text-nowrap">
        <table id="shipments-table" class="table table-hover table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>{{ __('admin.customer') }}</th>
                    <th>{{ __('admin.shipping_address') }}</th>
                    <th>{{ __('admin.qty') }}</th>
                    <th>{{ __('admin.payment') }}</th>
                    <th>{{ __('admin.status') }}</th>
                    <th>{{ __('admin.carrier') }} / {{ __('admin.tracking_number') }}</th>
                    <th>{{ __('admin.actions') }}</th>
                </tr>
            </thead>
        </table>
    </div>
</div>

@include('admin.shipments.partials.pack-modal')
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
    const table = $('#shipments-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('admin.shipments.data') }}",
            data: function(d) {
                d.status = $('#shipments-status').val();
            }
        },
        order: [[0, 'desc']],
        columns: [
            { data: 'id', name: 'shipments.id' },
            { data: 'customer', name: 'customer', orderable: false, searchable: false },
            { data: 'destination', name: 'destination', orderable: false, searchable: false },
            { data: 'items_count', name: 'items_count', orderable: false, searchable: false },
            { data: 'payment', name: 'payment', orderable: false, searchable: false },
            { data: 'status', name: 'shipments.status', searchable: false },
            { data: 'carrier_tracking', name: 'carrier_tracking', orderable: false, searchable: false },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        language: dtLang
    });
    $('#shipments-filter-btn').on('click', () => table.ajax.reload());
});
</script>
@endpush
