<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Models\WalletWithdrawal;
use App\Services\Wallet\WalletFinancialSettings;
use App\Services\Wallet\WalletWithdrawalNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class WalletWithdrawalController extends Controller
{
    public function __construct(
        protected WalletFinancialSettings $financialSettings,
        protected WalletWithdrawalNotifier $notifier
    ) {}

    public function quote(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
        ]);
        $amount = round((float) $validated['amount'], 2);
        $q = $this->financialSettings->withdrawalQuote($amount);

        return response()->json([
            'requested_amount' => $amount,
            'fee_percent' => $q['fee_percent'],
            'fee_amount' => $q['fee_amount'],
            'net_amount' => $q['net_amount'],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $rows = WalletWithdrawal::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return response()->json([
            'wallet_withdrawals' => $rows->map(fn (WalletWithdrawal $w) => $this->serialize($w, true)),
        ]);
    }

    public function show(Request $request, WalletWithdrawal $walletWithdrawal): JsonResponse
    {
        if ($walletWithdrawal->user_id !== $request->user()->id) {
            abort(404);
        }

        return response()->json([
            'wallet_withdrawal' => $this->serialize($walletWithdrawal, true),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'iban' => 'required|string|min:8|max:64',
            'bank_name' => 'required|string|max:255',
            'country' => 'required|string|max:255',
            'note' => 'nullable|string|max:2000',
        ]);

        $user = $request->user();
        $amount = round((float) $validated['amount'], 2);
        $quote = $this->financialSettings->withdrawalQuote($amount);

        $wallet = Wallet::firstOrCreate(
            ['user_id' => $user->id],
            ['available_balance' => 0, 'pending_balance' => 0, 'promo_balance' => 0]
        );

        $available = (float) $wallet->available_balance;
        $reserved = WalletWithdrawal::reservedAmountForUser((int) $user->id);

        if ($amount + $reserved > $available + 0.00001) {
            return response()->json([
                'message' => 'Insufficient wallet balance for this withdrawal (including pending requests).',
                'error_code' => 'insufficient_balance',
                'available' => round($available, 2),
                'reserved' => round($reserved, 2),
            ], 422);
        }

        $w = DB::transaction(function () use ($user, $amount, $quote, $validated) {
            return WalletWithdrawal::create([
                'user_id' => $user->id,
                'amount' => $amount,
                'fee_percent' => $quote['fee_percent'],
                'fee_amount' => $quote['fee_amount'],
                'net_amount' => $quote['net_amount'],
                'iban' => $validated['iban'],
                'bank_name' => $validated['bank_name'],
                'country' => $validated['country'],
                'note' => $validated['note'] ?? null,
                'status' => WalletWithdrawal::STATUS_PENDING,
            ]);
        });

        $this->notifier->notifySubmitted($w->fresh());

        return response()->json([
            'message' => 'Withdrawal request submitted.',
            'wallet_withdrawal' => $this->serialize($w->fresh(), true),
        ], 201);
    }

    private function serialize(WalletWithdrawal $w, bool $forUser): array
    {
        $proofUrl = '';
        if ($forUser && ! empty($w->transfer_proof)) {
            $proofUrl = str_starts_with((string) $w->transfer_proof, 'http')
                ? (string) $w->transfer_proof
                : Storage::disk('public')->url($w->transfer_proof);
        }

        return [
            'id' => (string) $w->id,
            'amount' => round((float) $w->amount, 2),
            'fee_percent' => round((float) $w->fee_percent, 4),
            'fee_amount' => round((float) $w->fee_amount, 2),
            'net_amount' => round((float) $w->net_amount, 2),
            'iban' => $w->iban,
            'bank_name' => $w->bank_name,
            'country' => $w->country,
            'note' => $w->note,
            'status' => $w->status,
            'admin_notes' => $w->admin_notes,
            'transfer_proof_url' => $proofUrl,
            'created_at' => $w->created_at?->toIso8601String(),
            'reviewed_at' => $w->reviewed_at?->toIso8601String(),
            'transferred_at' => $w->transferred_at?->toIso8601String(),
        ];
    }
}
