<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\User;
use App\Services\Fcm\FcmNotificationService;
use App\Services\Fcm\NotificationDispatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function __construct(
        protected NotificationDispatchService $dispatchService
    ) {}

    public function showSendForm(): View
    {
        return view('admin.notifications.send');
    }

    public function send(Request $request): RedirectResponse|JsonResponse
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
            'send_fcm' => 'boolean',
            'image_url' => 'nullable|string|url|max:500',
            'target_type' => 'nullable|string|max:50',
            'target_id' => 'nullable|string|max:100',
            'route_key' => 'nullable|string|max:100',
        ]);

        $userIds = [];
        if ($request->boolean('send_to_all')) {
            $userIds = User::pluck('id')->toArray();
        } elseif (! empty($validated['user_id'])) {
            $userIds = [(int) $validated['user_id']];
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

        if ($request->boolean('send_fcm')) {
            $body = $validated['subtitle'] ?? $validated['title'];
            $meta = null;
            if (! empty($validated['target_type']) || ! empty($validated['target_id']) || ! empty($validated['route_key'])) {
                $meta = array_filter([
                    'target_type' => $validated['target_type'] ?? null,
                    'target_id' => $validated['target_id'] ?? null,
                    'route_key' => $validated['route_key'] ?? null,
                ]);
            }
            $this->dispatchService->sendBulk(
                $validated['title'],
                $body,
                $validated['image_url'] ?? null,
                null,
                $meta ?: null,
                $request->user('admin'),
                $request->boolean('send_to_all') ? null : $userIds
            );
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('admin.success')]);
        }
        return redirect()->route('admin.notifications.send')->with('success', __('admin.success'));
    }

    /**
     * Send individual FCM notification to one user (from user detail page).
     */
    public function sendToUser(Request $request, User $user): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'body' => 'nullable|string|max:1000',
            'image_url' => 'nullable|string|url|max:500',
            'target_type' => 'nullable|string|max:50',
            'target_id' => 'nullable|string|max:100',
            'route_key' => 'nullable|string|max:100',
        ]);

        $meta = array_filter([
            'target_type' => $validated['target_type'] ?? null,
            'target_id' => $validated['target_id'] ?? null,
            'route_key' => $validated['route_key'] ?? null,
        ]) ?: null;

        $this->dispatchService->sendToUser(
            $user,
            $validated['title'],
            $validated['body'] ?? $validated['title'],
            $validated['image_url'] ?? null,
            null,
            $meta,
            $request->user('admin')
        );

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('admin.success')]);
        }
        return redirect()->route('admin.users.show', $user)->with('success', __('admin.success'));
    }
}
