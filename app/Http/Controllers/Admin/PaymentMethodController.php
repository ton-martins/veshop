<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePaymentMethodRequest;
use App\Http\Requests\Admin\UpdatePaymentMethodRequest;
use App\Models\Contractor;
use App\Models\PaymentGateway;
use App\Models\PaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PaymentMethodController extends Controller
{
    public function store(StorePaymentMethodRequest $request): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $data = $request->validated();
        if ($data['is_default']) {
            $data['is_active'] = true;
        }
        if (! $data['allows_installments']) {
            $data['max_installments'] = null;
        }

        $gatewayId = $data['payment_gateway_id'] ?? null;
        if ($gatewayId !== null) {
            $gatewayExists = PaymentGateway::query()
                ->where('contractor_id', $contractor->id)
                ->where('id', $gatewayId)
                ->exists();

            if (! $gatewayExists) {
                return back()->withErrors([
                    'payment_gateway_id' => 'Gateway selecionado não pertence ao contratante ativo.',
                ]);
            }
        }

        $data['contractor_id'] = $contractor->id;
        $method = PaymentMethod::query()->create($data);

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
        $data = $request->validated();

        if ($data['is_default']) {
            $data['is_active'] = true;
        }
        if (! $data['allows_installments']) {
            $data['max_installments'] = null;
        }

        $gatewayId = $data['payment_gateway_id'] ?? null;
        if ($gatewayId !== null) {
            $gatewayExists = PaymentGateway::query()
                ->where('contractor_id', $contractor->id)
                ->where('id', $gatewayId)
                ->exists();

            if (! $gatewayExists) {
                return back()->withErrors([
                    'payment_gateway_id' => 'Gateway selecionado não pertence ao contratante ativo.',
                ]);
            }
        }

        $method->fill($data)->save();

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
}
