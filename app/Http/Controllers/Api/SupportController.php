<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\SupportMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tickets = SupportTicket::where('user_id', $request->user()->id)->with('messages')->orderByDesc('created_at')->get();

        $items = $tickets->map(fn ($t) => [
            'id' => 'SUP-' . $t->id,
            'title' => 'SUP-' . $t->id,
            'subtitle' => $t->subject ?? 'Support',
            'status' => strtoupper(str_replace('_', ' ', $t->status)),
            'status_color' => match ($t->status) {
                'resolved' => 0xFF6B7280,
                'in_progress' => 0xFFF59E0B,
                default => 0xFF10B981,
            },
            'order_id' => $t->order_id ? 'Order #' . $t->order_id : null,
            'time_ago' => $t->created_at->diffForHumans(),
            'is_ticket' => true,
        ]);

        return response()->json($items);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $numericId = is_numeric($id) ? (int) $id : (int) preg_replace('/^SUP-/', '', $id);
        $ticket = SupportTicket::where('user_id', $request->user()->id)->with('messages')->findOrFail($numericId);

        return response()->json([
            'id' => (string) $ticket->id,
            'status' => strtoupper(str_replace('_', ' ', $ticket->status)),
            'avg_response_time' => $ticket->avg_response_time ?? '2h',
            'order_id' => $ticket->order_id ? 'ZX-' . $ticket->order_id : null,
            'events' => [['label' => 'Ticket Created', 'time' => $ticket->created_at->format('M j, g:i A')]],
            'messages' => $ticket->messages->map(fn ($m) => [
                'id' => (string) $m->id,
                'is_from_agent' => $m->is_from_agent,
                'sender_name' => $m->sender_name,
                'body' => $m->body,
                'timestamp' => $m->created_at->format('g:i A'),
                'image_url' => $m->image_url,
            ])->toArray(),
        ]);
    }

    public function storeMessage(Request $request, string $id): JsonResponse
    {
        $numericId = is_numeric($id) ? (int) $id : (int) preg_replace('/^SUP-/', '', $id);
        $ticket = SupportTicket::where('user_id', $request->user()->id)->findOrFail($numericId);

        $validated = $request->validate(['body' => 'required|string|max:2000']);

        $msg = SupportMessage::create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'is_from_agent' => false,
            'sender_name' => 'You',
            'body' => $validated['body'],
        ]);

        return response()->json($msg, 201);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => 'nullable|string',
            'issue_type' => 'required|string',
            'details' => 'required|string',
        ]);

        $orderId = null;
        if (!empty($validated['order_id'])) {
            $order = \App\Models\Order::where('user_id', $request->user()->id)->where('order_number', $validated['order_id'])->orWhere('id', $validated['order_id'])->first();
            $orderId = $order?->id;
        }

        $ticket = SupportTicket::create([
            'user_id' => $request->user()->id,
            'order_id' => $orderId,
            'issue_type' => $validated['issue_type'],
            'subject' => $validated['issue_type'] . ': ' . substr($validated['details'], 0, 50),
            'status' => 'open',
        ]);

        SupportMessage::create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'is_from_agent' => false,
            'sender_name' => 'You',
            'body' => $validated['details'],
        ]);

        return response()->json([
            'ticket_id' => 'SUP-' . $ticket->id,
            'message' => 'Ticket created',
        ], 201);
    }
}
