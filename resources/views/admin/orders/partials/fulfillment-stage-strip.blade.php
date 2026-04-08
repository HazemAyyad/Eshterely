<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3">
        <div class="small text-muted mb-2">{{ __('admin.fulfillment_stage_strip_title') }} <span class="text-muted">({{ __('admin.fulfillment_stage_strip_all_items') }})</span></div>
        <div class="d-flex flex-wrap align-items-center gap-1 gap-md-2">
            @foreach($fulfillmentStages as $idx => $stage)
                @php
                    $done = $stage['done'];
                    $badgeClass = $done ? 'success' : 'light text-muted border';
                @endphp
                <span class="badge rounded-pill bg-{{ $badgeClass }} px-3 py-2">
                    @if($done)✓ @endif
                    {{ __('admin.fulfillment_stage_'.$stage['id']) }}
                </span>
                @if(!$loop->last)
                    <span class="text-muted small d-none d-md-inline">→</span>
                @endif
            @endforeach
        </div>
        <p class="small text-muted mb-0 mt-2">{{ __('admin.fulfillment_stage_strip_help_v2') }}</p>
    </div>
</div>
