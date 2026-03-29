<?php

namespace App\Services\Payments\Providers;

use App\Models\PaymentGateway;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Services\Payments\Contracts\PaymentProviderContract;
use App\Services\Payments\Exceptions\PaymentProviderException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

class MercadoPagoPaymentProvider implements PaymentProviderContract
{
    public function providerCode(): string
    {
        return PaymentGateway::PROVIDER_MERCADO_PAGO;
    }

    public function testConnection(PaymentGateway $gateway, ?string $paymentMethodCode = null): array
    {
        $response = $this->sendWithGateway($gateway, fn (PendingRequest $request): Response => $request->get('/users/me'));
        $body = (array) $response->json();

        if (! $response->successful()) {
            $status = $response->status();
            if (in_array($status, [401, 403], true)) {
                throw new PaymentProviderException('Conexão OAuth do Mercado Pago inválida ou sem permissão. Conecte novamente a conta.');
            }

            throw new PaymentProviderException(
                'Falha ao validar conexão com Mercado Pago: HTTP '.$status.$this->resolveProviderMessageSuffix($body)
            );
        }

        $details = [
            'provider' => $this->providerCode(),
            'account_id' => (string) ($body['id'] ?? ''),
            'nickname' => (string) ($body['nickname'] ?? ''),
            'email' => (string) ($body['email'] ?? ''),
        ];

        $methodCode = $this->normalizeMethodCode((string) ($paymentMethodCode ?? ''));
        $message = 'Conexão com Mercado Pago validada com sucesso.';
        if ($methodCode !== '') {
            $details['payment_method'] = $this->validatePaymentMethodAvailability($gateway, $methodCode);
            $message = 'Conexão com Mercado Pago validada para '.$this->resolveMethodLabel($methodCode).'.';
        }

        return ['ok' => true, 'message' => $message, 'details' => $details];
    }

    public function createPaymentIntent(
        PaymentGateway $gateway,
        Sale $sale,
        SalePayment $salePayment,
        string $paymentMethodCode,
        array $context = []
    ): array {
        $methodCode = $this->normalizeMethodCode($paymentMethodCode);

        return match ($methodCode) {
            PaymentMethod::CODE_PIX => $this->createPixIntent($gateway, $sale, $salePayment, $context),
            PaymentMethod::CODE_BOLETO,
            PaymentMethod::CODE_CREDIT_CARD,
            PaymentMethod::CODE_DEBIT_CARD => $this->createPreferenceIntent(
                $gateway,
                $sale,
                $salePayment,
                $methodCode,
                $context
            ),
            default => throw new PaymentProviderException('Forma de pagamento integrada não suportada no Mercado Pago.'),
        };
    }

    public function createPixPayment(
        PaymentGateway $gateway,
        Sale $sale,
        SalePayment $salePayment,
        array $context = []
    ): array {
        return $this->createPaymentIntent($gateway, $sale, $salePayment, PaymentMethod::CODE_PIX, $context);
    }

    public function fetchPaymentIntent(PaymentGateway $gateway, string $transactionReference, ?string $paymentMethodCode = null): array
    {
        $reference = trim($transactionReference);
        if ($reference === '') {
            throw new PaymentProviderException('Referência da transação não informada.');
        }

        $methodCode = $this->normalizeMethodCode((string) ($paymentMethodCode ?? ''));

        if ($methodCode === PaymentMethod::CODE_PIX || $methodCode === '') {
            $payment = $this->tryFetchPaymentById($gateway, $reference);
            if (is_array($payment)) {
                return $this->normalizeIntentFromPayment($payment, $reference, PaymentMethod::CODE_PIX);
            }

            $order = $this->tryFetchOrderById($gateway, $reference);
            if (is_array($order)) {
                return $this->normalizeIntentFromOrder($order, $reference, PaymentMethod::CODE_PIX);
            }

            if ($methodCode === PaymentMethod::CODE_PIX) {
                throw new PaymentProviderException('Não foi possível consultar a cobrança Pix no Mercado Pago.');
            }
        }

        $order = $this->tryFetchOrderById($gateway, $reference);
        if (is_array($order)) {
            return $this->normalizeIntentFromOrder($order, $reference, $methodCode !== '' ? $methodCode : PaymentMethod::CODE_CREDIT_CARD);
        }

        $payment = $this->tryFetchPaymentById($gateway, $reference);
        if (is_array($payment)) {
            return $this->normalizeIntentFromPayment($payment, $reference, $methodCode !== '' ? $methodCode : PaymentMethod::CODE_CREDIT_CARD);
        }

        throw new PaymentProviderException('Não foi possível consultar o pagamento no Mercado Pago.');
    }

