<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PromoCode;
use App\Models\PromoRedemption;
use App\Models\User;

class PromoCodeService
{
    /**
     * Validate a promo code against a checkout base amount.
     *
     * @return array{valid: bool, message: string, error_key: string|null, promo: ?PromoCode, discount_amount: float, code: string, base_amount: float}
     */
    public function evaluate(?string $rawCode, User $user, float $baseAmount, bool $lockForUpdate = false): array
    {
        $code = $this->normalizeCode($rawCode);
        $baseAmount = round(max(0, $baseAmount), 2);

        if ($code === '') {
            return $this->result(false, 'Promo code is required.', 'missing_code', null, 0.0, $code, $baseAmount);
        }

        $query = PromoCode::query()->whereRaw('UPPER(code) = ?', [$code]);
        if ($lockForUpdate) {
            $query->lockForUpdate();
        }
        $promo = $query->first();

        if (! $promo) {
            return $this->result(false, 'Promo code not found.', 'not_found', null, 0.0, $code, $baseAmount);
        }

        if (! $promo->is_active) {
            return $this->result(false, 'Promo code is disabled.', 'inactive', $promo, 0.0, $code, $baseAmount);
        }

        if ($promo->starts_at !== null && $promo->starts_at->isFuture()) {
            return $this->result(false, 'Promo code is not active yet.', 'not_started', $promo, 0.0, $code, $baseAmount);
        }

        if ($promo->ends_at !== null && $promo->ends_at->isPast()) {
            return $this->result(false, 'Promo code expired.', 'expired', $promo, 0.0, $code, $baseAmount);
        }

        if ($promo->min_order_amount !== null && $baseAmount < (float) $promo->min_order_amount) {
            return $this->result(false, 'Order total does not meet the minimum required amount.', 'min_amount', $promo, 0.0, $code, $baseAmount);
        }

        $globalUsage = PromoRedemption::query()
            ->where('promo_code_id', $promo->id)
            ->where('status', 'applied')
            ->count();
        if ($promo->max_usage_total !== null && $globalUsage >= (int) $promo->max_usage_total) {
            return $this->result(false, 'Promo code usage limit reached.', 'usage_limit', $promo, 0.0, $code, $baseAmount);
        }

        $userUsage = PromoRedemption::query()
            ->where('promo_code_id', $promo->id)
            ->where('user_id', $user->id)
            ->where('status', 'applied')
            ->count();
        if ($promo->max_usage_per_user !== null && $userUsage >= (int) $promo->max_usage_per_user) {
            return $this->result(false, 'You already used this promo code.', 'user_limit', $promo, 0.0, $code, $baseAmount);
        }

        $discountAmount = $this->calculateDiscount($promo, $baseAmount);
        if ($discountAmount <= 0) {
            return $this->result(false, 'Promo code does not apply to this order.', 'no_discount', $promo, 0.0, $code, $baseAmount);
        }

        return $this->result(true, 'Promo code applied.', null, $promo, $discountAmount, $code, $baseAmount);
    }

    public function calculateDiscount(PromoCode $promo, float $baseAmount): float
    {
        $baseAmount = round(max(0, $baseAmount), 2);
        $discountValue = (float) $promo->discount_value;

        $discount = match (strtolower((string) $promo->discount_type)) {
            'percent', 'percentage' => ($baseAmount * $discountValue) / 100.0,
            default => $discountValue,
        };

        if ($promo->max_discount_amount !== null) {
            $discount = min($discount, (float) $promo->max_discount_amount);
        }

        return round(min($discount, $baseAmount), 2);
    }

    /**
     * Persist a redemption record after the order is created.
     */
    public function recordRedemption(
        PromoCode $promo,
        User $user,
        Order $order,
        array $context = []
    ): PromoRedemption {
        return PromoRedemption::create([
            'promo_code_id' => $promo->id,
            'user_id' => $user->id,
            'order_id' => $order->id,
            'code_snapshot' => $promo->code,
            'discount_type' => $promo->discount_type,
            'discount_value' => $promo->discount_value,
            'subtotal_amount' => (float) ($context['subtotal_amount'] ?? 0),
            'shipping_amount' => (float) ($context['shipping_amount'] ?? 0),
            'total_before_amount' => (float) ($context['total_before_amount'] ?? 0),
            'discount_amount' => (float) ($context['discount_amount'] ?? 0),
            'wallet_applied_amount' => (float) ($context['wallet_applied_amount'] ?? 0),
            'total_after_amount' => (float) ($context['total_after_amount'] ?? 0),
            'status' => $context['status'] ?? 'applied',
            'metadata' => $context['metadata'] ?? null,
            'redeemed_at' => $context['redeemed_at'] ?? now(),
        ]);
    }

    /**
     * @return array{valid: bool, message: string, error_key: string|null, promo: ?PromoCode, discount_amount: float, code: string, base_amount: float}
     */
    private function result(
        bool $valid,
        string $message,
        ?string $errorKey,
        ?PromoCode $promo,
        float $discountAmount,
        string $code,
        float $baseAmount
    ): array {
        return [
            'valid' => $valid,
            'message' => $message,
            'error_key' => $errorKey,
            'promo' => $promo,
            'discount_amount' => round($discountAmount, 2),
            'code' => $code,
            'base_amount' => round($baseAmount, 2),
        ];
    }

    private function normalizeCode(?string $code): string
    {
        return strtoupper(trim((string) $code));
    }
}
