<?php

namespace App\Services\Payments;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Single source for checkout_payment_mode rules (wallet / gateway / both),
 * shared by cart checkout, shipment pay, and order start-payment.
 */
class CheckoutPaymentModeService
{
    public function getMode(): string
    {
        if (! Schema::hasTable('payment_gateway_settings')) {
            return 'gateway_only';
        }
        if (! Schema::hasColumn('payment_gateway_settings', 'checkout_payment_mode')) {
            return 'gateway_only';
        }
        $row = DB::table('payment_gateway_settings')->first();
        if ($row === null) {
            return 'gateway_only';
        }
        if (! isset($row->checkout_payment_mode)) {
            return 'gateway_only';
        }
        $m = strtolower(trim((string) $row->checkout_payment_mode));
        $allowed = ['wallet_only', 'gateway_only', 'wallet_and_gateway'];

        return in_array($m, $allowed, true) ? $m : 'gateway_only';
    }

    /**
     * Same shape as CheckoutController::buildCheckoutPaymentModePayload (review API).
     *
     * @return array<string, mixed>
     */
    public function buildModePayload(float $payableTotal, float $walletBalance): array
    {
        $payable = round(max(0, $payableTotal), 2);
        $walletBalance = round(max(0, $walletBalance), 2);
        $shortfall = round(max(0, $payable - $walletBalance), 2);
        $mode = $this->getMode();
        $walletEnabled = in_array($mode, ['wallet_only', 'wallet_and_gateway'], true);
        $gatewayEnabled = in_array($mode, ['gateway_only', 'wallet_and_gateway'], true);
        $allowed = [];
        if ($walletEnabled) {
            $allowed[] = 'wallet';
        }
        if ($gatewayEnabled) {
            $allowed[] = 'gateway';
        }
        $walletCanPayNow = $payable <= 0.00001 || $walletBalance + 0.00001 >= $payable;
        $topUpRequired = $mode === 'wallet_only' && ! $walletCanPayNow && $payable > 0.00001;

        return [
            'checkout_payment_mode' => $mode,
            'wallet_enabled_for_checkout' => $walletEnabled,
            'gateway_enabled_for_checkout' => $gatewayEnabled,
            'allowed_payment_methods' => $allowed,
            'wallet_shortfall' => $shortfall,
            'wallet_can_pay_now' => $walletCanPayNow,
            'top_up_required' => $topUpRequired,
            'required_top_up_amount' => $shortfall > 0.00001 ? $shortfall : 0.0,
            'suggested_top_up_amount' => $shortfall > 0.00001 ? $shortfall : 0.0,
        ];
    }

    /**
     * Validates explicit payment_method against configured checkout mode (shipment pay pattern).
     */
    public function validatePaymentMethodForMode(string $paymentMethod): ?string
    {
        $mode = $this->getMode();
        if ($mode === 'wallet_only' && $paymentMethod !== 'wallet') {
            return 'Checkout is configured for wallet payment only.';
        }
        if ($mode === 'gateway_only' && $paymentMethod !== 'gateway') {
            return 'Checkout is configured for card payment only.';
        }

        return null;
    }

    /**
     * Resolves wallet vs gateway amounts for checkout confirm (cart checkout).
     *
     * @return array<string, float|string|bool|null>
     */
    public function resolveCheckoutPaymentAmounts(Request $request, string $mode, float $totalAfterPromo, float $walletBalance): array
    {
        $totalAfterPromo = round(max(0, $totalAfterPromo), 2);
        $walletBalance = round($walletBalance, 2);
        $explicit = $request->input('payment_method');
        if (is_string($explicit) && trim($explicit) !== '') {
            $m = strtolower(trim($explicit));
            if (! in_array($m, ['wallet', 'gateway'], true)) {
                return ['error' => 'invalid_payment_method', 'message' => 'Invalid payment_method. Use wallet or gateway.'];
            }
            if ($mode === 'gateway_only' && $m === 'wallet') {
                return ['error' => 'wallet_not_allowed', 'message' => 'Wallet checkout is disabled.'];
            }
            if ($mode === 'wallet_only' && $m === 'gateway') {
                return ['error' => 'gateway_not_allowed', 'message' => 'Card checkout is disabled for this store.'];
            }
            if ($m === 'wallet') {
                $shortfall = round(max(0, $totalAfterPromo - $walletBalance), 2);
                if ($shortfall > 0.00001) {
                    return [
                        'error' => 'insufficient_wallet_balance',
                        'wallet_balance' => $walletBalance,
                        'payable_now_total' => $totalAfterPromo,
                        'required_top_up_amount' => $shortfall,
                        'suggested_top_up_amount' => $shortfall,
                    ];
                }

                return ['wallet_applied' => $totalAfterPromo, 'amount_due_now' => 0.0];
            }

            return ['wallet_applied' => 0.0, 'amount_due_now' => $totalAfterPromo];
        }

        if ($mode === 'gateway_only') {
            return ['wallet_applied' => 0.0, 'amount_due_now' => $totalAfterPromo];
        }
        if ($mode === 'wallet_only') {
            $shortfall = round(max(0, $totalAfterPromo - $walletBalance), 2);
            if ($shortfall > 0.00001) {
                return [
                    'error' => 'insufficient_wallet_balance',
                    'wallet_balance' => $walletBalance,
                    'payable_now_total' => $totalAfterPromo,
                    'required_top_up_amount' => $shortfall,
                    'suggested_top_up_amount' => $shortfall,
                ];
            }

            return ['wallet_applied' => $totalAfterPromo, 'amount_due_now' => 0.0];
        }

        $useWallet = $request->boolean('use_wallet_balance', true);
        $walletApplied = $useWallet ? round(min($walletBalance, $totalAfterPromo), 2) : 0.0;
        $amountDueNow = round(max(0, $totalAfterPromo - $walletApplied), 2);

        return ['wallet_applied' => $walletApplied, 'amount_due_now' => $amountDueNow];
    }
}
