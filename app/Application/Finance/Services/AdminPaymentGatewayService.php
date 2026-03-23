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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminPaymentGatewayService
{
    use ResolvesCurrentContractor;

    public function testConnection(Request $request): JsonResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $validated = $request->validate([
            'provider' => ['required', 'string', Rule::in(PaymentGateway::SUPPORTED_PROVIDERS)],
            'is_sandbox' => ['nullable', 'boolean'],
            'gateway_id' => ['nullable', 'integer'],
            'mercado_pago_access_token' => ['nullable', 'string', 'max:255'],
            'mercado_pago_webhook_secret' => ['nullable', 'string', 'max:255'],
        ]);

        $provider = strtolower(trim((string) ($validated['provider'] ?? '')));
        $isSandbox = (bool) ($validated['is_sandbox'] ?? true);
        $gatewayId = (int) ($validated['gateway_id'] ?? 0);

        $existingGateway = null;
        if ($gatewayId > 0) {
            $candidate = PaymentGateway::query()->find($gatewayId);
            if (! $candidate || (int) $candidate->contractor_id !== (int) $contractor->id) {
                abort(404);
            }

            $existingGateway = $candidate;
        }

        $credentials = [];
        if ($provider === PaymentGateway::PROVIDER_MERCADO_PAGO) {
            $incomingAccessToken = trim((string) ($validated['mercado_pago_access_token'] ?? ''));
            $incomingWebhookSecret = trim((string) ($validated['mercado_pago_webhook_secret'] ?? ''));
            $existingCredentials = is_array($existingGateway?->credentials) ? $existingGateway->credentials : [];

            $accessToken = $incomingAccessToken !== ''
                ? $incomingAccessToken
                : trim((string) ($existingCredentials['access_token'] ?? ''));

            $webhookSecret = $incomingWebhookSecret !== ''
                ? $incomingWebhookSecret
                : trim((string) ($existingCredentials['webhook_secret'] ?? ''));

            if ($accessToken === '') {
                return response()->json([
                    'ok' => false,
                    'message' => 'Informe o access token do Mercado Pago para validar a conexão.',
                ], 422);
            }

            $credentials = [
                'access_token' => $accessToken,
                'webhook_secret' => $webhookSecret,
            ];
        }

        $gatewayForTest = new PaymentGateway([
            'contractor_id' => $contractor->id,
            'provider' => $provider,
            'name' => $existingGateway?->name ?? 'Gateway em teste',
            'is_active' => true,
            'is_default' => false,
            'is_sandbox' => $isSandbox,
            'credentials' => $credentials,
            'settings' => null,
        ]);

        try {
            $result = app(PaymentProviderManager::class)->testGatewayConnection($gatewayForTest);
        } catch (PaymentProviderException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        if ($existingGateway) {
            $existingGateway->forceFill([
                'last_health_check_at' => now(),
            ])->save();
        }

        return response()->json([
            'ok' => true,
            'message' => (string) ($result['message'] ?? 'Conexão validada com sucesso.'),
            'details' => is_array($result['details'] ?? null) ? $result['details'] : [],
        ]);
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

        $accessToken = trim((string) ($credentials['access_token'] ?? ''));
        $webhookSecret = trim((string) ($credentials['webhook_secret'] ?? ''));

        if ($accessToken === '') {
            throw ValidationException::withMessages([
                'mercado_pago_access_token' => 'Informe o access token do Mercado Pago.',
            ]);
        }

        return [
            'access_token' => $accessToken,
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

        $accessToken = trim((string) ($incoming['access_token'] ?? ''));
        if ($accessToken === '') {
            $accessToken = trim((string) ($current['access_token'] ?? ''));
        }

        $webhookSecret = trim((string) ($incoming['webhook_secret'] ?? ''));
        if ($webhookSecret === '') {
            $webhookSecret = trim((string) ($current['webhook_secret'] ?? ''));
        }

        if ($accessToken === '') {
            throw ValidationException::withMessages([
                'mercado_pago_access_token' => 'Informe o access token do Mercado Pago.',
            ]);
        }

        return [
            'access_token' => $accessToken,
            'webhook_secret' => $webhookSecret,
        ];
    }
}
