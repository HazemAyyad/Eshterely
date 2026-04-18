<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserActivity;
use App\Support\UserActivityAction;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserActivityAdminController extends Controller
{
    public function index(Request $request): View
    {
        $query = UserActivity::query()->with('user')->orderByDesc('created_at');

        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->input('user_id'));
        }
        if ($request->filled('action_type')) {
            $at = (string) $request->input('action_type');
            if (in_array($at, UserActivityAction::all(), true)) {
                $query->where('action_type', $at);
            }
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $activities = $query->paginate(50)->withQueryString();

        return view('admin.activity.index', [
            'activities' => $activities,
            'actionTypes' => UserActivityAction::all(),
            'filters' => $request->only(['user_id', 'action_type', 'date_from', 'date_to']),
        ]);
    }

    public function user(User $user): View
    {
        $activities = UserActivity::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate(50);

        return view('admin.activity.user', [
            'user' => $user,
            'activities' => $activities,
            'actionTypes' => UserActivityAction::all(),
        ]);
    }
}
