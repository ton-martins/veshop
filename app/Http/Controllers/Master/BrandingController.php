<?php

namespace App\Http\Controllers\Master;

use App\Application\Branding\Services\MasterBrandingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class BrandingController extends Controller
{
    public function __construct(
        private readonly MasterBrandingService $service,
    ) {}

    public function edit(): Response
    {
        return $this->service->edit();
    }

    public function update(Request $request): RedirectResponse
    {
        return $this->service->update($request);
    }
}
