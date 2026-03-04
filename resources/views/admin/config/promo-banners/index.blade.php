@extends('layouts.admin')

@section('title', __('admin.promo_banners'))

@section('content')
<h4 class="py-4 mb-4">{{ __('admin.promo_banners') }}</h4>

@if (session('success'))
    <div class="alert alert-success alert-dismissible">{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="card border-0 shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">{{ __('admin.list') }}</h5>
        <a href="{{ route('admin.config.promo-banners.create') }}" class="btn btn-primary">
            <span class="d-flex align-items-center gap-2">
                <i class="icon-base ti tabler-plus icon-xs"></i>
                <span>{{ __('admin.add_banner') }}</span>
            </span>
        </a>
    </div>
    <div class="table-responsive text-nowrap">
        <table id="promo-banners-table" class="table table-hover table-striped">
            <thead>
                <tr>
                    <th>#</th>
                    <th>label</th>
                    <th>title</th>
                    <th>sort_order</th>
                    <th>is_active</th>
                    <th>start_at</th>
                    <th>end_at</th>
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
    const table = $('#promo-banners-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: "{{ route('admin.config.promo-banners.data') }}",
        columns: [
            { data: 'id', name: 'id' },
            { data: 'label', name: 'label' },
            { data: 'title', name: 'title' },
            { data: 'sort_order', name: 'sort_order' },
            { data: 'is_active', name: 'is_active' },
            { data: 'start_at', name: 'start_at' },
            { data: 'end_at', name: 'end_at' },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        language: dtLang
    });
    $(document).on('click', '#promo-banners-table .btn-delete', function() {
        const url = $(this).data('url');
        Swal.fire({ title: @json(__('admin.confirm_delete_banner')), icon: 'warning', showCancelButton: true, confirmButtonText: @json(__('admin.yes')), cancelButtonText: @json(__('admin.no')) }).then((r) => {
            if (r.isConfirmed) { Swal.fire({ title: @json(__('admin.loading')), allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                fetch(url, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(x=>x.json()).then(d=>{ Swal.close(); d.success ? (Swal.fire({ icon: 'success', title: d.message }), table.ajax.reload()) : Swal.fire({ icon: 'error', title: d.message || @json(__('admin.error')) }); }).catch(()=>Swal.fire({ icon: 'error', title: @json(__('admin.error')) }));
            }
        });
    });
});
</script>
@endpush
