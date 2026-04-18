@once
@push('styles')
<style>
    .sh-pack-modal .modal-dialog {
        max-height: calc(100vh - 1.5rem);
        margin-top: 0.75rem;
        margin-bottom: 0.75rem;
    }
    .sh-pack-modal .modal-content {
        max-height: calc(100vh - 1.5rem);
    }
    .sh-pack-modal .modal-body {
        min-height: 0;
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
    }
</style>
@endpush
@endonce

<div class="modal fade sh-pack-modal" id="shipmentPackModal" tabindex="-1" aria-labelledby="shipmentPackModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content d-flex flex-column mh-100">
            <form method="POST" id="shipment-pack-modal-form" class="d-flex flex-column flex-grow-1" style="min-height:0;" enctype="multipart/form-data" action="">
                @csrf
                <div class="modal-header flex-shrink-0">
                    <h5 class="modal-title" id="shipmentPackModalLabel">{{ __('admin.pack_shipment') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body flex-grow-1 overflow-auto">
                    <p class="small text-muted mb-3">{{ __('admin.pack_modal_intro') }}</p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">{{ __('admin.weight_lb') }} *</label>
                            <input type="number" step="0.0001" min="0" name="final_weight" class="form-control" required>
                        </div>
                        <div class="col-12"><span class="text-muted small">{{ __('admin.pack_dims_lwh_in') }}</span></div>
                        <div class="col-md-4">
                            <label class="form-label">{{ __('admin.length_in') }} *</label>
                            <input type="number" step="0.0001" min="0" name="final_length" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ __('admin.width_in') }} *</label>
                            <input type="number" step="0.0001" min="0" name="final_width" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ __('admin.height_in') }} *</label>
                            <input type="number" step="0.0001" min="0" name="final_height" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">{{ __('admin.final_box_image') }}</label>
                            <input type="file" name="final_box_image" id="shipment-pack-image" class="form-control" accept="image/*">
                            <div class="form-text">{{ __('admin.pack_image_upload_help') }}</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer flex-shrink-0 border-top bg-body">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('admin.cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('admin.save') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function() {
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('shipmentPackModal');
        const form = document.getElementById('shipment-pack-modal-form');
        const fileInput = document.getElementById('shipment-pack-image');
        if (!modal || !form) return;

        modal.addEventListener('show.bs.modal', function(event) {
            const btn = event.relatedTarget;
            if (!btn || !btn.classList.contains('js-open-shipment-pack')) return;
            const url = btn.getAttribute('data-pack-url');
            if (url) form.setAttribute('action', url);
            form.reset();
            if (fileInput) fileInput.value = '';
        });

        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const actionUrl = form.getAttribute('action');
            if (!actionUrl) {
                if (typeof Swal !== 'undefined') Swal.fire({ icon: 'error', title: @json(__('admin.error')) });
                return;
            }
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) submitBtn.disabled = true;
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
                if (submitBtn) submitBtn.disabled = false;
                if (res.ok && res.data.success) {
                    bootstrap.Modal.getInstance(modal)?.hide();
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({ icon: 'success', title: res.data.message || @json(__('admin.success')) });
                    }
                    window.location.reload();
                } else {
                    const msg = (res.data && res.data.message) ? res.data.message : @json(__('admin.error'));
                    if (typeof Swal !== 'undefined') Swal.fire({ icon: 'error', title: msg });
                }
            }).catch(function() {
                if (submitBtn) submitBtn.disabled = false;
                if (typeof Swal !== 'undefined') Swal.fire({ icon: 'error', title: @json(__('admin.error')) });
            });
        });
    });
})();
</script>
@endpush
