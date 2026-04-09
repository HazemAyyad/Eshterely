@push('scripts')
<script>
(function() {
    function getReceiveReload() {
        if (typeof window.warehouseReceiveOnSuccess === 'function') {
            return window.warehouseReceiveOnSuccess;
        }
        return function() { window.location.reload(); };
    }
    const whCtxOrderLbl = @json(__('admin.order_number'));
    const whCtxProductLbl = @json(__('admin.product'));
    document.addEventListener('DOMContentLoaded', function() {
        const whModal = document.getElementById('warehouseReceiveModal');
        const form = document.getElementById('warehouse-receive-modal-form');
        if (!whModal || !form) return;

        function disposeWhTooltips() {
            whModal.querySelectorAll('[data-wh-tooltip]').forEach(function(el) {
                const t = bootstrap.Tooltip.getInstance(el);
                if (t) t.dispose();
            });
        }

        whModal.addEventListener('show.bs.modal', function(event) {
            const btn = event.relatedTarget;
            if (!btn || !btn.classList.contains('js-wh-receive-modal')) return;
            const url = btn.getAttribute('data-receive-url');
            if (url) form.setAttribute('action', url);
            const ctx = document.getElementById('wh-receive-modal-context');
            if (ctx) {
                const on = btn.getAttribute('data-order-number') || '';
                const pn = btn.getAttribute('data-product-name') || '';
                ctx.textContent = (on ? (whCtxOrderLbl + ': ' + on + ' — ') : '') + (pn ? (whCtxProductLbl + ': ' + pn) : '');
            }
        });

        whModal.addEventListener('shown.bs.modal', function() {
            disposeWhTooltips();
            whModal.querySelectorAll('[data-wh-tooltip]').forEach(function(el) {
                new bootstrap.Tooltip(el, { container: '#warehouseReceiveModal', boundary: 'viewport', trigger: 'hover focus' });
            });
        });
        whModal.addEventListener('hidden.bs.modal', disposeWhTooltips);

        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const actionUrl = form.getAttribute('action');
            if (!actionUrl) {
                if (typeof Swal !== 'undefined') Swal.fire({ icon: 'error', title: @json(__('admin.receive_modal_missing_url')) });
                return;
            }
            const btn = form.querySelector('button[type="submit"]');
            if (btn) btn.disabled = true;
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
                    getReceiveReload()();
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({ icon: 'success', title: res.data.message || @json(__('admin.success')) });
                    }
                } else {
                    const msg = (res.data && res.data.message) ? res.data.message : @json(__('admin.error'));
                    if (typeof Swal !== 'undefined') Swal.fire({ icon: 'error', title: msg });
                }
            }).catch(function() {
                if (btn) btn.disabled = false;
                if (typeof Swal !== 'undefined') Swal.fire({ icon: 'error', title: @json(__('admin.error')) });
            });
        });
    });
})();
</script>
@endpush
