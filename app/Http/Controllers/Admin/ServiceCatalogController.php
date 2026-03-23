<?php

namespace App\Http\Controllers\Admin;

use App\Application\Services\AdminServiceCatalogService;
use App\Http\Controllers\Controller;
use App\Models\ServiceCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class ServiceCatalogController extends Controller
{
    public function __construct(
        private readonly AdminServiceCatalogService $service,
    ) {}

    public function index(Request $request): Response
    {
        return $this->service->index($request);
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->service->store($request);
    }

    public function update(Request $request, ServiceCatalog $serviceCatalog): RedirectResponse
    {
        return $this->service->update($request, $serviceCatalog);
    }

    public function destroy(Request $request, ServiceCatalog $serviceCatalog): RedirectResponse
    {
        return $this->service->destroy($request, $serviceCatalog);
    }
}