    public function fetchPixPayment(PaymentGateway $gateway, string $transactionReference): array
    {
        return $this->fetchPaymentIntent($gateway, $transactionReference, PaymentMethod::CODE_PIX);
    }

    public function normalizeWebhookPayload(PaymentGateway $gateway, array $payload): array
    {
        $eventType = strtolower(trim((string) ($payload['type'] ?? $payload['topic'] ?? '')));
        $resourceId = trim((string) ($payload['data']['id'] ?? $payload['id'] ?? $payload['resource']['id'] ?? ''));
        $eventId = $this->resolveEventId($payload);

        if ($resourceId === '') {
            return [
                'status' => null,
                'transaction_reference' => null,
                'sale_code' => null,
                'event_id' => $eventId,
                'raw_payment' => null,
            ];
        }

        $isOrderEvent = str_contains($eventType, 'order')
            || str_starts_with(strtolower((string) ($payload['action'] ?? '')), 'order.');

        $order = null;
        $payment = null;

        if ($isOrderEvent) {
            $order = $this->tryFetchOrderById($gateway, $resourceId);
            if (! is_array($order)) {
                $payment = $this->tryFetchPaymentById($gateway, $resourceId);
            }
        } else {
            $payment = $this->fetchPaymentById($gateway, $resourceId);
            $orderId = trim((string) Arr::get($payment, 'order.id', ''));
            if ($orderId !== '') {
                $order = $this->tryFetchOrderById($gateway, $orderId);
            }
        }

        if (is_array($order)) {
            return [
                'status' => $this->resolveOrderStatus($order),
                'transaction_reference' => trim((string) ($order['id'] ?? $resourceId)),
                'sale_code' => $this->resolveSaleCodeFromPayload($order),
                'event_id' => $eventId,
                'raw_payment' => $order,
            ];
        }

        if (! is_array($payment)) {
            return [
                'status' => null,
                'transaction_reference' => $resourceId,
                'sale_code' => null,
                'event_id' => $eventId,
                'raw_payment' => null,
            ];
        }

        $saleCode = trim((string) ($payment['external_reference'] ?? Arr::get($payment, 'metadata.sale_code', '')));

        return [
            'status' => strtolower(trim((string) ($payment['status'] ?? ''))),
            'transaction_reference' => trim((string) ($payment['id'] ?? $resourceId)),
            'sale_code' => $saleCode !== '' ? $saleCode : null,
            'event_id' => $eventId,
            'raw_payment' => $payment,
        ];
    }

