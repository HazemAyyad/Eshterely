@once
@push('styles')
<style>
    .sh-ship-modal .modal-dialog {
        max-height: calc(100vh - 1.5rem);
        margin-top: 0.75rem;
        margin-bottom: 0.75rem;
    }
    .sh-ship-modal .modal-content {
        max-height: calc(100vh - 1.5rem);
    }
    .sh-ship-modal .modal-body {
        min-height: 0;
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
    }
</style>
@endpush
@endonce

<div class="modal fade sh-ship-modal" id="shipmentShipModal" tabindex="-1" aria-labelledby="shipmentShipModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content d-flex flex-column mh-100">
            <form method="POST" id="shipment-ship-modal-form" class="d-flex flex-column flex-grow-1" style="min-height:0;" action="">
                @csrf
                <div class="modal-header flex-shrink-0">
                    <h5 class="modal-title" id="shipmentShipModalLabel">{{ __('admin.mark_shipped') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body flex-grow-1 overflow-auto">
                    <p class="small text-muted mb-3">{{ __('admin.ship_modal_intro') }}</p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">{{ __('admin.carrier') }} *</label>
                            <input type="text" name="carrier" class="form-control" maxlength="80" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('admin.tracking_number') }} *</label>
                            <input type="text" name="tracking_number" class="form-control" maxlength="255" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">{{ __('admin.dispatch_note') }}</label>
                            <textarea name="dispatch_note" class="form-control" rows="2" maxlength="1000"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer flex-shrink-0 border-top bg-body">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('admin.cancel') }}</button>
                    <button type="submit" class="btn btn-success">{{ __('admin.mark_shipped') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function() {
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('shipmentShipModal');
        const form = document.getElementById('shipment-ship-modal-form');
        if (!modal || !form) return;

        modal.addEventListener('show.bs.modal', function(event) {
            const btn = event.relatedTarget;
            if (!btn || !btn.classList.contains('js-open-shipment-ship')) return;
            const url = btn.getAttribute('data-ship-url');
            if (url) form.setAttribute('action', url);
            form.reset();
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
            const fd = new FormData(form);
            fetch(actionUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: fd
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
