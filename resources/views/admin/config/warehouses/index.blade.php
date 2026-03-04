@extends('layouts.admin')

@section('title', __('admin.warehouses'))

@section('content')
<h4 class="py-4 mb-4">{{ __('admin.warehouses') }}</h4>

@if (session('success'))
    <div class="alert alert-success alert-dismissible">{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="card border-0 shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">{{ __('admin.list') }}</h5>
        <a href="{{ route('admin.config.warehouses.create') }}" class="btn btn-primary">
            <span class="d-flex align-items-center gap-2">
                <i class="icon-base ti tabler-plus icon-xs"></i>
                <span>{{ __('admin.add_warehouse') }}</span>
            </span>
        </a>
    </div>
    <div class="table-responsive text-nowrap">
        <table id="warehouses-table" class="table table-hover table-striped">
            <thead>
                <tr>
                    <th>#</th>
                    <th>slug</th>
                    <th>label</th>
                    <th>country_code</th>
                    <th>is_active</th>
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
    const table = $('#warehouses-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: "{{ route('admin.config.warehouses.data') }}",
        columns: [
            { data: 'id', name: 'id' },
            { data: 'slug', name: 'slug' },
            { data: 'label', name: 'label' },
            { data: 'country_code', name: 'country_code' },
            { data: 'is_active', name: 'is_active' },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        language: dtLang
    });
    $(document).on('click', '#warehouses-table .btn-delete', function() {
        const url = $(this).data('url');
        Swal.fire({ title: @json(__('admin.confirm_delete_warehouse')), icon: 'warning', showCancelButton: true, confirmButtonText: @json(__('admin.yes')), cancelButtonText: @json(__('admin.no')) }).then((r) => {
            if (r.isConfirmed) { Swal.fire({ title: @json(__('admin.loading')), allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                fetch(url, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(x=>x.json()).then(d=>{ Swal.close(); d.success ? (Swal.fire({ icon: 'success', title: d.message }), table.ajax.reload()) : Swal.fire({ icon: 'error', title: d.message || @json(__('admin.error')) }); }).catch(()=>Swal.fire({ icon: 'error', title: @json(__('admin.error')) }));
            }
        });
    });
});
</script>
@endpush
