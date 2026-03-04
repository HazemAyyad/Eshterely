@extends('layouts.admin')

@section('title', 'تفاصيل المحفظة')

@section('content')
<h4 class="py-4 mb-4">محفظة المستخدم {{ $userModel->phone ?? $userModel->email ?? '#'.$userModel->id }}</h4>

<div class="row g-4">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <p class="text-muted mb-1">الرصيد المتاح</p>
                <h4>{{ number_format($wallet->available_balance, 2) }}</h4>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <p class="text-muted mb-1">قيد الانتظار</p>
                <h4>{{ number_format($wallet->pending_balance, 2) }}</h4>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <p class="text-muted mb-1">عروض</p>
                <h4>{{ number_format($wallet->promo_balance, 2) }}</h4>
            </div>
        </div>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header"><h5 class="mb-0">آخر الحركات</h5></div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>النوع</th>
                    <th>العنوان</th>
                    <th>المبلغ</th>
                    <th>التاريخ</th>
                </tr>
            </thead>
            <tbody>
                @forelse($wallet->transactions as $t)
                <tr>
                    <td>{{ $t->id }}</td>
                    <td>{{ $t->type }}</td>
                    <td>{{ $t->title ?? $t->subtitle ?? '-' }}</td>
                    <td class="{{ $t->amount >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($t->amount, 2) }}</td>
                    <td>{{ $t->created_at?->format('Y-m-d H:i') }}</td>
                </tr>
                @empty
                <tr><td colspan="5" class="text-center">لا توجد حركات</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4">
    <a href="{{ route('admin.wallets.index') }}" class="btn btn-outline-secondary">رجوع</a>
</div>
@endsection
