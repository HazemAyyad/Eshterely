@extends('layouts.admin')

@section('title', __('admin.wallet_withdrawals_title'))

@section('content')
<h4 class="py-4 mb-4">{{ __('admin.wallet_withdrawals_title') }}</h4>

<div class="card border-0 shadow-sm">
    <div class="card-header">
        <h5 class="mb-0">{{ __('admin.list') }}</h5>
    </div>
    <div class="table-responsive text-nowrap">
        <table id="wallet-withdrawals-table" class="table table-hover table-striped">
            <thead>
                <tr>
                    <th>#</th>
                    <th>{{ __('admin.user') }}</th>
                    <th>{{ __('admin.amount') }}</th>
                    <th>{{ __('admin.status') }}</th>
                    <th>{{ __('admin.bank_name') }}</th>
                    <th>{{ __('admin.country') }}</th>
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
    $('#wallet-withdrawals-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: "{{ route('admin.wallet-withdrawals.data') }}",
        columns: [
            { data: 'id', name: 'id' },
            { data: 'user_contact', name: 'user_id' },
            { data: 'amount_fmt', name: 'amount' },
            { data: 'status', name: 'status' },
            { data: 'bank_name', name: 'bank_name' },
            { data: 'country', name: 'country' },
            { data: 'created_at', name: 'created_at' },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        order: [[0, 'desc']],
        language: dtLang
    });
});
</script>
@endpush
