@extends('layouts.admin')

@section('title', 'لوحة التحكم')

@section('content')
<h4 class="py-4 mb-4">لوحة التحكم التشغيلية</h4>

<div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="content-left">
                        <span class="text-primary d-block mb-2">المنتجات المستوردة</span>
                        <h4 class="mb-2 text-primary">{{ number_format($summary['imported_products_total'] ?? 0) }}</h4>
                        <small class="text-body">إجمالي المنتجات المستوردة</small>
                    </div>
                    <div class="avatar">
                        <span class="avatar-initial rounded bg-label-primary">
                            <i class="icon-base ti tabler-box-seam icon-lg"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="content-left">
                        <span class="text-success d-block mb-2">منتجات مؤكدة</span>
                        <h4 class="mb-2 text-success">{{ number_format($summary['imported_products_confirmed'] ?? 0) }}</h4>
                        <small class="text-body">مضافة للسلة أو تم طلبها</small>
                    </div>
                    <div class="avatar">
                        <span class="avatar-initial rounded bg-label-success">
                            <i class="icon-base ti tabler-check icon-lg"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="content-left">
                        <span class="text-info d-block mb-2">سلات نشطة</span>
                        <h4 class="mb-2 text-info">{{ number_format($summary['active_carts'] ?? 0) }}</h4>
                        <small class="text-body">عناصر غير مرتبطة بمسودة طلب</small>
                    </div>
                    <div class="avatar">
                        <span class="avatar-initial rounded bg-label-info">
                            <i class="icon-base ti tabler-shopping-cart icon-lg"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="content-left">
                        <span class="text-warning d-block mb-2">مسودات الطلبات</span>
                        <h4 class="mb-2 text-warning">{{ number_format($summary['draft_orders'] ?? 0) }}</h4>
                        <small class="text-body">مسودات بانتظار الإنهاء</small>
                    </div>
                    <div class="avatar">
                        <span class="avatar-initial rounded bg-label-warning">
                            <i class="icon-base ti tabler-file-description icon-lg"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="content-left">
                        <span class="text-warning d-block mb-2">بانتظار الدفع</span>
                        <h4 class="mb-2 text-warning">{{ number_format($summary['orders_pending_payment'] ?? 0) }}</h4>
                        <small class="text-body">طلبات حالة pending_payment</small>
                    </div>
                    <div class="avatar">
                        <span class="avatar-initial rounded bg-label-warning">
                            <i class="icon-base ti tabler-credit-card icon-lg"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="content-left">
                        <span class="text-success d-block mb-2">طلبات مدفوعة</span>
                        <h4 class="mb-2 text-success">{{ number_format($summary['orders_paid'] ?? 0) }}</h4>
                        <small class="text-body">طلبات جاهزة للتنفيذ</small>
                    </div>
                    <div class="avatar">
                        <span class="avatar-initial rounded bg-label-success">
                            <i class="icon-base ti tabler-badge-check icon-lg"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="content-left">
                        <span class="text-danger d-block mb-2">تحتاج مراجعة</span>
                        <h4 class="mb-2 text-danger">{{ number_format($summary['orders_needing_review'] ?? 0) }}</h4>
                        <small class="text-body">طلبات needs_review = true</small>
                    </div>
                    <div class="avatar">
                        <span class="avatar-initial rounded bg-label-danger">
                            <i class="icon-base ti tabler-alert-circle icon-lg"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="content-left">
                        <span class="text-info d-block mb-2">قيد التنفيذ</span>
                        <h4 class="mb-2 text-info">{{ number_format($summary['orders_in_fulfillment'] ?? 0) }}</h4>
                        <small class="text-body">طلبات في حالات التنفيذ والشحن</small>
                    </div>
                    <div class="avatar">
                        <span class="avatar-initial rounded bg-label-info">
                            <i class="icon-base ti tabler-truck icon-lg"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="content-left">
                        <span class="text-success d-block mb-2">طلبات تم تسليمها</span>
                        <h4 class="mb-2 text-success">{{ number_format($summary['orders_delivered'] ?? 0) }}</h4>
                        <small class="text-body">حالة delivered</small>
                    </div>
                    <div class="avatar">
                        <span class="avatar-initial rounded bg-label-success">
                            <i class="icon-base ti tabler-package-export icon-lg"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="content-left">
                        <span class="text-info d-block mb-2">شحنات في الطريق</span>
                        <h4 class="mb-2 text-info">{{ number_format($summary['shipments_in_transit'] ?? 0) }}</h4>
                        <small class="text-body">shipment_status = in_transit</small>
                    </div>
                    <div class="avatar">
                        <span class="avatar-initial rounded bg-label-info">
                            <i class="icon-base ti tabler-road icon-lg"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="content-left">
                        <span class="text-danger d-block mb-2">مدفوعات فاشلة</span>
                        <h4 class="mb-2 text-danger">{{ number_format($summary['failed_payments'] ?? 0) }}</h4>
                        <small class="text-body">Payment status = failed</small>
                    </div>
                    <div class="avatar">
                        <span class="avatar-initial rounded bg-label-danger">
                            <i class="icon-base ti tabler-x icon-lg"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="content-left">
                        <span class="text-success d-block mb-2">إشعارات ناجحة</span>
                        <h4 class="mb-2 text-success">{{ number_format($summary['successful_notifications'] ?? 0) }}</h4>
                        <small class="text-body">عمليات إرسال مكتملة</small>
                    </div>
                    <div class="avatar">
                        <span class="avatar-initial rounded bg-label-success">
                            <i class="icon-base ti tabler-bell-ringing icon-lg"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-none bg-label-danger h-100">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between mb-2">
                    <h6 class="card-title mb-0">مؤشرات المراجعة</h6>
                    <span class="badge bg-label-danger text-danger">
                        {{ number_format($review['orders_needs_review'] ?? 0) }}
                    </span>
                </div>
                <p class="mb-2">طلبات تحتاج تدخل إداري صريح.</p>
                <ul class="list-unstyled mb-0 small">
                    <li class="d-flex justify-content-between mb-1">
                        <span>طلبات تقديرية</span>
                        <span class="fw-semibold">{{ number_format($review['estimated_orders'] ?? 0) }}</span>
                    </li>
                    <li class="d-flex justify-content-between mb-1">
                        <span>مسودات محجوبة عن الإنهاء</span>
                        <span class="fw-semibold">{{ number_format($review['orders_blocked_from_checkout'] ?? 0) }}</span>
                    </li>
                    <li class="d-flex justify-content-between">
                        <span>بانتظار إجراء إداري</span>
                        <span class="fw-semibold">{{ number_format($review['orders_awaiting_admin_action'] ?? 0) }}</span>
                    </li>
                </ul>
                @if(!empty($review['review_state_distribution']))
                    <hr>
                    <p class="mb-1 fw-semibold">توزيع حالات المراجعة</p>
                    <ul class="list-unstyled mb-0 small">
                        @foreach($review['review_state_distribution'] as $state => $count)
                            <li class="d-flex justify-content-between">
                                <span>{{ __('admin.review_state_'.$state) ?? $state }}</span>
                                <span class="fw-semibold">{{ number_format($count) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-5">
        <div class="card h-100">
            <div class="card-header">
                <h6 class="card-title mb-0">حالة المدفوعات</h6>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    @php($pc = $payments['counts'] ?? [])
                    <div class="col-6 col-md-4">
                        <div class="d-flex flex-column">
                            <span class="text-muted small mb-1">بانتظار</span>
                            <span class="fw-semibold">{{ number_format($pc['pending'] ?? 0) }}</span>
                        </div>
                    </div>
                    <div class="col-6 col-md-4">
                        <div class="d-flex flex-column">
                            <span class="text-muted small mb-1">تتطلب إجراء</span>
                            <span class="fw-semibold">{{ number_format($pc['requires_action'] ?? 0) }}</span>
                        </div>
                    </div>
                    <div class="col-6 col-md-4">
                        <div class="d-flex flex-column">
                            <span class="text-muted small mb-1">قيد المعالجة</span>
                            <span class="fw-semibold">{{ number_format($pc['processing'] ?? 0) }}</span>
                        </div>
                    </div>
                    <div class="col-6 col-md-4">
                        <div class="d-flex flex-column">
                            <span class="text-muted small mb-1">مدفوعة</span>
                            <span class="fw-semibold text-success">{{ number_format($pc['paid'] ?? 0) }}</span>
                        </div>
                    </div>
                    <div class="col-6 col-md-4">
                        <div class="d-flex flex-column">
                            <span class="text-muted small mb-1">فاشلة</span>
                            <span class="fw-semibold text-danger">{{ number_format($pc['failed'] ?? 0) }}</span>
                        </div>
                    </div>
                    <div class="col-6 col-md-4">
                        <div class="d-flex flex-column">
                            <span class="text-muted small mb-1">ملغاة/مستردة</span>
                            <span class="fw-semibold">{{ number_format(($pc['cancelled'] ?? 0) + ($pc['refunded'] ?? 0)) }}</span>
                        </div>
                    </div>
                </div>

                <p class="mb-2 fw-semibold small">آخر محاولات الدفع</p>
                <div class="table-responsive mb-2" style="max-height: 180px;">
                    <table class="table table-sm table-borderless mb-0">
                        <thead>
                        <tr>
                            <th class="small">الطلب</th>
                            <th class="small">المبلغ</th>
                            <th class="small">الحالة</th>
                            <th class="small text-end">تاريخ</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($payments['recent_payments'] ?? [] as $payment)
                            <tr>
                                <td>#{{ $payment->order_id }}</td>
                                <td>{{ number_format((float) $payment->amount, 2) }} {{ $payment->currency }}</td>
                                <td><span class="badge bg-label-secondary">{{ $payment->status->label() }}</span></td>
                                <td class="text-end small text-muted">{{ optional($payment->created_at)->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted small">لا توجد بيانات حديثة.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                <p class="mb-1 fw-semibold small">أحدث محاولات بوابة الدفع</p>
                <ul class="list-unstyled mb-0 small" style="max-height: 80px; overflow-y: auto;">
                    @forelse($payments['recent_attempts'] ?? [] as $attempt)
                        <li class="d-flex justify-content-between mb-1">
                            <span>#{{ $attempt->payment_id }} / {{ $attempt->provider_reference }}</span>
                            <span class="text-muted">{{ optional($attempt->created_at)->diffForHumans() }}</span>
                        </li>
                    @empty
                        <li class="text-muted">لا توجد محاولات مسجلة.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>

    <div class="col-sm-12 col-xl-4">
        <div class="card h-100">
            <div class="card-header">
                <h6 class="card-title mb-0">الشحن والتنفيذ</h6>
            </div>
            <div class="card-body">
                <p class="mb-2 small text-muted">توزيع الشحنات حسب الحالة.</p>
                <ul class="list-unstyled mb-3 small">
                    @forelse(($shipments['by_status'] ?? []) as $status => $total)
                        <li class="d-flex justify-content-between mb-1">
                            <span>{{ $status ?: 'غير محدد' }}</span>
                            <span class="fw-semibold">{{ number_format($total) }}</span>
                        </li>
                    @empty
                        <li class="text-muted">لا توجد شحنات بعد.</li>
                    @endforelse
                </ul>

                <ul class="list-unstyled mb-3 small">
                    <li class="d-flex justify-content-between mb-1">
                        <span>طلبات بها رقم تتبع</span>
                        <span class="fw-semibold">{{ number_format($shipments['orders_with_tracking'] ?? 0) }}</span>
                    </li>
                    <li class="d-flex justify-content-between mb-1">
                        <span>شحنات تم تسليمها</span>
                        <span class="fw-semibold">{{ number_format($shipments['delivered_shipments'] ?? 0) }}</span>
                    </li>
                    <li class="d-flex justify-content-between">
                        <span>شحنات بها استثناء</span>
                        <span class="fw-semibold text-danger">{{ number_format($shipments['shipments_with_exceptions'] ?? 0) }}</span>
                    </li>
                </ul>

                <p class="mb-1 fw-semibold small">أفضل شركات الشحن</p>
                <ul class="list-unstyled mb-0 small">
                    @forelse($shipments['top_carriers'] ?? [] as $carrier)
                        <li class="d-flex justify-content-between mb-1">
                            <span>{{ $carrier->carrier ?: 'غير محدد' }}</span>
                            <span class="fw-semibold">{{ number_format($carrier->total) }}</span>
                        </li>
                    @empty
                        <li class="text-muted">لم يتم تعيين شركات شحن بعد.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-6 col-xl-4">
        <div class="card h-100">
            <div class="card-header">
                <h6 class="card-title mb-0">نشاط الإشعارات</h6>
            </div>
            <div class="card-body">
                @php($notif = $notifications ?? [])
                <p class="mb-1 fw-semibold small">حسب النوع</p>
                <ul class="list-unstyled mb-3 small">
                    <li class="d-flex justify-content-between mb-1">
                        <span>إرسال جماعي</span>
                        <span class="fw-semibold">{{ number_format($notif['by_type'][\App\Models\NotificationDispatch::TYPE_BULK] ?? 0) }}</span>
                    </li>
                    <li class="d-flex justify-content-between mb-1">
                        <span>إرسال فردي</span>
                        <span class="fw-semibold">{{ number_format($notif['by_type'][\App\Models\NotificationDispatch::TYPE_INDIVIDUAL] ?? 0) }}</span>
                    </li>
                    <li class="d-flex justify-content-between">
                        <span>أحداث النظام</span>
                        <span class="fw-semibold">{{ number_format($notif['by_type'][\App\Models\NotificationDispatch::TYPE_SYSTEM_EVENT] ?? 0) }}</span>
                    </li>
                </ul>

                <p class="mb-1 fw-semibold small">حسب حالة الإرسال</p>
                <ul class="list-unstyled mb-3 small">
                    <li class="d-flex justify-content-between mb-1">
                        <span>قيد الإرسال</span>
                        <span class="fw-semibold">{{ number_format($notif['by_status'][\App\Models\NotificationDispatch::STATUS_PENDING] ?? 0) }}</span>
                    </li>
                    <li class="d-flex justify-content-between mb-1">
                        <span>ناجحة</span>
                        <span class="fw-semibold text-success">{{ number_format($notif['by_status'][\App\Models\NotificationDispatch::STATUS_SENT] ?? 0) }}</span>
                    </li>
                    <li class="d-flex justify-content-between mb-1">
                        <span>جزئية</span>
                        <span class="fw-semibold text-warning">{{ number_format($notif['by_status'][\App\Models\NotificationDispatch::STATUS_PARTIAL] ?? 0) }}</span>
                    </li>
                    <li class="d-flex justify-content-between">
                        <span>فاشلة</span>
                        <span class="fw-semibold text-danger">{{ number_format($notif['by_status'][\App\Models\NotificationDispatch::STATUS_FAILED] ?? 0) }}</span>
                    </li>
                </ul>

                <p class="mb-1 fw-semibold small">آخر نشاط إرسال</p>
                <ul class="list-unstyled mb-0 small" style="max-height: 140px; overflow-y: auto;">
                    @forelse($notif['recent'] ?? [] as $dispatch)
                        <li class="mb-1">
                            <div class="d-flex justify-content-between">
                                <span class="fw-semibold">{{ $dispatch->title }}</span>
                                <span class="badge bg-label-secondary">{{ $dispatch->send_status }}</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <small class="text-muted">{{ $dispatch->type }}</small>
                                <small class="text-muted">{{ optional($dispatch->created_at)->diffForHumans() }}</small>
                            </div>
                        </li>
                    @empty
                        <li class="text-muted">لا توجد عمليات إرسال حديثة.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl-4">
        <div class="card h-100">
            <div class="card-header">
                <h6 class="card-title mb-0">أهم الوجهات والحالات</h6>
            </div>
            <div class="card-body">
                <p class="mb-1 fw-semibold small">أهم دول الوجهة</p>
                <ul class="list-unstyled mb-3 small">
                    @forelse($top['destination_countries'] ?? [] as $country)
                        <li class="d-flex justify-content-between mb-1">
                            <span>{{ $country->country_label ?? $country->country_code }}</span>
                            <span class="fw-semibold">{{ number_format($country->total) }}</span>
                        </li>
                    @empty
                        <li class="text-muted">لا توجد بيانات شحن كافية بعد.</li>
                    @endforelse
                </ul>

                <p class="mb-1 fw-semibold small">أهم شركات الشحن</p>
                <ul class="list-unstyled mb-3 small">
                    @forelse($top['carriers'] ?? [] as $carrier)
                        <li class="d-flex justify-content-between mb-1">
                            <span>{{ $carrier->carrier ?: 'غير محدد' }}</span>
                            <span class="fw-semibold">{{ number_format($carrier->total) }}</span>
                        </li>
                    @empty
                        <li class="text-muted">لم يتم تعيين شركات شحن بعد.</li>
                    @endforelse
                </ul>

                <p class="mb-1 fw-semibold small">أهم حالات الطلبات</p>
                <ul class="list-unstyled mb-0 small">
                    @forelse($top['order_statuses'] ?? [] as $os)
                        <li class="d-flex justify-content-between mb-1">
                            <span>{{ $os->status }}</span>
                            <span class="fw-semibold">{{ number_format($os->total) }}</span>
                        </li>
                    @empty
                        <li class="text-muted">لا توجد طلبات بعد.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>

    <div class="col-md-12 col-xl-4">
        <div class="card h-100">
            <div class="card-header">
                <h6 class="card-title mb-0">آخر النشاطات التشغيلية</h6>
            </div>
            <div class="card-body">
                <p class="mb-1 fw-semibold small">الطلبات</p>
                <ul class="list-unstyled mb-2 small" style="max-height: 80px; overflow-y: auto;">
                    @forelse(($recent_activity['orders'] ?? []) as $order)
                        <li class="d-flex justify-content-between mb-1">
                            <span>#{{ $order->id }} / {{ $order->status }}</span>
                            <span class="text-muted">{{ optional($order->created_at)->diffForHumans() }}</span>
                        </li>
                    @empty
                        <li class="text-muted">لا يوجد نشاط حديث.</li>
                    @endforelse
                </ul>

                <p class="mb-1 fw-semibold small">الشحنات</p>
                <ul class="list-unstyled mb-2 small" style="max-height: 80px; overflow-y: auto;">
                    @forelse(($recent_activity['shipments'] ?? []) as $shipment)
                        <li class="d-flex justify-content-between mb-1">
                            <span>#{{ $shipment->id }} / {{ $shipment->shipment_status ?? '-' }}</span>
                            <span class="text-muted">{{ optional($shipment->created_at)->diffForHumans() }}</span>
                        </li>
                    @empty
                        <li class="text-muted">لا يوجد نشاط شحن حديث.</li>
                    @endforelse
                </ul>

                <p class="mb-1 fw-semibold small">المدفوعات</p>
                <ul class="list-unstyled mb-2 small" style="max-height: 80px; overflow-y: auto;">
                    @forelse(($recent_activity['payments'] ?? []) as $payment)
                        <li class="d-flex justify-content-between mb-1">
                            <span>#{{ $payment->id }} / {{ $payment->status->label() }}</span>
                            <span class="text-muted">{{ optional($payment->created_at)->diffForHumans() }}</span>
                        </li>
                    @empty
                        <li class="text-muted">لا يوجد نشاط دفع حديث.</li>
                    @endforelse
                </ul>

                <p class="mb-1 fw-semibold small">الإشعارات</p>
                <ul class="list-unstyled mb-0 small" style="max-height: 80px; overflow-y: auto;">
                    @forelse(($recent_activity['notifications'] ?? []) as $dispatch)
                        <li class="d-flex justify-content-between mb-1">
                            <span>{{ $dispatch->title }}</span>
                            <span class="text-muted">{{ optional($dispatch->created_at)->diffForHumans() }}</span>
                        </li>
                    @empty
                        <li class="text-muted">لا يوجد نشاط إشعارات حديث.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
