<?php

namespace App\Http\Controllers\Admin;

use App\Application\Identity\Services\AdminAccessAuditService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class AccessAuditController extends Controller
{
    public function __construct(
        private readonly AdminAccessAuditService $service,
    ) {}

    public function index(Request $request): Response
    {
        return $this->service->index($request);
    }

    public function disconnectAll(Request $request): RedirectResponse
    {
        return $this->service->disconnectAll($request);
    }
}

