<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <meta name="robots" content="noindex, nofollow" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>@yield('title', __('admin.admin_panel')) | {{ $adminBrand['name'] }}</title>

    @if(!empty($adminBrand['icon_url']))
        <link rel="icon" href="{{ $adminBrand['icon_url'] }}" />
    @else
        <link rel="icon" type="image/x-icon" href="{{ asset('vuexy/assets/img/favicon/favicon.ico') }}" />
    @endif
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="{{ asset('vuexy/assets/vendor/fonts/iconify-icons.css') }}" />
    <link rel="stylesheet" href="{{ asset('vuexy/assets/vendor/libs/node-waves/node-waves.css') }}" />
    <link rel="stylesheet" href="{{ asset('vuexy/assets/vendor/libs/pickr/pickr-themes.css') }}" />
    <link rel="stylesheet" href="{{ asset('vuexy/assets/vendor/css/core.css') }}" />
    <link rel="stylesheet" href="{{ asset('vuexy/assets/css/demo.css') }}" />
    <link rel="stylesheet" href="{{ asset('vuexy/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css') }}" />
    <link rel="stylesheet" href="{{ asset('vuexy/assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
    <script src="{{ asset('vuexy/assets/vendor/js/helpers.js') }}"></script>
    <script src="{{ asset('vuexy/assets/vendor/js/template-customizer.js') }}"></script>
    <script src="{{ asset('vuexy/assets/js/config.js') }}"></script>
    @stack('styles')
</head>
