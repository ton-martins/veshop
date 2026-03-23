<?php

namespace App\Http\Controllers\Master;

use App\Application\Tenant\Services\MasterContractorService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreContractorRequest;
use App\Http\Requests\Master\UpdateContractorRequest;
use App\Models\Contractor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class ContractorController extends Controller
{
    public function __construct(
        private readonly MasterContractorService $service,
    ) {}

    /**
     * Display a listing of contractors.
     */
    public function index(Request $request): Response
    {
        return $this->service->index($request);
    }

    /**
     * Store a newly created contractor in storage.
     */
    public function store(StoreContractorRequest $request): RedirectResponse
    {
        return $this->service->store($request->validated());
    }

    /**
     * Update the specified contractor in storage.
     */
    public function update(UpdateContractorRequest $request, Contractor $contractor): RedirectResponse
    {
        return $this->service->update($request->validated(), $contractor);
    }

    /**
     * Remove the specified contractor from storage.
     */
    public function destroy(Request $request, Contractor $contractor): RedirectResponse
    {
        return $this->service->destroy($request, $contractor);
    }
}