    private function createPixIntent(PaymentGateway $gateway, Sale $sale, SalePayment $salePayment, array $context): array
    {
        $idempotencyKey = trim((string) ($context['idempotency_key'] ?? ''));
        $payload = $this->arrayFilterRecursive([
            'transaction_amount' => round((float) $salePayment->amount, 2),
            'payment_method_id' => 'pix',
            'description' => trim((string) ($context['description'] ?? 'Pedido '.$sale->code)),
            'external_reference' => (string) $sale->code,
            'notification_url' => trim((string) ($context['notification_url'] ?? '')) ?: null,
            'date_of_expiration' => $this->formatDateOfExpiration($context['expires_at'] ?? now()->addMinutes(30)),
            'payer' => [
                'email' => trim((string) ($context['payer_email'] ?? '')) ?: $this->fallbackPayerEmail(),
            ],
            'metadata' => [
                'sale_code' => (string) $sale->code,
                'sale_payment_id' => (int) $salePayment->id,
                'contractor_id' => (int) $sale->contractor_id,
            ],
        ]);

        $response = $this->sendWithGateway($gateway, function (PendingRequest $request) use ($payload, $idempotencyKey): Response {
            if ($idempotencyKey !== '') {
                $request = $request->withHeaders(['X-Idempotency-Key' => $idempotencyKey]);
            }

            return $request->post('/v1/payments', $payload);
        });

        if (! $response->successful()) {
            $body = (array) $response->json();
            throw new PaymentProviderException(
                'Falha ao criar cobrança Pix no Mercado Pago: HTTP '.$response->status().$this->resolveProviderMessageSuffix($body)
            );
        }

        $body = (array) $response->json();
        $reference = trim((string) ($body['id'] ?? ''));
        if ($reference === '') {
            throw new PaymentProviderException('Mercado Pago retornou cobrança Pix sem identificador de transação.');
        }

        $intent = $this->normalizeIntentFromPayment($body, $reference, PaymentMethod::CODE_PIX);

        if ($this->hasPixPayload([
            'qr_code' => (string) ($intent['qr_code'] ?? ''),
            'qr_code_base64' => (string) ($intent['qr_code_base64'] ?? ''),
            'ticket_url' => (string) ($intent['ticket_url'] ?? ''),
        ])) {
            return $intent;
        }

        $latest = $this->tryFetchPaymentById($gateway, $reference);

        return is_array($latest)
            ? $this->normalizeIntentFromPayment($latest, $reference, PaymentMethod::CODE_PIX)
            : $intent;
    }

    private function createPreferenceIntent(
        PaymentGateway $gateway,
        Sale $sale,
        SalePayment $salePayment,
        string $paymentMethodCode,
        array $context
    ): array {
        $idempotencyKey = trim((string) ($context['idempotency_key'] ?? ''));
        $payload = $this->arrayFilterRecursive([
            'items' => $this->resolvePreferenceItems(
                $sale,
                $salePayment,
                trim((string) ($context['description'] ?? 'Pedido '.$sale->code))
            ),
            'payer' => [
                'email' => trim((string) ($context['payer_email'] ?? '')) ?: $this->fallbackPayerEmail(),
            ],
            'external_reference' => (string) $sale->code,
            'notification_url' => trim((string) ($context['notification_url'] ?? '')) ?: null,
            'metadata' => [
                'sale_code' => (string) $sale->code,
                'sale_payment_id' => (int) $salePayment->id,
                'contractor_id' => (int) $sale->contractor_id,
                'payment_method_code' => $paymentMethodCode,
            ],
            'payment_methods' => $this->resolvePreferencePaymentFilters($paymentMethodCode, $context),
            'auto_return' => 'approved',
        ]);

        $response = $this->sendWithGateway($gateway, function (PendingRequest $request) use ($payload, $idempotencyKey): Response {
            if ($idempotencyKey !== '') {
                $request = $request->withHeaders(['X-Idempotency-Key' => $idempotencyKey]);
            }

            return $request->post('/checkout/preferences', $payload);
        });

        if (! $response->successful()) {
            $body = (array) $response->json();
            throw new PaymentProviderException(
                'Falha ao criar preferência de checkout no Mercado Pago: HTTP '.$response->status().$this->resolveProviderMessageSuffix($body)
            );
        }

        $body = (array) $response->json();
        $preferenceId = trim((string) ($body['id'] ?? ''));
        $checkoutUrl = trim((string) (
            ($gateway->is_sandbox ? ($body['sandbox_init_point'] ?? '') : ($body['init_point'] ?? ''))
            ?: ($body['init_point'] ?? '')
            ?: ($body['sandbox_init_point'] ?? '')
        ));

        if ($preferenceId === '' || $checkoutUrl === '') {
            throw new PaymentProviderException('Mercado Pago retornou preferência sem link de checkout válido.');
        }

        return [
            'provider' => $this->providerCode(),
            'payment_method_code' => $paymentMethodCode,
            'payment_method_type' => $this->resolveMethodType($paymentMethodCode),
            'flow' => 'checkout_preference',
            'transaction_reference' => $preferenceId,
            'status' => 'pending',
            'external_reference' => (string) ($body['external_reference'] ?? $sale->code),
            'date_of_expiration' => null,
            'qr_code' => '',
            'qr_code_base64' => '',
            'ticket_url' => '',
            'checkout_url' => $checkoutUrl,
            'raw' => $body,
        ];
    }

