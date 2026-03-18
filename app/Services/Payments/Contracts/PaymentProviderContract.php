<?php

namespace App\Services\Payments\Contracts;

use App\Models\PaymentGateway;
use App\Models\Sale;
use App\Models\SalePayment;

interface PaymentProviderContract
{
    public function providerCode(): string;

    /**
     * @return array{
     *   ok: bool,
     *   message: string,
     *   details: array<string, mixed>
     * }
     */
    public function testConnection(PaymentGateway $gateway): array;

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function createPixPayment(
        PaymentGateway $gateway,
        Sale $sale,
        SalePayment $salePayment,
        array $context = []
    ): array;

    /**
     * @param array<string, mixed> $payload
     * @return array{
     *   status: string|null,
     *   transaction_reference: string|null,
     *   sale_code: string|null,
     *   event_id: string|null,
     *   raw_payment: array<string, mixed>|null
     * }
     */
    public function normalizeWebhookPayload(PaymentGateway $gateway, array $payload): array;
}
