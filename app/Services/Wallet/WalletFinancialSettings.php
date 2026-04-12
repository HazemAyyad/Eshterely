<?php

namespace App\Services\Wallet;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WalletFinancialSettings
{
    public function withdrawalFeePercent(): float
    {
        if (! Schema::hasTable('payment_gateway_settings')) {
            return 0.0;
        }
        $row = DB::table('payment_gateway_settings')->first();
        if ($row === null) {
            return 0.0;
        }
        if (! isset($row->refund_fee_percent)) {
            return 0.0;
        }

        return max(0, (float) $row->refund_fee_percent);
    }

    /**
     * @return array{fee_percent: float, fee_amount: float, net_amount: float}
     */
    public function withdrawalQuote(float $requestedAmount): array
    {
        $requestedAmount = round(max(0, $requestedAmount), 2);
        $feePercent = $this->withdrawalFeePercent();
        $feeAmount = round($requestedAmount * ($feePercent / 100), 2);
        $netAmount = round(max(0, $requestedAmount - $feeAmount), 2);

        return [
            'fee_percent' => $feePercent,
            'fee_amount' => $feeAmount,
            'net_amount' => $netAmount,
        ];
    }
}
