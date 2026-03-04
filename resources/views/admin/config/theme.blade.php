@extends('layouts.admin')

@section('title', 'تحرير الثيم')

@section('content')
@if (session('success'))
    <div class="alert alert-success alert-dismissible">{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="card border-0 shadow-sm">
    <div class="card-header">
        <h5 class="mb-0">{{ __('admin.theme') }}</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.config.theme') }}" class="ajax-submit-form">
            @method('PATCH')
            @csrf
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">primary_color</label>
                    <div class="d-flex align-items-center gap-2">
                        <div id="pickr-primary" class="theme-color-picker"></div>
                        <input type="hidden" name="primary_color" id="input-primary" value="{{ old('primary_color', $theme->primary_color ?? '1E66F5') }}">
                        <input type="text" class="form-control form-control-sm" id="text-primary" value="{{ old('primary_color', $theme->primary_color ?? '1E66F5') }}" placeholder="1E66F5" style="max-width: 100px;">
                    </div>
                    @error('primary_color')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">background_color</label>
                    <div class="d-flex align-items-center gap-2">
                        <div id="pickr-background" class="theme-color-picker"></div>
                        <input type="hidden" name="background_color" id="input-background" value="{{ old('background_color', $theme->background_color ?? 'FFFFFF') }}">
                        <input type="text" class="form-control form-control-sm" id="text-background" value="{{ old('background_color', $theme->background_color ?? 'FFFFFF') }}" placeholder="FFFFFF" style="max-width: 100px;">
                    </div>
                    @error('background_color')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">text_color</label>
                    <div class="d-flex align-items-center gap-2">
                        <div id="pickr-text" class="theme-color-picker"></div>
                        <input type="hidden" name="text_color" id="input-text" value="{{ old('text_color', $theme->text_color ?? '0B1220') }}">
                        <input type="text" class="form-control form-control-sm" id="text-text" value="{{ old('text_color', $theme->text_color ?? '0B1220') }}" placeholder="0B1220" style="max-width: 100px;">
                    </div>
                    @error('text_color')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">muted_text_color</label>
                    <div class="d-flex align-items-center gap-2">
                        <div id="pickr-muted" class="theme-color-picker"></div>
                        <input type="hidden" name="muted_text_color" id="input-muted" value="{{ old('muted_text_color', $theme->muted_text_color ?? '6B7280') }}">
                        <input type="text" class="form-control form-control-sm" id="text-muted" value="{{ old('muted_text_color', $theme->muted_text_color ?? '6B7280') }}" placeholder="6B7280" style="max-width: 100px;">
                    </div>
                    @error('muted_text_color')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="mt-4">
                <button type="submit" class="btn btn-primary">{{ __('admin.save') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('styles')
<style>
.theme-color-picker .pcr-button { width: 40px; height: 40px; border-radius: 6px; border: 1px solid #d9dee3; }
</style>
@endpush
@push('scripts')
<script src="{{ asset('vuexy/assets/vendor/libs/pickr/pickr.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    function toHex(val) {
        if (!val || typeof val !== 'string') return '000000';
        val = val.replace(/^#/, '');
        if (val.length === 6) return val.toUpperCase();
        if (val.length === 3) return val.split('').map(c => c + c).join('').toUpperCase();
        return val.slice(0, 6).toUpperCase();
    }
    function fromHex(val) { return '#' + toHex(val); }

    function initPickr(id, defaultVal) {
        const el = document.getElementById('pickr-' + id);
        const input = document.getElementById('input-' + id);
        const textEl = document.getElementById('text-' + id);
        if (!el || !input) return;
        const hex = fromHex(input.value || defaultVal);
        const pickr = new Pickr({
            el: el,
            theme: 'nano',
            default: hex,
            components: { preview: true, hue: true, interaction: { hex: true, input: true, save: true } }
        });
        pickr.on('save', (color) => {
            if (color) {
                const h = color.toHEX().replace(/^#/, '').toUpperCase();
                input.value = h;
                if (textEl) textEl.value = h;
            }
        });
        pickr.on('change', (color) => {
            if (color) {
                const h = color.toHEX().replace(/^#/, '').toUpperCase();
                input.value = h;
                if (textEl) textEl.value = h;
            }
        });
        if (textEl) {
            textEl.addEventListener('input', function() {
                const v = toHex(this.value);
                input.value = v;
                if (v.length >= 6) pickr.setColor(fromHex(v));
            });
        }
    }
    initPickr('primary', '1E66F5');
    initPickr('background', 'FFFFFF');
    initPickr('text', '0B1220');
    initPickr('muted', '6B7280');
});
</script>
@endpush
@include('admin.partials.ajax-form-script', ['redirect' => route('admin.config.theme')])
