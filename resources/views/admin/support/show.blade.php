@extends('layouts.admin')

@section('title', 'تذكرة #' . $ticket->id)

@section('content')
<h4 class="py-4 mb-4">تذكرة #{{ $ticket->id }}</h4>

@if (session('success'))
    <div class="alert alert-success alert-dismissible">{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="row g-4">
    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header"><h5 class="mb-0">المحادثة</h5></div>
            <div class="card-body">
                @forelse($ticket->messages as $msg)
                <div class="d-flex mb-3 {{ $msg->is_from_agent ? 'justify-content-end' : '' }}">
                    <div class="rounded p-3 {{ $msg->is_from_agent ? 'bg-primary bg-opacity-10' : 'bg-light' }}" style="max-width: 80%;">
                        <small class="text-muted">{{ $msg->sender_name ?? ($msg->is_from_agent ? 'الدعم' : 'المستخدم') }}</small>
                        <p class="mb-0">{{ $msg->body }}</p>
                        <small class="text-muted">{{ $msg->created_at?->format('Y-m-d H:i') }}</small>
                    </div>
                </div>
                @empty
                <p class="text-muted">لا توجد رسائل بعد.</p>
                @endforelse

                <hr>
                <form method="POST" action="{{ route('admin.support.store-message', $ticket) }}" class="ajax-submit-form">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">رد</label>
                        <textarea name="body" class="form-control" rows="3" required placeholder="اكتب ردك..."></textarea>
                        @error('body')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <button type="submit" class="btn btn-primary">إرسال</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">التفاصيل</h5>
                <form method="POST" action="{{ route('admin.support.update-status', $ticket) }}" class="d-flex gap-2 ajax-submit-form">
                    @csrf
                    @method('PATCH')
                    <select name="status" class="form-select form-select-sm" style="width:auto">
                        <option value="open" {{ $ticket->status === 'open' ? 'selected' : '' }}>مفتوحة</option>
                        <option value="in_progress" {{ $ticket->status === 'in_progress' ? 'selected' : '' }}>قيد المعالجة</option>
                        <option value="resolved" {{ $ticket->status === 'resolved' ? 'selected' : '' }}>محلولة</option>
                    </select>
                    <button type="submit" class="btn btn-sm btn-primary">تحديث</button>
                </form>
            </div>
            <div class="card-body">
                <p><strong>المستخدم:</strong> {{ $ticket->user->phone ?? $ticket->user->email ?? '-' }}</p>
                <p><strong>النوع:</strong> {{ $ticket->issue_type ?? '-' }}</p>
                <p><strong>الموضوع:</strong> {{ $ticket->subject ?? '-' }}</p>
                <p><strong>الطلب:</strong> {{ $ticket->order_id ? '#' . $ticket->order_id : '-' }}</p>
                <p><strong>التاريخ:</strong> {{ $ticket->created_at?->format('Y-m-d H:i') }}</p>
            </div>
        </div>
    </div>
</div>

<div class="mt-4">
    <a href="{{ route('admin.support.index') }}" class="btn btn-outline-secondary">{{ __('admin.back') }}</a>
</div>
@endsection

@include('admin.partials.ajax-form-script', ['redirect' => route('admin.support.show', $ticket)])
