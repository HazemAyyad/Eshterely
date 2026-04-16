@extends('layouts.admin')

@section('title', __('admin.purchase_assistant_title'))

@section('content')
<h4 class="py-4 mb-4">{{ __('admin.purchase_assistant_title') }}</h4>

@if (session('success'))
    <div class="alert alert-success alert-dismissible">{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="card border-0 shadow-sm">
    <div class="card-header">
        <p class="mb-0"><span class="badge bg-label-info me-2">Purchase Assistant</span> {{ __('admin.purchase_assistant_intro') }}</p>
    </div>
    <div class="table-responsive text-nowrap">
        <table id="pa-table" class="table table-hover table-striped">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Customer</th>
                    <th>{{ __('admin.title') }}</th>
                    <th>Store</th>
                    <th>{{ __('admin.estimated_price') }}</th>
                    <th>{{ __('admin.status') }}</th>
                    <th>{{ __('admin.source_order') }}</th>
                    <th>{{ __('admin.created_at') }}</th>
                    <th class="text-center" title="Open product URL"><span class="visually-hidden">Link</span><i class="icon-base ti tabler-external-link icon-18px"></i></th>
                    <th></th>
                </tr>
            </thead>
        </table>
    </div>
</div>

@include('admin.partials.wallet-inline-status-modal')
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
    const table = $('#pa-table').DataTable({
        processing: true,
        serverSide: true,
        scrollX: true,
        ajax: "{{ route('admin.purchase-assistant.data') }}",
        language: dtLang,
        columns: [
            { data: 'id', name: 'id' },
            { data: 'customer', name: 'user_id' },
            { data: 'product_title', name: 'title' },
            { data: 'store_name', name: 'store_display_name' },
            { data: 'estimated_price', name: 'customer_estimated_price' },
            { data: 'status_badge', name: 'status', orderable: false, searchable: false },
            { data: 'order', name: 'converted_order_id', orderable: false, searchable: false },
            { data: 'created_at', name: 'created_at' },
            { data: 'link_icon', name: 'link_icon', orderable: false, searchable: false },
            { data: 'actions', name: 'actions', orderable: false, searchable: false },
        ],
        order: [[0, 'desc']],
    });
    window.walletInlineDataTableReload = function () { table.ajax.reload(null, false); };
});
</script>
@endpush
