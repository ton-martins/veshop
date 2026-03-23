<?php

namespace App\Http\Controllers\Admin;

use App\Application\Services\AdminServiceCategoryService;
use App\Http\Controllers\Controller;
use App\Models\ServiceCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class ServiceCategoryController extends Controller
{
    public function __construct(
        private readonly AdminServiceCategoryService $service,
    ) {}

    public function index(Request $request): Response
    {
        return $this->service->index($request);
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->service->store($request);
    }

    public function update(Request $request, ServiceCategory $serviceCategory): RedirectResponse
    {
        return $this->service->update($request, $serviceCategory);
    }

    public function destroy(Request $request, ServiceCategory $serviceCategory): RedirectResponse
    {
        return $this->service->destroy($request, $serviceCategory);
    }
}
