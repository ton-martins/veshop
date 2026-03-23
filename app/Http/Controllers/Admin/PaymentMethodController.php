<?php

namespace App\Http\Controllers\Admin;

use App\Application\Finance\Services\AdminPaymentMethodService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePaymentMethodRequest;
use App\Http\Requests\Admin\UpdatePaymentMethodRequest;
use App\Models\PaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PaymentMethodController extends Controller
{
    public function __construct(
        private readonly AdminPaymentMethodService $service,
    ) {}

    public function store(StorePaymentMethodRequest $request): RedirectResponse
    {
        return $this->service->store($request);
    }

    public function update(UpdatePaymentMethodRequest $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        return $this->service->update($request, $paymentMethod);
    }

    public function destroy(Request $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        return $this->service->destroy($request, $paymentMethod);
    }
}
