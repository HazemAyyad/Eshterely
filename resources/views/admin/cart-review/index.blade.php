@extends('layouts.admin')

@section('title', __('admin.cart_review'))

@section('content')
<h4 class="py-4 mb-4">{{ __('admin.cart_review_pending') }}</h4>

@if (session('success'))
    <div class="alert alert-success alert-dismissible">{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="card border-0 shadow-sm">
    <div class="card-header">
        <p class="mb-0">{{ __('admin.cart_review') }}</p>
    </div>
    <div class="table-responsive text-nowrap">
        <table id="cart-review-table" class="table table-hover table-striped">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Product</th>
                    <th>Store</th>
                    <th>User</th>
                    <th>Price</th>
                    <th>Qty</th>
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
    const table = $('#cart-review-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: "{{ route('admin.cart-review.data') }}",
        columns: [
            { data: 'id', name: 'id' },
            { data: 'name', name: 'name' },
            { data: 'store_name', name: 'store_name' },
            { data: 'user_contact', name: 'user_id' },
            { data: 'unit_price', name: 'unit_price' },
            { data: 'quantity', name: 'quantity' },
            { data: 'created_at', name: 'created_at' },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        language: dtLang
    });

    function doReview(url, status) {
        Swal.fire({ title: @json(__('admin.loading')), allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        const fd = new FormData();
        fd.append('review_status', status);
        fetch(url, { method: 'PATCH', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
            .then(r => r.json()).then(d => {
                Swal.close();
                d.success ? (Swal.fire({ icon: 'success', title: d.message }), table.ajax.reload()) : Swal.fire({ icon: 'error', title: d.message || @json(__('admin.error')) });
            }).catch(() => Swal.fire({ icon: 'error', title: @json(__('admin.error')) }));
    }
    $(document).on('click', '#cart-review-table .btn-approve', function() { doReview($(this).data('url'), 'reviewed'); });
    $(document).on('click', '#cart-review-table .btn-reject', function() { doReview($(this).data('url'), 'rejected'); });
});
</script>
@endpush
