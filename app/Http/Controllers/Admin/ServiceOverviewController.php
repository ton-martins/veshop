<?php

namespace App\Http\Controllers\Admin;

use App\Application\Services\AdminServiceOverviewService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Response;

class ServiceOverviewController extends Controller
{
    public function __construct(
        private readonly AdminServiceOverviewService $service,
    ) {}

    public function index(Request $request): Response
    {
        return $this->service->index($request);
    }
}
