<!doctype html>
@php
    $locale = app()->getLocale();
    $dir = $locale === 'ar' ? 'rtl' : 'ltr';
@endphp
<html lang="{{ $locale }}" class="layout-navbar-fixed layout-menu-fixed layout-compact" dir="{{ $dir }}" data-skin="default" data-bs-theme="light" data-assets-path="{{ asset('vuexy/assets') }}/" data-template="vertical-menu-template-starter">
@include('layouts.admin.partials.head')
<body>
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            @include('layouts.admin.partials.menu')

            <div class="menu-mobile-toggler d-xl-none rounded-1">
                <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large text-bg-secondary p-2 rounded-1">
                    <i class="ti tabler-menu icon-base"></i>
                    <i class="ti tabler-chevron-right icon-base"></i>
                </a>
            </div>

            <div class="layout-page">
                @include('layouts.admin.partials.header')

                <div class="content-wrapper">
                    <div class="container-xxl flex-grow-1 container-p-y">
                        @yield('content')
                    </div>
                    @include('layouts.admin.partials.footer')
                </div>
            </div>
        </div>
        <div class="layout-overlay layout-menu-toggle"></div>
        <div class="drag-target"></div>
    </div>

    @include('layouts.admin.partials.scripts')
</body>
</html>
