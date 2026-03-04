<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function showSendForm(): View
    {
        return view('admin.notifications.send');
    }

    public function send(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'send_to_all' => 'boolean',
            'title' => 'required|string|max:200',
            'subtitle' => 'nullable|string|max:500',
            'type' => 'nullable|string|max:30',
            'important' => 'boolean',
            'action_label' => 'nullable|string|max:100',
            'action_route' => 'nullable|string|max:200',
        ]);

        $userIds = [];
        if ($request->boolean('send_to_all')) {
            $userIds = User::pluck('id')->toArray();
        } elseif (!empty($validated['user_id'])) {
            $userIds = [$validated['user_id']];
        } else {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'errors' => ['user_id' => [__('admin.error')]]], 422);
            }
            return redirect()->back()->withErrors(['user_id' => 'اختر مستخدماً أو فعّل "إرسال للجميع".']);
        }

        foreach ($userIds as $userId) {
            Notification::create([
                'user_id' => $userId,
                'type' => $validated['type'] ?? 'all',
                'title' => $validated['title'],
                'subtitle' => $validated['subtitle'] ?? null,
                'read' => false,
                'important' => $request->boolean('important'),
                'action_label' => $validated['action_label'] ?? null,
                'action_route' => $validated['action_route'] ?? null,
            ]);
        }

        $count = count($userIds);
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('admin.success')]);
        }
        return redirect()->route('admin.notifications.send')->with('success', __('admin.success'));
    }
}
