@extends('layouts.admin')

@section('title', $title ?? 'قريباً')

@section('content')
<div class="card">
    <div class="card-body text-center py-8">
        <i class="icon-base ti tabler-clock icon-4xl text-body-secondary mb-4"></i>
        <h4>{{ $title ?? 'هذه الصفحة قيد التطوير' }}</h4>
        <p class="text-body-secondary">{{ $message ?? 'سيتم تفعيل هذه الصفحة قريباً.' }}</p>
        <a href="{{ route('admin.dashboard') }}" class="btn btn-primary mt-4">العودة للوحة التحكم</a>
    </div>
</div>
@endsection
