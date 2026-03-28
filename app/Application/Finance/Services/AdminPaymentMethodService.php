<?php

namespace App\Application\Finance\Services;

use App\Http\Controllers\Concerns\ResolvesCurrentContractor;
use App\Http\Requests\Admin\StorePaymentMethodRequest;
use App\Http\Requests\Admin\UpdatePaymentMethodRequest;
use App\Models\Contractor;
use App\Models\PaymentGateway;
use App\Models\PaymentMethod;
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

        if (! (bool) ($data['allows_installments'] ?? false)) {
            $data['max_installments'] = null;
        }

        $gateway = $this->resolveGatewayForMethod($contractor, $data, $method);
        $data['payment_gateway_id'] = $gateway?->id;

        if (strtolower(trim((string) ($data['checkout_mode'] ?? 'manual'))) === 'integrated') {
            $data['code'] = PaymentMethod::CODE_PIX;
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
            $data['gateway_name'],
            $data['gateway_is_active'],
            $data['gateway_is_default'],
            $data['gateway_is_sandbox'],
            $data['mercado_pago_access_token'],
            $data['mercado_pago_webhook_secret'],
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
                'gateway_provider' => 'No momento, somente Mercado Pago Pix está disponível para integração.',
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
            $gateway = new PaymentGateway();
            $gateway->contractor_id = $contractor->id;
            $gateway->provider = PaymentGateway::PROVIDER_MERCADO_PAGO;
        }

        $currentCredentials = is_array($gateway->credentials) ? $gateway->credentials : [];
        $accessToken = trim((string) ($data['mercado_pago_access_token'] ?? ''));
        if ($accessToken === '') {
            $accessToken = trim((string) ($currentCredentials['access_token'] ?? ''));
        }

        if ($accessToken === '') {
            throw ValidationException::withMessages([
                'mercado_pago_access_token' => 'Informe o access token do Mercado Pago.',
            ]);
        }

        $webhookSecret = trim((string) ($data['mercado_pago_webhook_secret'] ?? ''));
        if ($webhookSecret === '') {
            $webhookSecret = trim((string) ($currentCredentials['webhook_secret'] ?? ''));
        }

        $fallbackName = trim((string) ($method?->name ?? ($data['name'] ?? 'Pix')));
        $gatewayName = trim((string) ($data['gateway_name'] ?? ''));

        $gateway->provider = PaymentGateway::PROVIDER_MERCADO_PAGO;
        $gateway->name = $gatewayName !== '' ? $gatewayName : "Gateway {$fallbackName}";
        $gateway->is_active = (bool) ($data['gateway_is_active'] ?? true);
        $gateway->is_default = (bool) ($data['gateway_is_default'] ?? false);
        $gateway->is_sandbox = (bool) ($data['gateway_is_sandbox'] ?? true);
        $gateway->credentials = [
            'access_token' => $accessToken,
            'webhook_secret' => $webhookSecret,
        ];
        $gateway->save();

        if ($gateway->is_default) {
            PaymentGateway::query()
                ->where('contractor_id', $contractor->id)
                ->where('id', '!=', $gateway->id)
                ->update(['is_default' => false]);
        }

        return $gateway;
    }
}
