@extends('layouts.admin')

@section('title', 'إرسال إشعار')

@section('content')
@if (session('success'))
    <div class="alert alert-success alert-dismissible">{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="card border-0 shadow-sm">
    <div class="card-header">
        <h5 class="mb-0">{{ __('admin.send_notification') }}</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.notifications.send.submit') }}" class="ajax-submit-form">
            @csrf
            <div class="mb-4">
                <div class="form-check form-switch">
                    <input type="checkbox" name="send_to_all" value="1" class="form-check-input" id="sendAll" {{ old('send_to_all') ? 'checked' : '' }}>
                    <label class="form-check-label" for="sendAll">إرسال للجميع</label>
                </div>
            </div>
            <div class="mb-4" id="userSelectWrap">
                <label class="form-label">المستخدم (إن لم يكن للجميع)</label>
                <select name="user_id" class="form-select">
                    <option value="">-- اختر مستخدماً --</option>
                    @foreach(\App\Models\User::orderBy('id')->limit(100)->get() as $u)
                    <option value="{{ $u->id }}" {{ old('user_id') == $u->id ? 'selected' : '' }}>
                        {{ $u->id }} - {{ $u->phone ?? $u->email ?? $u->name ?? '-' }}
                    </option>
                    @endforeach
                </select>
                @error('user_id')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
            <div class="mb-4">
                <label class="form-label">العنوان *</label>
                <input type="text" name="title" class="form-control" value="{{ old('title') }}" required>
                @error('title')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
            <div class="mb-4">
                <label class="form-label">النص الفرعي</label>
                <input type="text" name="subtitle" class="form-control" value="{{ old('subtitle') }}">
                @error('subtitle')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <label class="form-label">النوع</label>
                    <select name="type" class="form-select">
                        <option value="all" {{ old('type', 'all') === 'all' ? 'selected' : '' }}>all</option>
                        <option value="orders" {{ old('type') === 'orders' ? 'selected' : '' }}>orders</option>
                        <option value="shipments" {{ old('type') === 'shipments' ? 'selected' : '' }}>shipments</option>
                        <option value="promo" {{ old('type') === 'promo' ? 'selected' : '' }}>promo</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">مهم</label>
                    <div class="form-check form-switch mt-2">
                        <input type="checkbox" name="important" value="1" class="form-check-input" {{ old('important') ? 'checked' : '' }}>
                    </div>
                </div>
            </div>
            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <label class="form-label">action_label</label>
                    <input type="text" name="action_label" class="form-control" value="{{ old('action_label') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">action_route</label>
                    <input type="text" name="action_route" class="form-control" value="{{ old('action_route') }}">
                </div>
            </div>
            <hr class="my-4">
            <h6 class="mb-2">Push (FCM)</h6>
            <div class="form-check form-switch mb-3">
                <input type="checkbox" name="send_fcm" value="1" class="form-check-input" id="sendFcm" {{ old('send_fcm') ? 'checked' : '' }}>
                <label class="form-check-label" for="sendFcm">إرسال إشعار push (FCM) أيضاً</label>
            </div>
            <div class="row g-4 mb-4" id="fcmFields">
                <div class="col-12">
                    <label class="form-label">رابط الصورة (اختياري)</label>
                    <input type="url" name="image_url" class="form-control" placeholder="https://..." value="{{ old('image_url') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">target_type</label>
                    <input type="text" name="target_type" class="form-control" placeholder="order" value="{{ old('target_type') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">target_id</label>
                    <input type="text" name="target_id" class="form-control" value="{{ old('target_id') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">route_key</label>
                    <input type="text" name="route_key" class="form-control" placeholder="order_detail" value="{{ old('route_key') }}">
                </div>
            </div>
            <button type="submit" class="btn btn-primary">{{ __('admin.send') }}</button>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('sendAll')?.addEventListener('change', function() {
        document.getElementById('userSelectWrap').style.display = this.checked ? 'none' : 'block';
    });
    if (document.getElementById('sendAll')?.checked) {
        document.getElementById('userSelectWrap').style.display = 'none';
    }
    document.getElementById('sendFcm')?.addEventListener('change', function() {
        document.getElementById('fcmFields').style.display = this.checked ? 'block' : 'none';
    });
    document.getElementById('fcmFields').style.display = document.getElementById('sendFcm')?.checked ? 'block' : 'none';
});
</script>
@endpush

@include('admin.partials.ajax-form-script', ['redirect' => route('admin.notifications.send')])
