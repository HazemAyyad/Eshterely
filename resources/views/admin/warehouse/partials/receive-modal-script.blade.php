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
    const defaultModalTitle = @json(__('admin.warehouse_receive_modal_title'));
    const editModalTitle = @json(__('admin.warehouse_receive_edit_title'));
    const defaultReceivedAt = @json(now()->format('Y-m-d\TH:i'));
    const keepImageLabel = @json(__('admin.keep_image_label'));

    document.addEventListener('DOMContentLoaded', function() {
        const whModal = document.getElementById('warehouseReceiveModal');
        const form = document.getElementById('warehouse-receive-modal-form');
        if (!whModal || !form) return;

        const methodInput = document.getElementById('wh-receive-http-method');
        const existingWrap = document.getElementById('wh-receive-existing-images-wrap');
        const existingContainer = document.getElementById('wh-receive-existing-images');
        const modalTitleEl = document.getElementById('warehouseReceiveModalLabel');
        const fileInput = document.getElementById('wh-receive-file-input');

        function disposeWhTooltips() {
            whModal.querySelectorAll('[data-wh-tooltip]').forEach(function(el) {
                const t = bootstrap.Tooltip.getInstance(el);
                if (t) t.dispose();
            });
        }

        function setField(name, val) {
            const el = form.querySelector('[name="' + name + '"]');
            if (!el) return;
            if (val === null || val === undefined) {
                el.value = '';
            } else {
                el.value = val;
            }
        }

        function resetCreateMode() {
            if (methodInput) {
                methodInput.value = '';
                methodInput.disabled = true;
            }
            if (modalTitleEl) modalTitleEl.textContent = defaultModalTitle;
            if (existingWrap) existingWrap.classList.add('d-none');
            if (existingContainer) existingContainer.innerHTML = '';
            form.reset();
            setField('received_at', defaultReceivedAt);
            setField('additional_fee_amount', '0');
            if (fileInput) fileInput.value = '';
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

            const b64 = btn.getAttribute('data-receive-edit-b64');
            if (b64 && methodInput && existingWrap && existingContainer && modalTitleEl) {
                let p;
                try {
                    p = JSON.parse(atob(b64));
                } catch (e) {
                    resetCreateMode();
                    return;
                }
                methodInput.disabled = false;
                methodInput.value = 'PUT';
                modalTitleEl.textContent = editModalTitle;
                existingWrap.classList.remove('d-none');
                existingContainer.innerHTML = '';

                setField('received_at', p.received_at || '');
                setField('received_weight', p.received_weight !== null && p.received_weight !== undefined ? p.received_weight : '');
                setField('received_length', p.received_length !== null && p.received_length !== undefined ? p.received_length : '');
                setField('received_width', p.received_width !== null && p.received_width !== undefined ? p.received_width : '');
                setField('received_height', p.received_height !== null && p.received_height !== undefined ? p.received_height : '');
                setField('additional_fee_amount', p.additional_fee_amount !== null && p.additional_fee_amount !== undefined ? p.additional_fee_amount : '0');
                setField('condition_notes', p.condition_notes || '');
                setField('special_handling_type', p.special_handling_type || '');
                setField('images_text', '');

                (p.images || []).forEach(function(img, idx) {
                    if (!img || !img.raw) return;
                    const row = document.createElement('div');
                    row.className = 'd-flex align-items-start gap-2 mb-2';
                    const cb = document.createElement('input');
                    cb.type = 'checkbox';
                    cb.className = 'form-check-input mt-1';
                    cb.name = 'retained_image_urls[]';
                    cb.value = img.raw;
                    cb.checked = true;
                    cb.id = 'wh-retain-img-' + idx;
                    const lab = document.createElement('label');
                    lab.className = 'form-check-label d-flex align-items-center gap-2 mb-0';
                    lab.setAttribute('for', cb.id);
                    const im = document.createElement('img');
                    im.src = img.display || '';
                    im.width = 40;
                    im.height = 40;
                    im.className = 'rounded border';
                    im.alt = '';
                    im.loading = 'lazy';
                    lab.appendChild(im);
                    lab.appendChild(document.createTextNode(' ' + keepImageLabel));
                    row.appendChild(cb);
                    row.appendChild(lab);
                    existingContainer.appendChild(row);
                });
                if (fileInput) fileInput.value = '';
            } else {
                resetCreateMode();
            }
        });

        whModal.addEventListener('shown.bs.modal', function() {
            disposeWhTooltips();
            whModal.querySelectorAll('[data-wh-tooltip]').forEach(function(el) {
                new bootstrap.Tooltip(el, { container: '#warehouseReceiveModal', boundary: 'viewport', trigger: 'hover focus' });
            });
        });
        whModal.addEventListener('hidden.bs.modal', function() {
            disposeWhTooltips();
            resetCreateMode();
        });

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
