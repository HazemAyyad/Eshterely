@extends('layouts.admin')

@section('title', 'تفاصيل الطلب ' . $order->order_number)

@section('content')
<h4 class="py-4 mb-4">تفاصيل الطلب {{ $order->order_number }}</h4>

@if (session('success'))
    <div class="alert alert-success alert-dismissible">{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="row g-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">معلومات الطلب</h5>
                <form method="POST" action="{{ route('admin.orders.update-status', $order) }}" class="d-flex gap-2 ajax-submit-form">
                    @csrf
                    @method('PATCH')
                    <select name="status" class="form-select form-select-sm" style="width:auto" required>
                        <option value="">تغيير الحالة</option>
                        <option value="in_transit">قيد الشحن</option>
                        <option value="delivered">تم التوصيل</option>
                        <option value="cancelled">ملغي</option>
                    </select>
                    <button type="submit" class="btn btn-sm btn-primary">تحديث</button>
                </form>
            </div>
            <div class="card-body">
                <p><strong>الحالة:</strong> <span class="badge bg-{{ $order->status === 'delivered' ? 'success' : ($order->status === 'cancelled' ? 'danger' : 'warning') }}">{{ $order->status }}</span></p>
                <p><strong>الأصل:</strong> {{ $order->origin }}</p>
                <p><strong>المبلغ الإجمالي:</strong> {{ number_format($order->total_amount, 2) }} {{ $order->currency }}</p>
                <p><strong>تاريخ الطلب:</strong> {{ $order->placed_at?->format('Y-m-d H:i') ?? '-' }}</p>
                <p><strong>التوصيل المتوقع:</strong> {{ $order->estimated_delivery ?? '-' }}</p>
                <p><strong>عنوان الشحن:</strong> {{ Str::limit($order->shipping_address_text ?? '-', 80) }}</p>
            </div>
        </div>
    </div>
</div>

@foreach($order->shipments as $shipment)
<div class="card mt-4">
    <div class="card-header"><h5 class="mb-0">شحنة {{ $shipment->country_label ?? $shipment->country_code }}</h5></div>
    <div class="card-body">
        <p><strong>طريقة الشحن:</strong> {{ $shipment->shipping_method ?? '-' }}</p>
        <p><strong>المجموع الفرعي:</strong> {{ number_format($shipment->subtotal ?? 0, 2) }}</p>
        <p><strong>رسوم الشحن:</strong> {{ number_format($shipment->shipping_fee ?? 0, 2) }}</p>
        <p><strong>الضريبة الجمركية:</strong> {{ number_format($shipment->customs_duties ?? 0, 2) }}</p>
        <table class="table table-sm mt-3">
            <thead><tr><th>المنتج</th><th>المتجر</th><th>السعر</th><th>الكمية</th></tr></thead>
            <tbody>
                @foreach($shipment->lineItems as $li)
                <tr>
                    <td>{{ $li->name }}</td>
                    <td>{{ $li->store_name ?? '-' }}</td>
                    <td>{{ number_format($li->price, 2) }}</td>
                    <td>{{ $li->quantity }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @if ($shipment->trackingEvents->isNotEmpty())
        <h6 class="mt-3">أحداث التتبع</h6>
        <ul class="list-unstyled">
            @foreach($shipment->trackingEvents->sortBy('sort_order') as $ev)
            <li>{{ $ev->title }} — {{ $ev->subtitle ?? '' }}</li>
            @endforeach
        </ul>
        @endif
    </div>
</div>
@endforeach

@if ($priceLines->isNotEmpty())
<div class="card mt-4">
    <div class="card-header"><h5 class="mb-0">بنود السعر</h5></div>
    <div class="card-body">
        <table class="table">
            @foreach($priceLines as $pl)
            <tr>
                <td>{{ $pl->label }}</td>
                <td class="{{ $pl->is_discount ? 'text-success' : '' }}">{{ $pl->amount }}</td>
            </tr>
            @endforeach
        </table>
    </div>
</div>
@endif

<div class="mt-4">
    <a href="{{ route('admin.orders.index') }}" class="btn btn-outline-secondary">{{ __('admin.back') }}</a>
</div>
@endsection

@include('admin.partials.ajax-form-script', ['redirect' => route('admin.orders.show', $order)])
