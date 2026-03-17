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
                    <th>Image</th>
                    <th>Product</th>
                    <th>Store</th>
                    <th>{{ __('admin.user_name') }}</th>
                    <th>Contact</th>
                    <th>Price</th>
                    <th>Qty</th>
                    <th>Variation</th>
                    <th>Weight / Dims</th>
                    <th>Shipping basis</th>
                    <th>{{ __('admin.shipping_cost') }}</th>
                    <th>{{ __('admin.date') }}</th>
                    <th>{{ __('admin.actions') }}</th>
                </tr>
            </thead>
        </table>
    </div>
</div>

{{-- Modal: full item details --}}
<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('admin.details') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailsModalBody">
                <dl class="row mb-0"></dl>
            </div>
        </div>
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
            { data: 'image', name: 'image', orderable: false, searchable: false },
            { data: 'name', name: 'name' },
            { data: 'store_name', name: 'store_name' },
            { data: 'user_name', name: 'user_id' },
            { data: 'user_contact', name: 'user_id' },
            { data: 'unit_price', name: 'unit_price' },
            { data: 'quantity', name: 'quantity' },
            { data: 'variation_text', name: 'variation_text', orderable: false, searchable: false },
            { data: 'weight_dims', name: 'weight_dims', orderable: false, searchable: false },
            { data: 'shipping_basis', name: 'shipping_basis', orderable: false, searchable: false },
            { data: 'shipping_cost_edit', name: 'shipping_cost', orderable: false, searchable: false },
            { data: 'created_at', name: 'created_at' },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        language: dtLang
    });

    function doReview(url, status) {
        Swal.fire({ title: @json(__('admin.loading')), allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        const fd = new FormData();
        fd.append('review_status', status);
        fd.append('_method', 'PATCH');
        fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
            .then(r => r.json()).then(d => {
                Swal.close();
                d.success ? (Swal.fire({ icon: 'success', title: d.message }), table.ajax.reload()) : Swal.fire({ icon: 'error', title: d.message || @json(__('admin.error')) });
            }).catch(() => Swal.fire({ icon: 'error', title: @json(__('admin.error')) }));
    }
    $(document).on('click', '#cart-review-table .btn-approve', function() { doReview($(this).data('url'), 'reviewed'); });
    $(document).on('click', '#cart-review-table .btn-reject', function() { doReview($(this).data('url'), 'rejected'); });

    // Details modal
    $(document).on('click', '#cart-review-table .btn-details', function() {
        const d = $(this).data('details');
        if (!d) return;
        const labels = {
            id: 'ID', name: 'Product', product_url: 'URL', unit_price: 'Unit price', quantity: 'Qty', currency: 'Currency',
            store_name: 'Store', store_key: 'Store key', product_id: 'Product ID', country: 'Country', variation_text: 'Variation',
            weight: 'Weight', weight_unit: 'Weight unit', length: 'Length', width: 'Width', height: 'Height', dimension_unit: 'Dimension unit',
            source: 'Source', review_status: 'Review status', shipping_cost: 'Shipping cost', created_at: 'Created'
        };
        let html = '';
        for (const [k, v] of Object.entries(d)) {
            if (v === null || v === '') continue;
            html += '<dt class="col-sm-4">' + (labels[k] || k) + '</dt><dd class="col-sm-8">' + (typeof v === 'string' && v.startsWith('http') ? '<a href="' + v + '" target="_blank">' + v + '</a>' : v) + '</dd>';
        }
        $('#detailsModalBody dl').html(html);
        new bootstrap.Modal('#detailsModal').show();
    });

    // Save shipping cost
    $(document).on('click', '#cart-review-table .btn-save-shipping', function() {
        const btn = $(this);
        const url = btn.data('url');
        const row = btn.closest('tr');
        const input = row.find('.shipping-cost-input');
        const val = parseFloat(input.val());
        if (isNaN(val) || val < 0) {
            Swal.fire({ icon: 'warning', title: 'Enter a valid shipping cost (≥ 0)' });
            return;
        }
        Swal.fire({ title: @json(__('admin.loading')), allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        const fd = new FormData();
        fd.append('shipping_cost', val);
        fd.append('_method', 'PATCH');
        fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
            .then(r => r.json()).then(d => {
                Swal.close();
                if (d.success) {
                    Swal.fire({ icon: 'success', title: d.message });
                    table.ajax.reload();
                } else {
                    Swal.fire({ icon: 'error', title: d.message || @json(__('admin.error')) });
                }
            }).catch(() => Swal.fire({ icon: 'error', title: @json(__('admin.error')) }));
    });
});
</script>
@endpush
