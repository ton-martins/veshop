<?php

namespace App\Http\Controllers\Admin;

use App\Application\Reports\Services\AdminDashboardService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly AdminDashboardService $service,
    ) {}

    /**
     * Display the admin dashboard.
     */
    public function __invoke(Request $request): Response
    {
        return $this->service->index($request);
    }
}
