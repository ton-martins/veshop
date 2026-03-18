<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contractor;
use App\Models\PaymentGateway;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FinanceController extends Controller
{
    public function index(Request $request): Response
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $tab = trim((string) $request->string('tab')->toString());
        if (! in_array($tab, ['payables', 'receivables', 'payments'], true)) {
            $tab = 'payables';
        }

        $gateways = PaymentGateway::query()
            ->where('contractor_id', $contractor->id)
            ->withCount('paymentMethods')
            ->orderByDesc('is_default')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->map(static function (PaymentGateway $gateway): array {
                $credentials = is_array($gateway->credentials) ? $gateway->credentials : [];

                return [
                    'id' => $gateway->id,
                    'provider' => $gateway->provider,
                    'name' => $gateway->name,
                    'is_active' => (bool) $gateway->is_active,
                    'is_default' => (bool) $gateway->is_default,
                    'is_sandbox' => (bool) $gateway->is_sandbox,
                    'credentials_status' => [
                        'access_token_configured' => trim((string) ($credentials['access_token'] ?? '')) !== '',
                        'webhook_secret_configured' => trim((string) ($credentials['webhook_secret'] ?? '')) !== '',
                    ],
                    'methods_count' => (int) $gateway->payment_methods_count,
                    'last_health_check_at' => optional($gateway->last_health_check_at)?->format('d/m/Y H:i'),
                ];
            })
            ->values()
            ->all();

        $methods = PaymentMethod::query()
            ->where('contractor_id', $contractor->id)
            ->with('paymentGateway:id,name,provider')
            ->orderByDesc('is_default')
            ->orderByDesc('is_active')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(static function (PaymentMethod $method): array {
                return [
                    'id' => $method->id,
                    'code' => $method->code,
                    'name' => $method->name,
                    'payment_gateway_id' => $method->payment_gateway_id,
                    'payment_gateway_name' => $method->paymentGateway?->name,
                    'payment_gateway_provider' => $method->paymentGateway?->provider,
                    'is_active' => (bool) $method->is_active,
                    'is_default' => (bool) $method->is_default,
                    'allows_installments' => (bool) $method->allows_installments,
                    'max_installments' => $method->max_installments,
                    'fee_fixed' => $method->fee_fixed !== null ? (float) $method->fee_fixed : null,
                    'fee_percent' => $method->fee_percent !== null ? (float) $method->fee_percent : null,
                    'sort_order' => (int) $method->sort_order,
                ];
            })
            ->values()
            ->all();

        $activeGateways = PaymentGateway::query()
            ->where('contractor_id', $contractor->id)
            ->where('is_active', true)
            ->count();

        $activeMethods = PaymentMethod::query()
            ->where('contractor_id', $contractor->id)
            ->where('is_active', true)
            ->count();

        $defaultGateway = PaymentGateway::query()
            ->where('contractor_id', $contractor->id)
            ->where('is_default', true)
            ->first();

        $defaultMethod = PaymentMethod::query()
            ->where('contractor_id', $contractor->id)
            ->where('is_default', true)
            ->first();

        return Inertia::render('Admin/Finance/Index', [
            'initialTab' => $tab,
            'paymentConfig' => [
                'gateways' => $gateways,
                'methods' => $methods,
                'provider_options' => [
                    ['value' => PaymentGateway::PROVIDER_MANUAL, 'label' => 'Operação manual'],
                    ['value' => PaymentGateway::PROVIDER_MERCADO_PAGO, 'label' => 'Mercado Pago'],
                ],
                'stats' => [
                    'gateways_total' => count($gateways),
                    'gateways_active' => $activeGateways,
                    'methods_total' => count($methods),
                    'methods_active' => $activeMethods,
                    'default_gateway' => $defaultGateway?->name,
                    'default_method' => $defaultMethod?->name,
                ],
            ],
        ]);
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