    private function resolvePreferenceItems(Sale $sale, SalePayment $salePayment, string $fallbackTitle): array
    {
        $items = $sale->relationLoaded('items')
            ? $sale->items
            : $sale->items()->orderBy('id')->limit(50)->get();

        $normalized = $items
            ->map(static function (mixed $item): ?array {
                if (! $item instanceof \App\Models\SaleItem) {
                    return null;
                }

                $quantity = max(1, (int) $item->quantity);
                $unitPrice = round((float) $item->unit_price, 2);
                if ($unitPrice <= 0) {
                    return null;
                }

                $title = trim((string) ($item->description ?? ''));
                $title = $title !== '' ? $title : 'Item do pedido';

                return [
                    'title' => mb_substr($title, 0, 120),
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'currency_id' => 'BRL',
                ];
            })
            ->filter(static fn (mixed $item): bool => is_array($item))
            ->values()
            ->all();

        if ($normalized !== []) {
            return $normalized;
        }

        $title = trim($fallbackTitle);
        $title = $title !== '' ? $title : 'Pedido '.$sale->code;

        return [[
            'title' => mb_substr($title, 0, 120),
            'quantity' => 1,
            'unit_price' => round((float) $salePayment->amount, 2),
            'currency_id' => 'BRL',
        ]];
    }

    private function resolvePreferencePaymentFilters(string $methodCode, array $context): array
    {
        $allowedType = $this->resolveMethodType($methodCode);
        if ($allowedType === '') {
            return [];
        }

        $allTypes = ['account_money', 'credit_card', 'debit_card', 'ticket', 'bank_transfer', 'atm', 'prepaid_card'];
        $excluded = collect($allTypes)
            ->reject(static fn (string $type): bool => $type === $allowedType)
            ->map(static fn (string $type): array => ['id' => $type])
            ->values()
            ->all();

        $installments = $methodCode === PaymentMethod::CODE_CREDIT_CARD
            ? min(24, max(1, (int) ($context['max_installments'] ?? 1)))
            : 1;

        return [
            'excluded_payment_types' => $excluded,
            'installments' => $installments,
        ];
    }

    private function validatePaymentMethodAvailability(PaymentGateway $gateway, string $methodCode): array
    {
        $code = $this->normalizeMethodCode($methodCode);
        if (! in_array($code, PaymentMethod::INTEGRATED_CODES, true)) {
            throw new PaymentProviderException('Forma de pagamento integrada não suportada no Mercado Pago.');
        }

        $response = $this->sendWithGateway($gateway, fn (PendingRequest $request): Response => $request->get('/v1/payment_methods'));
        $body = (array) $response->json();

        if (! $response->successful()) {
            throw new PaymentProviderException(
                'Falha ao consultar formas de pagamento do Mercado Pago: HTTP '.$response->status().$this->resolveProviderMessageSuffix($body)
            );
        }

        $methods = collect($body)
            ->filter(static fn (mixed $item): bool => is_array($item))
            ->map(static fn (array $item): array => [
                'id' => strtolower(trim((string) ($item['id'] ?? ''))),
                'type' => strtolower(trim((string) ($item['payment_type_id'] ?? ''))),
                'status' => strtolower(trim((string) ($item['status'] ?? ''))),
            ])
            ->filter(static fn (array $item): bool => $item['id'] !== '' && $item['status'] !== 'deactivated')
            ->values();

        $supportedIds = match ($code) {
            PaymentMethod::CODE_PIX => $methods->filter(static fn (array $item): bool => $item['id'] === 'pix' || $item['type'] === 'bank_transfer')->pluck('id')->unique()->values()->all(),
            PaymentMethod::CODE_BOLETO => $methods->filter(static fn (array $item): bool => $item['type'] === 'ticket')->pluck('id')->unique()->values()->all(),
            PaymentMethod::CODE_CREDIT_CARD => $methods->filter(static fn (array $item): bool => $item['type'] === 'credit_card')->pluck('id')->unique()->values()->all(),
            PaymentMethod::CODE_DEBIT_CARD => $methods->filter(static fn (array $item): bool => $item['type'] === 'debit_card')->pluck('id')->unique()->values()->all(),
            default => [],
        };

        if ($supportedIds === []) {
            throw new PaymentProviderException(
                'A forma '.$this->resolveMethodLabel($code).' não está disponível na conta conectada do Mercado Pago. '
                .'Conecte uma conta com Pix habilitado e tente novamente.'
            );
        }

        return ['method_code' => $code, 'available' => true, 'supported_ids' => $supportedIds];
    }

