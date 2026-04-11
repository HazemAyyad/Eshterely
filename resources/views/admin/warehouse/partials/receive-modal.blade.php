@once
@push('styles')
<style>
    .wh-receive-modal .modal-dialog {
        max-height: calc(100vh - 1.5rem);
        margin-top: 0.75rem;
        margin-bottom: 0.75rem;
    }
    .wh-receive-modal .modal-content {
        max-height: calc(100vh - 1.5rem);
    }
    .wh-receive-modal .modal-body {
        min-height: 0;
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
    }
</style>
@endpush
@endonce

<div class="modal fade wh-receive-modal" id="warehouseReceiveModal" tabindex="-1" aria-labelledby="warehouseReceiveModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content d-flex flex-column mh-100">
            <form method="POST" id="warehouse-receive-modal-form" class="d-flex flex-column flex-grow-1" style="min-height:0;" enctype="multipart/form-data" action="">
                @csrf
                <input type="hidden" name="_method" id="wh-receive-http-method" value="" disabled>
                <div class="modal-header flex-shrink-0">
                    <h5 class="modal-title" id="warehouseReceiveModalLabel">{{ __('admin.warehouse_receive_modal_title') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body flex-grow-1 overflow-auto">
                    <p class="small text-muted mb-3" id="wh-receive-modal-context"></p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">{{ __('admin.date') }}</label>
                            <input type="datetime-local" name="received_at" class="form-control" value="{{ now()->format('Y-m-d\TH:i') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('admin.weight_lb') }}</label>
                            <input type="number" step="0.0001" min="0" name="received_weight" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label d-flex align-items-center gap-1">
                                {{ __('admin.additional_fee') }}
                                <span class="text-primary wh-modal-tooltip" tabindex="0" data-wh-tooltip="1" data-bs-toggle="tooltip" data-bs-container="#warehouseReceiveModal" data-bs-placement="top" title="{{ __('admin.tooltip_additional_fee') }}"><i class="ti tabler-help-circle"></i></span>
                            </label>
                            <input type="number" step="0.01" min="0" name="additional_fee_amount" class="form-control" value="0">
                        </div>
                        <div class="col-12"><span class="text-muted small">{{ __('admin.receive_dimensions_hint_in') }}</span></div>
                        <div class="col-md-3">
                            <label class="form-label">{{ __('admin.length_in') }}</label>
                            <input type="number" step="0.0001" min="0" name="received_length" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ __('admin.width_in') }}</label>
                            <input type="number" step="0.0001" min="0" name="received_width" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ __('admin.height_in') }}</label>
                            <input type="number" step="0.0001" min="0" name="received_height" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label d-flex align-items-center gap-1">
                                {{ __('admin.special_handling') }}
                                <span class="text-primary wh-modal-tooltip" tabindex="0" data-wh-tooltip="1" data-bs-toggle="tooltip" data-bs-container="#warehouseReceiveModal" data-bs-placement="top" title="{{ __('admin.tooltip_special_handling') }}"><i class="ti tabler-help-circle"></i></span>
                            </label>
                            <input type="text" name="special_handling_type" class="form-control" maxlength="50">
                        </div>
                        <div class="col-12 d-none" id="wh-receive-existing-images-wrap">
                            <label class="form-label">{{ __('admin.existing_receipt_images') }}</label>
                            <p class="small text-muted mb-2">{{ __('admin.existing_receipt_images_help') }}</p>
                            <div id="wh-receive-existing-images" class="border rounded p-2 bg-body-secondary"></div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">{{ __('admin.receipt_images_upload') }}</label>
                            <input type="file" name="receipt_images[]" id="wh-receive-file-input" class="form-control" accept="image/*" multiple>
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
                <div class="modal-footer flex-shrink-0 border-top bg-body">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('admin.cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('admin.save') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
