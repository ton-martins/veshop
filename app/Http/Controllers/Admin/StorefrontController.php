<?php

namespace App\Http\Controllers\Admin;

use App\Application\Storefront\Services\AdminStorefrontService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class StorefrontController extends Controller
{
    public function __construct(
        private readonly AdminStorefrontService $service,
    ) {}

    public function edit(Request $request): Response
    {
        return $this->service->edit($request);
    }

    public function update(Request $request): RedirectResponse
    {
        return $this->service->update($request);
    }
}
