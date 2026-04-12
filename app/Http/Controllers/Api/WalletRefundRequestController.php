<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Models\WalletRefundRequest;
use App\Services\Wallet\WalletRefundRequestNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WalletRefundRequestController extends Controller
{
    public function __construct(
        protected WalletRefundRequestNotifier $notifier
    ) {}

    public function index(Request $request): JsonResponse
    {
        $rows = WalletRefundRequest::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return response()->json([
            'refund_requests' => $rows->map(fn (WalletRefundRequest $r) => $this->serialize($r)),
        ]);
    }

    public function show(Request $request, WalletRefundRequest $walletRefundRequest): JsonResponse
    {
        if ($walletRefundRequest->user_id !== $request->user()->id) {
            abort(404);
        }

        return response()->json([
            'refund_request' => $this->serialize($walletRefundRequest),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string|min:3|max:5000',
            'iban' => 'required|string|min:8|max:64',
            'bank_name' => 'required|string|max:255',
            'country' => 'required|string|max:255',
        ]);

        $user = $request->user();
        $amount = round((float) $validated['amount'], 2);

        $wallet = Wallet::firstOrCreate(
            ['user_id' => $user->id],
            ['available_balance' => 0, 'pending_balance' => 0, 'promo_balance' => 0]
        );

        $available = (float) $wallet->available_balance;
        if ($amount > $available + 0.00001) {
            return response()->json([
                'message' => 'Requested amount exceeds your available wallet balance.',
                'error_code' => 'amount_exceeds_balance',
            ], 422);
        }

        $reservedStatuses = [
            WalletRefundRequest::STATUS_PENDING,
            WalletRefundRequest::STATUS_UNDER_REVIEW,
            WalletRefundRequest::STATUS_APPROVED,
            WalletRefundRequest::STATUS_PROCESSED,
        ];

        $reserved = (float) WalletRefundRequest::query()
            ->where('user_id', $user->id)
            ->whereIn('status', $reservedStatuses)
            ->sum('amount');

        if ($amount + $reserved > $available + 0.00001) {
            return response()->json([
                'message' => 'You already have open refund requests. Total requested amount would exceed your available balance.',
                'error_code' => 'pending_requests_exceed_balance',
            ], 422);
        }

        $req = DB::transaction(function () use ($user, $amount, $validated) {
            return WalletRefundRequest::create([
                'user_id' => $user->id,
                'amount' => $amount,
                'currency' => 'USD',
                'reason' => $validated['reason'],
                'iban' => $validated['iban'],
                'bank_name' => $validated['bank_name'],
                'country' => $validated['country'],
                'status' => WalletRefundRequest::STATUS_PENDING,
            ]);
        });

        $this->notifier->notifySubmitted($req->fresh());

        return response()->json([
            'message' => 'Refund request submitted.',
            'refund_request' => $this->serialize($req->fresh()),
        ], 201);
    }

    private function serialize(WalletRefundRequest $r): array
    {
        return [
            'id' => (string) $r->id,
            'amount' => round((float) $r->amount, 2),
            'currency' => $r->currency,
            'reason' => $r->reason,
            'iban' => $r->iban,
            'bank_name' => $r->bank_name,
            'country' => $r->country,
            'status' => $r->status,
            'admin_notes' => $r->admin_notes,
            'created_at' => $r->created_at?->toIso8601String(),
            'reviewed_at' => $r->reviewed_at?->toIso8601String(),
            'processed_at' => $r->processed_at?->toIso8601String(),
            'transferred_at' => $r->transferred_at?->toIso8601String(),
        ];
    }
}
