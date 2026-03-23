<?php

namespace App\Http\Controllers\Admin;

use App\Application\Services\AdminServiceScheduleService;
use App\Http\Controllers\Controller;
use App\Models\ServiceAppointment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class ServiceScheduleController extends Controller
{
    public function __construct(
        private readonly AdminServiceScheduleService $service,
    ) {}

    public function index(Request $request): Response
    {
        return $this->service->index($request);
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->service->store($request);
    }

    public function update(Request $request, ServiceAppointment $serviceAppointment): RedirectResponse
    {
        return $this->service->update($request, $serviceAppointment);
    }

    public function destroy(Request $request, ServiceAppointment $serviceAppointment): RedirectResponse
    {
        return $this->service->destroy($request, $serviceAppointment);
    }
}
