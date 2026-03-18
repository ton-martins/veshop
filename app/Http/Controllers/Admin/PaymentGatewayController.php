<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePaymentGatewayRequest;
use App\Http\Requests\Admin\UpdatePaymentGatewayRequest;
use App\Models\Contractor;
use App\Models\PaymentGateway;
use App\Models\PaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PaymentGatewayController extends Controller
{
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

    private function resolveCurrentContractor(Request $request): ?Contractor
    {
        $user = $request->user();
        if (! $user) {
            return null;
        }

        $user->loadMissing('contractors');
        $availableContractors = $user->contractors->values();

        if ($availableContractors->isEmpty()) {
            return null;
        }

        $sessionContractorId = (int) $request->session()->get('current_contractor_id', 0);
        if ($sessionContractorId > 0) {
            $selected = $availableContractors->firstWhere('id', $sessionContractorId);
            if ($selected) {
                return $selected;
            }
        }

        $fallback = $availableContractors->first();
        if ($fallback) {
            $request->session()->put('current_contractor_id', $fallback->id);
        }

        return $fallback;
    }

    /**
     * @param array<string, mixed> $data
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
     * @param array<string, mixed> $data
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
