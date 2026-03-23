<?php

namespace App\Http\Controllers\Master;

use App\Application\Tenant\Services\MasterPlanService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StorePlanRequest;
use App\Http\Requests\Master\UpdatePlanRequest;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class PlanController extends Controller
{
    public function __construct(
        private readonly MasterPlanService $service,
    ) {}

    public function index(Request $request): Response
    {
        return $this->service->index($request);
    }

    public function store(StorePlanRequest $request): RedirectResponse
    {
        return $this->service->store($request);
    }

    public function update(UpdatePlanRequest $request, Plan $plan): RedirectResponse
    {
        return $this->service->update($request, $plan);
    }

    public function bulkUpdate(Request $request): RedirectResponse
    {
        return $this->service->bulkUpdate($request);
    }

    public function destroy(Request $request, Plan $plan): RedirectResponse
    {
        return $this->service->destroy($request, $plan);
    }
}
