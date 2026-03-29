<?php

namespace App\Application\Finance\Services;

use App\Http\Controllers\Concerns\ResolvesCurrentContractor;
use App\Models\PaymentGateway;
use App\Models\PaymentMethod;
use App\Services\Payments\Exceptions\PaymentProviderException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AdminMercadoPagoOAuthService
{
    use ResolvesCurrentContractor;

    private const SESSION_STATE_KEY = 'finance.mercadopago.oauth';

    public function redirectToAuthorization(Request $request): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $clientId = $this->resolveClientId();
        $clientSecret = $this->resolveClientSecret();
        if ($clientId === '' || $clientSecret === '') {
            return back()->withErrors([
                'mercado_pago_oauth' => 'Conexão OAuth do Mercado Pago indisponível no momento. Contate o suporte da plataforma.',
            ]);
        }

        $state = Str::uuid()->toString();
        $codeVerifier = $this->generatePkceCodeVerifier();
        $codeChallenge = $this->generatePkceCodeChallenge($codeVerifier);
        $fallbackReturnTo = route('admin.finance.payments');
        $requestedReturnTo = trim((string) $request->query('return_to', ''));
        $returnTo = $this->resolveSafeReturnTo($requestedReturnTo, $fallbackReturnTo);

        $request->session()->put(self::SESSION_STATE_KEY, [
            'state' => $state,
            'code_verifier' => $codeVerifier,
            'user_id' => (int) ($request->user()?->id ?? 0),
            'contractor_id' => (int) $contractor->id,
            'return_to' => $returnTo,
            'created_at' => now()->toIso8601String(),
        ]);

        $query = http_build_query([
            'client_id' => $clientId,
            'response_type' => 'code',
            'platform_id' => 'mp',
            'state' => $state,
            'redirect_uri' => $this->resolveRedirectUri(),
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ]);

        return redirect()->away($this->resolveAuthorizeUrl().'?'.$query);
    }

    public function handleAuthorizationCallback(Request $request): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $sessionState = $request->session()->pull(self::SESSION_STATE_KEY);
        if (! is_array($sessionState)) {
            return redirect()->route('admin.finance.payments')->withErrors([
                'mercado_pago_oauth' => 'Sessão de autorização expirada. Tente conectar novamente.',
            ]);
        }

        $state = trim((string) $request->query('state', ''));
        $expectedState = trim((string) ($sessionState['state'] ?? ''));
        if ($state === '' || $expectedState === '' || ! hash_equals($expectedState, $state)) {
            return redirect()->route('admin.finance.payments')->withErrors([
                'mercado_pago_oauth' => 'Estado de autorização inválido. Tente conectar novamente.',
            ]);
        }

        $sessionContractorId = (int) ($sessionState['contractor_id'] ?? 0);
        if ($sessionContractorId <= 0 || $sessionContractorId !== (int) $contractor->id) {
            return redirect()->route('admin.finance.payments')->withErrors([
                'mercado_pago_oauth' => 'Contratante da autorização não confere com a sessão atual.',
            ]);
        }

        $codeVerifier = trim((string) ($sessionState['code_verifier'] ?? ''));
        if ($codeVerifier === '') {
            return redirect()->route('admin.finance.payments')->withErrors([
                'mercado_pago_oauth' => 'Sessão de autorização inválida. Tente conectar novamente.',
            ]);
        }

        $returnTo = $this->resolveSafeReturnTo(
            (string) ($sessionState['return_to'] ?? ''),
            route('admin.finance.payments')
        );

        $error = trim((string) $request->query('error', ''));
        if ($error !== '') {
            $errorDescription = trim((string) $request->query('error_description', ''));
            $message = 'Mercado Pago retornou erro na autorização.';
            if ($errorDescription !== '') {
                $message .= ' '.$errorDescription;
            }

            return redirect($returnTo)->withErrors([
                'mercado_pago_oauth' => $message,
            ]);
        }

        $code = trim((string) $request->query('code', ''));
        if ($code === '') {
            return redirect($returnTo)->withErrors([
                'mercado_pago_oauth' => 'Código de autorização não recebido.',
            ]);
        }

        try {
            $tokenPayload = $this->exchangeAuthorizationCode($code, $codeVerifier);
            $account = $this->fetchAccount($tokenPayload['access_token']);
            $gateway = $this->persistGatewayConnection($contractor->id, $tokenPayload, $account);
        } catch (PaymentProviderException $exception) {
            return redirect($returnTo)->withErrors([
                'mercado_pago_oauth' => $exception->getMessage(),
            ]);
        }

        return redirect($returnTo)->with('status', sprintf(
            'Conta Mercado Pago conectada com sucesso%s.',
            $gateway->name !== '' ? ' no gateway '.$gateway->name : ''
        ));
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $gatewayId = (int) $request->input('gateway_id', 0);

        $gatewayQuery = PaymentGateway::query()
            ->where('contractor_id', $contractor->id)
            ->where('provider', PaymentGateway::PROVIDER_MERCADO_PAGO);

        if ($gatewayId > 0) {
            $gatewayQuery->where('id', $gatewayId);
        }

        $gateway = $gatewayQuery
            ->orderByDesc('is_default')
            ->latest('id')
            ->first();

        if (! $gateway) {
            return back()->withErrors([
                'mercado_pago_oauth' => 'Gateway Mercado Pago não encontrado para este contratante.',
            ]);
        }

        $credentials = is_array($gateway->credentials) ? $gateway->credentials : [];
        unset($credentials['access_token']);

        $gateway->forceFill([
            'credentials' => $credentials === [] ? null : $credentials,
            'mp_access_token' => null,
            'mp_refresh_token' => null,
            'mp_token_expires_at' => null,
            'mp_scope' => null,
            'mp_live_mode' => null,
            'mp_status' => PaymentGateway::MP_STATUS_DISCONNECTED,
            'mp_connected_at' => null,
            'mp_last_error' => null,
            'mp_user_id' => null,
            'mp_public_key' => null,
            'mp_metadata' => null,
        ])->save();

        return back()->with('status', 'Conta Mercado Pago desconectada com sucesso.');
    }

    /**
     * @return array{
     *   access_token: string,
     *   refresh_token: string|null,
     *   expires_in: int|null,
     *   user_id: string|null,
     *   scope: string|null,
     *   public_key: string|null,
     *   live_mode: bool|null,
     *   token_type: string|null
     * }
     */
    private function exchangeAuthorizationCode(string $code, string $codeVerifier): array
    {
        $payload = [
            'client_id' => $this->resolveClientId(),
            'client_secret' => $this->resolveClientSecret(),
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->resolveRedirectUri(),
            'code_verifier' => $codeVerifier,
        ];

        $response = $this->baseRequest()
            ->asForm()
            ->post('/oauth/token', $payload);

        $body = (array) $response->json();
        if (! $response->successful()) {
            throw new PaymentProviderException(
                'Falha ao obter token OAuth do Mercado Pago: HTTP '.$response->status()
                .$this->resolveProviderMessageSuffix($body)
            );
        }

        $accessToken = trim((string) ($body['access_token'] ?? ''));
        if ($accessToken === '') {
            throw new PaymentProviderException('Mercado Pago não retornou access token no OAuth.');
        }

        return [
            'access_token' => $accessToken,
            'refresh_token' => ($tmp = trim((string) ($body['refresh_token'] ?? ''))) !== '' ? $tmp : null,
            'expires_in' => isset($body['expires_in']) ? max(0, (int) $body['expires_in']) : null,
            'user_id' => ($tmp = trim((string) ($body['user_id'] ?? ''))) !== '' ? $tmp : null,
            'scope' => ($tmp = trim((string) ($body['scope'] ?? ''))) !== '' ? $tmp : null,
            'public_key' => ($tmp = trim((string) ($body['public_key'] ?? ''))) !== '' ? $tmp : null,
            'live_mode' => isset($body['live_mode']) ? (bool) $body['live_mode'] : null,
            'token_type' => ($tmp = trim((string) ($body['token_type'] ?? ''))) !== '' ? $tmp : null,
        ];
    }

    /**
     * @param  array{access_token:string}  $tokenPayload
     * @return array{
     *   id: string|null,
     *   nickname: string|null,
     *   email: string|null
     * }
     */
    private function fetchAccount(string $accessToken): array
    {
        $response = $this->baseRequest()
            ->withToken($accessToken)
            ->get('/users/me');

        $body = (array) $response->json();
        if (! $response->successful()) {
            throw new PaymentProviderException(
                'Token OAuth recebido, mas a consulta da conta falhou: HTTP '.$response->status()
                .$this->resolveProviderMessageSuffix($body)
            );
        }

        return [
            'id' => ($tmp = trim((string) ($body['id'] ?? ''))) !== '' ? $tmp : null,
            'nickname' => ($tmp = trim((string) ($body['nickname'] ?? ''))) !== '' ? $tmp : null,
            'email' => ($tmp = trim((string) ($body['email'] ?? ''))) !== '' ? $tmp : null,
        ];
    }

    /**
     * @param  array{
     *   access_token: string,
     *   refresh_token: string|null,
     *   expires_in: int|null,
     *   user_id: string|null,
     *   scope: string|null,
     *   public_key: string|null,
     *   live_mode: bool|null,
     *   token_type: string|null
     * }  $tokenPayload
     * @param  array{id:string|null,nickname:string|null,email:string|null}  $account
     */
    private function persistGatewayConnection(int $contractorId, array $tokenPayload, array $account): PaymentGateway
    {
        $gateway = PaymentGateway::query()
            ->where('contractor_id', $contractorId)
            ->where('provider', PaymentGateway::PROVIDER_MERCADO_PAGO)
            ->orderByDesc('is_default')
            ->latest('id')
            ->first();

        if (! $gateway) {
            $gateway = new PaymentGateway([
                'contractor_id' => $contractorId,
                'provider' => PaymentGateway::PROVIDER_MERCADO_PAGO,
                'name' => 'Mercado Pago',
                'is_active' => true,
                'is_default' => ! PaymentGateway::query()
                    ->where('contractor_id', $contractorId)
                    ->exists(),
            ]);
        }

        $expiresAt = null;
        if (($tokenPayload['expires_in'] ?? 0) > 0) {
            $expiresAt = now()->addSeconds((int) $tokenPayload['expires_in']);
        }

        $credentials = is_array($gateway->credentials) ? $gateway->credentials : [];
        unset($credentials['access_token']);

        $gateway->forceFill([
            'name' => trim((string) ($gateway->name ?: 'Mercado Pago')),
            'is_active' => true,
            'is_sandbox' => isset($tokenPayload['live_mode']) ? ! (bool) $tokenPayload['live_mode'] : (bool) $gateway->is_sandbox,
            'credentials' => $credentials === [] ? null : $credentials,
            'mp_user_id' => $account['id'] ?? $tokenPayload['user_id'],
            'mp_public_key' => $tokenPayload['public_key'],
            'mp_access_token' => $tokenPayload['access_token'],
            'mp_refresh_token' => $tokenPayload['refresh_token'],
            'mp_token_expires_at' => $expiresAt,
            'mp_scope' => $tokenPayload['scope'],
            'mp_live_mode' => $tokenPayload['live_mode'],
            'mp_status' => PaymentGateway::MP_STATUS_CONNECTED,
            'mp_connected_at' => now(),
            'mp_last_error' => null,
            'mp_metadata' => array_filter([
                'nickname' => $account['nickname'] ?? null,
                'email' => $account['email'] ?? null,
                'token_type' => $tokenPayload['token_type'],
            ], static fn (mixed $value): bool => $value !== null && $value !== ''),
            'last_health_check_at' => now(),
        ])->save();

        $this->relinkMercadoPagoIntegratedMethods($contractorId, $gateway);

        return $gateway->refresh();
    }

    private function relinkMercadoPagoIntegratedMethods(int $contractorId, PaymentGateway $gateway): void
    {
        $methods = PaymentMethod::query()
            ->where('contractor_id', $contractorId)
            ->whereIn('code', PaymentMethod::INTEGRATED_CODES)
            ->orderBy('id')
            ->get();

        foreach ($methods as $method) {
            $settings = is_array($method->settings) ? $method->settings : [];
            $integrationProvider = strtolower(trim((string) data_get($settings, 'gateway_integration.provider', '')));
            $hasLegacyGatewayId = (int) ($method->payment_gateway_id ?? 0) > 0;

            if ($integrationProvider !== PaymentGateway::PROVIDER_MERCADO_PAGO && ! $hasLegacyGatewayId) {
                continue;
            }

            data_set($settings, 'gateway_integration.provider', PaymentGateway::PROVIDER_MERCADO_PAGO);
            data_set($settings, 'gateway_integration.gateway_id', (int) $gateway->id);

            $method->forceFill([
                'payment_gateway_id' => (int) $gateway->id,
                'settings' => $settings,
            ])->save();
        }
    }

    private function resolveSafeReturnTo(string $candidate, string $fallback): string
    {
        $value = trim($candidate);
        if ($value === '' || ! str_starts_with($value, '/')) {
            return $fallback;
        }

        if (str_starts_with($value, '//')) {
            return $fallback;
        }

        return $value;
    }

    private function baseRequest(): PendingRequest
    {
        return Http::baseUrl($this->resolveApiBaseUrl())
            ->timeout($this->resolveTimeoutSeconds())
            ->acceptJson()
            ->asJson();
    }

    private function resolveAuthorizeUrl(): string
    {
        return rtrim((string) config('services.mercadopago.oauth_authorize_url', 'https://auth.mercadopago.com.br/authorization'), '/');
    }

    private function resolveApiBaseUrl(): string
    {
        return rtrim((string) config('services.mercadopago.base_url', 'https://api.mercadopago.com'), '/');
    }

    private function resolveRedirectUri(): string
    {
        return (string) config('services.mercadopago.oauth_redirect_uri', route('admin.finance.mercadopago.callback'));
    }

    private function resolveClientId(): string
    {
        return trim((string) config('services.mercadopago.client_id', ''));
    }

    private function resolveClientSecret(): string
    {
        return trim((string) config('services.mercadopago.client_secret', ''));
    }

    private function resolveTimeoutSeconds(): int
    {
        $timeout = (int) config('services.mercadopago.timeout', 15);

        return $timeout > 0 ? $timeout : 15;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function resolveProviderMessageSuffix(array $body): string
    {
        $error = trim((string) ($body['error'] ?? ''));
        $message = trim((string) ($body['message'] ?? ''));
        $description = trim((string) ($body['error_description'] ?? ''));

        $parts = array_values(array_filter([
            $error !== '' ? strtoupper($error) : '',
            $message,
            $description,
        ], static fn (string $part): bool => $part !== ''));

        if ($parts === []) {
            return '';
        }

        return ' - '.implode(' | ', $parts);
    }

    private function generatePkceCodeVerifier(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');
    }

    private function generatePkceCodeChallenge(string $codeVerifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
    }
}
