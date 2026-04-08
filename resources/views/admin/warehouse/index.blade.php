@extends('layouts.admin')

@section('title', __('admin.warehouse_title'))

@section('content')
<h4 class="py-4 mb-2">{{ __('admin.warehouse_title') }}</h4>
<p class="text-muted mb-3">{{ __('admin.warehouse_intro_tabs') }}</p>

<ul class="nav nav-tabs mb-0 flex-wrap" id="warehouse-tabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-awaiting-arrival" data-bs-toggle="tab" data-bs-target="#pane-awaiting-arrival" type="button" role="tab" data-queue="awaiting_arrival" aria-controls="pane-awaiting-arrival" aria-selected="false">
            {{ __('admin.warehouse_tab_awaiting_arrival') }}
            <span class="badge rounded-pill bg-warning text-dark ms-1">{{ $counts['awaiting_arrival'] ?? 0 }}</span>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="tab-ready" data-bs-toggle="tab" data-bs-target="#pane-ready" type="button" role="tab" data-queue="ready_to_receive" aria-controls="pane-ready" aria-selected="true">
            {{ __('admin.warehouse_tab_ready') }}
            <span class="badge rounded-pill bg-primary ms-1">{{ $counts['ready_to_receive'] ?? 0 }}</span>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-received" data-bs-toggle="tab" data-bs-target="#pane-received" type="button" role="tab" data-queue="received" aria-controls="pane-received" aria-selected="false">
            {{ __('admin.warehouse_tab_received') }}
            <span class="badge rounded-pill bg-success ms-1">{{ $counts['received'] ?? 0 }}</span>
        </button>
    </li>
</ul>

<input type="hidden" id="warehouse-queue" value="ready_to_receive">

<div class="tab-content border border-top-0 rounded-bottom shadow-sm bg-body">
    <div class="tab-pane fade p-3" id="pane-awaiting-arrival" role="tabpanel" aria-labelledby="tab-awaiting-arrival" tabindex="0">
        <p class="small text-muted mb-0">{{ __('admin.warehouse_tab_awaiting_arrival_help') }}</p>
    </div>
    <div class="tab-pane fade show active p-3" id="pane-ready" role="tabpanel" aria-labelledby="tab-ready" tabindex="0">
        <p class="small text-muted mb-0">{{ __('admin.warehouse_tab_ready_help') }}</p>
    </div>
    <div class="tab-pane fade p-3" id="pane-received" role="tabpanel" aria-labelledby="tab-received" tabindex="0">
        <p class="small text-muted mb-0">{{ __('admin.warehouse_tab_received_help') }}</p>
    </div>
</div>

