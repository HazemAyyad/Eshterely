@extends('layouts.admin')

@section('title', __('admin.user_details'))

@push('styles')
<link rel="stylesheet" href="{{ asset('vuexy/assets/vendor/css/pages/page-user-view.css') }}" />
@endpush

@section('content')
<div class="row">
    <!-- User Sidebar -->
    <div class="col-xl-4 col-lg-5 order-1 order-md-0">
        <!-- User Card -->
        <div class="card mb-4 border-0 shadow-sm">
            <div class="card-body pt-4">
                <div class="user-avatar-section">
                    <div class="d-flex align-items-center flex-column">
                        @if($user->avatar_url ?? null)
                            <img class="img-fluid rounded mb-3" src="{{ $user->avatar_url }}" height="120" width="120" alt="User avatar" />
                        @else
                            <div class="avatar avatar-xl mb-3">
                                <span class="avatar-initial rounded-circle bg-label-primary">
                                    {{ strtoupper(mb_substr($user->display_name ?? $user->full_name ?? $user->name ?? 'U', 1)) }}
                                </span>
                            </div>
                        @endif
                        <div class="user-info text-center">
                            <h5 class="mb-1">{{ $user->display_name ?? $user->full_name ?? $user->name ?? '-' }}</h5>
                            <span class="badge {{ $user->verified ? 'bg-label-success' : 'bg-label-secondary' }}">
                                {{ $user->verified ? __('admin.verified') : __('admin.unverified') }}
                            </span>
                        </div>
                    </div>
                </div>
                <h5 class="pb-3 border-bottom mb-3 mt-4">{{ __('admin.details') }}</h5>
                <div class="info-container">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2">
                            <span class="h6">{{ __('admin.full_legal_name') }}:</span>
                            <span>{{ $user->full_name ?? '-' }}</span>
                        </li>
                        <li class="mb-2">
                            <span class="h6">{{ __('admin.phone') }}:</span>
                            <span>{{ $user->phone ?? '-' }}</span>
                        </li>
                        <li class="mb-2">
                            <span class="h6">{{ __('admin.email') }}:</span>
                            <span>{{ $user->email ?? '-' }}</span>
                        </li>
                        <li class="mb-2">
                            <span class="h6">{{ __('admin.date_of_birth') }}:</span>
                            <span>{{ $user->date_of_birth?->format('Y-m-d') ?? '-' }}</span>
                        </li>
                        <li class="mb-2">
                            <span class="h6">{{ __('admin.verified') }}:</span>
                            <span>{{ $user->verified ? __('admin.yes') : __('admin.no') }}</span>
                        </li>
                        <li class="mb-2">
                            <span class="h6">{{ __('admin.last_verified_at') }}:</span>
                            <span>{{ $user->last_verified_at?->format('Y-m-d') ?? '-' }}</span>
                        </li>
                        <li class="mb-2">
                            <span class="h6">{{ __('admin.registered_at') }}:</span>
                            <span>{{ $user->created_at?->format('Y-m-d H:i') }}</span>
                        </li>
                        <li class="mb-2">
                            <span class="h6">{{ __('admin.primary_address_country') }}:</span>
                            <span>{{ $primaryAddressCountry ?? '-' }}</span>
                        </li>
                        <li class="mb-2">
                            <span class="h6">{{ __('admin.address_locked') }}:</span>
                            <span>{{ ($primaryAddressIsLocked ?? false) ? __('admin.yes') : __('admin.no') }}</span>
                        </li>
                    </ul>
                    <div class="mt-3">
                        <div class="text-body-secondary small mb-1">{{ __('admin.primary_address') }}</div>
                        @if(!empty($primaryAddressText))
                            <div class="text-body" style="white-space: pre-line;">{{ $primaryAddressText }}</div>
                        @else
                            <div class="text-body-secondary">—</div>
                        @endif
                    </div>
                </div>
                <div class="d-grid gap-2 mt-4">
                    <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">
                        <i class="icon-base ti tabler-arrow-left me-2"></i>{{ __('admin.back_to_list') }}
                    </a>
                </div>
                <hr class="my-4">
                <h6 class="mb-2">Send push (FCM)</h6>
                <form method="POST" action="{{ route('admin.notifications.send-to-user', $user) }}" class="ajax-submit-form">
                    @csrf
                    <input type="text" name="title" class="form-control form-control-sm mb-2" placeholder="Title" required maxlength="200">
                    <textarea name="body" class="form-control form-control-sm mb-2" rows="2" placeholder="Body" maxlength="1000"></textarea>
                    <input type="url" name="image_url" class="form-control form-control-sm mb-2" placeholder="Image URL (optional)">
                    <input type="hidden" name="target_type" value="user">
                    <input type="hidden" name="target_id" value="{{ $user->id }}">
                    <button type="submit" class="btn btn-sm btn-primary">Send</button>
                </form>
            </div>
        </div>
        <!-- /User Card -->

        @if($settings ?? null)
        <!-- Settings Card -->
        <div class="card mb-4 border-0 shadow-sm">
            <h5 class="card-header">{{ __('admin.settings') }}</h5>
            <div class="card-body">
                <ul class="list-unstyled mb-0">
                    <li class="mb-2">
                        <span class="h6">{{ __('admin.language') }}:</span>
                        <span>{{ $settings->language_code ?? '-' }}</span>
                    </li>
                    <li class="mb-2">
                        <span class="h6">{{ __('admin.currency') }}:</span>
                        <span>{{ $settings->currency_code ?? '-' }}</span>
                    </li>
                    <li class="mb-2">
                        <span class="h6">{{ __('admin.default_warehouse') }}:</span>
                        <span>{{ $settings->default_warehouse_label ?? '-' }}</span>
                    </li>
                    <li class="mb-2">
                        <span class="h6">{{ __('admin.smart_consolidation') }}:</span>
                        <span>{{ ($settings->smart_consolidation_enabled ?? false) ? __('admin.yes') : __('admin.no') }}</span>
                    </li>
                    <li class="mb-2">
                        <span class="h6">{{ __('admin.auto_insurance') }}:</span>
                        <span>{{ ($settings->auto_insurance_enabled ?? false) ? __('admin.yes') : __('admin.no') }}</span>
                    </li>
                </ul>
            </div>
        </div>
        <!-- /Settings Card -->
        @endif
    </div>
    <!--/ User Sidebar -->

    <!-- User Content -->
    <div class="col-xl-8 col-lg-7 order-0 order-md-1">
        <!-- User Pills -->
        <div class="nav-align-top mb-4">
            <ul class="nav nav-pills flex-column flex-md-row flex-wrap mb-4 row-gap-2" role="tablist">
                <li class="nav-item">
                    <button type="button" class="nav-link active" role="tab" data-bs-toggle="tab" data-bs-target="#tab-addresses" aria-controls="tab-addresses" aria-selected="true">
                        <i class="icon-base ti tabler-map-pin icon-sm me-1_5"></i>{{ __('admin.addresses') }}
                    </button>
                </li>
                <li class="nav-item">
                    <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#tab-sessions" aria-controls="tab-sessions" aria-selected="false">
                        <i class="icon-base ti tabler-device-desktop icon-sm me-1_5"></i>{{ __('admin.sessions') }}
                    </button>
                </li>
                    <li class="nav-item">
                        <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#tab-settings" aria-controls="tab-settings" aria-selected="false">
                            <i class="icon-base ti tabler-settings icon-sm me-1_5"></i>{{ __('admin.settings') }}
                        </button>
                    </li>
                    <li class="nav-item">
                        <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#tab-wallet" aria-controls="tab-wallet" aria-selected="false">
                            <i class="icon-base ti tabler-wallet icon-sm me-1_5"></i>{{ __('admin.wallet') }}
                        </button>
                    </li>
                    <li class="nav-item">
                        <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#tab-orders" aria-controls="tab-orders" aria-selected="false">
                            <i class="icon-base ti tabler-shopping-cart icon-sm me-1_5"></i>{{ __('admin.orders') }}
                        </button>
                    </li>
                    <li class="nav-item">
                        <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#tab-support" aria-controls="tab-support" aria-selected="false">
                            <i class="icon-base ti tabler-headphones icon-sm me-1_5"></i>{{ __('admin.support') }}
                        </button>
                    </li>
                    <li class="nav-item">
                        <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#tab-favorites" aria-controls="tab-favorites" aria-selected="false">
                            <i class="icon-base ti tabler-heart icon-sm me-1_5"></i>{{ __('admin.favorites') }}
                        </button>
                    </li>
            </ul>
        </div>
        <!--/ User Pills -->

        <div class="tab-content">
            <!-- Addresses -->
            <div class="tab-pane fade show active" id="tab-addresses" role="tabpanel">
                <div class="card border-0 shadow-sm">
                    <h5 class="card-header">{{ __('admin.addresses') }}</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="border-top">
                                <tr>
                                    <th>#</th>
                                    <th>{{ __('admin.nickname') }}</th>
                                    <th>{{ __('admin.type') }}</th>
                                    <th>{{ __('admin.address') }}</th>
                                    <th>{{ __('admin.default') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($user->addresses as $a)
                                <tr>
                                    <td>{{ $a->id }}</td>
                                    <td>{{ $a->nickname ?? '-' }}</td>
                                    <td><span class="badge bg-label-secondary">{{ $a->address_type ?? '-' }}</span></td>
                                    <td>{{ Str::limit($a->street_address ?? $a->address_line ?? '-', 50) }}</td>
                                    <td>
                                        @if($a->is_default)
                                            <span class="badge bg-label-success">{{ __('admin.yes') }}</span>
                                        @else
                                            <span class="text-body-secondary">{{ __('admin.no') }}</span>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-body-secondary">{{ __('admin.no_addresses') }}</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Sessions -->
            <div class="tab-pane fade" id="tab-sessions" role="tabpanel">
                <div class="card border-0 shadow-sm">
                    <h5 class="card-header">{{ __('admin.active_sessions') }}</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="border-top">
                                <tr>
                                    <th>{{ __('admin.device') }}</th>
                                    <th>{{ __('admin.location') }}</th>
                                    <th>{{ __('admin.last_active') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @if($sessions && $sessions->isNotEmpty())
                                    @foreach($sessions as $s)
                                    <tr>
                                        <td>{{ $s->device_name ?? '-' }}</td>
                                        <td>{{ $s->location ?? '-' }}</td>
                                        <td>{{ $s->last_active_at ? \Carbon\Carbon::parse($s->last_active_at)->diffForHumans() : '-' }}</td>
                                    </tr>
                                    @endforeach
                                @else
                                <tr>
                                    <td colspan="3" class="text-center py-4 text-body-secondary">{{ __('admin.no_sessions') }}</td>
                                </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Settings -->
            <div class="tab-pane fade" id="tab-settings" role="tabpanel">
                <div class="card border-0 shadow-sm">
                    <h5 class="card-header">{{ __('admin.settings') }}</h5>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="text-body-secondary small mb-1">{{ __('admin.language') }}</div>
                                <div class="fw-medium">{{ $settings?->language_code ?? '-' }}{{ $languageLabel ? " ({$languageLabel})" : '' }}</div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-body-secondary small mb-1">{{ __('admin.currency') }}</div>
                                <div class="fw-medium">{{ $settings?->currency_code ?? '-' }}{{ $currencySymbol ? " ({$currencySymbol})" : '' }}</div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-body-secondary small mb-1">{{ __('admin.default_warehouse') }}</div>
                                <div class="fw-medium">{{ $settings?->default_warehouse_label ?? '-' }}</div>
                                <div class="text-body-secondary small">{{ $settings?->default_warehouse_id ?? '' }}</div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-body-secondary small mb-1">{{ __('admin.server_region') }}</div>
                                <div class="fw-medium">{{ $settings?->server_region ?? '-' }}</div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-body-secondary small mb-1">{{ __('admin.smart_consolidation') }}</div>
                                <span class="badge {{ ($settings?->smart_consolidation_enabled ?? false) ? 'bg-label-success' : 'bg-label-secondary' }}">
                                    {{ ($settings?->smart_consolidation_enabled ?? false) ? __('admin.yes') : __('admin.no') }}
                                </span>
                            </div>
                            <div class="col-md-6">
                                <div class="text-body-secondary small mb-1">{{ __('admin.auto_insurance') }}</div>
                                <span class="badge {{ ($settings?->auto_insurance_enabled ?? false) ? 'bg-label-success' : 'bg-label-secondary' }}">
                                    {{ ($settings?->auto_insurance_enabled ?? false) ? __('admin.yes') : __('admin.no') }}
                                </span>
                            </div>
                            <div class="col-12">
                                <div class="text-body-secondary small mb-1">{{ __('admin.notification_center_summary') }}</div>
                                <div class="fw-medium">{{ $notificationCenterSummary ?? '—' }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Wallet -->
            <div class="tab-pane fade" id="tab-wallet" role="tabpanel">
                <div class="card border-0 shadow-sm mb-4">
                    <h5 class="card-header">{{ __('admin.wallet_summary') }}</h5>
                    <div class="card-body">
                        @if($wallet)
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="text-body-secondary small mb-1">{{ __('admin.available') }}</div>
                                    <div class="h5 mb-0">{{ number_format((float) $wallet->available_balance, 2) }}</div>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-body-secondary small mb-1">{{ __('admin.pending') }}</div>
                                    <div class="h5 mb-0">{{ number_format((float) $wallet->pending_balance, 2) }}</div>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-body-secondary small mb-1">{{ __('admin.promo') }}</div>
                                    <div class="h5 mb-0">{{ number_format((float) $wallet->promo_balance, 2) }}</div>
                                </div>
                            </div>
                        @else
                            <div class="text-body-secondary">—</div>
                        @endif
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <h5 class="card-header">{{ __('admin.wallet_transactions') }}</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="border-top">
                                <tr>
                                    <th>{{ __('admin.type') }}</th>
                                    <th>{{ __('admin.title') }}</th>
                                    <th>{{ __('admin.amount') }}</th>
                                    <th>{{ __('admin.date') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($walletTransactions as $tx)
                                <tr>
                                    <td><span class="badge bg-label-secondary">{{ $tx->type }}</span></td>
                                    <td>
                                        <div class="fw-medium">{{ $tx->title }}</div>
                                        @if($tx->subtitle)<div class="text-body-secondary small">{{ $tx->subtitle }}</div>@endif
                                    </td>
                                    <td>{{ number_format((float) $tx->amount, 2) }}</td>
                                    <td>{{ $tx->created_at?->format('Y-m-d H:i') ?? '-' }}</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-body-secondary">{{ __('admin.no_data') }}</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Orders -->
            <div class="tab-pane fade" id="tab-orders" role="tabpanel">
                <div class="card border-0 shadow-sm">
                    <h5 class="card-header">{{ __('admin.latest_orders') }}</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="border-top">
                                <tr>
                                    <th>{{ __('admin.order_number') }}</th>
                                    <th>{{ __('admin.status') }}</th>
                                    <th>{{ __('admin.origin') }}</th>
                                    <th>{{ __('admin.total') }}</th>
                                    <th>{{ __('admin.date') }}</th>
                                    <th>{{ __('admin.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentOrders as $o)
                                <tr>
                                    <td>{{ $o->order_number }}</td>
                                    <td><span class="badge bg-label-secondary">{{ $o->status }}</span></td>
                                    <td>{{ $o->origin }}</td>
                                    <td>{{ number_format((float) $o->total_amount, 2) }} {{ $o->currency }}</td>
                                    <td>{{ $o->placed_at?->format('Y-m-d') ?? '-' }}</td>
                                    <td>
                                        <a href="{{ route('admin.orders.show', $o) }}" class="btn btn-text-secondary rounded-pill waves-effect btn-icon" title="{{ __('admin.show') }}">
                                            <i class="icon-base ti tabler-eye icon-22px"></i>
                                        </a>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-body-secondary">{{ __('admin.no_orders') }}</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Support -->
            <div class="tab-pane fade" id="tab-support" role="tabpanel">
                <div class="card border-0 shadow-sm">
                    <h5 class="card-header">{{ __('admin.latest_tickets') }}</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="border-top">
                                <tr>
                                    <th>#</th>
                                    <th>{{ __('admin.subject') }}</th>
                                    <th>{{ __('admin.status') }}</th>
                                    <th>{{ __('admin.date') }}</th>
                                    <th>{{ __('admin.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentTickets as $t)
                                <tr>
                                    <td>{{ $t->id }}</td>
                                    <td>{{ $t->subject ?? '-' }}</td>
                                    <td><span class="badge bg-label-secondary">{{ $t->status }}</span></td>
                                    <td>{{ $t->updated_at?->format('Y-m-d') ?? '-' }}</td>
                                    <td>
                                        <a href="{{ route('admin.support.show', $t) }}" class="btn btn-text-secondary rounded-pill waves-effect btn-icon" title="{{ __('admin.show') }}">
                                            <i class="icon-base ti tabler-eye icon-22px"></i>
                                        </a>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-body-secondary">{{ __('admin.no_tickets') }}</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Favorites -->
            <div class="tab-pane fade" id="tab-favorites" role="tabpanel">
                <div class="card border-0 shadow-sm mb-4">
                    <h5 class="card-header d-flex align-items-center justify-content-between">
                        <span>{{ __('admin.favorites') }}</span>
                        <span class="badge bg-label-primary">{{ $favoritesCount ?? 0 }}</span>
                    </h5>
                </div>
                <div class="card border-0 shadow-sm">
                    <h5 class="card-header">{{ __('admin.latest_favorites') }}</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="border-top">
                                <tr>
                                    <th>{{ __('admin.title') }}</th>
                                    <th>{{ __('admin.price') }}</th>
                                    <th>{{ __('admin.status') }}</th>
                                    <th>{{ __('admin.date') }}</th>
                                    <th>{{ __('admin.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentFavorites as $f)
                                <tr>
                                    <td>{{ $f->title }}</td>
                                    <td>{{ number_format((float) $f->price, 2) }} {{ $f->currency }}</td>
                                    <td><span class="badge bg-label-secondary">{{ $f->stock_status }}</span></td>
                                    <td>{{ $f->created_at?->format('Y-m-d') ?? '-' }}</td>
                                    <td>
                                        @if($f->product_url)
                                            <a href="{{ $f->product_url }}" target="_blank" class="btn btn-text-secondary rounded-pill waves-effect btn-icon" title="{{ __('admin.open') }}">
                                                <i class="icon-base ti tabler-external-link icon-22px"></i>
                                            </a>
                                        @else
                                            <span class="text-body-secondary">—</span>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-body-secondary">{{ __('admin.no_data') }}</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!--/ User Content -->
</div>
@endsection
