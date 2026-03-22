@if($adminBrand['has_icon'])
<span class="app-brand-logo demo d-inline-flex align-items-center justify-content-center flex-shrink-0" style="width:32px;height:32px;">
    <img src="{{ $adminBrand['icon_url'] }}" alt="" class="rounded" style="max-width:32px;max-height:32px;object-fit:contain;">
</span>
@else
<span class="app-brand-logo demo d-inline-flex align-items-center justify-content-center flex-shrink-0 rounded-2 bg-primary text-white fw-semibold user-select-none" style="width:32px;height:32px;font-size:0.65rem;letter-spacing:0.03em;line-height:1;">
    {{ strtoupper($adminBrand['fallback_initials']) }}
</span>
@endif
