<?php

namespace App\Services\Users;

use App\Models\CustomerCodeSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CustomerCodeService
{
    public function assignNextCode(User $user): void
    {
        if (trim((string) $user->customer_code) !== '') {
            return;
        }

        DB::transaction(function () use ($user) {
            $user->refresh();
            if (trim((string) $user->customer_code) !== '') {
                return;
            }

            /** @var CustomerCodeSetting $setting */
            $setting = CustomerCodeSetting::query()->lockForUpdate()->orderBy('id')->first()
                ?? CustomerCodeSetting::query()->create([
                    'prefix' => 'ESH',
                    'numeric_padding' => 5,
                ]);

            $prefix = strtoupper(trim($setting->prefix));
            if ($prefix === '') {
                $prefix = 'ESH';
            }
            $padding = max(1, min(12, (int) $setting->numeric_padding));

            $attempts = 0;
            do {
                $attempts++;
                $next = $this->nextSequenceNumber($prefix);
                $code = $prefix.str_pad((string) $next, $padding, '0', STR_PAD_LEFT);
                try {
                    $user->forceFill(['customer_code' => $code])->save();

                    return;
                } catch (\Illuminate\Database\QueryException $e) {
                    if ($attempts > 25 || ! $this->isUniqueViolation($e)) {
                        throw $e;
                    }
                }
            } while ($attempts < 30);
        });
    }

    private function isUniqueViolation(\Illuminate\Database\QueryException $e): bool
    {
        $msg = $e->getMessage();

        return str_contains($msg, 'UNIQUE constraint') || str_contains($msg, 'Duplicate entry');
    }

    private function nextSequenceNumber(string $prefix): int
    {
        $max = 0;
        $codes = User::query()
            ->whereNotNull('customer_code')
            ->where('customer_code', 'like', $prefix.'%')
            ->pluck('customer_code');
        foreach ($codes as $cc) {
            $suffix = substr((string) $cc, strlen($prefix));
            if (preg_match('/^\d+$/', $suffix)) {
                $max = max($max, (int) $suffix);
            }
        }

        return $max + 1;
    }
}
