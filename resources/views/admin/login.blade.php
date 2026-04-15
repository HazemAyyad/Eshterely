<!doctype html>
<html lang="en" class="layout-wide customizer-hide" dir="ltr" data-skin="default" data-bs-theme="light" data-assets-path="{{ asset('vuexy/assets') }}/" data-template="vertical-menu-template">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <meta name="robots" content="noindex, nofollow" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>{{ __('admin.login_page_title', ['name' => $adminBrand['name']]) }}</title>

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
    <link rel="stylesheet" href="{{ asset('vuexy/assets/vendor/libs/@form-validation/form-validation.css') }}" />
    <link rel="stylesheet" href="{{ asset('vuexy/assets/vendor/css/pages/page-auth.css') }}" />
    <script src="{{ asset('vuexy/assets/vendor/js/helpers.js') }}"></script>
    <script src="{{ asset('vuexy/assets/vendor/libs/pickr/pickr.js') }}"></script>
    <script src="{{ asset('vuexy/assets/vendor/js/template-customizer.js') }}"></script>
    <script src="{{ asset('vuexy/assets/js/config.js') }}"></script>
</head>
<body>
    <div class="container-xxl">
        <div class="authentication-wrapper authentication-basic container-p-y">
            <div class="authentication-inner py-6">
                <div class="card">
                    <div class="card-body">
                        <div class="app-brand justify-content-center mb-6">
                            <a href="{{ route('admin.dashboard') }}" class="app-brand-link">
                                @include('layouts.admin.partials.brand-mark')
                                <span class="app-brand-text demo text-heading fw-bold ms-2">{{ $adminBrand['name'] }}</span>
                            </a>
                        </div>
                        <h4 class="mb-1">{{ __('admin.login_welcome_heading', ['name' => $adminBrand['name']]) }}</h4>
                        <p class="mb-6">سجّل دخولك للوصول إلى لوحة الإدارة</p>

                        @if ($errors->any())
                            <div class="alert alert-danger alert-dismissible" role="alert">
                                {{ $errors->first() }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('admin.login') }}" class="mb-4">
                            @csrf
                            <div class="mb-6">
                                <label for="email" class="form-label">البريد الإلكتروني</label>
                                <input type="email" class="form-control" id="email" name="email" value="{{ old('email') }}" placeholder="أدخل بريدك الإلكتروني" required autofocus />
                            </div>
                            <div class="mb-6 form-password-toggle">
                                <label class="form-label" for="password">كلمة المرور</label>
                                <div class="input-group input-group-merge">
                                    <input type="password" id="password" class="form-control" name="password" placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;" required />
                                    <span class="input-group-text cursor-pointer"><i class="icon-base ti tabler-eye-off"></i></span>
                                </div>
                            </div>
                            <div class="my-8">
                                <div class="form-check mb-0">
                                    <input class="form-check-input" type="checkbox" id="remember" name="remember" />
                                    <label class="form-check-label" for="remember">تذكرني</label>
                                </div>
                            </div>
                            <div class="mb-6">
                                <button class="btn btn-primary d-grid w-100" type="submit">تسجيل الدخول</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="{{ asset('vuexy/assets/vendor/libs/jquery/jquery.js') }}"></script>
    <script src="{{ asset('vuexy/assets/vendor/libs/popper/popper.js') }}"></script>
    <script src="{{ asset('vuexy/assets/vendor/js/bootstrap.js') }}"></script>
    <script src="{{ asset('vuexy/assets/vendor/libs/node-waves/node-waves.js') }}"></script>
    <script src="{{ asset('vuexy/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js') }}"></script>
    <script src="{{ asset('vuexy/assets/vendor/js/menu.js') }}"></script>
    <script src="{{ asset('vuexy/assets/js/main.js') }}"></script>
    <script src="{{ asset('vuexy/assets/js/pages-auth.js') }}"></script>
</body>
</html>