    private function normalizeIntentFromPayment(array $payment, string $fallbackReference, string $methodCode): array
    {
        $pix = $this->extractPixPayload($payment);
        $checkoutUrl = $this->firstNonEmpty([
            Arr::get($payment, 'point_of_interaction.transaction_data.ticket_url'),
            Arr::get($payment, 'transaction_details.external_resource_url'),
        ]);

        return [
            'provider' => $this->providerCode(),
            'payment_method_code' => $methodCode,
            'payment_method_type' => $this->resolveMethodType($methodCode),
            'flow' => 'payments_api',
            'transaction_reference' => trim((string) ($payment['id'] ?? $fallbackReference)),
            'status' => strtolower(trim((string) ($payment['status'] ?? 'pending'))),
            'external_reference' => trim((string) ($payment['external_reference'] ?? Arr::get($payment, 'metadata.sale_code', ''))),
            'date_of_expiration' => $this->firstNonEmpty([
                Arr::get($payment, 'date_of_expiration'),
                Arr::get($payment, 'expiration_date'),
            ]) ?: null,
            'qr_code' => $pix['qr_code'],
            'qr_code_base64' => $pix['qr_code_base64'],
            'ticket_url' => $pix['ticket_url'],
            'checkout_url' => $checkoutUrl !== '' ? $checkoutUrl : ($pix['ticket_url'] !== '' ? $pix['ticket_url'] : null),
            'raw' => $payment,
        ];
    }

    private function normalizeIntentFromOrder(array $order, string $fallbackReference, string $methodCode): array
    {
        $pix = $this->extractPixPayload($order);
        $checkoutUrl = $this->firstNonEmpty([
            Arr::get($order, 'point_of_interaction.transaction_data.ticket_url'),
            Arr::get($order, 'transaction_details.external_resource_url'),
            Arr::get($order, 'transactions.payments.0.transaction_details.external_resource_url'),
        ]);

        return [
            'provider' => $this->providerCode(),
            'payment_method_code' => $methodCode,
            'payment_method_type' => $this->resolveMethodType($methodCode),
            'flow' => 'orders_api',
            'transaction_reference' => trim((string) ($order['id'] ?? $fallbackReference)),
            'status' => $this->resolveOrderStatus($order),
            'external_reference' => $this->resolveOrderExternalReference($order),
            'date_of_expiration' => $this->resolveOrderExpiration($order),
            'qr_code' => $pix['qr_code'],
            'qr_code_base64' => $pix['qr_code_base64'],
            'ticket_url' => $pix['ticket_url'],
            'checkout_url' => $checkoutUrl !== '' ? $checkoutUrl : ($pix['ticket_url'] !== '' ? $pix['ticket_url'] : null),
            'raw' => $order,
        ];
    }

    private function tryFetchOrderById(PaymentGateway $gateway, string $orderId): ?array
    {
        try {
            return $this->fetchOrderById($gateway, $orderId);
        } catch (PaymentProviderException) {
            return null;
        }
    }

    private function tryFetchPaymentById(PaymentGateway $gateway, string $paymentId): ?array
    {
        try {
            return $this->fetchPaymentById($gateway, $paymentId);
        } catch (PaymentProviderException) {
            return null;
        }
    }

