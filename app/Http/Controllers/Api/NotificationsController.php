<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $type = $request->query('type', 'all');
        $query = Notification::where('user_id', $request->user()->id);

        if ($type !== 'all') {
            $query->where('type', $type);
        }

        $items = $query->orderByDesc('created_at')->limit(50)->get();

        return response()->json($items->map(fn ($n) => [
            'id' => (string) $n->id,
            'type' => $n->type,
            'title' => $n->title,
            'subtitle' => $n->subtitle,
            'image_url' => $n->image_url,
            'time_ago' => $n->created_at->diffForHumans(),
            'read' => $n->read,
            'important' => $n->important,
            'action_label' => $n->action_label,
            'action_route' => $n->action_route,
        ]));
    }

    public function markRead(Request $request, int $id): JsonResponse
    {
        Notification::where('user_id', $request->user()->id)->where('id', $id)->update(['read' => true]);

        return response()->json(['message' => 'Updated']);
    }
}
