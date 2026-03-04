@extends('layouts.admin')

@section('title', __('admin.onboarding'))

@section('content')
<h4 class="py-4 mb-4">{{ __('admin.onboarding') }}</h4>

@if (session('success'))
    <div class="alert alert-success alert-dismissible">{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="card border-0 shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">{{ __('admin.list') }}</h5>
        <a href="{{ route('admin.config.onboarding.create') }}" class="btn btn-primary">
            <span class="d-flex align-items-center gap-2">
                <i class="icon-base ti tabler-plus icon-xs"></i>
                <span>{{ __('admin.add_page') }}</span>
            </span>
        </a>
    </div>
    <div class="table-responsive text-nowrap">
        <table id="onboarding-table" class="table table-hover table-striped">
            <thead>
                <tr>
                    <th>#</th>
                    <th>sort_order</th>
                    <th>title_en</th>
                    <th>title_ar</th>
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
    const table = $('#onboarding-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: "{{ route('admin.config.onboarding.data') }}",
        columns: [
            { data: 'id', name: 'id' },
            { data: 'sort_order', name: 'sort_order' },
            { data: 'title_en', name: 'title_en' },
            { data: 'title_ar', name: 'title_ar' },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        language: dtLang
    });

    $(document).on('click', '.btn-delete', function() {
        const url = $(this).data('url');
        const confirmMsg = @json(__('admin.confirm_delete_page'));

        Swal.fire({
            title: confirmMsg,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: @json(__('admin.yes')),
            cancelButtonText: @json(__('admin.no'))
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({ title: @json(__('admin.loading')), allowOutsideClick: false, didOpen: () => Swal.showLoading() });

                fetch(url, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                }).then(r => r.json()).then(data => {
                    Swal.close();
                    if (data.success) {
                        Swal.fire({ icon: 'success', title: data.message });
                        table.ajax.reload();
                    } else {
                        Swal.fire({ icon: 'error', title: data.message || @json(__('admin.error')) });
                    }
                }).catch(() => {
                    Swal.fire({ icon: 'error', title: @json(__('admin.error')) });
                });
            }
        });
    });
});
</script>
@endpush
