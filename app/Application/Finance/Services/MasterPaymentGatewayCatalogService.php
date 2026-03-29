<?php

namespace App\Application\Finance\Services;

use App\Http\Requests\Master\StorePaymentGatewayCatalogRequest;
use App\Http\Requests\Master\UpdatePaymentGatewayCatalogRequest;
use App\Services\Payments\PaymentGatewayCatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MasterPaymentGatewayCatalogService
{
    public function __construct(
        private readonly PaymentGatewayCatalogService $catalogService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorizeManager($request);

        $gateways = $this->catalogService->allForMaster();

        $total = count($gateways);
        $active = collect($gateways)->where('is_active', true)->count();
        $automaticActive = collect($gateways)
            ->where('is_active', true)
            ->where('checkout_mode', 'automatic')
            ->count();
        $implemented = collect($gateways)->where('is_implemented', true)->count();

        return Inertia::render('Master/PaymentGateways/Index', [
            'gateways' => $gateways,
            'stats' => [
                'total' => $total,
                'active' => $active,
                'automatic_active' => $automaticActive,
                'implemented' => $implemented,
            ],
        ]);
    }

    public function store(StorePaymentGatewayCatalogRequest $request): RedirectResponse
    {
        $this->authorizeManager($request);
        $this->catalogService->create($request->validated());

        return back()->with('status', 'Gateway salvo com sucesso.');
    }

    public function update(UpdatePaymentGatewayCatalogRequest $request, string $gatewayId): RedirectResponse
    {
        $this->authorizeManager($request);
        $this->catalogService->update($gatewayId, $request->validated());

        return back()->with('status', 'Gateway atualizado com sucesso.');
    }

    public function destroy(Request $request, string $gatewayId): RedirectResponse
    {
        $this->authorizeManager($request);
        $this->catalogService->delete($gatewayId);

        return back()->with('status', 'Gateway removido com sucesso.');
    }

    private function authorizeManager(Request $request): void
    {
        abort_unless((bool) $request->user()?->isMaster(), 403);
    }
}

