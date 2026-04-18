@if($order->user)
    @php
        $u = $order->user;
    @endphp
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-3 d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div class="flex-grow-1" style="min-width: 200px;">
                <div class="text-uppercase small text-muted mb-1">{{ __('admin.customer') }}</div>
                <div class="fs-5 fw-semibold mb-1">
                    <a href="{{ route('admin.users.show', $u) }}" class="text-body text-decoration-none">{{ $customerDisplayName }}</a>
                </div>
                @if($u->email)
                    <div class="small mb-1"><a href="mailto:{{ $u->email }}">{{ $u->email }}</a></div>
                @endif
                @if($u->customer_code)
                    <div class="small text-muted mb-1">Customer code: <span class="font-monospace">{{ $u->customer_code }}</span></div>
                @endif
                @if($u->phone)
                    <div class="small text-muted">{{ __('admin.phone') }}: {{ $u->phone }}</div>
                @endif
            </div>
            <div>
                <a href="{{ route('admin.users.show', $u) }}" class="btn btn-sm btn-primary">{{ __('admin.view_profile') }}</a>
            </div>
        </div>
    </div>
@endif
