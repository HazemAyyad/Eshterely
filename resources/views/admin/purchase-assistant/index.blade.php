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
                    <th>{{ __('admin.user_name') }}</th>
                    <th>URL</th>
                    <th>{{ __('admin.status') }}</th>
                    <th>{{ __('admin.source_order') }}</th>
                    <th>{{ __('admin.created_at') }}</th>
                    <th></th>
                </tr>
            </thead>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(function () {
    $('#pa-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: "{{ route('admin.purchase-assistant.data') }}",
        columns: [
            { data: 'id', name: 'id' },
            { data: 'user_name', name: 'user_name', orderable: false },
            { data: 'source_url', name: 'source_url' },
            { data: 'status', name: 'status' },
            { data: 'order', name: 'converted_order_id', orderable: false },
            { data: 'created_at', name: 'created_at' },
            { data: 'actions', name: 'actions', orderable: false, searchable: false },
        ],
        order: [[0, 'desc']],
    });
});
</script>
@endpush
