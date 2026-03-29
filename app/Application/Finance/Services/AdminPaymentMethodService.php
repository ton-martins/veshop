<?php

namespace App\Application\Finance\Services;

use App\Http\Controllers\Concerns\ResolvesCurrentContractor;
use App\Http\Requests\Admin\SyncMercadoPagoPaymentMethodsRequest;
use App\Http\Requests\Admin\StorePaymentMethodRequest;
use App\Http\Requests\Admin\UpdatePaymentMethodRequest;
use App\Models\Contractor;
use App\Models\PaymentGateway;
use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AdminPaymentMethodService
{
    use ResolvesCurrentContractor;

    public function store(StorePaymentMethodRequest $request): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $data = $this->normalizeMethodPayload($contractor, $request->validated(), null);
        $data['contractor_id'] = $contractor->id;

        $method = PaymentMethod::query()->create($this->sanitizeMethodPayload($data));

        if ($method->is_default) {
            PaymentMethod::query()
                ->where('contractor_id', $contractor->id)
                ->where('id', '!=', $method->id)
                ->update(['is_default' => false]);
        }

        return back()->with('status', 'Forma de pagamento criada com sucesso.');
    }

    public function update(UpdatePaymentMethodRequest $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $method = $this->resolveOwnedMethod($contractor, $paymentMethod);
        $data = $this->normalizeMethodPayload($contractor, $request->validated(), $method);

        $method->fill($this->sanitizeMethodPayload($data))->save();

        if ($method->is_default) {
            PaymentMethod::query()
                ->where('contractor_id', $contractor->id)
                ->where('id', '!=', $method->id)
                ->update(['is_default' => false]);
        }

        return back()->with('status', 'Forma de pagamento atualizada com sucesso.');
    }

    public function destroy(Request $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $method = $this->resolveOwnedMethod($contractor, $paymentMethod);
        $wasDefault = (bool) $method->is_default;

        $method->delete();

        if ($wasDefault) {
            $fallback = PaymentMethod::query()
                ->where('contractor_id', $contractor->id)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->first();

            if ($fallback) {
                $fallback->is_default = true;
                $fallback->save();
            }
        }

        return back()->with('status', 'Forma de pagamento removida com sucesso.');
    }

    public function syncMercadoPagoMethods(SyncMercadoPagoPaymentMethodsRequest $request): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $data = $request->validated();
        $gateway = $this->resolveMercadoPagoGatewayForSync($contractor, $data);

        $credentials = is_array($gateway->credentials) ? $gateway->credentials : [];
        $incomingAccessToken = trim((string) ($data['mercado_pago_access_token'] ?? ''));
        $incomingWebhookSecret = trim((string) ($data['mercado_pago_webhook_secret'] ?? ''));

        if ($incomingAccessToken !== '') {
            $credentials['access_token'] = $incomingAccessToken;
        }
        if ($incomingWebhookSecret !== '') {
            $credentials['webhook_secret'] = $incomingWebhookSecret;
        }

        $gateway->credentials = $credentials === [] ? null : $credentials;
        $gateway->save();

        if ($gateway->is_default) {
            PaymentGateway::query()
                ->where('contractor_id', $contractor->id)
                ->where('id', '!=', $gateway->id)
                ->update(['is_default' => false]);
        }

        if ($gateway->resolveMercadoPagoAccessToken() === '') {
            throw ValidationException::withMessages([
                'mercado_pago_access_token' => 'Informe o access token do Mercado Pago ou conecte a conta por OAuth.',
            ]);
        }

        $methodsByCode = collect($data['methods'] ?? [])
            ->filter(static fn (mixed $row): bool => is_array($row))
            ->keyBy(static fn (array $row): string => strtolower(trim((string) ($row['code'] ?? ''))));
        $fixedSortOrderByCode = $this->resolveMercadoPagoSortOrderMap();
        $this->relinkMercadoPagoIntegratedMethods($contractor, $gateway);

        $enabledCodes = [];
        foreach (PaymentMethod::INTEGRATED_CODES as $code) {
            $row = $methodsByCode->get($code, []);
            $isEnabled = (bool) ($row['enabled'] ?? false);

            if (! $isEnabled) {
                $this->disableMercadoPagoMethodCode($contractor, $gateway, $code);

                continue;
            }

            $enabledCodes[] = $code;

            $method = $this->findMercadoPagoIntegratedMethod($contractor, $gateway, $code);

            if (! $method) {
                $method = new PaymentMethod();
                $method->contractor_id = $contractor->id;
                $method->payment_gateway_id = $gateway->id;
                $method->code = $code;
            }

            if ($method->trashed()) {
                $method->restore();
            }

            $allowsInstallments = $code === PaymentMethod::CODE_CREDIT_CARD
                ? (bool) ($row['allows_installments'] ?? false)
                : false;

            $maxInstallments = null;
            if ($allowsInstallments) {
                $requestedInstallments = max(2, (int) ($row['max_installments'] ?? 2));
                $maxInstallments = min(24, $requestedInstallments);
            }

            $currentSettings = is_array($method->settings) ? $method->settings : [];

            $method->fill([
                'name' => $this->resolveMercadoPagoMethodLabel($code),
                'is_active' => true,
                'is_default' => false,
                'allows_installments' => $allowsInstallments,
                'max_installments' => $maxInstallments,
                'fee_fixed' => ($row['fee_fixed'] ?? null) !== null ? round((float) $row['fee_fixed'], 2) : null,
                'fee_percent' => ($row['fee_percent'] ?? null) !== null ? round((float) $row['fee_percent'], 2) : null,
                'sort_order' => (int) ($fixedSortOrderByCode[$code] ?? 0),
                'settings' => $this->resolveIntegratedSettings($currentSettings, $code, $gateway),
            ])->save();

            $this->deactivateOtherMercadoPagoCandidates($contractor, $gateway, $code, (int) $method->id);
        }

        $defaultCode = strtolower(trim((string) ($data['default_code'] ?? '')));
        if (! in_array($defaultCode, $enabledCodes, true)) {
            $defaultCode = $enabledCodes[0] ?? '';
        }

        if ($defaultCode !== '') {
            $defaultMethod = PaymentMethod::query()
                ->where('contractor_id', $contractor->id)
                ->where('payment_gateway_id', $gateway->id)
                ->where('code', $defaultCode)
                ->first();

            if ($defaultMethod) {
                PaymentMethod::query()
                    ->where('contractor_id', $contractor->id)
                    ->where('id', '!=', $defaultMethod->id)
                    ->update(['is_default' => false]);

                $defaultMethod->is_default = true;
                $defaultMethod->save();
            }
        }

        if ($enabledCodes === []) {
            return back()->with('status', 'Configuração automática do Mercado Pago salva sem formas ativas.');
        }

        $enabledLabels = collect($enabledCodes)
            ->map(fn (string $code): string => $this->resolveMercadoPagoMethodLabel($code))
            ->implode(', ');

        return back()->with('status', "Configuração automática atualizada. Formas ativas: {$enabledLabels}.");
    }

    private function relinkMercadoPagoIntegratedMethods(Contractor $contractor, PaymentGateway $gateway): void
    {
        foreach (PaymentMethod::INTEGRATED_CODES as $code) {
            $candidates = $this->resolveMercadoPagoCandidateMethods($contractor, $code);

            foreach ($candidates as $candidate) {
                $settings = is_array($candidate->settings) ? $candidate->settings : [];
                data_set($settings, 'gateway_integration.provider', PaymentGateway::PROVIDER_MERCADO_PAGO);
                data_set($settings, 'gateway_integration.gateway_id', (int) $gateway->id);

                $candidate->forceFill([
                    'payment_gateway_id' => (int) $gateway->id,
                    'settings' => $settings,
                ])->save();
            }
        }
    }

    private function disableMercadoPagoMethodCode(Contractor $contractor, PaymentGateway $gateway, string $code): void
    {
        $candidates = $this->resolveMercadoPagoCandidateMethods($contractor, $code);

        foreach ($candidates as $candidate) {
            $candidate->forceFill([
                'payment_gateway_id' => (int) $gateway->id,
                'is_active' => false,
                'is_default' => false,
            ])->save();
        }
    }

    private function findMercadoPagoIntegratedMethod(Contractor $contractor, PaymentGateway $gateway, string $code): ?PaymentMethod
    {
        $candidates = $this->resolveMercadoPagoCandidateMethods($contractor, $code);
        $preferred = $candidates->firstWhere('payment_gateway_id', (int) $gateway->id);

        return $preferred ?: $candidates->first();
    }

    private function deactivateOtherMercadoPagoCandidates(Contractor $contractor, PaymentGateway $gateway, string $code, int $keepId): void
    {
        $candidates = $this->resolveMercadoPagoCandidateMethods($contractor, $code)
            ->filter(static fn (PaymentMethod $candidate): bool => (int) $candidate->id !== $keepId);

        foreach ($candidates as $candidate) {
            $candidate->forceFill([
                'payment_gateway_id' => (int) $gateway->id,
                'is_active' => false,
                'is_default' => false,
            ])->save();
        }
    }

    /**
     * @return Collection<int, PaymentMethod>
     */
    private function resolveMercadoPagoCandidateMethods(Contractor $contractor, string $code): Collection
    {
        return PaymentMethod::withTrashed()
            ->where('contractor_id', $contractor->id)
            ->where('code', $code)
            ->orderBy('id')
            ->get()
            ->filter(fn (PaymentMethod $method): bool => $this->isMercadoPagoIntegratedCandidate($method))
            ->values();
    }

    private function isMercadoPagoIntegratedCandidate(PaymentMethod $method): bool
    {
        $settings = is_array($method->settings) ? $method->settings : [];
        $integrationProvider = strtolower(trim((string) data_get($settings, 'gateway_integration.provider', '')));
        $hasGatewayId = (int) ($method->payment_gateway_id ?? 0) > 0;

        if ($integrationProvider === PaymentGateway::PROVIDER_MERCADO_PAGO) {
            return true;
        }

        return $integrationProvider === '' && $hasGatewayId;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveMercadoPagoGatewayForSync(Contractor $contractor, array $data): PaymentGateway
    {
        $gatewayId = isset($data['gateway_id']) && $data['gateway_id'] !== null
            ? (int) $data['gateway_id']
            : null;

        $gateway = null;
        if ($gatewayId !== null && $gatewayId > 0) {
            $gateway = PaymentGateway::query()
                ->where('contractor_id', $contractor->id)
                ->where('provider', PaymentGateway::PROVIDER_MERCADO_PAGO)
                ->where('id', $gatewayId)
                ->first();

            if (! $gateway) {
                throw ValidationException::withMessages([
                    'gateway_id' => 'Gateway Mercado Pago inválido para o contratante ativo.',
                ]);
            }
        }

        if (! $gateway) {
            $gateway = PaymentGateway::query()
                ->where('contractor_id', $contractor->id)
                ->where('provider', PaymentGateway::PROVIDER_MERCADO_PAGO)
                ->orderByDesc('is_default')
                ->latest('id')
                ->first();
        }

        if (! $gateway) {
            $gateway = new PaymentGateway();
            $gateway->contractor_id = $contractor->id;
            $gateway->provider = PaymentGateway::PROVIDER_MERCADO_PAGO;
            $gateway->name = 'Mercado Pago';
            $gateway->is_default = ! PaymentGateway::query()
                ->where('contractor_id', $contractor->id)
                ->exists();
        }

        $gateway->provider = PaymentGateway::PROVIDER_MERCADO_PAGO;
        $gateway->name = 'Mercado Pago';
        $gateway->is_active = true;
        $gateway->is_default = $gateway->exists
            ? (bool) $gateway->is_default
            : ! PaymentGateway::query()
                ->where('contractor_id', $contractor->id)
                ->exists();
        $gateway->is_sandbox = (bool) ($data['gateway_is_sandbox'] ?? ($gateway->is_sandbox ?? true));
        $gateway->save();

        return $gateway;
    }

    private function resolveMercadoPagoMethodLabel(string $code): string
    {
        return match (strtolower(trim($code))) {
            PaymentMethod::CODE_PIX => 'Pix',
            PaymentMethod::CODE_BOLETO => 'Boleto',
            PaymentMethod::CODE_CREDIT_CARD => 'Cartão de crédito',
            PaymentMethod::CODE_DEBIT_CARD => 'Cartão de débito',
            default => ucfirst(strtolower(trim($code))),
        };
    }

    /**
     * @return array<string, int>
     */
    private function resolveMercadoPagoSortOrderMap(): array
    {
        return [
            PaymentMethod::CODE_PIX => 1,
            PaymentMethod::CODE_CREDIT_CARD => 2,
            PaymentMethod::CODE_DEBIT_CARD => 3,
            PaymentMethod::CODE_BOLETO => 4,
        ];
    }

    private function resolveOwnedMethod(Contractor $contractor, PaymentMethod $method): PaymentMethod
    {
        abort_unless((int) $method->contractor_id === (int) $contractor->id, 404);

        return $method;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeMethodPayload(Contractor $contractor, array $data, ?PaymentMethod $method): array
    {
        if ((bool) ($data['is_default'] ?? false)) {
            $data['is_active'] = true;
        }
        $data['show_on_storefront'] = (bool) ($data['show_on_storefront'] ?? true);

        $checkoutMode = strtolower(trim((string) ($data['checkout_mode'] ?? 'manual')));
        $code = strtolower(trim((string) ($data['code'] ?? '')));

        if ($checkoutMode !== 'integrated' && ! (bool) ($data['allows_installments'] ?? false)) {
            $data['max_installments'] = null;
        }

        $gateway = $this->resolveGatewayForMethod($contractor, $data, $method);
        $data['payment_gateway_id'] = $gateway?->id;

        $currentSettings = is_array($method?->settings) ? $method->settings : [];
        if ($checkoutMode === 'integrated') {
            $data['settings'] = $this->resolveIntegratedSettings($currentSettings, $code, $gateway);
            if ($code !== PaymentMethod::CODE_CREDIT_CARD) {
                $data['allows_installments'] = false;
                $data['max_installments'] = null;
            }
        } else {
            $data['settings'] = $this->resolveManualSettings(
                $currentSettings,
                (bool) ($data['show_on_storefront'] ?? true),
            );
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function sanitizeMethodPayload(array $data): array
    {
        unset(
            $data['checkout_mode'],
            $data['gateway_provider'],
            $data['gateway_is_sandbox'],
            $data['mercado_pago_access_token'],
            $data['mercado_pago_webhook_secret'],
            $data['show_on_storefront'],
        );

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveGatewayForMethod(Contractor $contractor, array $data, ?PaymentMethod $method): ?PaymentGateway
    {
        $checkoutMode = strtolower(trim((string) ($data['checkout_mode'] ?? (($data['payment_gateway_id'] ?? null) ? 'integrated' : 'manual'))));
        if ($checkoutMode !== 'integrated') {
            return null;
        }

        $provider = strtolower(trim((string) ($data['gateway_provider'] ?? PaymentGateway::PROVIDER_MERCADO_PAGO)));
        if ($provider !== PaymentGateway::PROVIDER_MERCADO_PAGO) {
            throw ValidationException::withMessages([
                'gateway_provider' => 'No momento, somente Mercado Pago está disponível para integração.',
            ]);
        }

        $code = strtolower(trim((string) ($data['code'] ?? '')));
        if (! in_array($code, PaymentMethod::INTEGRATED_CODES, true)) {
            throw ValidationException::withMessages([
                'code' => 'Código da forma integrada inválido para Mercado Pago.',
            ]);
        }

        $gatewayId = isset($data['payment_gateway_id']) && $data['payment_gateway_id'] !== null
            ? (int) $data['payment_gateway_id']
            : null;
        $gateway = null;

        if ($gatewayId !== null && $gatewayId > 0) {
            $gateway = PaymentGateway::query()
                ->where('contractor_id', $contractor->id)
                ->where('id', $gatewayId)
                ->first();

            if (! $gateway) {
                throw ValidationException::withMessages([
                    'payment_gateway_id' => 'Gateway selecionado não pertence ao contratante ativo.',
                ]);
            }
        }

        if (! $gateway && $method && $method->payment_gateway_id) {
            $gateway = PaymentGateway::query()
                ->where('contractor_id', $contractor->id)
                ->where('id', (int) $method->payment_gateway_id)
                ->first();
        }

        if (! $gateway) {
            $gateway = PaymentGateway::query()
                ->where('contractor_id', $contractor->id)
                ->where('provider', PaymentGateway::PROVIDER_MERCADO_PAGO)
                ->orderByDesc('is_default')
                ->latest('id')
                ->first();
        }

        if (! $gateway) {
            $gateway = new PaymentGateway();
            $gateway->contractor_id = $contractor->id;
            $gateway->provider = PaymentGateway::PROVIDER_MERCADO_PAGO;
            $gateway->name = 'Mercado Pago';
            $gateway->is_active = true;
            $gateway->is_default = ! PaymentGateway::query()
                ->where('contractor_id', $contractor->id)
                ->exists();
        }

        $gateway->provider = PaymentGateway::PROVIDER_MERCADO_PAGO;
        $gateway->name = 'Mercado Pago';
        $gateway->is_active = true;
        $gateway->is_default = $gateway->exists
            ? (bool) $gateway->is_default
            : ! PaymentGateway::query()
                ->where('contractor_id', $contractor->id)
                ->exists();
        $gateway->is_sandbox = isset($gateway->mp_live_mode)
            ? ! (bool) $gateway->mp_live_mode
            : (bool) ($data['gateway_is_sandbox'] ?? true);
        $gateway->save();

        if ($gateway->is_default) {
            PaymentGateway::query()
                ->where('contractor_id', $contractor->id)
                ->where('id', '!=', $gateway->id)
                ->update(['is_default' => false]);
        }

        if ($gateway->resolveMercadoPagoAccessToken() === '') {
            throw ValidationException::withMessages([
                'checkout_mode' => 'Conecte a conta Mercado Pago antes de salvar a forma integrada.',
            ]);
        }

        return $gateway;
    }

    /**
     * @param  array<string, mixed>  $current
     * @return array<string, mixed>
     */
    private function resolveIntegratedSettings(array $current, string $code, ?PaymentGateway $gateway): array
    {
        $profile = $this->resolveMercadoPagoMethodProfile($code);

        $current['gateway_integration'] = array_filter([
            'provider' => PaymentGateway::PROVIDER_MERCADO_PAGO,
            'flow' => 'orders',
            'payment_method_code' => $code,
            'payment_method_id' => $profile['method_id'],
            'payment_method_type' => $profile['method_type'],
            'supports_installments' => $profile['supports_installments'],
            'supports_async_confirmation' => $profile['supports_async_confirmation'],
            'gateway_id' => $gateway?->id,
        ], static fn (mixed $value): bool => $value !== null);

        return $current;
    }

    /**
     * @param  array<string, mixed>  $current
     * @return array<string, mixed>|null
     */
    private function resolveManualSettings(array $current, bool $showOnStorefront = true): ?array
    {
        unset($current['gateway_integration']);
        $storefront = is_array($current['storefront'] ?? null) ? $current['storefront'] : [];
        $storefront['visible'] = $showOnStorefront;
        $current['storefront'] = $storefront;

        return $current === [] ? null : $current;
    }

    /**
     * @return array{
     *   method_id:string|null,
     *   method_type:string,
     *   supports_installments:bool,
     *   supports_async_confirmation:bool
     * }
     */
    private function resolveMercadoPagoMethodProfile(string $code): array
    {
        return match ($code) {
            PaymentMethod::CODE_PIX => [
                'method_id' => 'pix',
                'method_type' => 'bank_transfer',
                'supports_installments' => false,
                'supports_async_confirmation' => true,
            ],
            PaymentMethod::CODE_BOLETO => [
                'method_id' => 'ticket',
                'method_type' => 'ticket',
                'supports_installments' => false,
                'supports_async_confirmation' => true,
            ],
            PaymentMethod::CODE_CREDIT_CARD => [
                'method_id' => null,
                'method_type' => 'credit_card',
                'supports_installments' => true,
                'supports_async_confirmation' => false,
            ],
            PaymentMethod::CODE_DEBIT_CARD => [
                'method_id' => null,
                'method_type' => 'debit_card',
                'supports_installments' => false,
                'supports_async_confirmation' => false,
            ],
            default => throw ValidationException::withMessages([
                'code' => 'Forma de pagamento não suportada no gateway Mercado Pago.',
            ]),
        };
    }
}
