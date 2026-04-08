<div class="modal fade" id="orderLineProcurementModal" tabindex="-1" aria-labelledby="orderLineProcurementModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" class="ajax-submit-form" id="order-line-procurement-form" action="">
                @csrf
                @method('PATCH')
                <div class="modal-header">
                    <h5 class="modal-title" id="orderLineProcurementModalLabel">{{ __('admin.procurement_modal_title') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">{{ __('admin.actual_purchase_price') }}</label>
                        <input type="number" step="0.01" min="0" name="actual_purchase_price" class="form-control" id="proc-modal-actual-price">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('admin.store_tracking') }}</label>
                        <input type="text" name="store_tracking" class="form-control" id="proc-modal-store-tracking" maxlength="255" autocomplete="off">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('admin.purchase_notes') }}</label>
                        <input type="text" name="purchase_notes" class="form-control" id="proc-modal-purchase-notes" maxlength="2000" autocomplete="off">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('admin.purchase_details') }}</label>
                        <textarea name="purchase_details" class="form-control" id="proc-modal-purchase-details" rows="3" maxlength="5000" placeholder="{{ __('admin.purchase_details_placeholder') }}"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('admin.assigned_buyer') }}</label>
                        <input type="text" name="assigned_buyer" class="form-control" id="proc-modal-assigned-buyer" maxlength="120" autocomplete="off">
                    </div>
                    <input type="hidden" name="procurement_action" id="proc-modal-action" value="">
                </div>
                <div class="modal-footer flex-wrap gap-2">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('admin.cancel') }}</button>
                    <button type="submit" class="btn btn-primary" id="proc-modal-save-only">{{ __('admin.save_procurement_notes') }}</button>
                    <button type="submit" class="btn btn-success" id="proc-modal-mark-purchased" data-action="mark_purchased">{{ __('admin.mark_purchased') }}</button>
                    <button type="submit" class="btn btn-warning text-dark" id="proc-modal-mark-transit" data-action="mark_in_transit">{{ __('admin.mark_in_transit_wh') }}</button>
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

    modalEl.addEventListener('show.bs.modal', function(event) {
        const btn = event.relatedTarget;
        if (!btn || !btn.dataset.procurement) return;
        let p;
        try { p = JSON.parse(btn.dataset.procurement); } catch (e) { return; }
        form.setAttribute('action', p.action);
        document.getElementById('proc-modal-actual-price').value = p.actual_purchase_price !== null && p.actual_purchase_price !== undefined && p.actual_purchase_price !== '' ? p.actual_purchase_price : '';
        document.getElementById('proc-modal-store-tracking').value = p.store_tracking || '';
        document.getElementById('proc-modal-purchase-notes').value = p.purchase_notes || '';
        document.getElementById('proc-modal-purchase-details').value = p.purchase_details || '';
        document.getElementById('proc-modal-assigned-buyer').value = p.assigned_buyer || '';
        document.getElementById('proc-modal-action').value = '';

        const st = p.fulfillment_status || '';
        const canPurch = ['paid', 'reviewed'].includes(st);
        const canTransit = ['paid', 'reviewed', 'purchased'].includes(st);
        document.getElementById('proc-modal-mark-purchased').disabled = !canPurch;
        document.getElementById('proc-modal-mark-transit').disabled = !canTransit;
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
