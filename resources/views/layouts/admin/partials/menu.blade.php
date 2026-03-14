<aside id="layout-menu" class="layout-menu menu-vertical menu">
    <div class="app-brand demo">
        <a href="{{ route('admin.dashboard') }}" class="app-brand-link">
            <span class="app-brand-logo demo">
                <span class="text-primary">
                    <svg width="32" height="22" viewBox="0 0 32 22" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M0.00172773 0V6.85398C0.00172773 6.85398 -0.133178 9.01207 1.98092 10.8388L13.6912 21.9964L19.7809 21.9181L18.8042 9.88248L16.4951 7.17289L9.23799 0H0.00172773Z" fill="currentColor" />
                        <path opacity="0.06" fill-rule="evenodd" clip-rule="evenodd" d="M7.69824 16.4364L12.5199 3.23696L16.5541 7.25596L7.69824 16.4364Z" fill="#161616" />
                        <path opacity="0.06" fill-rule="evenodd" clip-rule="evenodd" d="M8.07751 15.9175L13.9419 4.63989L16.5849 7.28475L8.07751 15.9175Z" fill="#161616" />
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M7.77295 16.3566L23.6563 0H32V6.88383C32 6.88383 31.8262 9.17836 30.6591 10.4057L19.7824 22H13.6938L7.77295 16.3566Z" fill="currentColor" />
                    </svg>
                </span>
            </span>
            <span class="app-brand-text demo menu-text fw-bold ms-3">Zayer</span>
        </a>
        <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto">
            <i class="icon-base ti menu-toggle-icon d-none d-xl-block"></i>
            <i class="icon-base ti tabler-x d-block d-xl-none"></i>
        </a>
    </div>
    <div class="menu-inner-shadow"></div>
    <ul class="menu-inner py-1">
        <li class="menu-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
            <a href="{{ route('admin.dashboard') }}" class="menu-link">
                <i class="menu-icon icon-base ti tabler-smart-home"></i>
                <div>{{ __('admin.dashboard') }}</div>
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
                <li class="menu-item {{ request()->routeIs('admin.config.warehouses*') ? 'active' : '' }}">
                    <a href="{{ route('admin.config.warehouses.index') }}" class="menu-link">
                        <div>{{ __('admin.warehouses') }}</div>
                    </a>
                </li>
                <li class="menu-item {{ request()->routeIs('admin.config.app-config') ? 'active' : '' }}">
                    <a href="{{ route('admin.config.app-config') }}" class="menu-link">
                        <div>إعدادات التطبيق (API)</div>
                    </a>
                </li>
                <li class="menu-item {{ request()->routeIs('admin.config.shipping-settings*') ? 'active' : '' }}">
                    <a href="{{ route('admin.config.shipping-settings.edit') }}" class="menu-link">
                        <div>{{ __('admin.shipping_settings') }}</div>
                    </a>
                </li>
            </ul>
        </li>
        <li class="menu-item {{ request()->routeIs('admin.users*', 'admin.orders*', 'admin.cart-review*', 'admin.wallets*', 'admin.support*') ? 'active open' : '' }}">
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
                <li class="menu-item {{ request()->routeIs('admin.orders*') ? 'active' : '' }}">
                    <a href="{{ route('admin.orders.index') }}" class="menu-link">
                        <div>{{ __('admin.orders') }}</div>
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
