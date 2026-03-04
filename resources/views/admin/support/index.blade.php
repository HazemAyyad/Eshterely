@extends('layouts.admin')

@section('title', __('admin.support_tickets'))

@section('content')
<h4 class="py-4 mb-4">{{ __('admin.support_tickets') }}</h4>

<div class="card border-0 shadow-sm">
    <div class="card-header">
        <form id="support-filter" class="d-flex gap-2">
            <select name="status" id="support-status" class="form-select" style="max-width: 200px;">
                <option value="">{{ __('admin.all_statuses') }}</option>
                <option value="open">{{ __('admin.open') }}</option>
                <option value="in_progress">{{ __('admin.in_progress') }}</option>
                <option value="resolved">{{ __('admin.resolved') }}</option>
            </select>
            <button type="button" id="support-filter-btn" class="btn btn-primary">{{ __('admin.filter') }}</button>
        </form>
    </div>
    <div class="table-responsive text-nowrap">
        <table id="support-table" class="table table-hover table-striped">
            <thead>
                <tr>
                    <th>#</th>
                    <th>User</th>
                    <th>Type</th>
                    <th>Subject</th>
                    <th>{{ __('admin.status') }}</th>
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
    const table = $('#support-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('admin.support.data') }}",
            data: function(d) {
                d.status = $('#support-status').val();
            }
        },
        columns: [
            { data: 'id', name: 'id' },
            { data: 'user_contact', name: 'user_id' },
            { data: 'issue_type', name: 'issue_type' },
            { data: 'subject', name: 'subject' },
            { data: 'status', name: 'status' },
            { data: 'created_at', name: 'created_at' },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        language: dtLang
    });
    $('#support-filter-btn').on('click', () => table.ajax.reload());
});
</script>
@endpush
