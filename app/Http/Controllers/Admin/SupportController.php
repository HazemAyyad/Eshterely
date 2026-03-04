<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class SupportController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.support.index');
    }

    public function data(Request $request)
    {
        $query = SupportTicket::with('user')->orderBy('updated_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return DataTables::eloquent($query)
            ->addColumn('user_contact', fn (SupportTicket $t) => $t->user?->phone ?? $t->user?->email ?? '-')
            ->editColumn('issue_type', fn (SupportTicket $t) => $t->issue_type ?? '-')
            ->editColumn('subject', fn (SupportTicket $t) => \Str::limit($t->subject ?? '-', 40))
            ->editColumn('status', fn (SupportTicket $t) => '<span class="badge bg-' . ($t->status === 'resolved' ? 'success' : ($t->status === 'in_progress' ? 'warning' : 'primary')) . '">' . $t->status . '</span>')
            ->editColumn('created_at', fn (SupportTicket $t) => $t->created_at?->format('Y-m-d'))
            ->addColumn('actions', fn (SupportTicket $t) => '<a href="' . route('admin.support.show', $t) . '" class="btn btn-text-secondary rounded-pill waves-effect btn-icon" title="' . __('admin.show') . '"><i class="icon-base ti tabler-eye icon-22px"></i></a>')
            ->rawColumns(['status', 'actions'])
            ->toJson();
    }

    public function show(SupportTicket $ticket): View
    {
        $ticket->load(['user', 'order', 'messages']);

        return view('admin.support.show', compact('ticket'));
    }

    public function storeMessage(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $validated = $request->validate([
            'body' => 'required|string|max:2000',
        ]);

        SupportMessage::create([
            'support_ticket_id' => $ticket->id,
            'user_id' => null,
            'is_from_agent' => true,
            'sender_name' => auth()->guard('admin')->user()->email ?? 'Support',
            'body' => $validated['body'],
        ]);

        $ticket->touch();

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('admin.success')]);
        }

        return redirect()->route('admin.support.show', $ticket)->with('success', __('admin.success'));
    }

    public function updateStatus(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:open,in_progress,resolved',
        ]);

        $ticket->update($validated);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('admin.success')]);
        }

        return redirect()->route('admin.support.show', $ticket)->with('success', __('admin.success'));
    }
}
