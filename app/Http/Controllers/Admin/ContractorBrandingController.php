<?php

namespace App\Http\Controllers\Admin;

use App\Application\Branding\Services\AdminContractorBrandingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class ContractorBrandingController extends Controller
{
    public function __construct(
        private readonly AdminContractorBrandingService $service,
    ) {}

    /**
     * Show contractor branding form.
     */
    public function edit(Request $request): Response
    {
        return $this->service->edit($request);
    }

    /**
     * Update contractor branding.
     */
    public function update(Request $request): RedirectResponse
    {
        return $this->service->update($request);
    }
}
