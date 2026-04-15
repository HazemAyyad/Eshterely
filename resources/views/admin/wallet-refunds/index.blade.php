@extends('layouts.admin')

@section('title', __('admin.wallet_refunds_to_wallet_title'))

@section('content')
<h4 class="py-4 mb-4">{{ __('admin.wallet_refunds_to_wallet_title') }}</h4>

<div class="card border-0 shadow-sm">
    <div class="card-header">
        <h5 class="mb-0">{{ __('admin.list') }}</h5>
    </div>
    <div class="table-responsive text-nowrap">
        <table id="wallet-refunds-table" class="table table-hover table-striped">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Customer</th>
                    <th>{{ __('admin.source') }}</th>
                    <th>{{ __('admin.amount') }}</th>
                    <th>{{ __('admin.status') }}</th>
                    <th>{{ __('admin.created_at') }}</th>
                    <th>{{ __('admin.actions') }}</th>
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
    const table = $('#wallet-refunds-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: "{{ route('admin.wallet-refunds.data') }}",
        columns: [
            { data: 'id', name: 'id' },
            { data: 'customer', name: 'user_id' },
            { data: 'source_label', name: 'source_type' },
            { data: 'amount_fmt', name: 'amount' },
            { data: 'status_badge', name: 'status', orderable: false, searchable: false },
            { data: 'created_at', name: 'created_at' },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        order: [[0, 'desc']],
        language: dtLang
    });
    window.walletInlineDataTableReload = function () { table.ajax.reload(null, false); };
});
</script>
@endpush
