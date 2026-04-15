@extends('layouts.admin')

@section('title', __('admin.saved_payment_methods_title'))

@section('content')
<h4 class="py-4 mb-4">{{ __('admin.saved_payment_methods_title') }}</h4>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="alert alert-secondary mb-4" role="note">
    Cards with status <strong>blocked</strong> reached the failed verification attempt limit. Use <strong>Unblock / reset</strong>
    to clear attempts and return the card to <em>pending verification</em> so the customer can try again (after you have confirmed it is safe).
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header">
        <h5 class="mb-0">{{ __('admin.list') }}</h5>
    </div>
    <div class="table-responsive text-nowrap">
        <table id="saved-payment-methods-table" class="table table-hover table-striped">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Customer</th>
                    <th>Card</th>
                    <th>Status</th>
                    <th>Failed attempts</th>
                    <th>Blocked at</th>
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
    $('#saved-payment-methods-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: "{{ route('admin.saved-payment-methods.data') }}",
        columns: [
            { data: 'id', name: 'id' },
            { data: 'customer', name: 'user_id' },
            { data: 'card_label', name: 'brand', orderable: false },
            { data: 'status_badge', name: 'verification_status' },
            { data: 'failed_attempts', name: 'verification_attempts' },
            { data: 'blocked_at_fmt', name: 'blocked_at' },
            { data: 'created_at', name: 'created_at' },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        order: [[0, 'desc']],
        language: dtLang
    });
});
</script>
@endpush
