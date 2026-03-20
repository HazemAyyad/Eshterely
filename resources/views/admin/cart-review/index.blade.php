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
                    <th>{{ __('admin.package_modal_title') }}</th>
                    <th>Shipping basis</th>
                    <th>{{ __('admin.shipping_cost') }} / {{ __('admin.recalculate_shipping') }}</th>
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

{{-- Modal: edit weight & dimensions (then admin can run system recalc) --}}
<div class="modal fade" id="editPackageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('admin.package_modal_title') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editPackageForm" action="#" method="post">
                @csrf
                <input type="hidden" name="_method" value="PATCH">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label" for="pkg-weight">Weight</label>
                            <input type="number" step="any" min="0" class="form-control" name="weight" id="pkg-weight" placeholder="e.g. 10" autocomplete="off">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="pkg-weight-unit">Unit</label>
                            <select class="form-select" name="weight_unit" id="pkg-weight-unit">
                                <option value="lb">lb</option>
                                <option value="g">g</option>
                                <option value="kg">kg</option>
                            </select>
                        </div>
                        <div class="col-4">
                            <label class="form-label" for="pkg-length">L</label>
                            <input type="number" step="any" min="0" class="form-control" name="length" id="pkg-length" autocomplete="off">
                        </div>
                        <div class="col-4">
                            <label class="form-label" for="pkg-width">W</label>
                            <input type="number" step="any" min="0" class="form-control" name="width" id="pkg-width" autocomplete="off">
                        </div>
                        <div class="col-4">
                            <label class="form-label" for="pkg-height">H</label>
                            <input type="number" step="any" min="0" class="form-control" name="height" id="pkg-height" autocomplete="off">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="pkg-dim-unit">Dimension unit</label>
                            <select class="form-select" name="dimension_unit" id="pkg-dim-unit">
                                <option value="in">in</option>
                                <option value="cm">cm</option>
                            </select>
                        </div>
                    </div>
                    <p class="small text-muted mt-3 mb-0">{{ __('admin.package_save_hint') }}</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">{{ __('admin.cancel') }}</button>
                    <button type="submit" class="btn btn-primary" id="pkg-save-btn">{{ __('admin.save') }}</button>
                </div>
            </form>
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
        scrollX: true,
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

    let editPackageModal = null;
    $(document).on('click', '#cart-review-table .btn-edit-package', function() {
        const $btn = $(this);
        let p = $btn.data('package');
        if (typeof p === 'string') {
            try { p = JSON.parse(p); } catch (e) { return; }
        }
        if (!p || !p.id) return;
        // Use attr: jQuery .data('save-url') is unreliable (camelCase cache vs data-save-url)
        const saveUrl = $btn.attr('data-save-url');
        $('#editPackageForm').attr('data-save-url', saveUrl || '');
        $('#pkg-weight').val(p.weight != null && p.weight !== '' ? p.weight : '');
        $('#pkg-weight-unit').val(p.weight_unit && ['lb','g','kg'].includes(p.weight_unit) ? p.weight_unit : 'lb');
        $('#pkg-length').val(p.length != null && p.length !== '' ? p.length : '');
        $('#pkg-width').val(p.width != null && p.width !== '' ? p.width : '');
        $('#pkg-height').val(p.height != null && p.height !== '' ? p.height : '');
        $('#pkg-dim-unit').val(p.dimension_unit && ['in','cm'].includes(p.dimension_unit) ? p.dimension_unit : 'in');
        if (!editPackageModal) {
            editPackageModal = new bootstrap.Modal(document.getElementById('editPackageModal'));
        }
        editPackageModal.show();
    });

    $('#editPackageForm').on('submit', function(e) {
        e.preventDefault();
        const saveUrl = $(this).attr('data-save-url');
        if (!saveUrl) {
            Swal.fire({ icon: 'warning', title: @json(__('admin.error')), text: 'Missing save URL' });
            return;
        }
        const token = document.querySelector('meta[name="csrf-token"]');
        const fd = new FormData(this);
        Swal.fire({ title: @json(__('admin.loading')), allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        fetch(saveUrl, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token ? token.content : ''
            },
            body: fd
        })
            .then(async function(r) {
                const text = await r.text();
                let d = {};
                try { d = text ? JSON.parse(text) : {}; } catch (err) { d = { message: text || r.statusText }; }
                return { ok: r.ok, status: r.status, data: d };
            })
            .then(function(res) {
                Swal.close();
                if (res.ok && res.data.success) {
                    Swal.fire({ icon: 'success', title: res.data.message || @json(__('admin.package_updated')) });
                    if (editPackageModal) {
                        editPackageModal.hide();
                    }
                    table.ajax.reload(null, false);
                } else {
                    const msg = (res.data && res.data.message) ? res.data.message : (@json(__('admin.error')) + ' (' + res.status + ')');
                    Swal.fire({ icon: 'error', title: msg });
                }
            })
            .catch(function() {
                Swal.close();
                Swal.fire({ icon: 'error', title: @json(__('admin.error')) });
            });
    });

    $(document).on('click', '#cart-review-table .btn-recalc-shipping', function() {
        const url = $(this).data('url');
        Swal.fire({ title: @json(__('admin.loading')), allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json()).then(d => {
                Swal.close();
                d.success ? (Swal.fire({ icon: 'success', title: d.message }), table.ajax.reload()) : Swal.fire({ icon: 'error', title: d.message || @json(__('admin.error')) });
            }).catch(() => Swal.fire({ icon: 'error', title: @json(__('admin.error')) }));
    });

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
