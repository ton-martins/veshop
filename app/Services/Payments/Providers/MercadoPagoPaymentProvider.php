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
     * @return array{
     *   ok: bool,
     *   message: string,
     *   details: array<string, mixed>
     * }
     */
    public function testConnection(PaymentGateway $gateway): array
    {
        $response = $this->baseRequest($gateway)->get('/users/me');
        $body = (array) $response->json();

        if (! $response->successful()) {
            $status = $response->status();

            if (in_array($status, [401, 403], true)) {
                throw new PaymentProviderException(
                    'Access token do Mercado Pago inválido ou sem permissão.'
                );
            }

            $providerMessage = $this->resolveProviderErrorMessage($body);

            throw new PaymentProviderException(
                'Falha ao validar conexão com Mercado Pago: HTTP '.$status
                .($providerMessage !== '' ? ' - '.$providerMessage : '')
            );
        }

        return [
            'ok' => true,
            'message' => 'Conexao com Mercado Pago validada com sucesso.',
            'details' => [
                'provider' => $this->providerCode(),
                'account_id' => (string) ($body['id'] ?? ''),
                'nickname' => (string) ($body['nickname'] ?? ''),
                'email' => (string) ($body['email'] ?? ''),
            ],
        ];
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
        $dateOfExpiration = $this->formatDateOfExpiration($expiresAt);

        $payload = [
            'transaction_amount' => round((float) $salePayment->amount, 2),
            'description' => $description !== '' ? $description : 'Pedido '.$sale->code,
            'payment_method_id' => 'pix',
            'external_reference' => (string) $sale->code,
            'notification_url' => $notificationUrl !== '' ? $notificationUrl : null,
            'date_of_expiration' => $dateOfExpiration,
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
            $providerMessage = $this->resolveProviderErrorMessage($response->json());

            throw new PaymentProviderException(
                'Falha ao criar pagamento Pix no Mercado Pago: HTTP '.$response->status()
                .($providerMessage !== '' ? ' - '.$providerMessage : '')
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

        $pixPayload = $this->extractPixPayload($body);
        if (! $this->hasPixPayload($pixPayload)) {
            try {
                $latestPayment = $this->fetchPaymentById($gateway, $transactionReference);
                $latestPixPayload = $this->extractPixPayload($latestPayment);

                if ($this->hasPixPayload($latestPixPayload)) {
                    $body = $latestPayment;
                    $pixPayload = $latestPixPayload;
                }
            } catch (PaymentProviderException) {
                // Keep original payload when enrichment fails.
            }
        }

        $status = strtolower(trim((string) ($body['status'] ?? 'pending')));

        return [
            'provider' => $this->providerCode(),
            'transaction_reference' => $transactionReference,
            'status' => $status,
            'external_reference' => trim((string) ($body['external_reference'] ?? '')),
            'date_of_expiration' => data_get($body, 'date_of_expiration'),
            'qr_code' => $pixPayload['qr_code'],
            'qr_code_base64' => $pixPayload['qr_code_base64'],
            'ticket_url' => $pixPayload['ticket_url'],
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
            $providerMessage = $this->resolveProviderErrorMessage($response->json());

            throw new PaymentProviderException(
                'Falha ao consultar pagamento Pix no Mercado Pago: HTTP '.$response->status()
                .($providerMessage !== '' ? ' - '.$providerMessage : '')
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

    private function formatDateOfExpiration(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d\TH:i:s.vP');
        }

        try {
            return \Carbon\Carbon::parse((string) $value)->format('Y-m-d\TH:i:s.vP');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $payment
     * @return array{qr_code:string, qr_code_base64:string, ticket_url:string}
     */
    private function extractPixPayload(array $payment): array
    {
        $transactionData = data_get($payment, 'point_of_interaction.transaction_data');
        $transactionData = is_array($transactionData) ? $transactionData : [];

        $qrCode = trim((string) ($transactionData['qr_code'] ?? data_get($payment, 'point_of_interaction.transaction_data.qr_code', '')));
        $qrCodeBase64 = trim((string) ($transactionData['qr_code_base64'] ?? data_get($payment, 'point_of_interaction.transaction_data.qr_code_base64', '')));
        $ticketUrl = trim((string) (
            $transactionData['ticket_url']
            ?? data_get($payment, 'point_of_interaction.transaction_data.ticket_url')
            ?? data_get($payment, 'transaction_details.external_resource_url', '')
        ));

        if ($qrCodeBase64 !== '' && ! str_starts_with($qrCodeBase64, 'data:')) {
            $qrCodeBase64 = preg_replace('/\s+/', '', $qrCodeBase64) ?: $qrCodeBase64;
        }

        return [
            'qr_code' => $qrCode,
            'qr_code_base64' => $qrCodeBase64,
            'ticket_url' => $ticketUrl,
        ];
    }

    /**
     * @param  array{qr_code:string, qr_code_base64:string, ticket_url:string}  $payload
     */
    private function hasPixPayload(array $payload): bool
    {
        return trim($payload['qr_code']) !== ''
            || trim($payload['qr_code_base64']) !== ''
            || trim($payload['ticket_url']) !== '';
    }

    private function resolveProviderErrorMessage(mixed $body): string
    {
        if (! is_array($body)) {
            return '';
        }

        $message = trim((string) ($body['message'] ?? ''));
        $error = trim((string) ($body['error'] ?? ''));
        $causes = collect($body['cause'] ?? [])
            ->filter(static fn (mixed $item): bool => is_array($item))
            ->map(static function (array $item): string {
                $description = trim((string) ($item['description'] ?? ''));
                if ($description !== '') {
                    return $description;
                }

                $code = trim((string) ($item['code'] ?? ''));

                return $code !== '' ? 'cause_code='.$code : '';
            })
            ->filter()
            ->values()
            ->all();

        $parts = array_filter([
            $error !== '' ? strtoupper($error) : '',
            $message,
            ! empty($causes) ? implode('; ', $causes) : '',
        ], static fn (string $part): bool => $part !== '');

        $uniqueParts = [];
        foreach ($parts as $part) {
            $normalized = strtolower(trim($part));
            if ($normalized === '') {
                continue;
            }

            if (in_array($normalized, $uniqueParts, true)) {
                continue;
            }

            $uniqueParts[] = $normalized;
        }

        if (empty($uniqueParts)) {
            return '';
        }

        $originals = [];
        foreach ($parts as $part) {
            $normalized = strtolower(trim($part));
            if ($normalized === '' || ! in_array($normalized, $uniqueParts, true)) {
                continue;
            }

            $originals[$normalized] = $part;
        }

        return implode(' | ', array_values($originals));
    }
}