    private function fetchOrderById(PaymentGateway $gateway, string $orderId): array
    {
        $response = $this->sendWithGateway($gateway, fn (PendingRequest $request): Response => $request->get('/v1/orders/'.$orderId));
        $body = (array) $response->json();
        if (! $response->successful()) {
            throw new PaymentProviderException(
                'Falha ao consultar pedido no Mercado Pago: HTTP '.$response->status().$this->resolveProviderMessageSuffix($body)
            );
        }

        return $body;
    }

    private function fetchPaymentById(PaymentGateway $gateway, string $paymentId): array
    {
        $response = $this->sendWithGateway($gateway, fn (PendingRequest $request): Response => $request->get('/v1/payments/'.$paymentId));
        $body = (array) $response->json();
        if (! $response->successful()) {
            throw new PaymentProviderException(
                'Falha ao consultar pagamento no Mercado Pago: HTTP '.$response->status().$this->resolveProviderMessageSuffix($body)
            );
        }

        return $body;
    }

    private function sendWithGateway(PaymentGateway $gateway, callable $callback): Response
    {
        $response = $callback($this->baseRequest($gateway));
        if ($response->status() === 401 && $this->canRefreshToken($gateway)) {
            $this->refreshAccessToken($gateway);
            $response = $callback($this->baseRequest($gateway->refresh()));
        }

        return $response;
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
        if ($gateway->provider !== PaymentGateway::PROVIDER_MERCADO_PAGO) {
            throw new PaymentProviderException('Gateway informado não pertence ao Mercado Pago.');
        }

        if ($gateway->isMercadoPagoTokenExpired() && $this->canRefreshToken($gateway)) {
            $this->refreshAccessToken($gateway);
            $gateway = $gateway->refresh();
        }

        $token = $gateway->resolveMercadoPagoAccessToken();
        if ($token === '') {
            throw new PaymentProviderException('Gateway Mercado Pago sem token de acesso. Conecte a conta no Financeiro.');
        }

        return $token;
    }

    private function canRefreshToken(PaymentGateway $gateway): bool
    {
        return trim((string) ($gateway->mp_refresh_token ?? '')) !== ''
            && $this->clientId() !== ''
            && $this->clientSecret() !== '';
    }

    private function refreshAccessToken(PaymentGateway $gateway): void
    {
        $refreshToken = trim((string) ($gateway->mp_refresh_token ?? ''));
        if ($refreshToken === '') {
            throw new PaymentProviderException('Token Mercado Pago expirado e sem refresh token. Conecte novamente a conta.');
        }

        $response = Http::baseUrl($this->baseUrl())
            ->timeout($this->timeoutSeconds())
            ->acceptJson()
            ->asForm()
            ->post('/oauth/token', [
                'client_id' => $this->clientId(),
                'client_secret' => $this->clientSecret(),
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
            ]);

        $body = (array) $response->json();
        if (! $response->successful()) {
            $gateway->forceFill([
                'mp_status' => PaymentGateway::MP_STATUS_EXPIRED,
                'mp_last_error' => 'Refresh token inválido: HTTP '.$response->status().$this->resolveProviderMessageSuffix($body),
            ])->save();

            throw new PaymentProviderException('Não foi possível renovar o token Mercado Pago. Conecte novamente a conta.');
        }

        $accessToken = trim((string) ($body['access_token'] ?? ''));
        if ($accessToken === '') {
            throw new PaymentProviderException('Mercado Pago não retornou access token no refresh.');
        }

        $expiresAt = null;
        $expiresIn = (int) ($body['expires_in'] ?? 0);
        if ($expiresIn > 0) {
            $expiresAt = now()->addSeconds($expiresIn);
        }

        $gateway->forceFill([
            'mp_access_token' => $accessToken,
            'mp_refresh_token' => ($tmp = trim((string) ($body['refresh_token'] ?? ''))) !== '' ? $tmp : $refreshToken,
            'mp_token_expires_at' => $expiresAt,
            'mp_scope' => ($tmp = trim((string) ($body['scope'] ?? ''))) !== '' ? $tmp : $gateway->mp_scope,
            'mp_public_key' => ($tmp = trim((string) ($body['public_key'] ?? ''))) !== '' ? $tmp : $gateway->mp_public_key,
            'mp_live_mode' => isset($body['live_mode']) ? (bool) $body['live_mode'] : $gateway->mp_live_mode,
            'mp_status' => PaymentGateway::MP_STATUS_CONNECTED,
            'mp_last_error' => null,
            'last_health_check_at' => now(),
        ])->save();
    }

