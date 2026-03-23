<?php

namespace App\Http\Controllers\Admin;

use App\Application\Finance\Services\AdminFinanceService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Response;

class FinanceController extends Controller
{
    public function __construct(
        private readonly AdminFinanceService $service,
    ) {}

    public function index(Request $request): Response
    {
        return $this->service->index($request);
    }

    public function payments(Request $request): Response
    {
        return $this->service->payments($request);
    }
}
