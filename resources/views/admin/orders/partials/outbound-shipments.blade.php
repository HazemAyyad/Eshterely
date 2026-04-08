@php
    use App\Support\AdminFulfillmentLabels;
@endphp

@if(isset($outboundShipments) && $outboundShipments->isNotEmpty())
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header py-3">
        <h5 class="mb-1">{{ __('admin.customer_outbound_shipments_title') }}</h5>
        <div class="small text-muted mb-0">{{ __('admin.customer_outbound_shipments_help') }}</div>
    </div>
    <div class="card-body py-3">
        <ul class="list-unstyled mb-0">
            @foreach($outboundShipments as $os)
                @php $op = AdminFulfillmentLabels::outboundShipment($os->status); @endphp
                <li class="d-flex flex-wrap justify-content-between align-items-center gap-2 py-2 @if(!$loop->last) border-bottom @endif">
                    <div>
                        <a href="{{ route('admin.shipments.show', $os) }}" class="fw-semibold">#{{ $os->id }}</a>
                        <span class="badge bg-{{ $op['badge'] }} ms-1">{{ $op['label'] }}</span>
                        @if($os->carrier || $os->tracking_number)
                            <div class="small text-muted mt-1">
                                {{ $os->carrier ?? '—' }}
                                @if($os->tracking_number)
                                    · {{ Str::limit($os->tracking_number, 40) }}
                                @endif
                            </div>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    </div>
</div>
@endif
