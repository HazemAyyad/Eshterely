{{-- Shared modal + script: set window.walletInlineDataTableReload before including --}}
<div class="modal fade" id="walletInlineStatusModal" tabindex="-1" aria-labelledby="walletInlineStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="walletInlineStatusModalLabel">{{ __('admin.update_status') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-2" id="wallet-inline-status-hint"></p>
                <div class="mb-3">
                    <label class="form-label">{{ __('admin.status') }}</label>
                    <select id="wallet-inline-status-select" class="form-select"></select>
                </div>
                <div class="mb-0">
                    <label class="form-label">{{ __('admin.admin_notes') }}</label>
                    <textarea id="wallet-inline-status-notes" class="form-control" rows="3" placeholder="Optional"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('admin.cancel') }}</button>
                <button type="button" class="btn btn-primary" id="wallet-inline-status-save">{{ __('admin.save') }}</button>
            </div>
        </div>
    </div>
</div>

<script>
// Runs on window.load so bootstrap.js (end of body) has executed first.
(function () {
    let modalInstance = null;
    let pendingUrl = '';

    function setup() {
        const modalEl = document.getElementById('walletInlineStatusModal');
        if (!modalEl) {
            return;
        }
        if (typeof bootstrap === 'undefined') {
            return;
        }
        modalInstance = new bootstrap.Modal(modalEl);

        document.addEventListener('click', function (e) {
            const btn = e.target.closest('.js-wallet-inline-status');
            if (!btn) {
                return;
            }
            e.preventDefault();
            pendingUrl = btn.getAttribute('data-url') || '';
            let options = [];
            try {
                options = JSON.parse(btn.getAttribute('data-options') || '[]');
            } catch (err) {
                options = [];
            }
            const current = btn.getAttribute('data-current') || '';
            const sel = document.getElementById('wallet-inline-status-select');
            const notes = document.getElementById('wallet-inline-status-notes');
            const hint = document.getElementById('wallet-inline-status-hint');
            if (!sel || !notes) {
                return;
            }
            sel.innerHTML = '';
            options.forEach(function (st) {
                const opt = document.createElement('option');
                opt.value = st;
                opt.textContent = st;
                if (st === current) {
                    opt.selected = true;
                }
                sel.appendChild(opt);
            });
            notes.value = '';
            if (hint) {
                hint.textContent = 'Approvals and rejections follow the same rules as the detail page. For withdrawals, marking transferred requires a proof file — open Details.';
            }
            modalInstance.show();
        });

        const saveBtn = document.getElementById('wallet-inline-status-save');
        if (saveBtn) {
            saveBtn.addEventListener('click', function () {
                const sel = document.getElementById('wallet-inline-status-select');
                const notes = document.getElementById('wallet-inline-status-notes');
                if (!pendingUrl || !sel || !modalInstance) {
                    return;
                }
                const token = document.querySelector('meta[name="csrf-token"]');
                saveBtn.disabled = true;
                fetch(pendingUrl, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token ? token.getAttribute('content') : '',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        status: sel.value,
                        admin_notes: notes ? notes.value : '',
                    }),
                })
                    .then(function (r) {
                        return r.text().then(function (text) {
                            var data = {};
                            try { data = text ? JSON.parse(text) : {}; } catch (e) {}
                            return { ok: r.ok, data: data, status: r.status };
                        });
                    })
                    .then(function (res) {
                        saveBtn.disabled = false;
                        if (res.ok && res.data && res.data.ok) {
                            modalInstance.hide();
                            if (typeof window.walletInlineDataTableReload === 'function') {
                                window.walletInlineDataTableReload();
                            }
                            return;
                        }
                        var msg = 'Could not update status.';
                        if (res.data) {
                            if (res.data.message) {
                                msg = res.data.message;
                            } else if (res.data.errors) {
                                var first = Object.values(res.data.errors)[0];
                                if (Array.isArray(first) && first[0]) {
                                    msg = first[0];
                                }
                            }
                        }
                        alert(msg);
                    })
                    .catch(function () {
                        saveBtn.disabled = false;
                        alert('Request failed.');
                    });
            });
        }
    }

    window.addEventListener('load', function () {
        setup();
    });
})();
</script>
