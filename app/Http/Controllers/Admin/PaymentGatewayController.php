<?php

namespace App\Http\Controllers\Admin;

use App\Application\Finance\Services\AdminPaymentGatewayService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePaymentGatewayRequest;
use App\Http\Requests\Admin\UpdatePaymentGatewayRequest;
use App\Models\PaymentGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PaymentGatewayController extends Controller
{
    public function __construct(
        private readonly AdminPaymentGatewayService $service,
    ) {}

    public function testConnection(Request $request): JsonResponse
    {
        return $this->service->testConnection($request);
    }

    public function store(StorePaymentGatewayRequest $request): RedirectResponse
    {
        return $this->service->store($request);
    }

    public function update(UpdatePaymentGatewayRequest $request, PaymentGateway $paymentGateway): RedirectResponse
    {
        return $this->service->update($request, $paymentGateway);
    }

    public function destroy(Request $request, PaymentGateway $paymentGateway): RedirectResponse
    {
        return $this->service->destroy($request, $paymentGateway);
    }
}
