<aside id="layout-menu" class="layout-menu menu-vertical menu">
    <div class="app-brand demo">
        <a href="{{ route('admin.dashboard') }}" class="app-brand-link">
            @include('layouts.admin.partials.brand-mark')
            <span class="app-brand-text demo menu-text fw-bold ms-3">{{ $adminBrand['name'] }}</span>
        </a>
        <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto">
            <i class="icon-base ti menu-toggle-icon d-none d-xl-block"></i>
            <i class="icon-base ti tabler-x d-block d-xl-none"></i>
        </a>
    </div>
    <div class="menu-inner-shadow"></div>
    <ul class="menu-inner py-1">
        @php
            $adminMenuCounts = $adminMenuCounts ?? ['orders_procurement' => 0, 'warehouse_queue' => 0, 'shipments_ops' => 0];
        @endphp
        <li class="menu-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
            <a href="{{ route('admin.dashboard') }}" class="menu-link">
                <i class="menu-icon icon-base ti tabler-smart-home"></i>
                <div>{{ __('admin.dashboard') }}</div>
            </a>
        </li>
        <li class="menu-item {{ request()->routeIs('admin.orders*') ? 'active' : '' }}">
            <a href="{{ route('admin.orders.index') }}" class="menu-link">
                <i class="menu-icon icon-base ti tabler-shopping-cart"></i>
                <div>{{ __('admin.orders') }}</div>
                @if(($adminMenuCounts['orders_procurement'] ?? 0) > 0)
                    <span class="badge rounded-pill bg-warning ms-auto">{{ $adminMenuCounts['orders_procurement'] }}</span>
                @endif
            </a>
        </li>
        <li class="menu-item {{ request()->routeIs('admin.warehouse*') ? 'active' : '' }}">
            <a href="{{ route('admin.warehouse.index') }}" class="menu-link">
                <i class="menu-icon icon-base ti tabler-building-warehouse"></i>
                <div>{{ __('admin.menu_warehouse_ops') }}</div>
                @if(($adminMenuCounts['warehouse_queue'] ?? 0) > 0)
                    <span class="badge rounded-pill bg-primary ms-auto">{{ $adminMenuCounts['warehouse_queue'] }}</span>
                @endif
            </a>
        </li>
        <li class="menu-item {{ request()->routeIs('admin.shipments*') ? 'active' : '' }}">
            <a href="{{ route('admin.shipments.index') }}" class="menu-link">
                <i class="menu-icon icon-base ti tabler-truck-delivery"></i>
                <div>{{ __('admin.shipments_ops_title') }}</div>
                @if(($adminMenuCounts['shipments_ops'] ?? 0) > 0)
                    <span class="badge rounded-pill bg-info ms-auto">{{ $adminMenuCounts['shipments_ops'] }}</span>
                @endif
            </a>
        </li>
        <li class="menu-item {{ request()->routeIs('admin.config.*') ? 'active open' : '' }}">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
                <i class="menu-icon icon-base ti tabler-settings"></i>
                <div>{{ __('admin.content_config') }}</div>
            </a>
            <ul class="menu-sub">
                <li class="menu-item {{ request()->routeIs('admin.config.theme') ? 'active' : '' }}">
                    <a href="{{ route('admin.config.theme') }}" class="menu-link">
                        <div>{{ __('admin.theme') }}</div>
                    </a>
                </li>
                <li class="menu-item {{ request()->routeIs('admin.config.splash') ? 'active' : '' }}">
                    <a href="{{ route('admin.config.splash') }}" class="menu-link">
                        <div>{{ __('admin.splash') }}</div>
                    </a>
                </li>
                <li class="menu-item {{ request()->routeIs('admin.config.onboarding*') ? 'active' : '' }}">
                    <a href="{{ route('admin.config.onboarding.index') }}" class="menu-link">
                        <div>{{ __('admin.onboarding') }}</div>
                    </a>
                </li>
                <li class="menu-item {{ request()->routeIs('admin.config.market-countries*') ? 'active' : '' }}">
                    <a href="{{ route('admin.config.market-countries.index') }}" class="menu-link">
                        <div>{{ __('admin.market_countries') }}</div>
                    </a>
                </li>
                <li class="menu-item {{ request()->routeIs('admin.config.featured-stores*') ? 'active' : '' }}">
                    <a href="{{ route('admin.config.featured-stores.index') }}" class="menu-link">
                        <div>{{ __('admin.featured_stores') }}</div>
                    </a>
                </li>
                <li class="menu-item {{ request()->routeIs('admin.config.promo-banners*') ? 'active' : '' }}">
                    <a href="{{ route('admin.config.promo-banners.index') }}" class="menu-link">
                        <div>{{ __('admin.promo_banners') }}</div>
                    </a>
                </li>
                <li class="menu-item {{ request()->routeIs('admin.config.promo-codes*') ? 'active' : '' }}">
                    <a href="{{ route('admin.config.promo-codes.index') }}" class="menu-link">
                        <div>{{ __('admin.promo_codes') }}</div>
                    </a>
                </li>
                <li class="menu-item {{ request()->routeIs('admin.config.warehouses*') ? 'active' : '' }}">
                    <a href="{{ route('admin.config.warehouses.index') }}" class="menu-link">
                        <div>{{ __('admin.warehouses') }}</div>
                    </a>
                </li>
                <li class="menu-item {{ request()->routeIs('admin.config.app-config') ? 'active' : '' }}">
                    <a href="{{ route('admin.config.app-config') }}" class="menu-link">
                        <div>{{ __('admin.app_config') }}</div>
                    </a>
                </li>
                <li class="menu-item {{ request()->routeIs('admin.config.payment-gateways*') ? 'active' : '' }}">
                    <a href="{{ route('admin.config.payment-gateways.edit') }}" class="menu-link">
                        <div>Payment Gateways</div>
                    </a>
                </li>
                <li class="menu-item {{ request()->routeIs('admin.config.shipping-settings*') ? 'active' : '' }}">
                    <a href="{{ route('admin.config.shipping-settings.edit') }}" class="menu-link">
                        <div>{{ __('admin.shipping_settings') }}</div>
                    </a>
                </li>
                <li class="menu-item {{ request()->routeIs('admin.config.shipping-zones*') ? 'active' : '' }}">
                    <a href="{{ route('admin.config.shipping-zones.index') }}" class="menu-link">
                        <div>{{ __('admin.shipping_zones') }}</div>
                    </a>
                </li>
                <li class="menu-item {{ request()->routeIs('admin.config.shipping-rates*') ? 'active' : '' }}">
                    <a href="{{ route('admin.config.shipping-rates.index') }}" class="menu-link">
                        <div>{{ __('admin.shipping_rates') }}</div>
                    </a>
                </li>
            </ul>
        </li>
        <li class="menu-item {{ request()->routeIs('admin.users*', 'admin.cart-review*', 'admin.wallets*', 'admin.wallet-refunds*', 'admin.support*') ? 'active open' : '' }}">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
                <i class="menu-icon icon-base ti tabler-users"></i>
                <div>{{ __('admin.management') }}</div>
            </a>
            <ul class="menu-sub">
                <li class="menu-item {{ request()->routeIs('admin.users*') ? 'active' : '' }}">
                    <a href="{{ route('admin.users.index') }}" class="menu-link">
                        <div>{{ __('admin.users') }}</div>
                    </a>
                </li>
                <li class="menu-item {{ request()->routeIs('admin.cart-review*') ? 'active' : '' }}">
                    <a href="{{ route('admin.cart-review.index') }}" class="menu-link">
                        <div>{{ __('admin.cart_review') }}</div>
                    </a>
                </li>
                <li class="menu-item {{ request()->routeIs('admin.wallets*') ? 'active' : '' }}">
                    <a href="{{ route('admin.wallets.index') }}" class="menu-link">
                        <div>{{ __('admin.wallet') }}</div>
                    </a>
                </li>
                <li class="menu-item {{ request()->routeIs('admin.wallet-refunds*') ? 'active' : '' }}">
                    <a href="{{ route('admin.wallet-refunds.index') }}" class="menu-link">
                        <div>{{ __('admin.wallet_refunds_title') }}</div>
                    </a>
                </li>
                <li class="menu-item {{ request()->routeIs('admin.support*') ? 'active' : '' }}">
                    <a href="{{ route('admin.support.index') }}" class="menu-link">
                        <div>{{ __('admin.support') }}</div>
                    </a>
                </li>
            </ul>
        </li>
        <li class="menu-item {{ request()->routeIs('admin.notifications.send*') ? 'active' : '' }}">
            <a href="{{ route('admin.notifications.send') }}" class="menu-link">
                <i class="menu-icon icon-base ti tabler-bell"></i>
                <div>{{ __('admin.send_notification') }}</div>
            </a>
        </li>
    </ul>
</aside>
