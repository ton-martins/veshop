<?php

namespace App\Http\Controllers\Admin;

use App\Application\Sales\Services\AdminOrderService;
use App\Http\Controllers\Controller;
use App\Models\Sale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class OrderController extends Controller
{
    public function __construct(
        private readonly AdminOrderService $service,
    ) {}

    public function index(Request $request): Response
    {
        return $this->service->index($request);
    }

    public function update(Request $request, Sale $sale): RedirectResponse
    {
        return $this->service->update($request, $sale);
    }

    public function confirm(Request $request, Sale $sale): RedirectResponse
    {
        return $this->service->confirm($request, $sale);
    }

    public function markAsAwaitingPayment(Request $request, Sale $sale): RedirectResponse
    {
        return $this->service->markAsAwaitingPayment($request, $sale);
    }

    public function reject(Request $request, Sale $sale): RedirectResponse
    {
        return $this->service->reject($request, $sale);
    }

    public function markAsPaid(Request $request, Sale $sale): RedirectResponse
    {
        return $this->service->markAsPaid($request, $sale);
    }

    public function cancel(Request $request, Sale $sale): RedirectResponse
    {
        return $this->service->cancel($request, $sale);
    }

    public function updateDeliveryStatus(Request $request, Sale $sale): RedirectResponse
    {
        return $this->service->updateDeliveryStatus($request, $sale);
    }
}
