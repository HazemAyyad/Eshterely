<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $wallet = Wallet::firstOrCreate(
            ['user_id' => $request->user()->id],
            ['available_balance' => 0, 'pending_balance' => 0, 'promo_balance' => 0]
        );

        return response()->json([
            'available' => (float) $wallet->available_balance,
            'pending' => (float) $wallet->pending_balance,
            'promo' => (float) $wallet->promo_balance,
        ]);
    }

    public function transactions(Request $request): JsonResponse
    {
        $wallet = Wallet::firstOrCreate(
            ['user_id' => $request->user()->id],
            ['available_balance' => 0, 'pending_balance' => 0, 'promo_balance' => 0]
        );

        $type = $request->query('type', 'all');
        $query = $wallet->transactions();

        if ($type !== 'all') {
            $query->where('type', $type);
        }

        $txs = $query->orderByDesc('created_at')->limit(50)->get();

        return response()->json($txs->map(fn ($t) => [
            'id' => (string) $t->id,
            'type' => $t->type,
            'title' => $t->title,
            'date_time' => $t->created_at->format('M j, Y • H:i'),
            'amount' => ($t->amount >= 0 ? '+' : '-') . ' $' . number_format(abs($t->amount), 2),
            'subtitle' => $t->subtitle ?? '',
            'is_credit' => $t->amount >= 0,
        ]));
    }

    public function topUp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'payment_method' => 'nullable|string',
        ]);

        $wallet = Wallet::firstOrCreate(
            ['user_id' => $request->user()->id],
            ['available_balance' => 0, 'pending_balance' => 0, 'promo_balance' => 0]
        );

        $amount = $validated['amount'];
        $wallet->available_balance += $amount;
        $wallet->save();

        $wallet->transactions()->create([
            'type' => 'top_up',
            'title' => 'Top-up',
            'amount' => $amount,
            'subtitle' => 'COMPLETED',
        ]);

        return response()->json([
            'message' => 'Top-up successful',
            'available' => (float) $wallet->available_balance,
        ]);
    }
}