<div class="card border-0 shadow-sm mt-3">
    <div class="card-body">
        <form id="warehouse-filter" class="row g-2 align-items-end flex-wrap mb-3">
            <div class="col-auto">
                <label class="form-label small mb-0">{{ __('admin.warehouse_filter_user') }}</label>
                <input type="number" name="user_id" id="warehouse-user" class="form-control form-control-sm" min="1" placeholder="—">
            </div>
            <div class="col-auto">
                <label class="form-label small mb-0">{{ __('admin.warehouse_filter_order') }}</label>
                <input type="text" name="order_number" id="warehouse-order" class="form-control form-control-sm" placeholder="—">
            </div>
            <div class="col-auto">
                <label class="form-label small mb-0">{{ __('admin.warehouse_filter_store') }}</label>
                <input type="text" name="store" id="warehouse-store" class="form-control form-control-sm" placeholder="—">
            </div>
            <div class="col-auto">
                <button type="button" id="warehouse-filter-btn" class="btn btn-sm btn-primary">{{ __('admin.filter') }}</button>
            </div>
        </form>
        <div class="table-responsive text-nowrap">
            <table id="warehouse-table" class="table table-hover table-striped align-middle">
                <thead>
                    <tr>
                        <th>{{ __('admin.product') }}</th>
                        <th>{{ __('admin.customer') }}</th>
                        <th>{{ __('admin.order_number') }}</th>
                        <th>{{ __('admin.procurement_status') }}</th>
                        <th>{{ __('admin.store') }}</th>
                        <th>{{ __('admin.store_tracking') }}</th>
                        <th>{{ __('admin.actions') }}</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="warehouseReceiveModal" tabindex="-1" aria-labelledby="warehouseReceiveModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" id="warehouse-receive-modal-form" enctype="multipart/form-data" action="">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="warehouseReceiveModalLabel">{{ __('admin.warehouse_receive_modal_title') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3" id="wh-receive-modal-context"></p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">{{ __('admin.date') }}</label>
                            <input type="datetime-local" name="received_at" class="form-control" value="{{ now()->format('Y-m-d\TH:i') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('admin.weight_kg') }}</label>
                            <input type="number" step="0.0001" min="0" name="received_weight" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label d-flex align-items-center gap-1">
                                {{ __('admin.additional_fee') }}
                                <span class="text-muted" data-bs-toggle="tooltip" title="{{ __('admin.tooltip_additional_fee') }}"><i class="ti tabler-help-circle"></i></span>
                            </label>
                            <input type="number" step="0.01" min="0" name="additional_fee_amount" class="form-control" value="0">
                        </div>
                        <div class="col-12"><span class="text-muted small">{{ __('admin.dims_lwh') }}</span></div>
                        <div class="col-md-3">
                            <label class="form-label">L</label>
                            <input type="number" step="0.0001" min="0" name="received_length" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">W</label>
                            <input type="number" step="0.0001" min="0" name="received_width" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">H</label>
                            <input type="number" step="0.0001" min="0" name="received_height" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label d-flex align-items-center gap-1">
                                {{ __('admin.special_handling') }}
                                <span class="text-muted" data-bs-toggle="tooltip" title="{{ __('admin.tooltip_special_handling') }}"><i class="ti tabler-help-circle"></i></span>
                            </label>
                            <input type="text" name="special_handling_type" class="form-control" maxlength="50">
                        </div>
                        <div class="col-12">
                            <label class="form-label">{{ __('admin.receipt_images_upload') }}</label>
                            <input type="file" name="receipt_images[]" class="form-control" accept="image/*" multiple>
                            <div class="form-text">{{ __('admin.receipt_images_upload_help') }}</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">{{ __('admin.images_urls_optional') }}</label>
                            <textarea name="images_text" class="form-control" rows="2" placeholder="https://..."></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">{{ __('admin.notes') }}</label>
                            <textarea name="condition_notes" class="form-control" rows="2" maxlength="2000"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('admin.cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('admin.save') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="https://cdn.datatables.net/2.0.8/css/dataTables.bootstrap5.min.css" />
<style>
    #warehouse-tabs .nav-link { cursor: pointer; }
</style>
@endpush

@push('scripts')
<script src="https://cdn.datatables.net/2.0.8/js/dataTables.min.js"></script>
<script src="https://cdn.datatables.net/2.0.8/js/dataTables.bootstrap5.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const dtLang = @json(__('datatatables'));
    const queueInput = document.getElementById('warehouse-queue');
    const table = $('#warehouse-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('admin.warehouse.data') }}",
            data: function(d) {
                d.queue = queueInput.value;
                d.user_id = $('#warehouse-user').val();
                d.order_number = $('#warehouse-order').val();
                d.store = $('#warehouse-store').val();
            }
        },
        order: [[0, 'asc']],
        columns: [
            { data: 'product', name: 'order_line_items.name', searchable: true },
            { data: 'customer', name: 'customer', orderable: false, searchable: false },
            { data: 'order_number', name: 'order_number', orderable: false, searchable: false },
            { data: 'fulfillment', name: 'order_line_items.fulfillment_status', orderable: true, searchable: false },
            { data: 'store_name', name: 'order_line_items.store_name', searchable: true },
            { data: 'store_tracking', name: 'store_tracking', orderable: false, searchable: false },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        language: dtLang
    });

    document.querySelectorAll('#warehouse-tabs [data-bs-toggle="tab"]').forEach(function(el) {
        el.addEventListener('shown.bs.tab', function(e) {
            const q = e.target.getAttribute('data-queue');
            if (q) {
                queueInput.value = q;
                table.ajax.reload();
            }
        });
    });

    $('#warehouse-filter-btn').on('click', function() { table.ajax.reload(); });

    const whModal = document.getElementById('warehouseReceiveModal');
    if (whModal) {
        whModal.addEventListener('show.bs.modal', function(event) {
            const btn = event.relatedTarget;
            if (!btn || !btn.classList.contains('js-wh-receive-modal')) return;
            const url = btn.getAttribute('data-receive-url');
            const form = document.getElementById('warehouse-receive-modal-form');
            if (form && url) form.setAttribute('action', url);
            const ctx = document.getElementById('wh-receive-modal-context');
            if (ctx) {
                const on = btn.getAttribute('data-order-number') || '';
                const pn = btn.getAttribute('data-product-name') || '';
                ctx.textContent = (on ? ('{{ __('admin.order_number') }}: ' + on + ' — ') : '') + (pn ? ('{{ __('admin.product') }}: ' + pn) : '');
            }
        });
        whModal.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function(el) {
            new bootstrap.Tooltip(el);
        });
        document.getElementById('warehouse-receive-modal-form')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const form = this;
            const btn = form.querySelector('button[type="submit"]');
            if (btn) btn.disabled = true;
            const actionUrl = form.getAttribute('action');
            fetch(actionUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new FormData(form)
            }).then(function(r) { return r.json().then(function(data) { return { ok: r.ok, data: data }; }); })
              .then(function(res) {
                if (btn) btn.disabled = false;
                if (res.ok && res.data.success) {
                    bootstrap.Modal.getInstance(whModal)?.hide();
                    table.ajax.reload();
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({ icon: 'success', title: res.data.message || '{{ __('admin.success') }}' });
                    }
                } else {
                    const msg = (res.data && res.data.message) ? res.data.message : '{{ __('admin.error') }}';
                    if (typeof Swal !== 'undefined') Swal.fire({ icon: 'error', title: msg });
                }
            }).catch(function() {
                if (btn) btn.disabled = false;
                if (typeof Swal !== 'undefined') Swal.fire({ icon: 'error', title: '{{ __('admin.error') }}' });
            });
        });
    }
});
</script>
@endpush
