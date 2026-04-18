@extends('layouts.admin')

@section('title', __('admin.users_list'))

@section('content')
<h4 class="py-4 mb-4">{{ __('admin.users_list') }}</h4>

<div class="card border-0 shadow-sm">
    <div class="card-header">
        <h5 class="mb-0">{{ __('admin.list') }}</h5>
    </div>
    <div class="table-responsive text-nowrap">
        <table id="users-table" class="table table-hover table-striped">
            <thead>
                <tr>
                    <th>#</th>
                    <th>{{ __('admin.name') }}</th>
                    <th>Customer code</th>
                    <th>phone</th>
                    <th>{{ __('admin.email') }}</th>
                    <th>{{ __('admin.verified') }}</th>
                    <th>{{ __('admin.date') }}</th>
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
    $('#users-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: "{{ route('admin.users.data') }}",
        columns: [
            { data: 'id', name: 'id' },
            { data: 'display_name_col', name: 'name' },
            { data: 'customer_code_col', name: 'customer_code', orderable: false },
            { data: 'phone', name: 'phone' },
            { data: 'email', name: 'email' },
            { data: 'verified', name: 'verified' },
            { data: 'created_at', name: 'created_at' },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        language: dtLang
    });
});
</script>
@endpush
