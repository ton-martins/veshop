<?php

namespace App\Http\Controllers\Master;

use App\Application\Finance\Services\MasterPaymentGatewayCatalogService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StorePaymentGatewayCatalogRequest;
use App\Http\Requests\Master\UpdatePaymentGatewayCatalogRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class PaymentGatewayCatalogController extends Controller
{
    public function __construct(
        private readonly MasterPaymentGatewayCatalogService $service,
    ) {}

    public function index(Request $request): Response
    {
        return $this->service->index($request);
    }

    public function store(StorePaymentGatewayCatalogRequest $request): RedirectResponse
    {
        return $this->service->store($request);
    }

    public function update(UpdatePaymentGatewayCatalogRequest $request, string $gatewayId): RedirectResponse
    {
        return $this->service->update($request, $gatewayId);
    }

    public function destroy(Request $request, string $gatewayId): RedirectResponse
    {
        return $this->service->destroy($request, $gatewayId);
    }
}