    private function resolveMethodLabel(string $methodCode): string
    {
        return match ($this->normalizeMethodCode($methodCode)) {
            PaymentMethod::CODE_PIX => 'Pix',
            PaymentMethod::CODE_BOLETO => 'Boleto',
            PaymentMethod::CODE_CREDIT_CARD => 'Cartão de crédito',
            PaymentMethod::CODE_DEBIT_CARD => 'Cartão de débito',
            default => ucfirst(strtolower(trim($methodCode))),
        };
    }

    private function resolveMethodType(string $methodCode): string
    {
        return match ($this->normalizeMethodCode($methodCode)) {
            PaymentMethod::CODE_PIX => 'bank_transfer',
            PaymentMethod::CODE_BOLETO => 'ticket',
            PaymentMethod::CODE_CREDIT_CARD => 'credit_card',
            PaymentMethod::CODE_DEBIT_CARD => 'debit_card',
            default => '',
        };
    }

    private function normalizeMethodCode(string $methodCode): string
    {
        return match (strtolower(trim($methodCode))) {
            'credito', 'cartao_credito', 'credit', 'creditcard' => PaymentMethod::CODE_CREDIT_CARD,
            'debito', 'cartao_debito', 'debit', 'debitcard' => PaymentMethod::CODE_DEBIT_CARD,
            default => strtolower(trim($methodCode)),
        };
    }

    private function resolveEventId(array $payload): ?string
    {
        $value = trim((string) ($payload['event_id'] ?? $payload['id'] ?? ''));

        return $value !== '' ? $value : null;
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.mercadopago.base_url', 'https://api.mercadopago.com'), '/');
    }

    private function clientId(): string
    {
        return trim((string) config('services.mercadopago.client_id', ''));
    }

    private function clientSecret(): string
    {
        return trim((string) config('services.mercadopago.client_secret', ''));
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
            return Carbon::instance($value)->format('Y-m-d\TH:i:s.vP');
        }

