@extends('layouts.admin')

@section('title', __('admin.wallet_topup_requests_title'))

@section('content')
<h4 class="py-4 mb-4">{{ __('admin.wallet_topup_requests_title') }}</h4>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body row g-2 align-items-end">
        <div class="col-auto">
            <label class="form-label mb-0">Status</label>
            <select id="filter-status" class="form-select form-select-sm">
                <option value="">All</option>
                <option value="pending">pending</option>
                <option value="under_review">under_review</option>
                <option value="approved">approved</option>
                <option value="rejected">rejected</option>
            </select>
        </div>
        <div class="col-auto">
            <label class="form-label mb-0">Method</label>
            <select id="filter-method" class="form-select form-select-sm">
                <option value="">All</option>
                <option value="wire_transfer">wire_transfer</option>
                <option value="zelle">zelle</option>
            </select>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header">
        <h5 class="mb-0">{{ __('admin.list') }}</h5>
    </div>
    <div class="table-responsive text-nowrap">
        <table id="wallet-topup-requests-table" class="table table-hover table-striped">
            <thead>
                <tr>
                    <th>#</th>
                    <th>{{ __('admin.user') }}</th>
                    <th>Method</th>
                    <th>{{ __('admin.amount') }}</th>
                    <th>{{ __('admin.status') }}</th>
                    <th>{{ __('admin.created_at') }}</th>
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
    const table = $('#wallet-topup-requests-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('admin.wallet-topup-requests.data') }}",
            data: function(d) {
                d.status = document.getElementById('filter-status').value;
                d.method = document.getElementById('filter-method').value;
            }
        },
        columns: [
            { data: 'id', name: 'id' },
            { data: 'user_contact', name: 'user_id' },
            { data: 'method_label', name: 'method', orderable: false, searchable: false },
            { data: 'amount_fmt', name: 'amount' },
            { data: 'status', name: 'status' },
            { data: 'created_at', name: 'created_at' },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        order: [[0, 'desc']],
        language: dtLang
    });
    document.getElementById('filter-status').addEventListener('change', () => table.ajax.reload());
    document.getElementById('filter-method').addEventListener('change', () => table.ajax.reload());
});
</script>
@endpush
