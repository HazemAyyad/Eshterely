@extends('layouts.admin')

@section('title', __('admin.send_notification'))

@section('content')
@if (session('success'))
    <div class="alert alert-success alert-dismissible">{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="row">
    <div class="col-lg-8">
        <form method="POST" action="{{ route('admin.notifications.send.submit') }}" class="ajax-submit-form" enctype="multipart/form-data" id="notificationForm">
            @csrf

            {{-- 1. Recipient --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="icon-base ti tabler-users me-2"></i>{{ __('admin.notification_recipient') }}</h5>
                </div>
                <div class="card-body">
                    <div class="form-check form-switch mb-3">
                        <input type="checkbox" name="send_to_all" value="1" class="form-check-input" id="sendAll" {{ old('send_to_all') ? 'checked' : '' }}>
                        <label class="form-check-label" for="sendAll">{{ __('admin.notification_send_to_all') }}</label>
                    </div>
                    <div id="userSelectWrap">
                        <label class="form-label">{{ __('admin.notification_select_user') }}</label>
                        <select name="user_id" class="form-select">
                            <option value="">-- {{ __('admin.notification_select_user') }} --</option>
                            @foreach(\App\Models\User::orderBy('id')->limit(200)->get() as $u)
                            <option value="{{ $u->id }}" {{ old('user_id') == $u->id ? 'selected' : '' }}>
                                {{ $u->id }} - {{ $u->phone ?? $u->email ?? $u->name ?? '-' }}
                            </option>
                            @endforeach
                        </select>
                        @error('user_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>

            {{-- 2. Notification Content --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="icon-base ti tabler-message me-2"></i>{{ __('admin.notification_content') }}</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">{{ __('admin.notification_title') }} *</label>
                        <input type="text" name="title" class="form-control" id="previewTitle" value="{{ old('title') }}" required maxlength="200" placeholder="{{ __('admin.notification_title') }}">
                        @error('title')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('admin.notification_subtitle') }}</label>
                        <input type="text" name="subtitle" class="form-control" id="previewSubtitle" value="{{ old('subtitle') }}" maxlength="500" placeholder="{{ __('admin.notification_subtitle') }}">
                        @error('subtitle')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">{{ __('admin.notification_type') }}</label>
                            <select name="type" class="form-select">
                                <option value="all" {{ old('type', 'all') === 'all' ? 'selected' : '' }}>all</option>
                                <option value="orders" {{ old('type') === 'orders' ? 'selected' : '' }}>orders</option>
                                <option value="shipments" {{ old('type') === 'shipments' ? 'selected' : '' }}>shipments</option>
                                <option value="promo" {{ old('type') === 'promo' ? 'selected' : '' }}>promo</option>
                            </select>
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <div class="form-check form-switch mb-2">
                                <input type="checkbox" name="important" value="1" class="form-check-input" {{ old('important') ? 'checked' : '' }}>
                                <label class="form-check-label">{{ __('admin.notification_important') }}</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 3. Mobile Navigation / Action --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="icon-base ti tabler-route me-2"></i>{{ __('admin.notification_mobile_action') }}</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">{{ __('admin.notification_route_key') }}</label>
                        <select name="route_key" class="form-select" id="routeKey">
                            <option value="">-- {{ __('admin.notification_route_key') }} --</option>
                            @foreach($routeKeys as $key => $label)
                            <option value="{{ $key }}" {{ old('route_key') === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        <small class="text-body-secondary d-block mt-1">{{ __('admin.notification_route_key_help') }}</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('admin.notification_target_type') }}</label>
                        <select name="target_type" class="form-select" id="targetType">
                            @foreach($targetTypes as $key => $label)
                            <option value="{{ $key }}" {{ old('target_type', 'none') === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        <small class="text-body-secondary d-block mt-1">{{ __('admin.notification_target_type_help') }}</small>
                    </div>
                    <div class="mb-3" id="targetIdWrap">
                        <label class="form-label" id="targetIdLabel">{{ __('admin.notification_target_id') }}</label>
                        <input type="text" name="target_id" class="form-control" value="{{ old('target_id') }}" maxlength="100" placeholder="مثال: 123">
                        <small class="text-body-secondary d-block mt-1">{{ __('admin.notification_target_id_help') }}</small>
                        @error('target_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('admin.notification_action_label') }}</label>
                        <select name="action_label" class="form-select" id="actionLabelSelect">
                            <option value="">-- {{ __('admin.notification_action_label') }} --</option>
                            @foreach($actionLabelPresets as $key => $label)
                            <option value="{{ $key }}" {{ old('action_label') === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                            <option value="custom" {{ old('action_label') === 'custom' ? 'selected' : '' }}>{{ __('admin.notification_action_label_custom') }}</option>
                        </select>
                        <small class="text-body-secondary d-block mt-1">{{ __('admin.notification_action_label_help') }}</small>
                        <input type="text" name="action_label_custom" class="form-control mt-2" id="actionLabelCustom" value="{{ old('action_label_custom') }}" maxlength="100" placeholder="{{ __('admin.notification_action_label_custom') }}" style="display: none;">
                    </div>
                    <div class="mb-0">
                        <label class="form-label">{{ __('admin.notification_action_route_override') }}</label>
                        <input type="text" name="action_route_override" class="form-control" value="{{ old('action_route_override') }}" maxlength="200" placeholder="/orders/123">
                        <small class="text-body-secondary d-block mt-1">{{ __('admin.notification_action_route_override_help') }}</small>
                    </div>
                </div>
            </div>

            {{-- 4. Push Options / Image --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="icon-base ti tabler-device-mobile me-2"></i>{{ __('admin.notification_push_options') }}</h5>
                </div>
                <div class="card-body">
                    <div class="form-check form-switch mb-4">
                        <input type="checkbox" name="send_fcm" value="1" class="form-check-input" id="sendFcm" {{ old('send_fcm') ? 'checked' : '' }}>
                        <label class="form-check-label" for="sendFcm">{{ __('admin.notification_send_fcm') }}</label>
                    </div>
                    <div id="fcmFields">
                        <div class="mb-3">
                            <label class="form-label">{{ __('admin.notification_image') }}</label>
                            <input type="file" name="image" class="form-control" id="imageUpload" accept="image/jpeg,image/png,image/webp,image/gif">
                            <small class="text-body-secondary d-block mt-1">{{ __('admin.notification_image_help') }}</small>
                            @error('image')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('admin.notification_image_url') }}</label>
                            <input type="url" name="image_url" class="form-control" id="imageUrlInput" placeholder="https://..." value="{{ old('image_url') }}" maxlength="500">
                        </div>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">{{ __('admin.send') }}</button>
        </form>
    </div>

    {{-- Live Preview --}}
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm sticky-top" style="top: 1rem;">
            <div class="card-header">
                <h5 class="mb-0"><i class="icon-base ti tabler-eye me-2"></i>{{ __('admin.notification_preview') }}</h5>
            </div>
            <div class="card-body">
                <div class="border rounded-3 overflow-hidden bg-light">
                    <div id="previewImageWrap" class="bg-dark position-relative" style="aspect-ratio: 2; min-height: 100px;">
                        <img id="previewImage" src="" alt="" class="w-100 h-100" style="object-fit: cover; object-position: center top; display: none;">
                        <div id="previewImagePlaceholder" class="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center text-body-secondary small">
                            {{ __('admin.notification_no_image') }}
                        </div>
                    </div>
                    <div class="p-3">
                        <div class="fw-semibold mb-1" id="previewTitleText">—</div>
                        <div class="small text-body-secondary mb-2" id="previewSubtitleText">—</div>
                        <div id="previewActionWrap" style="display: none;">
                            <span class="badge bg-primary" id="previewActionText">—</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    var sendAll = document.getElementById('sendAll');
    var userSelectWrap = document.getElementById('userSelectWrap');
    var sendFcm = document.getElementById('sendFcm');
    var fcmFields = document.getElementById('fcmFields');
    var targetType = document.getElementById('targetType');
    var targetIdWrap = document.getElementById('targetIdWrap');
    var targetIdLabel = document.getElementById('targetIdLabel');
    var actionLabelSelect = document.getElementById('actionLabelSelect');
    var actionLabelCustom = document.getElementById('actionLabelCustom');
    var imageUpload = document.getElementById('imageUpload');
    var imageUrlInput = document.getElementById('imageUrlInput');
    var previewTitle = document.getElementById('previewTitle');
    var previewSubtitle = document.getElementById('previewSubtitle');
    var previewTitleText = document.getElementById('previewTitleText');
    var previewSubtitleText = document.getElementById('previewSubtitleText');
    var previewActionWrap = document.getElementById('previewActionWrap');
    var previewActionText = document.getElementById('previewActionText');
    var previewImage = document.getElementById('previewImage');
    var previewImagePlaceholder = document.getElementById('previewImagePlaceholder');

    function toggleUserSelect() {
        userSelectWrap.style.display = sendAll && sendAll.checked ? 'none' : 'block';
    }
    function toggleFcm() {
        fcmFields.style.display = sendFcm && sendFcm.checked ? 'block' : 'none';
    }
    function toggleTargetId() {
        var isNone = targetType && targetType.value === 'none';
        targetIdWrap.style.display = isNone ? 'none' : 'block';
    }
    function toggleActionLabelCustom() {
        var show = actionLabelSelect && actionLabelSelect.value === 'custom';
        actionLabelCustom.style.display = show ? 'block' : 'none';
    }
    function updatePreview() {
        previewTitleText.textContent = previewTitle && previewTitle.value.trim() ? previewTitle.value.trim() : '—';
        previewSubtitleText.textContent = previewSubtitle && previewSubtitle.value.trim() ? previewSubtitle.value.trim() : '—';
        var label = actionLabelSelect && actionLabelSelect.value === 'custom' ? (actionLabelCustom && actionLabelCustom.value.trim()) : (actionLabelSelect && actionLabelSelect.selectedOptions[0] && actionLabelSelect.value ? actionLabelSelect.selectedOptions[0].text : '');
        if (label) {
            previewActionText.textContent = label;
            previewActionWrap.style.display = 'block';
        } else {
            previewActionWrap.style.display = 'none';
        }
    }
    function updateImagePreview(src) {
        if (src && src.length > 0) {
            previewImage.src = src;
            previewImage.style.display = 'block';
            previewImagePlaceholder.style.display = 'none';
        } else {
            previewImage.src = '';
            previewImage.style.display = 'none';
            previewImagePlaceholder.style.display = 'flex';
        }
    }

    if (sendAll) sendAll.addEventListener('change', toggleUserSelect);
    if (sendFcm) sendFcm.addEventListener('change', toggleFcm);
    if (targetType) targetType.addEventListener('change', toggleTargetId);
    if (actionLabelSelect) actionLabelSelect.addEventListener('change', function() { toggleActionLabelCustom(); updatePreview(); });
    if (actionLabelCustom) actionLabelCustom.addEventListener('input', updatePreview);
    if (previewTitle) previewTitle.addEventListener('input', updatePreview);
    if (previewSubtitle) previewSubtitle.addEventListener('input', updatePreview);

    toggleUserSelect();
    toggleFcm();
    toggleTargetId();
    toggleActionLabelCustom();
    updatePreview();

    if (imageUpload) {
        imageUpload.addEventListener('change', function() {
            var f = this.files && this.files[0];
            if (f) {
                var r = new FileReader();
                r.onload = function() { updateImagePreview(r.result); };
                r.readAsDataURL(f);
            } else {
                updateImagePreview(imageUrlInput ? imageUrlInput.value : '');
            }
        });
    }
    if (imageUrlInput) {
        imageUrlInput.addEventListener('input', function() {
            if (!imageUpload || !imageUpload.files.length) updateImagePreview(this.value);
        });
    }
    var initialImageUrl = @json(old('image_url'));
    if (initialImageUrl) updateImagePreview(initialImageUrl);
});
</script>
@endpush

@include('admin.partials.ajax-form-script', ['redirect' => route('admin.notifications.send')])