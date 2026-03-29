<?php

namespace App\Http\Controllers\Admin;

use App\Application\Finance\Services\AdminMercadoPagoOAuthService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MercadoPagoOAuthController extends Controller
{
    public function __construct(
        private readonly AdminMercadoPagoOAuthService $service,
    ) {}

    public function redirect(Request $request): RedirectResponse
    {
        return $this->service->redirectToAuthorization($request);
    }

    public function callback(Request $request): RedirectResponse
    {
        return $this->service->handleAuthorizationCallback($request);
    }

    public function disconnect(Request $request): RedirectResponse
    {
        return $this->service->disconnect($request);
    }
}

