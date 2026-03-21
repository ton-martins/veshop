<?php

namespace App\Support;

use App\Models\PaymentMethod;

final class PaymentFeeSnapshot
{
    /**
     * @return array{
     *   payment_method_id:int|null,
     *   payment_method_code:string|null,
     *   payment_method_name:string|null,
     *   fee_fixed:float,
     *   fee_percent:float
     * }
     */
    public static function fromPaymentMethod(?PaymentMethod $paymentMethod): array
    {
        return [
            'payment_method_id' => $paymentMethod ? (int) $paymentMethod->id : null,
            'payment_method_code' => $paymentMethod ? strtolower(trim((string) $paymentMethod->code)) : null,
            'payment_method_name' => $paymentMethod ? trim((string) $paymentMethod->name) : null,
            'fee_fixed' => self::normalizeMoney($paymentMethod ? (float) ($paymentMethod->fee_fixed ?? 0) : 0),
            'fee_percent' => self::normalizePercent($paymentMethod ? (float) ($paymentMethod->fee_percent ?? 0) : 0),
        ];
    }

    /**
     * @param array<string, mixed>|null $snapshot
     */
    public static function resolveFeeAmount(float $baseAmount, ?array $snapshot): float
    {
        if (! is_array($snapshot)) {
            return 0.0;
        }

        $safeBaseAmount = self::normalizeMoney($baseAmount);
        if ($safeBaseAmount <= 0) {
            return 0.0;
        }

        $feeFixed = self::normalizeMoney((float) ($snapshot['fee_fixed'] ?? 0));
        $feePercent = self::normalizePercent((float) ($snapshot['fee_percent'] ?? 0));

        if ($feeFixed <= 0 && $feePercent <= 0) {
            return 0.0;
        }

        $feePercentAmount = round($safeBaseAmount * ($feePercent / 100), 2);

        return self::normalizeMoney($feeFixed + $feePercentAmount);
    }

    public static function normalizeMoney(float $value): float
    {
        return round(max(0, $value), 2);
    }

    public static function normalizePercent(float $value): float
    {
        return round(min(max(0, $value), 100), 2);
    }
}
