<?php

namespace App\Http\Controllers\Admin;

use App\Application\CRM\Services\AdminSupplierService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSupplierRequest;
use App\Http\Requests\Admin\UpdateSupplierRequest;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class SupplierController extends Controller
{
    public function __construct(
        private readonly AdminSupplierService $service,
    ) {}

    /**
     * Display a listing of suppliers.
     */
    public function index(Request $request): Response
    {
        return $this->service->index($request);
    }

    /**
     * Store a newly created supplier in storage.
     */
    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        return $this->service->store($request);
    }

    /**
     * Update the specified supplier in storage.
     */
    public function update(UpdateSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        return $this->service->update($request, $supplier);
    }

    /**
     * Remove the specified supplier from storage.
     */
    public function destroy(Request $request, Supplier $supplier): RedirectResponse
    {
        return $this->service->destroy($request, $supplier);
    }
}
