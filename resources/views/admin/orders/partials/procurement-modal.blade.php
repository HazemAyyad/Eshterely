<div class="modal fade" id="orderLineProcurementModal" tabindex="-1" aria-labelledby="orderLineProcurementModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content d-flex flex-column" style="max-height: min(90vh, 900px);">
            <form method="POST" class="ajax-submit-form d-flex flex-column flex-grow-1" id="order-line-procurement-form" style="min-height:0;" action="">
                @csrf
                @method('PATCH')
                <div class="modal-header flex-shrink-0">
                    <div>
                        <h5 class="modal-title mb-0" id="orderLineProcurementModalLabel">{{ __('admin.procurement_modal_title') }}</h5>
                        <div class="small text-muted mt-1">{{ __('admin.procurement_modal_fields_intro') }}</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body flex-grow-1 overflow-auto" style="min-height:0;">
                    <fieldset class="border rounded-3 p-3 mb-0">
                        <legend class="float-none w-auto px-1 fs-6 mb-3 text-secondary">{{ __('admin.procurement_modal_fieldset_legend') }}</legend>
                        <div class="mb-3">
                            <label class="form-label" for="proc-modal-actual-price">{{ __('admin.actual_purchase_price') }}</label>
                            <input type="number" step="0.01" min="0" name="actual_purchase_price" class="form-control" id="proc-modal-actual-price">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="proc-modal-store-tracking">{{ __('admin.store_tracking') }}</label>
                            <input type="text" name="store_tracking" class="form-control" id="proc-modal-store-tracking" maxlength="255" autocomplete="off">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="proc-modal-purchase-notes">{{ __('admin.purchase_notes') }}</label>
                            <input type="text" name="purchase_notes" class="form-control" id="proc-modal-purchase-notes" maxlength="2000" autocomplete="off">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="proc-modal-purchase-details">{{ __('admin.purchase_details') }}</label>
                            <textarea name="purchase_details" class="form-control" id="proc-modal-purchase-details" rows="3" maxlength="5000" placeholder="{{ __('admin.purchase_details_placeholder') }}"></textarea>
                        </div>
                        <div class="mb-0">
                            <label class="form-label" for="proc-modal-assigned-buyer">{{ __('admin.assigned_buyer') }}</label>
                            <input type="text" name="assigned_buyer" class="form-control" id="proc-modal-assigned-buyer" maxlength="120" autocomplete="off">
                        </div>
                    </fieldset>
                    <input type="hidden" name="procurement_action" id="proc-modal-action" value="">
                    <p class="small text-muted mt-3 mb-0 d-none" id="proc-modal-no-transitions-note">{{ __('admin.procurement_modal_no_transition_note') }}</p>
                </div>
                <div class="modal-footer flex-wrap gap-2 flex-shrink-0 border-top bg-body">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('admin.cancel') }}</button>
                    <button type="submit" class="btn btn-primary" id="proc-modal-save-only">{{ __('admin.save_procurement_notes') }}</button>
                    <button type="submit" class="btn btn-success d-none" id="proc-modal-mark-purchased" data-action="mark_purchased">{{ __('admin.mark_purchased') }}</button>
                    <button type="submit" class="btn btn-warning text-dark d-none" id="proc-modal-mark-transit" data-action="mark_in_transit">{{ __('admin.mark_in_transit_wh') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const modalEl = document.getElementById('orderLineProcurementModal');
    const form = document.getElementById('order-line-procurement-form');
    if (!modalEl || !form) return;

    function normalizeFs(st) {
        return String(st ?? '').trim();
    }

    function applyProcurementActionButtons(stRaw) {
        const st = normalizeFs(stRaw);
        const btnPurch = document.getElementById('proc-modal-mark-purchased');
        const btnTransit = document.getElementById('proc-modal-mark-transit');
        const note = document.getElementById('proc-modal-no-transitions-note');
        if (!btnPurch || !btnTransit) return;

        btnPurch.classList.add('d-none');
        btnTransit.classList.add('d-none');
        if (note) note.classList.add('d-none');

        if (st === 'paid' || st === 'reviewed') {
            btnPurch.classList.remove('d-none');
            return;
        }
        if (st === 'purchased') {
            btnTransit.classList.remove('d-none');
            return;
        }
        const pastPurchase = ['in_transit_to_warehouse', 'arrived_at_warehouse', 'ready_for_shipment'];
        if (note && pastPurchase.indexOf(st) !== -1) {
            note.classList.remove('d-none');
        }
    }

    function parseProcurementPayload(btn) {
        const b64 = btn.getAttribute('data-procurement-b64');
        if (b64) {
            try {
                return JSON.parse(atob(b64));
            } catch (e) {
                return null;
            }
        }
        if (btn.dataset.procurement) {
            try {
                return JSON.parse(btn.dataset.procurement);
            } catch (e) {
                return null;
            }
        }
        return null;
    }

    modalEl.addEventListener('show.bs.modal', function(event) {
        const btn = event.relatedTarget;
        if (!btn) return;
        const p = parseProcurementPayload(btn);
        if (!p || !p.action) return;
        form.setAttribute('action', p.action);
        document.getElementById('proc-modal-actual-price').value = p.actual_purchase_price !== null && p.actual_purchase_price !== undefined && p.actual_purchase_price !== '' ? p.actual_purchase_price : '';
        document.getElementById('proc-modal-store-tracking').value = p.store_tracking || '';
        document.getElementById('proc-modal-purchase-notes').value = p.purchase_notes || '';
        document.getElementById('proc-modal-purchase-details').value = p.purchase_details || '';
        document.getElementById('proc-modal-assigned-buyer').value = p.assigned_buyer || '';
        document.getElementById('proc-modal-action').value = '';

        applyProcurementActionButtons(p.fulfillment_status);
    });

    document.getElementById('proc-modal-save-only')?.addEventListener('click', function() {
        document.getElementById('proc-modal-action').value = '';
    });
    document.getElementById('proc-modal-mark-purchased')?.addEventListener('click', function() {
        document.getElementById('proc-modal-action').value = 'mark_purchased';
    });
    document.getElementById('proc-modal-mark-transit')?.addEventListener('click', function() {
        document.getElementById('proc-modal-action').value = 'mark_in_transit';
    });
});
</script>
@endpush
