<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\WalletRefund;
use App\Services\Wallet\WalletRefundableAmountService;
use App\Services\Wallet\WalletRefundNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WalletRefundController extends Controller
{
    public function __construct(
        protected WalletRefundableAmountService $refundableAmounts,
        protected WalletRefundNotifier $notifier
    ) {}

    public function index(Request $request): JsonResponse
    {
        $rows = WalletRefund::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return response()->json([
            'wallet_refunds' => $rows->map(fn (WalletRefund $r) => $this->serialize($r)),
        ]);
    }

    public function show(Request $request, WalletRefund $walletRefund): JsonResponse
    {
        if ($walletRefund->user_id !== $request->user()->id) {
            abort(404);
        }

        return response()->json([
            'wallet_refund' => $this->serialize($walletRefund),
        ]);
    }

    public function refundable(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source_type' => 'required|string|in:order,shipment',
            'source_id' => 'required|integer|min:1',
        ]);

        $userId = $request->user()->id;
        $max = 0.0;

        if ($validated['source_type'] === 'order') {
            $order = Order::where('id', $validated['source_id'])->where('user_id', $userId)->first();
            if ($order === null) {
                return response()->json(['message' => 'Order not found.', 'max_refundable' => 0], 404);
            }
            $max = $this->refundableAmounts->maxRefundableForOrder($order);
        } else {
            $shipment = Shipment::where('id', $validated['source_id'])->where('user_id', $userId)->first();
            if ($shipment === null) {
                return response()->json(['message' => 'Shipment not found.', 'max_refundable' => 0], 404);
            }
            $max = $this->refundableAmounts->maxRefundableForShipment($shipment);
        }

        return response()->json([
            'max_refundable' => $max,
            'source_type' => $validated['source_type'],
            'source_id' => (int) $validated['source_id'],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source_type' => 'required|string|in:order,shipment',
            'source_id' => 'required|integer|min:1',
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string|min:3|max:5000',
        ]);

        $user = $request->user();
        $amount = round((float) $validated['amount'], 2);

        if ($validated['source_type'] === 'order') {
            $order = Order::where('id', $validated['source_id'])->where('user_id', $user->id)->first();
            if ($order === null) {
                return response()->json(['message' => 'Order not found.'], 404);
            }
            $max = $this->refundableAmounts->maxRefundableForOrder($order);
        } else {
            $shipment = Shipment::where('id', $validated['source_id'])->where('user_id', $user->id)->first();
            if ($shipment === null) {
                return response()->json(['message' => 'Shipment not found.'], 404);
            }
            $max = $this->refundableAmounts->maxRefundableForShipment($shipment);
        }

        if ($amount > $max + 0.00001) {
            return response()->json([
                'message' => 'Amount exceeds the refundable amount for this '.($validated['source_type'] === 'order' ? 'order' : 'shipment').'.',
                'error_code' => 'exceeds_refundable',
                'max_refundable' => $max,
            ], 422);
        }

        $req = DB::transaction(function () use ($user, $amount, $validated) {
            return WalletRefund::create([
                'user_id' => $user->id,
                'source_type' => $validated['source_type'],
                'source_id' => (int) $validated['source_id'],
                'amount' => $amount,
                'currency' => 'USD',
                'reason' => $validated['reason'],
                'status' => WalletRefund::STATUS_PENDING,
            ]);
        });

        $this->notifier->notifySubmitted($req->fresh());

        return response()->json([
            'message' => 'Refund to wallet requested.',
            'wallet_refund' => $this->serialize($req->fresh()),
        ], 201);
    }

    private function serialize(WalletRefund $r): array
    {
        return [
            'id' => (string) $r->id,
            'source_type' => $r->source_type,
            'source_id' => (string) $r->source_id,
            'amount' => round((float) $r->amount, 2),
            'currency' => $r->currency,
            'reason' => $r->reason,
            'status' => $r->status,
            'admin_notes' => $r->admin_notes,
            'created_at' => $r->created_at?->toIso8601String(),
            'reviewed_at' => $r->reviewed_at?->toIso8601String(),
        ];
    }
}
