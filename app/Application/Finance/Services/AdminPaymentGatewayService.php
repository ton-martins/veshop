<?php

namespace App\Application\Finance\Services;

use App\Http\Controllers\Concerns\ResolvesCurrentContractor;
use App\Http\Requests\Admin\StorePaymentGatewayRequest;
use App\Http\Requests\Admin\UpdatePaymentGatewayRequest;
use App\Models\Contractor;
use App\Models\PaymentGateway;
use App\Models\PaymentMethod;
use App\Services\Payments\Exceptions\PaymentProviderException;
use App\Services\Payments\PaymentProviderManager;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminPaymentGatewayService
{
    use ResolvesCurrentContractor;

    public function testConnection(Request $request): JsonResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $validated = $request->validate([
            'provider' => ['required', 'string', Rule::in(PaymentGateway::SUPPORTED_PROVIDERS)],
            'gateway_id' => ['nullable', 'integer'],
            'payment_method_code' => ['nullable', 'string', Rule::in(PaymentMethod::INTEGRATED_CODES)],
            'validate_checkout_flow' => ['nullable', 'boolean'],
        ]);

        $provider = strtolower(trim((string) ($validated['provider'] ?? '')));
        $gatewayId = (int) ($validated['gateway_id'] ?? 0);
        $paymentMethodCode = strtolower(trim((string) ($validated['payment_method_code'] ?? '')));
        $incomingAccessToken = trim((string) $request->input('mercado_pago_access_token', ''));
        $incomingWebhookSecret = trim((string) $request->input('mercado_pago_webhook_secret', ''));
        $incomingIsSandbox = $request->has('is_sandbox')
            ? (bool) $request->boolean('is_sandbox')
            : null;
        $validateCheckoutFlow = (bool) ($validated['validate_checkout_flow'] ?? false);

        $existingGateway = null;
        if ($gatewayId > 0) {
            $candidate = PaymentGateway::query()->find($gatewayId);
            if (! $candidate || (int) $candidate->contractor_id !== (int) $contractor->id) {
                abort(404);
            }

            $existingGateway = $candidate;
        }

        if ($provider === PaymentGateway::PROVIDER_MERCADO_PAGO) {
            if (! $existingGateway && $incomingAccessToken === '') {
                return response()->json([
                    'ok' => false,
                    'message' => 'Conecte primeiro a conta Mercado Pago para testar a integração.',
                ], 422);
            }

            if ($existingGateway && $existingGateway->resolveMercadoPagoAccessToken() === '' && $incomingAccessToken === '') {
                return response()->json([
                    'ok' => false,
                    'message' => 'Gateway Mercado Pago sem token OAuth. Reconecte a conta.',
                ], 422);
            }
        }

        $testCredentials = is_array($existingGateway?->credentials) ? $existingGateway->credentials : [];
        if ($provider === PaymentGateway::PROVIDER_MERCADO_PAGO) {
            if ($incomingAccessToken !== '') {
                $testCredentials['access_token'] = $incomingAccessToken;
            }
            if ($incomingWebhookSecret !== '') {
                $testCredentials['webhook_secret'] = $incomingWebhookSecret;
            }
        }

        $gatewayForTest = new PaymentGateway([
            'contractor_id' => $contractor->id,
            'provider' => $provider,
            'name' => $existingGateway?->name ?? 'Gateway em teste',
            'is_active' => true,
            'is_default' => false,
            'is_sandbox' => $incomingIsSandbox ?? (bool) ($existingGateway?->is_sandbox ?? true),
            'credentials' => $testCredentials === [] ? null : $testCredentials,
            'mp_access_token' => $existingGateway?->mp_access_token,
            'mp_refresh_token' => $existingGateway?->mp_refresh_token,
            'mp_token_expires_at' => $existingGateway?->mp_token_expires_at,
            'mp_status' => $existingGateway?->mp_status,
            'settings' => null,
        ]);

        try {
            $result = app(PaymentProviderManager::class)->testGatewayConnection(
                $gatewayForTest,
                $paymentMethodCode !== '' ? $paymentMethodCode : null
            );

            if (
                $provider === PaymentGateway::PROVIDER_MERCADO_PAGO
                && $validateCheckoutFlow
                && $paymentMethodCode === PaymentMethod::CODE_PIX
            ) {
                $pixCheckoutProbe = $this->validateMercadoPagoPixCheckoutFlow($gatewayForTest);
                $result['message'] = trim(
                    ((string) ($result['message'] ?? 'Conexão validada com sucesso.')).' '.$pixCheckoutProbe['message']
                );
                $result['details'] = array_merge(
                    is_array($result['details'] ?? null) ? $result['details'] : [],
                    ['pix_checkout_probe' => $pixCheckoutProbe['details']]
                );
            }
        } catch (PaymentProviderException $exception) {
            $message = trim((string) $exception->getMessage());
            $normalizedMessage = strtolower($message);
            if (
                $provider === PaymentGateway::PROVIDER_MERCADO_PAGO
                && (
                    str_contains($normalizedMessage, 'oauth')
                    || str_contains($normalizedMessage, 'unauthorized')
                    || str_contains($normalizedMessage, 'permiss')
                    || str_contains($normalizedMessage, 'inválida')
                    || str_contains($normalizedMessage, 'invalida')
                )
            ) {
                $message = 'Access token do Mercado Pago inválido ou sem permissão.';
            }

            return response()->json([
                'ok' => false,
                'message' => $message !== '' ? $message : 'Falha ao validar conexão com o gateway.',
            ], 422);
        }

        if ($existingGateway) {
            $existingGateway->forceFill([
                'last_health_check_at' => now(),
                'mp_last_error' => null,
            ])->save();
        }

        return response()->json([
            'ok' => true,
            'message' => (string) ($result['message'] ?? 'Conexão validada com sucesso.'),
            'details' => is_array($result['details'] ?? null) ? $result['details'] : [],
        ]);
    }

    /**
     * @return array{
     *   ok: bool,
     *   message: string,
     *   details: array<string, mixed>
     * }
     */
    private function validateMercadoPagoPixCheckoutFlow(PaymentGateway $gateway): array
    {
        $token = trim($gateway->resolveMercadoPagoAccessToken());
        if ($token === '') {
            throw new PaymentProviderException('Gateway Mercado Pago sem token de acesso para validar checkout Pix.');
        }

        $request = Http::baseUrl($this->mercadoPagoBaseUrl())
            ->timeout($this->mercadoPagoTimeoutSeconds())
            ->acceptJson()
            ->withToken($token)
            ->asJson();

        $accountResponse = $request->get('/users/me');
        $accountBody = (array) $accountResponse->json();
        if (! $accountResponse->successful()) {
            throw new PaymentProviderException(
                'Não foi possível validar a conta Mercado Pago antes do teste Pix: HTTP '.$accountResponse->status()
                .$this->resolveMercadoPagoErrorSuffix($accountBody)
            );
        }

        $payerEmail = trim((string) ($accountBody['email'] ?? ''));
        if ($payerEmail === '') {
            throw new PaymentProviderException('A conta Mercado Pago conectada não retornou e-mail para o teste Pix.');
        }

        $idempotencyKey = 'veshop-admin-pix-test-'.Str::uuid()->toString();
        $externalReference = 'VESHOP-PIX-TEST-'.now()->format('YmdHis').'-'.Str::lower(Str::random(6));
        $payload = [
            'transaction_amount' => 1.00,
            'payment_method_id' => 'pix',
            'description' => 'Teste de integração Veshop',
            'external_reference' => $externalReference,
            'date_of_expiration' => now()->addMinutes(15)->format('Y-m-d\TH:i:s.vP'),
            'payer' => [
                'email' => $payerEmail,
            ],
            'metadata' => [
                'veshop_checkout_probe' => true,
            ],
        ];

        $response = $this->sendMercadoPagoPixProbeRequest($request, $payload, $idempotencyKey);
        $body = (array) $response->json();

        if (! $response->successful()) {
            $normalizedMessage = strtolower(trim((string) ($body['message'] ?? '')));
            if (str_contains($normalizedMessage, 'unauthorized use of live credentials')) {
                throw new PaymentProviderException(
                    'Mercado Pago recusou o Pix de teste no ambiente atual. Revise as credenciais e a conta conectada (teste/produção).'
                );
            }

            throw new PaymentProviderException(
                'Falha ao criar cobrança Pix de teste no Mercado Pago: HTTP '.$response->status()
                .$this->resolveMercadoPagoErrorSuffix($body)
            );
        }

        $transactionReference = trim((string) ($body['id'] ?? ''));
        $qrCode = trim((string) (
            data_get($body, 'point_of_interaction.transaction_data.qr_code')
            ?? data_get($body, 'qr_code')
            ?? ''
        ));
        $qrCodeBase64 = trim((string) (
            data_get($body, 'point_of_interaction.transaction_data.qr_code_base64')
            ?? data_get($body, 'qr_code_base64')
            ?? ''
        ));
        $ticketUrl = trim((string) (
            data_get($body, 'point_of_interaction.transaction_data.ticket_url')
            ?? data_get($body, 'transaction_details.external_resource_url')
            ?? ''
        ));

        if ($transactionReference === '') {
            throw new PaymentProviderException('Mercado Pago respondeu ao teste Pix sem identificador da transação.');
        }

        if ($qrCode === '' && $qrCodeBase64 === '' && $ticketUrl === '') {
            throw new PaymentProviderException(
                'Mercado Pago criou a cobrança de teste, mas não retornou dados de QR Pix. Verifique as permissões da conta.'
            );
        }

        return [
            'ok' => true,
            'message' => 'Fluxo de checkout Pix validado com sucesso.',
            'details' => [
                'transaction_reference' => $transactionReference,
                'has_qr_code' => $qrCode !== '' || $qrCodeBase64 !== '',
                'has_ticket_url' => $ticketUrl !== '',
                'status' => trim((string) ($body['status'] ?? '')),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function sendMercadoPagoPixProbeRequest(PendingRequest $request, array $payload, string $idempotencyKey): Response
    {
        return $request
            ->withHeaders(['X-Idempotency-Key' => $idempotencyKey])
            ->post('/v1/payments', $payload);
    }

    private function mercadoPagoBaseUrl(): string
    {
        return rtrim((string) config('services.mercadopago.base_url', 'https://api.mercadopago.com'), '/');
    }

    private function mercadoPagoTimeoutSeconds(): int
    {
        $timeout = (int) config('services.mercadopago.timeout', 15);

        return $timeout > 0 ? $timeout : 15;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function resolveMercadoPagoErrorSuffix(array $body): string
    {
        $error = trim((string) ($body['error'] ?? ''));
        $message = trim((string) ($body['message'] ?? ''));
        $description = trim((string) ($body['error_description'] ?? ''));

        $firstCauseDescription = '';
        $firstCause = $body['cause'][0] ?? null;
        if (is_array($firstCause)) {
            $firstCauseDescription = trim((string) ($firstCause['description'] ?? ''));
        }

        $parts = array_values(array_filter([
            $error !== '' ? strtoupper($error) : '',
            $message,
            $description,
            $firstCauseDescription,
        ], static fn (string $part): bool => $part !== ''));

        if ($parts === []) {
            return '';
        }

        return ' - '.implode(' | ', $parts);
    }

    public function store(StorePaymentGatewayRequest $request): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $data = $request->validated();
        if ($data['is_default']) {
            $data['is_active'] = true;
        }

        $data['contractor_id'] = $contractor->id;
        $data['credentials'] = $this->normalizeGatewayCredentialsForStore($data);

        $gateway = PaymentGateway::query()->create($data);

        if ($gateway->is_default) {
            PaymentGateway::query()
                ->where('contractor_id', $contractor->id)
                ->where('id', '!=', $gateway->id)
                ->update(['is_default' => false]);
        }

        return back()->with('status', 'Gateway de pagamento criado com sucesso.');
    }

    public function update(UpdatePaymentGatewayRequest $request, PaymentGateway $paymentGateway): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $gateway = $this->resolveOwnedGateway($contractor, $paymentGateway);
        $data = $request->validated();

        if ($data['is_default']) {
            $data['is_active'] = true;
        }

        $data['credentials'] = $this->normalizeGatewayCredentialsForUpdate($gateway, $data);

        $gateway->fill($data)->save();

        if ($gateway->is_default) {
            PaymentGateway::query()
                ->where('contractor_id', $contractor->id)
                ->where('id', '!=', $gateway->id)
                ->update(['is_default' => false]);
        }

        return back()->with('status', 'Gateway de pagamento atualizado com sucesso.');
    }

    public function destroy(Request $request, PaymentGateway $paymentGateway): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $gateway = $this->resolveOwnedGateway($contractor, $paymentGateway);
        $wasDefault = (bool) $gateway->is_default;

        PaymentMethod::query()
            ->where('contractor_id', $contractor->id)
            ->where('payment_gateway_id', $gateway->id)
            ->update(['payment_gateway_id' => null]);

        $gateway->delete();

        if ($wasDefault) {
            $fallback = PaymentGateway::query()
                ->where('contractor_id', $contractor->id)
                ->where('is_active', true)
                ->orderBy('id')
                ->first();

            if ($fallback) {
                $fallback->is_default = true;
                $fallback->save();
            }
        }

        return back()->with('status', 'Gateway de pagamento removido com sucesso.');
    }

    private function resolveOwnedGateway(Contractor $contractor, PaymentGateway $gateway): PaymentGateway
    {
        abort_unless((int) $gateway->contractor_id === (int) $contractor->id, 404);

        return $gateway;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    private function normalizeGatewayCredentialsForStore(array $data): ?array
    {
        $provider = (string) ($data['provider'] ?? '');
        if ($provider !== PaymentGateway::PROVIDER_MERCADO_PAGO) {
            return null;
        }

        $credentials = is_array($data['credentials'] ?? null) ? $data['credentials'] : [];
        $webhookSecret = trim((string) ($credentials['webhook_secret'] ?? ''));

        if ($webhookSecret === '') {
            return null;
        }

        return [
            'webhook_secret' => $webhookSecret,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    private function normalizeGatewayCredentialsForUpdate(PaymentGateway $gateway, array $data): ?array
    {
        $provider = (string) ($data['provider'] ?? '');
        if ($provider !== PaymentGateway::PROVIDER_MERCADO_PAGO) {
            return null;
        }

        $current = is_array($gateway->credentials) ? $gateway->credentials : [];
        $incoming = is_array($data['credentials'] ?? null) ? $data['credentials'] : [];

        $webhookSecret = trim((string) ($incoming['webhook_secret'] ?? ''));
        if ($webhookSecret === '') {
            $webhookSecret = trim((string) ($current['webhook_secret'] ?? ''));
        }

        if ($webhookSecret === '') {
            return null;
        }

        return [
            'webhook_secret' => $webhookSecret,
        ];
    }
}
