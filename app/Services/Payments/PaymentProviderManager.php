<?php

namespace App\Services\Payments;

use App\Models\PaymentGateway;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Services\Payments\Contracts\PaymentProviderContract;
use App\Services\Payments\Exceptions\PaymentProviderException;
use App\Services\Payments\Providers\MercadoPagoPaymentProvider;

class PaymentProviderManager
{
    /**
     * @var array<string, PaymentProviderContract>
     */
    private array $providersByCode;

    public function __construct(
        MercadoPagoPaymentProvider $mercadoPagoProvider,
    ) {
        $this->providersByCode = [
            $mercadoPagoProvider->providerCode() => $mercadoPagoProvider,
        ];
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function createPaymentIntent(
        PaymentGateway $gateway,
        Sale $sale,
        SalePayment $salePayment,
        string $paymentMethodCode,
        array $context = []
    ): array {
        return $this->resolveProvider($gateway)->createPaymentIntent(
            $gateway,
            $sale,
            $salePayment,
            $paymentMethodCode,
            $context
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchPaymentIntent(
        PaymentGateway $gateway,
        string $transactionReference,
        ?string $paymentMethodCode = null
    ): array {
        return $this->resolveProvider($gateway)->fetchPaymentIntent($gateway, $transactionReference, $paymentMethodCode);
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function createPixPayment(
        PaymentGateway $gateway,
        Sale $sale,
        SalePayment $salePayment,
        array $context = []
    ): array {
        return $this->createPaymentIntent($gateway, $sale, $salePayment, PaymentMethod::CODE_PIX, $context);
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchPixPayment(PaymentGateway $gateway, string $transactionReference): array
    {
        return $this->fetchPaymentIntent($gateway, $transactionReference, PaymentMethod::CODE_PIX);
    }

    /**
     * @return array{
     *   ok: bool,
     *   message: string,
     *   details: array<string, mixed>
     * }
     */
    public function testGatewayConnection(PaymentGateway $gateway, ?string $paymentMethodCode = null): array
    {
        if ((string) $gateway->provider === PaymentGateway::PROVIDER_MANUAL) {
            return [
                'ok' => true,
                'message' => 'Gateway manual validado sem chamada externa.',
                'details' => [
                    'provider' => PaymentGateway::PROVIDER_MANUAL,
                ],
            ];
        }

        return $this->resolveProvider($gateway)->testConnection($gateway, $paymentMethodCode);
    }

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
    public function normalizeWebhookPayload(PaymentGateway $gateway, array $payload): array
    {
        return $this->resolveProvider($gateway)->normalizeWebhookPayload($gateway, $payload);
    }

    private function resolveProvider(PaymentGateway $gateway): PaymentProviderContract
    {
        $code = trim((string) $gateway->provider);
        $provider = $this->providersByCode[$code] ?? null;

        if (! $provider) {
            throw new PaymentProviderException(
                'Provider de pagamento nao suportado para integracao: '.$code
            );
        }

        return $provider;
    }
}
