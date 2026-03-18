<?php

namespace App\Services\Payments\Providers;

use App\Models\PaymentGateway;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Services\Payments\Contracts\PaymentProviderContract;
use App\Services\Payments\Exceptions\PaymentProviderException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class MercadoPagoPaymentProvider implements PaymentProviderContract
{
    public function providerCode(): string
    {
        return PaymentGateway::PROVIDER_MERCADO_PAGO;
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
        $idempotencyKey = trim((string) ($context['idempotency_key'] ?? ''));
        $notificationUrl = trim((string) ($context['notification_url'] ?? ''));
        $payerEmail = trim((string) ($context['payer_email'] ?? ''));
        $description = trim((string) ($context['description'] ?? 'Pedido '.$sale->code));
        $expiresAt = $context['expires_at'] ?? now()->addMinutes(30);

        $payload = [
            'transaction_amount' => round((float) $salePayment->amount, 2),
            'description' => $description !== '' ? $description : 'Pedido '.$sale->code,
            'payment_method_id' => 'pix',
            'external_reference' => (string) $sale->code,
            'notification_url' => $notificationUrl !== '' ? $notificationUrl : null,
            'date_of_expiration' => $expiresAt instanceof \DateTimeInterface
                ? $expiresAt->format(\DateTimeInterface::ATOM)
                : null,
            'payer' => [
                'email' => $payerEmail !== '' ? $payerEmail : $this->fallbackPayerEmail(),
            ],
            'metadata' => [
                'sale_code' => (string) $sale->code,
                'sale_payment_id' => (int) $salePayment->id,
                'contractor_id' => (int) $sale->contractor_id,
            ],
        ];

        $payload = array_filter($payload, static fn (mixed $value): bool => $value !== null);

        $request = $this->baseRequest($gateway);
        if ($idempotencyKey !== '') {
            $request = $request->withHeaders([
                'X-Idempotency-Key' => $idempotencyKey,
            ]);
        }

        $response = $request->post('/v1/payments', $payload);

        if (! $response->successful()) {
            throw new PaymentProviderException(
                'Falha ao criar pagamento Pix no Mercado Pago: HTTP '.$response->status()
            );
        }

        /** @var array<string, mixed> $body */
        $body = (array) $response->json();
        $transactionReference = trim((string) ($body['id'] ?? ''));
        if ($transactionReference === '') {
            throw new PaymentProviderException(
                'Mercado Pago retornou pagamento sem identificador de transacao.'
            );
        }

        $transactionData = (array) data_get($body, 'point_of_interaction.transaction_data', []);

        return [
            'provider' => $this->providerCode(),
            'transaction_reference' => $transactionReference,
            'status' => strtolower(trim((string) ($body['status'] ?? 'pending'))),
            'external_reference' => trim((string) ($body['external_reference'] ?? '')),
            'date_of_expiration' => data_get($body, 'date_of_expiration'),
            'qr_code' => (string) ($transactionData['qr_code'] ?? ''),
            'qr_code_base64' => (string) ($transactionData['qr_code_base64'] ?? ''),
            'ticket_url' => (string) ($transactionData['ticket_url'] ?? ''),
            'raw' => $body,
        ];
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
        $paymentId = $this->resolvePaymentIdFromWebhook($payload);
        if ($paymentId === '') {
            return [
                'status' => null,
                'transaction_reference' => null,
                'sale_code' => null,
                'event_id' => $this->resolveEventId($payload),
                'raw_payment' => null,
            ];
        }

        $payment = $this->fetchPaymentById($gateway, $paymentId);

        $saleCode = trim((string) ($payment['external_reference'] ?? ''));
        if ($saleCode === '') {
            $saleCode = trim((string) data_get($payment, 'metadata.sale_code', ''));
        }

        return [
            'status' => strtolower(trim((string) ($payment['status'] ?? ''))),
            'transaction_reference' => trim((string) ($payment['id'] ?? $paymentId)),
            'sale_code' => $saleCode !== '' ? $saleCode : null,
            'event_id' => $this->resolveEventId($payload),
            'raw_payment' => $payment,
        ];
    }

    private function resolvePaymentIdFromWebhook(array $payload): string
    {
        $value = $payload['data']['id']
            ?? $payload['id']
            ?? $payload['resource']['id']
            ?? null;

        return trim((string) ($value ?? ''));
    }

    private function resolveEventId(array $payload): ?string
    {
        $value = $payload['event_id']
            ?? $payload['id']
            ?? $payload['data']['id']
            ?? null;

        $eventId = trim((string) ($value ?? ''));

        return $eventId !== '' ? $eventId : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchPaymentById(PaymentGateway $gateway, string $paymentId): array
    {
        $response = $this->baseRequest($gateway)->get('/v1/payments/'.$paymentId);

        if (! $response->successful()) {
            throw new PaymentProviderException(
                'Falha ao consultar pagamento Pix no Mercado Pago: HTTP '.$response->status()
            );
        }

        /** @var array<string, mixed> $body */
        $body = (array) $response->json();

        return $body;
    }

    private function baseRequest(PaymentGateway $gateway): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())
            ->timeout($this->timeoutSeconds())
            ->acceptJson()
            ->withToken($this->resolveAccessToken($gateway))
            ->asJson();
    }

    private function resolveAccessToken(PaymentGateway $gateway): string
    {
        $credentials = is_array($gateway->credentials) ? $gateway->credentials : [];
        $token = trim((string) ($credentials['access_token'] ?? ''));

        if ($token === '') {
            throw new PaymentProviderException('Gateway Mercado Pago sem access token configurado.');
        }

        return $token;
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.mercadopago.base_url', 'https://api.mercadopago.com'), '/');
    }

    private function timeoutSeconds(): int
    {
        $timeout = (int) config('services.mercadopago.timeout', 15);

        return $timeout > 0 ? $timeout : 15;
    }

    private function fallbackPayerEmail(): string
    {
        $mailFrom = trim((string) config('mail.from.address', ''));

        return $mailFrom !== '' ? $mailFrom : 'contato@veshop.com.br';
    }
}
