<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\User;
use App\Services\Admin\AdminNotificationPayloadService;
use App\Services\Fcm\NotificationDispatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function __construct(
        protected NotificationDispatchService $dispatchService,
        protected AdminNotificationPayloadService $payloadService
    ) {}

    public function showSendForm(): View
    {
        return view('admin.notifications.send', [
            'routeKeys' => $this->payloadService->routeKeys(),
            'targetTypes' => $this->payloadService->targetTypes(),
            'actionLabelPresets' => $this->payloadService->actionLabelPresets(),
        ]);
    }

    public function send(Request $request): RedirectResponse|JsonResponse
    {
        $payloadService = $this->payloadService;
        $validated = $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'send_to_all' => 'boolean',
            'title' => 'required|string|max:200',
            'subtitle' => 'nullable|string|max:500',
            'type' => 'nullable|string|max:30',
            'important' => 'boolean',
            'action_label' => 'nullable|string|max:100',
            'action_label_custom' => 'nullable|string|max:100',
            'action_route_override' => 'nullable|string|max:200',
            'send_fcm' => 'boolean',
            'image' => 'nullable|image|mimes:jpeg,png,webp,gif|max:512',
            'image_url' => 'nullable|string|url|max:500',
            'target_type' => $payloadService->targetTypeRules(),
            'target_id' => 'nullable|string|max:100',
            'route_key' => $payloadService->routeKeyRules(),
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
            return redirect()->back()->withErrors(['user_id' => __('admin.notification_choose_user_or_all')]);
        }

        $actionLabel = $this->resolveActionLabel(
            $validated['action_label'] ?? null,
            $validated['action_label_custom'] ?? null
        );
        $routeKey = $validated['route_key'] ?? null;
        $targetType = $validated['target_type'] ?? null;
        $targetId = trim((string) ($validated['target_id'] ?? ''));
        if (strtolower($targetType ?? '') === 'none') {
            $targetType = null;
            $targetId = '';
        }
        $actionRoute = $this->payloadService->resolveActionRoute(
            $routeKey,
            $targetType,
            $targetId ?: null,
            $validated['action_route_override'] ?? null
        );

        $imageUrl = $this->resolveImageUrl($request, $validated);

        // Persist in-app notifications (shows in mobile app list).
        // Use bulk insert for performance when send_to_all (can be many users).
        $type = trim((string) ($validated['type'] ?? 'all'));
        if ($type === '') {
            $type = 'all';
        }
        $now = now();
        $important = $request->boolean('important');
        foreach (array_chunk($userIds, 500) as $chunk) {
            $rows = [];
            foreach ($chunk as $userId) {
                $rows[] = [
                    'user_id' => $userId,
                    'type' => $type,
                    'title' => $validated['title'],
                    'subtitle' => $validated['subtitle'] ?? null,
                    'image_url' => $imageUrl,
                    'read' => false,
                    'important' => $important,
                    'action_label' => $actionLabel,
                    'action_route' => $actionRoute,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('notifications')->insert($rows);
        }

        if ($request->boolean('send_fcm')) {
            $body = $validated['subtitle'] ?? $validated['title'];
            $meta = $this->payloadService->buildMeta(
                $routeKey,
                $targetType,
                $targetId ?: null,
                $actionLabel,
                $actionRoute
            );
            $this->dispatchService->sendBulk(
                $validated['title'],
                $body,
                $imageUrl,
                null,
                $meta !== [] ? $meta : null,
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
            'target_type' => $this->payloadService->targetTypeRules(),
            'target_id' => 'nullable|string|max:100',
            'route_key' => $this->payloadService->routeKeyRules(),
        ]);

        $targetType = $validated['target_type'] ?? null;
        $targetId = trim((string) ($validated['target_id'] ?? ''));
        if (strtolower($targetType ?? '') === 'none') {
            $targetType = null;
            $targetId = '';
        }
        $meta = $this->payloadService->buildMeta(
            $validated['route_key'] ?? null,
            $targetType,
            $targetId ?: null,
            null,
            null
        );
        $meta = $meta !== [] ? $meta : null;

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

    private function resolveActionLabel(?string $actionLabel, ?string $actionLabelCustom): ?string
    {
        if ($actionLabel === 'custom' && $actionLabelCustom !== null && trim($actionLabelCustom) !== '') {
            return trim($actionLabelCustom);
        }
        if ($actionLabel !== null && trim($actionLabel) !== '' && $actionLabel !== 'custom') {
            return trim($actionLabel);
        }
        return null;
    }

    private function resolveImageUrl(Request $request, array $validated): ?string
    {
        if ($request->hasFile('image') && $request->file('image')->isValid()) {
            $disk = config('notifications.image.disk', 'public');
            $dir = config('notifications.image.directory', 'notifications');
            $path = $request->file('image')->store($dir, $disk);
            if ($path) {
                return Storage::disk($disk)->url($path);
            }
        }
        return ! empty($validated['image_url']) ? $validated['image_url'] : null;
    }
}
