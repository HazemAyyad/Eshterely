@extends('layouts.admin')

@section('title', __('admin.wallet'))

@section('content')
<h4 class="py-4 mb-4">{{ __('admin.wallets_title') }}</h4>

<div class="card border-0 shadow-sm">
    <div class="card-header">
        <h5 class="mb-0">{{ __('admin.list') }}</h5>
    </div>
    <div class="table-responsive text-nowrap">
        <table id="wallets-table" class="table table-hover table-striped">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Customer</th>
                    <th>{{ __('admin.available') }}</th>
                    <th>{{ __('admin.pending') }}</th>
                    <th>{{ __('admin.promo') }}</th>
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
    $('#wallets-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: "{{ route('admin.wallets.data') }}",
        columns: [
            { data: 'id', name: 'id' },
            { data: 'customer', name: 'user_id' },
            { data: 'available_balance', name: 'available_balance' },
            { data: 'pending_balance', name: 'pending_balance' },
            { data: 'promo_balance', name: 'promo_balance' },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        language: dtLang
    });
});
</script>
@endpush