        try {
            return Carbon::parse((string) $value)->format('Y-m-d\TH:i:s.vP');
        } catch (\Throwable) {
            return null;
        }
    }

    private function extractPixPayload(array $payload): array
    {
        $payment = $this->resolveFirstPaymentPayload($payload);

        $qrCode = $this->firstNonEmpty([
            Arr::get($payment, 'payment_method.qr_code'),
            Arr::get($payment, 'payment_method.data.qr_code'),
            Arr::get($payment, 'qr_code'),
            Arr::get($payment, 'point_of_interaction.transaction_data.qr_code'),
            Arr::get($payload, 'point_of_interaction.transaction_data.qr_code'),
        ]);

        $qrCodeBase64 = $this->firstNonEmpty([
            Arr::get($payment, 'payment_method.qr_code_base64'),
            Arr::get($payment, 'payment_method.data.qr_code_base64'),
            Arr::get($payment, 'qr_code_base64'),
            Arr::get($payment, 'point_of_interaction.transaction_data.qr_code_base64'),
            Arr::get($payload, 'point_of_interaction.transaction_data.qr_code_base64'),
        ]);

        $ticketUrl = $this->firstNonEmpty([
            Arr::get($payment, 'payment_method.ticket_url'),
            Arr::get($payment, 'payment_method.data.ticket_url'),
            Arr::get($payment, 'ticket_url'),
            Arr::get($payment, 'point_of_interaction.transaction_data.ticket_url'),
            Arr::get($payment, 'transaction_details.external_resource_url'),
            Arr::get($payload, 'point_of_interaction.transaction_data.ticket_url'),
        ]);

        if ($qrCodeBase64 !== '' && ! str_starts_with($qrCodeBase64, 'data:')) {
            $qrCodeBase64 = preg_replace('/\s+/', '', $qrCodeBase64) ?: $qrCodeBase64;
        }

        return ['qr_code' => $qrCode, 'qr_code_base64' => $qrCodeBase64, 'ticket_url' => $ticketUrl];
    }

    private function resolveFirstPaymentPayload(array $payload): array
    {
        $payments = Arr::get($payload, 'transactions.payments');
        if (is_array($payments) && isset($payments[0]) && is_array($payments[0])) {
            return $payments[0];
        }

        $payment = Arr::get($payload, 'payment');
        if (is_array($payment)) {
            return $payment;
        }

        if (isset($payload['id']) && (isset($payload['status']) || isset($payload['payment_method_id']))) {
            return $payload;
        }

        return [];
    }

    private function resolveOrderStatus(array $payload): string
    {
        $payment = $this->resolveFirstPaymentPayload($payload);
        $status = $this->firstNonEmpty([Arr::get($payment, 'status'), Arr::get($payload, 'status')]);

        return strtolower($status !== '' ? $status : 'pending');
    }

    private function resolveOrderExternalReference(array $payload): string
    {
        return $this->firstNonEmpty([Arr::get($payload, 'external_reference'), Arr::get($payload, 'metadata.external_reference')]);
    }

    private function resolveSaleCodeFromPayload(array $payload): ?string
    {
        $saleCode = $this->firstNonEmpty([Arr::get($payload, 'external_reference'), Arr::get($payload, 'metadata.sale_code')]);

        return $saleCode !== '' ? $saleCode : null;
    }

    private function resolveOrderExpiration(array $payload): ?string
    {
        $payment = $this->resolveFirstPaymentPayload($payload);
        $value = $this->firstNonEmpty([
            Arr::get($payment, 'date_of_expiration'),
            Arr::get($payment, 'expiration_date'),
            Arr::get($payload, 'date_of_expiration'),
            Arr::get($payload, 'expiration_date'),
        ]);

        return $value !== '' ? $value : null;
    }

    private function hasPixPayload(array $payload): bool
    {
        return trim((string) ($payload['qr_code'] ?? '')) !== ''
            || trim((string) ($payload['qr_code_base64'] ?? '')) !== ''
            || trim((string) ($payload['ticket_url'] ?? '')) !== '';
    }

    private function firstNonEmpty(array $values): string
    {
        foreach ($values as $value) {
            $string = trim((string) ($value ?? ''));
            if ($string !== '') {
                return $string;
            }
        }

        return '';
    }

    private function arrayFilterRecursive(array $payload): array
    {
        $filtered = [];
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $value = $this->arrayFilterRecursive($value);
            }
            if ($value === null || $value === '' || $value === []) {
                continue;
            }
            $filtered[$key] = $value;
        }

        return $filtered;
    }

    private function resolveProviderMessageSuffix(mixed $body): string
    {
        if (! is_array($body)) {
            return '';
        }

        $error = trim((string) ($body['error'] ?? ''));
        $message = trim((string) ($body['message'] ?? ''));
        $description = trim((string) ($body['error_description'] ?? ''));
        $causes = collect($body['cause'] ?? [])
            ->filter(static fn (mixed $item): bool => is_array($item))
            ->map(static function (array $item): string {
                $desc = trim((string) ($item['description'] ?? ''));
                if ($desc !== '') {
                    return $desc;
                }

                $code = trim((string) ($item['code'] ?? ''));
                return $code !== '' ? 'cause_code='.$code : '';
            })
            ->filter()
            ->values()
            ->all();

        $parts = array_values(array_filter([
            $error !== '' ? strtoupper($error) : '',
            $message,
            $description,
            $causes !== [] ? implode('; ', $causes) : '',
        ], static fn (string $part): bool => $part !== ''));

        return $parts === [] ? '' : ' - '.implode(' | ', $parts);
    }
}
