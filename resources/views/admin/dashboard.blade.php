@extends('layouts.admin')

@section('title', 'لوحة التحكم')

@section('content')
<h4 class="py-4 mb-4">لوحة التحكم</h4>

<div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl-4">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="content-left">
                        <span class="text-primary d-block mb-2">المستخدمون</span>
                        <h4 class="mb-2 text-primary">{{ $stats['users'] }}</h4>
                        <small class="text-body">إجمالي المسجلين</small>
                    </div>
                    <div class="avatar">
                        <span class="avatar-initial rounded bg-label-primary">
                            <i class="icon-base ti tabler-users icon-lg"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-4">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="content-left">
                        <span class="text-success d-block mb-2">الطلبات</span>
                        <h4 class="mb-2 text-success">{{ $stats['orders'] }}</h4>
                        <small class="text-body">إجمالي الطلبات</small>
                    </div>
                    <div class="avatar">
                        <span class="avatar-initial rounded bg-label-success">
                            <i class="icon-base ti tabler-shopping-cart icon-lg"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-4">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="content-left">
                        <span class="text-warning d-block mb-2">الدعم المفتوح</span>
                        <h4 class="mb-2 text-warning">{{ $stats['support_open'] }}</h4>
                        <small class="text-body">تذاكر بانتظار الرد</small>
                    </div>
                    <div class="avatar">
                        <span class="avatar-initial rounded bg-label-warning">
                            <i class="icon-base ti tabler-headphones icon-lg"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">مرحباً بك في لوحة إدارة Zayer</h5>
    </div>
    <div class="card-body">
        <p class="mb-0">تم دمج قالب <strong>Vuexy v10.11.1</strong> بنجاح. يمكنك الآن إدارة المستخدمين والطلبات والدعم الفني من خلال القوائم الجانبية.</p>
    </div>
</div>
@endsection
