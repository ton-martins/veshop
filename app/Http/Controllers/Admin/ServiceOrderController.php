<?php

namespace App\Http\Controllers\Admin;

use App\Application\Services\AdminServiceOrderService;
use App\Http\Controllers\Controller;
use App\Models\ServiceOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class ServiceOrderController extends Controller
{
    public function __construct(
        private readonly AdminServiceOrderService $service,
    ) {}

    public function index(Request $request): Response
    {
        return $this->service->index($request);
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->service->store($request);
    }

    public function update(Request $request, ServiceOrder $serviceOrder): RedirectResponse
    {
        return $this->service->update($request, $serviceOrder);
    }

    public function destroy(Request $request, ServiceOrder $serviceOrder): RedirectResponse
    {
        return $this->service->destroy($request, $serviceOrder);
    }
}
